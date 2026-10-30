import Link from "next/link";
import { Product } from "@/types";
import { PriceDisplay } from "@/components/PriceDisplay";
import { ProductImage } from "@/components/ProductImage";
import { isNewArrival } from "@/lib/newArrival";

export function ProductCard({ product }: { product: Product }) {
  return (
    <Link
      href={`/product/${product.slug}`}
      className="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-sand/60 transition-shadow hover:shadow-md"
    >
      <div className="relative aspect-[3/4] overflow-hidden bg-sand">
        <ProductImage
          src={product.imageUrl}
          alt={product.name}
          fill
          className="object-cover transition-transform duration-500 group-hover:scale-105"
          sizes="(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw"
        />
        <div className="absolute left-3 top-3 flex gap-1.5">
          {isNewArrival(product.createdAt) && (
            <span className="rounded-full bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-terracotta shadow-sm">
              New
            </span>
          )}
          {product.featured && (
            <span className="rounded-full bg-terracotta px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">
              Drop
            </span>
          )}
        </div>
      </div>
      <div className="flex flex-1 flex-col p-4">
        <p className="text-xs uppercase tracking-wide text-warmgray">{product.category}</p>
        <h3 className="mt-1 font-display text-lg leading-tight text-charcoal">{product.name}</h3>
        <div className="mt-auto flex items-end justify-between gap-2 pt-3">
          <PriceDisplay
            originalPrice={product.originalPrice}
            salePrice={product.salePrice}
            size="sm"
          />
          <span className="shrink-0 text-xs text-warmgray">Size {product.size}</span>
        </div>
      </div>
    </Link>
  );
}
