import { NextRequest, NextResponse } from "next/server";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { verifyTransaction } from "@/lib/paystack";
import { logger } from "@/lib/logger";

export async function GET(request: NextRequest) {
  const reference = new URL(request.url).searchParams.get("reference");

  if (!reference) {
    return NextResponse.json({ error: "Reference is required" }, { status: 400 });
  }

  try {
    const order = await prisma.order.findUnique({
      where: { reference },
      include: {
        items: { include: { product: true } },
      },
    });

    if (!order) {
      return NextResponse.json({ error: "Order not found" }, { status: 404 });
    }

    if (order.status === "paid") {
      return NextResponse.json({ order, alreadyPaid: true });
    }

    const verification = await verifyTransaction(reference);

    if (
      verification.data.status !== "success" ||
      verification.data.amount !== order.totalAmount
    ) {
      await prisma.order.update({
        where: { id: order.id },
        data: { status: "failed" },
      });
      // Release the reservation — this payment didn't go through, so the
      // items should become purchasable by someone else again.
      await prisma.product.updateMany({
        where: { id: { in: order.items.map((i) => i.productId) } },
        data: { reservedUntil: null },
      });
      return NextResponse.json(
        { error: "Payment was not successful" },
        { status: 400 }
      );
    }

    const updated = await prisma.$transaction(async (tx) => {
      const paidOrder = await tx.order.update({
        where: { id: order.id },
        data: { status: "paid", paystackRef: verification.data.reference },
        include: {
          items: { include: { product: true } },
        },
      });

      // The item just sold — take it off sale so a second buyer can't also
      // pay for the same one-of-one piece.
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

    return NextResponse.json({ order: updated });
  } catch (err) {
    logger.error("Paystack verify error", err, { reference });
    const message = err instanceof Error ? err.message : "Verification failed";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
