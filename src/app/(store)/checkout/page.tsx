"use client";

import { FormEvent, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useCart } from "@/store/cart";
import { formatNaira } from "@/lib/format";
import { Button } from "@/components/ui/Button";
import { AlertTriangle, Lock } from "lucide-react";

const NIGERIAN_STATES = [
  "Abia", "Adamawa", "Akwa Ibom", "Anambra", "Bauchi", "Bayelsa", "Benue", "Borno",
  "Cross River", "Delta", "Ebonyi", "Edo", "Ekiti", "Enugu", "FCT", "Gombe", "Imo",
  "Jigawa", "Kaduna", "Kano", "Katsina", "Kebbi", "Kogi", "Kwara", "Lagos", "Nasarawa",
  "Niger", "Ogun", "Ondo", "Osun", "Oyo", "Plateau", "Rivers", "Sokoto", "Taraba",
  "Yobe", "Zamfara",
];

// Loose client-side sanity check (digits/spaces/dashes/plus, reasonable length) —
// the server normalizes and validates the actual number authoritatively.
const PHONE_PATTERN = "^[0-9+\\-\\s()]{10,20}$";

export default function CheckoutPage() {
  const { items, subtotal, removeItem } = useCart();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [unavailable, setUnavailable] = useState<string[]>([]);
  const [checkingStock, setCheckingStock] = useState(true);
  const idempotencyKey = useRef(crypto.randomUUID());

  useEffect(() => {
    if (items.length === 0) {
      setCheckingStock(false);
      return;
    }
    let cancelled = false;
    setCheckingStock(true);
    fetch("/api/cart/check", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ productIds: items.map((i) => i.productId) }),
    })
      .then((res) => res.json())
      .then((data: { items: { id: string; available: boolean }[] }) => {
        if (cancelled) return;
        const soldIds = data.items.filter((i) => !i.available).map((i) => i.id);
        setUnavailable(soldIds);
      })
      .catch(() => {
        // Non-fatal — the server re-validates at payment time regardless.
      })
      .finally(() => {
        if (!cancelled) setCheckingStock(false);
      });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [items.length]);

  if (items.length === 0) {
    return (
      <div className="mx-auto max-w-lg px-4 py-20 text-center">
        <p className="text-warmgray">Your cart is empty.</p>
        <Link href="/shop" className="mt-4 inline-block text-terracotta hover:text-rust">
          Go to shop
        </Link>
      </div>
    );
  }

  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setLoading(true);
    setError("");

    const form = new FormData(e.currentTarget);

    try {
      const res = await fetch("/api/paystack/initialize", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Idempotency-Key": idempotencyKey.current,
        },
        body: JSON.stringify({
          customerName: form.get("name"),
          customerEmail: form.get("email"),
          customerPhone: form.get("phone"),
          shippingAddress: form.get("address"),
          city: form.get("city"),
          state: form.get("state"),
          items: items.map((i) => ({
            productId: i.productId,
            quantity: i.quantity,
            size: i.size,
          })),
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.error || "Checkout failed");
      }

      window.location.href = data.authorization_url;
    } catch (err) {
      setError(err instanceof Error ? err.message : "Something went wrong");
      setLoading(false);
    }
  }

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <h1 className="font-display text-3xl font-semibold">Checkout</h1>
      <p className="mt-2 flex items-center gap-2 text-sm text-warmgray">
        <Lock className="h-4 w-4" />
        Secure payment powered by Paystack
      </p>

      <form onSubmit={handleSubmit} className="mt-10 grid gap-10 lg:grid-cols-2">
        <div className="space-y-5">
          <h2 className="font-display text-lg font-semibold">Delivery details</h2>

          <div>
            <label htmlFor="name" className="block text-sm font-medium">Full name</label>
            <input
              id="name"
              name="name"
              required
              className="mt-1 w-full rounded-xl border border-sand bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none focus:ring-1 focus:ring-terracotta"
              placeholder="Ada Okonkwo"
            />
          </div>

          <div className="grid gap-5 sm:grid-cols-2">
            <div>
              <label htmlFor="email" className="block text-sm font-medium">Email</label>
              <input
                id="email"
                name="email"
                type="email"
                required
                className="mt-1 w-full rounded-xl border border-sand bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none focus:ring-1 focus:ring-terracotta"
                placeholder="you@email.com"
              />
            </div>
            <div>
              <label htmlFor="phone" className="block text-sm font-medium">Phone (WhatsApp)</label>
              <input
                id="phone"
                name="phone"
                type="tel"
                required
                pattern={PHONE_PATTERN}
                title="Enter a valid Nigerian phone number, e.g. 0801 234 5678"
                className="mt-1 w-full rounded-xl border border-sand bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none focus:ring-1 focus:ring-terracotta"
                placeholder="08012345678"
              />
            </div>
          </div>

          <div>
            <label htmlFor="address" className="block text-sm font-medium">Street address</label>
            <textarea
              id="address"
              name="address"
              required
              rows={2}
              className="mt-1 w-full rounded-xl border border-sand bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none focus:ring-1 focus:ring-terracotta"
              placeholder="House number, street, landmark"
            />
          </div>

          <div className="grid gap-5 sm:grid-cols-2">
            <div>
              <label htmlFor="city" className="block text-sm font-medium">City</label>
              <input
                id="city"
                name="city"
                required
                className="mt-1 w-full rounded-xl border border-sand bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none focus:ring-1 focus:ring-terracotta"
                placeholder="Lagos"
              />
            </div>
            <div>
              <label htmlFor="state" className="block text-sm font-medium">State</label>
              <select
                id="state"
                name="state"
                required
                className="mt-1 w-full rounded-xl border border-sand bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none focus:ring-1 focus:ring-terracotta"
                defaultValue=""
              >
                <option value="" disabled>Select state</option>
                {NIGERIAN_STATES.map((s) => (
                  <option key={s} value={s}>{s}</option>
                ))}
              </select>
            </div>
          </div>
        </div>

        <div className="h-fit rounded-2xl bg-white p-6 ring-1 ring-sand/60">
          <h2 className="font-display text-lg font-semibold">Your order</h2>
          <ul className="mt-4 space-y-3 border-b border-sand pb-4">
            {items.map((item) => {
              const isUnavailable = unavailable.includes(item.productId);
              return (
                <li
                  key={`${item.productId}-${item.size}`}
                  className="flex items-start justify-between gap-3 text-sm"
                >
                  <span className={isUnavailable ? "text-warmgray line-through" : undefined}>
                    {item.name} × {item.quantity}
                    <span className="text-warmgray"> · {item.size}</span>
                    {isUnavailable && (
                      <span className="mt-0.5 block text-xs font-medium text-rust no-underline">
                        No longer available
                      </span>
                    )}
                  </span>
                  {isUnavailable ? (
                    <button
                      type="button"
                      onClick={() => {
                        removeItem(item.productId, item.size);
                        setUnavailable((prev) => prev.filter((id) => id !== item.productId));
                      }}
                      className="shrink-0 text-xs font-medium text-rust underline underline-offset-2 hover:text-rust/80"
                    >
                      Remove
                    </button>
                  ) : (
                    <span>{formatNaira(item.salePrice * item.quantity)}</span>
                  )}
                </li>
              );
            })}
          </ul>
          <div className="mt-4 flex justify-between font-semibold">
            <span>Total</span>
            <span className="text-terracotta">{formatNaira(subtotal())}</span>
          </div>

          {unavailable.length > 0 && (
            <p className="mt-4 flex items-start gap-2 rounded-lg bg-rust/10 px-3 py-2 text-sm text-rust">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
              Some items in your cart just sold or are being purchased by someone else. Remove
              them to continue.
            </p>
          )}

          {error && (
            <p className="mt-4 rounded-lg bg-rust/10 px-3 py-2 text-sm text-rust">{error}</p>
          )}

          <Button
            type="submit"
            fullWidth
            className="mt-6"
            disabled={loading || checkingStock || unavailable.length > 0}
          >
            {loading
              ? "Redirecting to Paystack…"
              : checkingStock
                ? "Checking availability…"
                : "Pay with Paystack"}
          </Button>

          <p className="mt-4 text-center text-xs text-warmgray">
            You&apos;ll be redirected to Paystack to complete payment via card, bank, or USSD.
          </p>
        </div>
      </form>
    </div>
  );
}
