import Link from "next/link";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { prisma } from "@/lib/prisma";
import { getSiteSettings } from "@/lib/settings";
import { PriceDisplay } from "@/components/PriceDisplay";
import { AddToCartButton } from "@/components/AddToCartButton";
import { ProductCard } from "@/components/ProductCard";
import { ProductGallery } from "@/components/ProductGallery";
import { productImages } from "@/lib/images";
import { isAvailable, isReserved, availabilityWhere } from "@/lib/availability";
import { ArrowLeft } from "lucide-react";

export const revalidate = 60;

type Props = {
  params: { slug: string };
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const product = await prisma.product.findUnique({ where: { slug: params.slug } });
  if (!product) return {};

  const settings = await getSiteSettings();
  const description =
    product.description?.slice(0, 160) || `${product.name} — ${settings.storeName}`;

  return {
    title: product.name,
    description,
    openGraph: {
      title: `${product.name} | ${settings.storeName}`,
      description,
      images: [{ url: product.imageUrl }],
      type: "website",
    },
    twitter: {
      card: "summary_large_image",
      title: product.name,
      description,
      images: [product.imageUrl],
    },
  };
}

export default async function ProductPage({ params }: Props) {
  const { slug } = params;
  const product = await prisma.product.findUnique({ where: { slug } });

  // Only a genuinely nonexistent slug 404s. A sold/reserved item still
  // renders — a shared link (chat bot, Instagram, WhatsApp) landing on a
  // blank 404 for something that was here yesterday reads as broken.
  if (!product) notFound();

  const available = isAvailable(product);
  const reserved = !available && isReserved(product);
  const availability = available ? "available" : reserved ? "reserved" : "sold";

  const similar =
    availability === "available"
      ? []
      : await prisma.product.findMany({
          where: { category: product.category, id: { not: product.id }, ...availabilityWhere() },
          take: 4,
          orderBy: { createdAt: "desc" },
        });

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
      <Link
        href="/shop"
        className="inline-flex items-center gap-2 text-sm text-warmgray hover:text-charcoal"
      >
        <ArrowLeft className="h-4 w-4" />
        Back to shop
      </Link>

      <div className="mt-8 grid gap-10 lg:grid-cols-2">
        <ProductGallery images={productImages(product)} alt={product.name} />

        <div className="flex flex-col">
          <div className="flex items-center gap-3">
            <p className="text-sm uppercase tracking-wide text-warmgray">{product.category}</p>
            {availability === "sold" && (
              <span className="rounded-full bg-rust/10 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-rust">
                Sold out
              </span>
            )}
            {availability === "reserved" && (
              <span className="rounded-full bg-olive/10 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-olive">
                Reserved
              </span>
            )}
          </div>
          <h1 className="mt-2 font-display text-3xl font-semibold sm:text-4xl">{product.name}</h1>
          <div className="mt-4">
            <PriceDisplay
              originalPrice={product.originalPrice}
              salePrice={product.salePrice}
              size="lg"
            />
          </div>

          <dl className="mt-6 grid grid-cols-2 gap-4 rounded-xl bg-white p-4 ring-1 ring-sand/60">
            <div>
              <dt className="text-xs uppercase text-warmgray">Size</dt>
              <dd className="mt-1 font-medium">{product.size}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-warmgray">Condition</dt>
              <dd className="mt-1 font-medium">{product.condition}</dd>
            </div>
          </dl>

          <p className="mt-6 leading-relaxed text-warmgray">{product.description}</p>

          <p className="mt-4 text-sm text-warmgray">
            One-of-one thrift piece. Secure checkout with Paystack — card, bank, or USSD.
          </p>

          <div className="mt-8">
            <AddToCartButton product={product} availability={availability} />
          </div>
        </div>
      </div>

      {similar.length > 0 && (
        <div className="mt-16">
          <h2 className="font-display text-2xl font-semibold">You might also like</h2>
          <div className="mt-6 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
            {similar.map((p) => (
              <ProductCard key={p.id} product={p} />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
