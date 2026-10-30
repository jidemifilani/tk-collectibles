"use client";

import Link from "next/link";
import { Minus, Plus, Trash2 } from "lucide-react";
import { useCart } from "@/store/cart";
import { PriceDisplay } from "@/components/PriceDisplay";
import { ProductImage } from "@/components/ProductImage";
import { formatNaira } from "@/lib/format";
import { Button } from "@/components/ui/Button";

export default function CartPage() {
  const { items, updateQuantity, removeItem, subtotal, totalItems } = useCart();

  if (items.length === 0) {
    return (
      <div className="mx-auto max-w-lg px-4 py-20 text-center sm:px-6">
        <h1 className="font-display text-2xl font-semibold">Your cart is empty</h1>
        <p className="mt-2 text-warmgray">Find something you love from this week&apos;s drop.</p>
        <Link href="/shop" className="mt-8 inline-block">
          <Button>Browse shop</Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <h1 className="font-display text-3xl font-semibold">Cart ({totalItems()})</h1>

      <div className="mt-10 grid gap-10 lg:grid-cols-3">
        <ul className="space-y-4 lg:col-span-2">
          {items.map((item) => (
            <li
              key={`${item.productId}-${item.size}`}
              className="flex gap-4 rounded-2xl bg-white p-4 ring-1 ring-sand/60"
            >
              <div className="relative h-28 w-24 shrink-0 overflow-hidden rounded-xl bg-sand">
                <ProductImage
                  src={item.imageUrl}
                  alt={item.name}
                  fill
                  className="object-cover"
                />
              </div>
              <div className="flex flex-1 flex-col">
                <Link href={`/product/${item.slug}`} className="font-medium hover:text-terracotta">
                  {item.name}
                </Link>
                <p className="text-sm text-warmgray">Size {item.size}</p>
                <PriceDisplay
                  originalPrice={item.originalPrice}
                  salePrice={item.salePrice}
                  size="sm"
                  className="mt-1"
                />
                <div className="mt-auto flex items-center justify-between pt-3">
                  <div className="flex items-center gap-2 rounded-full border border-sand px-1">
                    <button
                      type="button"
                      className="rounded-full p-2 hover:bg-sand/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-terracotta"
                      onClick={() =>
                        updateQuantity(item.productId, item.size, item.quantity - 1)
                      }
                      aria-label="Decrease quantity"
                    >
                      <Minus className="h-4 w-4" />
                    </button>
                    <span className="w-8 text-center text-sm font-medium">{item.quantity}</span>
                    <button
                      type="button"
                      className="rounded-full p-2 hover:bg-sand/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-terracotta"
                      onClick={() =>
                        updateQuantity(item.productId, item.size, item.quantity + 1)
                      }
                      aria-label="Increase quantity"
                    >
                      <Plus className="h-4 w-4" />
                    </button>
                  </div>
                  <button
                    type="button"
                    className="rounded-full p-2 text-warmgray hover:text-rust focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-terracotta"
                    onClick={() => removeItem(item.productId, item.size)}
                    aria-label="Remove item"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>

        <div className="h-fit rounded-2xl bg-white p-6 ring-1 ring-sand/60">
          <h2 className="font-display text-lg font-semibold">Order summary</h2>
          <div className="mt-4 flex justify-between text-sm">
            <span className="text-warmgray">Subtotal</span>
            <span className="font-semibold">{formatNaira(subtotal())}</span>
          </div>
          <p className="mt-2 text-xs text-warmgray">
            Shipping calculated at checkout. Delivery across Nigeria.
          </p>
          <Link href="/checkout" className="mt-6 block">
            <Button fullWidth>Proceed to checkout</Button>
          </Link>
          <Link href="/shop" className="mt-3 block text-center text-sm text-terracotta hover:text-rust">
            Continue shopping
          </Link>
        </div>
      </div>
    </div>
  );
}
