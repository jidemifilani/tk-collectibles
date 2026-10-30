import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";

type Params = { params: { slug: string } };

export async function GET(_request: Request, { params }: Params) {
  const { slug } = params;
  const product = await prisma.product.findUnique({ where: { slug } });

  if (!product) {
    return NextResponse.json({ error: "Product not found" }, { status: 404 });
  }

  return NextResponse.json(product);
}
