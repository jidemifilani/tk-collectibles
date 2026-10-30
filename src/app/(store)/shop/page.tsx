import { prisma } from "@/lib/prisma";
import { ProductCard } from "@/components/ProductCard";
import { availabilityWhere } from "@/lib/availability";
import { CATEGORIES } from "@/types";
import Link from "next/link";
import clsx from "clsx";

export const revalidate = 60;

const PAGE_SIZE = 12;

type Props = {
  searchParams: { category?: string; page?: string };
};

export default async function ShopPage({ searchParams }: Props) {
  const rawCategory = searchParams.category;
  const category = rawCategory === "featured" ? "featured" : rawCategory || "all";
  const page = Math.max(1, Number(searchParams.page) || 1);

  const where = {
    ...availabilityWhere(),
    ...(category === "featured"
      ? { featured: true }
      : category !== "all"
        ? { category }
        : {}),
  };

  const [products, total] = await Promise.all([
    prisma.product.findMany({
      where,
      orderBy: { createdAt: "desc" },
      skip: (page - 1) * PAGE_SIZE,
      take: PAGE_SIZE,
    }),
    prisma.product.count({ where }),
  ]);

  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  const pageHref = (p: number) => {
    const params = new URLSearchParams();
    if (category !== "all") params.set("category", category);
    if (p > 1) params.set("page", String(p));
    const qs = params.toString();
    return qs ? `/shop?${qs}` : "/shop";
  };

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <div className="max-w-xl">
        <h1 className="font-display text-3xl font-semibold sm:text-4xl">Shop all</h1>
        <p className="mt-2 text-warmgray">
          Browse our full collection of curated thrift — updated weekly.
        </p>
      </div>

      <div className="relative mt-8">
        <div className="flex gap-2 overflow-x-auto pb-2">
          <Link
            href="/shop?category=featured"
            aria-current={category === "featured" ? "page" : undefined}
            className={clsx(
              "shrink-0 rounded-full px-4 py-2 text-sm font-medium transition-colors",
              category === "featured"
                ? "bg-terracotta text-white"
                : "bg-white text-charcoal ring-1 ring-sand hover:bg-sand/50"
            )}
          >
            New drops
          </Link>
          {CATEGORIES.map((cat) => (
            <Link
              key={cat.id}
              href={cat.id === "all" ? "/shop" : `/shop?category=${cat.id}`}
              aria-current={category === cat.id ? "page" : undefined}
              className={clsx(
                "shrink-0 rounded-full px-4 py-2 text-sm font-medium transition-colors",
                category === cat.id
                  ? "bg-terracotta text-white"
                  : "bg-white text-charcoal ring-1 ring-sand hover:bg-sand/50"
              )}
            >
              {cat.label}
            </Link>
          ))}
        </div>
        {/* Fades the trailing edge so it's clear the pill row scrolls further on mobile. */}
        <div className="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-cream to-transparent" />
      </div>

      {products.length > 0 ? (
        <>
          <div className="mt-10 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>

          {totalPages > 1 && (
            <nav
              className="mt-10 flex items-center justify-center gap-2"
              aria-label="Shop pagination"
            >
              <Link
                href={pageHref(Math.max(1, page - 1))}
                aria-disabled={page === 1}
                className={clsx(
                  "rounded-full px-4 py-2 text-sm font-medium ring-1 ring-sand transition-colors",
                  page === 1
                    ? "pointer-events-none text-warmgray/50"
                    : "bg-white text-charcoal hover:bg-sand/50"
                )}
              >
                Previous
              </Link>
              <span className="text-sm text-warmgray">
                Page {page} of {totalPages}
              </span>
              <Link
                href={pageHref(Math.min(totalPages, page + 1))}
                aria-disabled={page === totalPages}
                className={clsx(
                  "rounded-full px-4 py-2 text-sm font-medium ring-1 ring-sand transition-colors",
                  page === totalPages
                    ? "pointer-events-none text-warmgray/50"
                    : "bg-white text-charcoal hover:bg-sand/50"
                )}
              >
                Next
              </Link>
            </nav>
          )}
        </>
      ) : (
        <p className="mt-16 text-center text-warmgray">No items in this category right now.</p>
      )}
    </div>
  );
}
