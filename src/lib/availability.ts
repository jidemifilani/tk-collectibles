import type { Product } from "@prisma/client";
import type { Prisma } from "@prisma/client";

/** Prisma `where` fragment for products a shopper can currently buy. */
export function availabilityWhere(now: Date = new Date()): Prisma.ProductWhereInput {
  return {
    inStock: true,
    OR: [{ reservedUntil: null }, { reservedUntil: { lt: now } }],
  };
}

export function isReserved(
  product: Pick<Product, "reservedUntil">,
  now: Date = new Date()
): boolean {
  return Boolean(product.reservedUntil && product.reservedUntil > now);
}

export function isAvailable(
  product: Pick<Product, "inStock" | "reservedUntil">,
  now: Date = new Date()
): boolean {
  return product.inStock && !isReserved(product, now);
}

/** How long a checkout attempt holds an item before the reservation expires. */
export const RESERVATION_MINUTES = 15;
