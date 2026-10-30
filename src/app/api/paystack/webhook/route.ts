import { NextRequest, NextResponse } from "next/server";
import { revalidatePath } from "next/cache";
import { createHmac, timingSafeEqual } from "crypto";
import { prisma } from "@/lib/prisma";
import { logger } from "@/lib/logger";

/**
 * Source of truth for payment status. The client-side /paystack/verify call
 * (triggered by the browser landing on /order/success) is a UX nicety only —
 * if the customer closes the tab right after paying, this webhook is what
 * still marks the order paid and takes the item off sale. Configure this URL
 * (https://yourdomain.com/api/paystack/webhook) in the Paystack dashboard
 * under Settings → API Keys & Webhooks.
 */
export async function POST(request: NextRequest) {
  const secret = process.env.PAYSTACK_SECRET_KEY;
  const signature = request.headers.get("x-paystack-signature");
  const rawBody = await request.text();

  if (!secret || !signature) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const expected = createHmac("sha512", secret).update(rawBody).digest("hex");
  const expectedBuf = Buffer.from(expected);
  const signatureBuf = Buffer.from(signature);
  const validSignature =
    expectedBuf.length === signatureBuf.length &&
    timingSafeEqual(expectedBuf, signatureBuf);

  if (!validSignature) {
    logger.warn("Paystack webhook signature mismatch");
    return NextResponse.json({ error: "Invalid signature" }, { status: 401 });
  }

  let event: { event?: string; data?: { reference?: string; amount?: number } };
  try {
    event = JSON.parse(rawBody);
  } catch {
    return NextResponse.json({ error: "Invalid payload" }, { status: 400 });
  }

  if (event.event === "charge.success" && event.data?.reference) {
    const { reference, amount } = event.data;

    try {
      const order = await prisma.order.findUnique({
        where: { reference },
        include: { items: { include: { product: true } } },
      });

      if (order && order.status !== "paid" && amount === order.totalAmount) {
        const updated = await prisma.$transaction(async (tx) => {
          const paidOrder = await tx.order.update({
            where: { id: order.id },
            data: { status: "paid", paystackRef: reference },
            include: { items: { include: { product: true } } },
          });
          await tx.product.updateMany({
            where: { id: { in: order.items.map((i) => i.productId) } },
            data: { inStock: false, reservedUntil: null },
          });
          return paidOrder;
        });

        for (const item of updated.items) {
          revalidatePath(`/product/${item.product.slug}`);
        }
        revalidatePath("/shop");
        revalidatePath("/");
      }
    } catch (err) {
      logger.error("Paystack webhook processing error", err, { reference });
      // Still ack with 200 below — Paystack retries on non-2xx, and retrying
      // won't fix a bug on our side. The error is logged for investigation.
    }
  }

  return NextResponse.json({ received: true });
}
