import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { initializeTransaction } from "@/lib/paystack";
import { checkoutSchema, zodErrorMessage } from "@/lib/validation";
import { RESERVATION_MINUTES } from "@/lib/availability";
import { logger } from "@/lib/logger";

function generateReference(): string {
  return `THRIFT_${Date.now()}_${Math.random().toString(36).slice(2, 9).toUpperCase()}`;
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json().catch(() => null);
    const parsed = checkoutSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json({ error: zodErrorMessage(parsed.error) }, { status: 400 });
    }
    const data = parsed.data;

    const idempotencyKey = request.headers.get("idempotency-key") || undefined;

    if (idempotencyKey) {
      const existing = await prisma.order.findUnique({
        where: { idempotencyKey },
        include: { items: true },
      });
      if (existing) {
        if (existing.status === "paid") {
          return NextResponse.json(
            { error: "This order has already been paid for." },
            { status: 409 }
          );
        }
        if (existing.authorizationUrl) {
          return NextResponse.json({
            authorization_url: existing.authorizationUrl,
            reference: existing.reference,
          });
        }
        // A previous attempt under this idempotency key created the order but
        // never got a Paystack authorization URL (e.g. Paystack was down).
        // Clear its reservation and the stale row so this retry can start
        // clean instead of colliding with the unique idempotencyKey, or
        // self-blocking on its own still-active reservation.
        await prisma.product.updateMany({
          where: { id: { in: existing.items.map((i) => i.productId) } },
          data: { reservedUntil: null },
        });
        await prisma.order.delete({ where: { id: existing.id } });
      }
    }

    // Reserve every item atomically before creating the order — this closes
    // the race where two shoppers check out the same one-of-one item at once.
    // A reservation blocks other buyers for RESERVATION_MINUTES; it's released
    // automatically (by simply expiring) if this checkout never completes.
    const now = new Date();
    const reservedUntil = new Date(now.getTime() + RESERVATION_MINUTES * 60 * 1000);

    const order = await prisma.$transaction(async (tx) => {
      const productIds = data.items.map((i) => i.productId);
      const products = await tx.product.findMany({ where: { id: { in: productIds } } });
      const productMap = new Map(products.map((p) => [p.id, p]));

      let totalAmount = 0;
      const orderItemsData: {
        productId: string;
        quantity: number;
        price: number;
        size: string;
      }[] = [];

      for (const item of data.items) {
        const product = productMap.get(item.productId);
        if (!product) {
          throw new Error("One or more items are unavailable");
        }

        const reserveResult = await tx.product.updateMany({
          where: {
            id: item.productId,
            inStock: true,
            OR: [{ reservedUntil: null }, { reservedUntil: { lt: now } }],
          },
          data: { reservedUntil },
        });
        if (reserveResult.count === 0) {
          throw new Error(`"${product.name}" just sold or is being purchased by someone else`);
        }

        const lineTotal = product.salePrice * item.quantity;
        totalAmount += lineTotal;
        orderItemsData.push({
          productId: product.id,
          quantity: item.quantity,
          price: product.salePrice,
          size: item.size,
        });
      }

      if (totalAmount < 10000) {
        throw new Error("Minimum order amount is ₦100");
      }

      return tx.order.create({
        data: {
          reference: generateReference(),
          idempotencyKey,
          customerName: data.customerName,
          customerEmail: data.customerEmail.toLowerCase(),
          customerPhone: data.customerPhone,
          shippingAddress: data.shippingAddress,
          city: data.city,
          state: data.state,
          totalAmount,
          status: "pending",
          items: { create: orderItemsData },
        },
      });
    });

    const appUrl = process.env.NEXT_PUBLIC_APP_URL || "http://localhost:3000";

    try {
      const paystack = await initializeTransaction({
        email: order.customerEmail,
        amount: order.totalAmount,
        reference: order.reference,
        callback_url: `${appUrl}/order/success?reference=${order.reference}`,
        metadata: {
          order_id: order.id,
          customer_name: order.customerName,
        },
      });

      await prisma.order.update({
        where: { id: order.id },
        data: {
          paystackRef: paystack.data.reference,
          authorizationUrl: paystack.data.authorization_url,
        },
      });

      return NextResponse.json({
        authorization_url: paystack.data.authorization_url,
        reference: order.reference,
      });
    } catch (paystackError) {
      // Paystack init failed — release the reservation so the items go back
      // on sale instead of being locked for RESERVATION_MINUTES for nothing.
      await prisma.product.updateMany({
        where: { id: { in: data.items.map((i) => i.productId) } },
        data: { reservedUntil: null },
      });
      throw paystackError;
    }
  } catch (err) {
    logger.error("Paystack initialize error", err);
    const message =
      err instanceof Error ? err.message : "Payment initialization failed";
    return NextResponse.json({ error: message }, { status: 400 });
  }
}
