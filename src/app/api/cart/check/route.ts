import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { isAvailable } from "@/lib/availability";

type CheckBody = { productIds?: string[] };

export async function POST(request: NextRequest) {
  const body = (await request.json().catch(() => null)) as CheckBody | null;
  const productIds = Array.isArray(body?.productIds) ? body!.productIds.slice(0, 50) : [];

  if (productIds.length === 0) {
    return NextResponse.json({ items: [] });
  }

  const now = new Date();
  const products = await prisma.product.findMany({
    where: { id: { in: productIds } },
    select: { id: true, inStock: true, reservedUntil: true, salePrice: true },
  });

  const found = new Map(products.map((p) => [p.id, p]));
  const items = productIds.map((id) => {
    const product = found.get(id);
    return {
      id,
      available: product ? isAvailable(product, now) : false,
      salePrice: product?.salePrice ?? null,
    };
  });

  return NextResponse.json({ items });
}
