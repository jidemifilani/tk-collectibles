import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { availabilityWhere } from "@/lib/availability";

const DEFAULT_PAGE_SIZE = 24;

export async function GET(request: NextRequest) {
  const { searchParams } = new URL(request.url);
  const category = searchParams.get("category");
  const featured = searchParams.get("featured");
  const page = Math.max(1, Number(searchParams.get("page")) || 1);
  const pageSize = Math.min(
    100,
    Math.max(1, Number(searchParams.get("pageSize")) || DEFAULT_PAGE_SIZE)
  );

  const where = {
    ...availabilityWhere(),
    ...(category && category !== "all" ? { category } : {}),
    ...(featured === "true" ? { featured: true } : {}),
  };

  const [products, total] = await Promise.all([
    prisma.product.findMany({
      where,
      orderBy: { createdAt: "desc" },
      skip: (page - 1) * pageSize,
      take: pageSize,
    }),
    prisma.product.count({ where }),
  ]);

  return NextResponse.json({ products, total, page, pageSize });
}
