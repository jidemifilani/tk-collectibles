import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { requireAdmin } from "@/lib/adminGuard";

const DEFAULT_PAGE_SIZE = 50;

export async function GET(request: NextRequest) {
  const guardError = await requireAdmin(request);
  if (guardError) return guardError;

  const { searchParams } = new URL(request.url);
  const page = Math.max(1, Number(searchParams.get("page")) || 1);
  const pageSize = Math.min(
    100,
    Math.max(1, Number(searchParams.get("pageSize")) || DEFAULT_PAGE_SIZE)
  );

  const [orders, total] = await Promise.all([
    prisma.order.findMany({
      orderBy: { createdAt: "desc" },
      include: { items: { include: { product: true } } },
      skip: (page - 1) * pageSize,
      take: pageSize,
    }),
    prisma.order.count(),
  ]);

  return NextResponse.json({ orders, total, page, pageSize });
}
