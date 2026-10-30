import { NextRequest, NextResponse } from "next/server";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { requireAdmin } from "@/lib/adminGuard";
import { slugify } from "@/lib/slug";
import { formatNaira, nairaToKobo } from "@/lib/format";
import { notifyAllSubscribers } from "@/lib/push";
import { productSchema, zodErrorMessage } from "@/lib/validation";
import { logger } from "@/lib/logger";

export async function GET(request: NextRequest) {
  const guardError = await requireAdmin(request);
  if (guardError) return guardError;

  const products = await prisma.product.findMany({
    orderBy: { createdAt: "desc" },
  });
  return NextResponse.json(products);
}

export async function POST(request: NextRequest) {
  const guardError = await requireAdmin(request, { mutating: true });
  if (guardError) return guardError;

  try {
    const body = await request.json().catch(() => null);
    const parsed = productSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json({ error: zodErrorMessage(parsed.error) }, { status: 400 });
    }
    const data = parsed.data;

    const originalPrice = nairaToKobo(data.originalPriceNaira);
    const salePrice = nairaToKobo(data.salePriceNaira);

    let slug = data.slug ? slugify(data.slug) : slugify(data.name);
    const existing = await prisma.product.findUnique({ where: { slug } });
    if (existing) slug = `${slug}-${Date.now().toString(36)}`;

    const product = await prisma.product.create({
      data: {
        name: data.name,
        slug,
        description: data.description,
        originalPrice,
        salePrice,
        category: data.category,
        size: data.size,
        condition: data.condition,
        imageUrl: data.imageUrl,
        images: data.images,
        featured: data.featured,
        inStock: data.inStock,
      },
    });

    revalidatePath("/");
    revalidatePath("/shop");

    if (product.inStock) {
      // Don't make the admin wait on phone delivery.
      notifyAllSubscribers({
        title: "New arrival ✨",
        body: `${product.name} — ${formatNaira(product.salePrice)} · Size ${product.size}`,
        url: `/product/${product.slug}`,
        image: product.imageUrl || undefined,
      }).catch((err) => logger.error("New-arrival push failed", err));
    }

    return NextResponse.json(product, { status: 201 });
  } catch (err) {
    logger.error("Failed to create product", err);
    return NextResponse.json({ error: "Failed to create product" }, { status: 500 });
  }
}
