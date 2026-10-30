import { NextRequest, NextResponse } from "next/server";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { requireAdmin } from "@/lib/adminGuard";
import { slugify } from "@/lib/slug";
import { nairaToKobo } from "@/lib/format";
import { productSchema, zodErrorMessage } from "@/lib/validation";
import { logger } from "@/lib/logger";

type Params = { params: { id: string } };

export async function GET(request: NextRequest, { params }: Params) {
  const guardError = await requireAdmin(request);
  if (guardError) return guardError;

  const product = await prisma.product.findUnique({ where: { id: params.id } });
  if (!product) return NextResponse.json({ error: "Not found" }, { status: 404 });
  return NextResponse.json(product);
}

export async function PUT(request: NextRequest, { params }: Params) {
  const guardError = await requireAdmin(request, { mutating: true });
  if (guardError) return guardError;

  try {
    const body = await request.json().catch(() => null);
    const parsed = productSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json({ error: zodErrorMessage(parsed.error) }, { status: 400 });
    }
    const data = parsed.data;

    const existing = await prisma.product.findUnique({ where: { id: params.id } });
    if (!existing) return NextResponse.json({ error: "Not found" }, { status: 404 });

    const originalPrice = nairaToKobo(data.originalPriceNaira);
    const salePrice = nairaToKobo(data.salePriceNaira);

    let slug = data.slug ? slugify(data.slug) : slugify(data.name);
    const conflict = await prisma.product.findFirst({
      where: { slug, NOT: { id: params.id } },
    });
    if (conflict) slug = `${slug}-${Date.now().toString(36)}`;

    const product = await prisma.product.update({
      where: { id: params.id },
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
    revalidatePath(`/product/${existing.slug}`);
    if (product.slug !== existing.slug) revalidatePath(`/product/${product.slug}`);

    return NextResponse.json(product);
  } catch (err) {
    logger.error("Failed to update product", err, { productId: params.id });
    return NextResponse.json({ error: "Failed to update product" }, { status: 500 });
  }
}

export async function DELETE(request: NextRequest, { params }: Params) {
  const guardError = await requireAdmin(request, { mutating: true });
  if (guardError) return guardError;

  try {
    const existing = await prisma.product.findUnique({ where: { id: params.id } });
    if (!existing) return NextResponse.json({ error: "Not found" }, { status: 404 });

    await prisma.product.delete({ where: { id: params.id } });

    revalidatePath("/");
    revalidatePath("/shop");
    revalidatePath(`/product/${existing.slug}`);

    return NextResponse.json({ success: true });
  } catch (err) {
    logger.error("Failed to delete product", err, { productId: params.id });
    return NextResponse.json({ error: "Failed to delete product" }, { status: 500 });
  }
}
