"use client";

import { useEffect, useState, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import Link from "next/link";
import { CheckCircle, Loader2, XCircle } from "lucide-react";
import { useCart } from "@/store/cart";
import { formatNaira } from "@/lib/format";
import { Button } from "@/components/ui/Button";

type OrderItem = {
  quantity: number;
  size: string;
  price: number;
  product: { name: string };
};

type Order = {
  reference: string;
  customerName: string;
  totalAmount: number;
  status: string;
  items: OrderItem[];
};

function SuccessContent() {
  const searchParams = useSearchParams();
  const reference = searchParams.get("reference");
  const clearCart = useCart((s) => s.clearCart);
  const [order, setOrder] = useState<Order | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!reference) {
      setError("No payment reference found");
      setLoading(false);
      return;
    }

    fetch(`/api/paystack/verify?reference=${encodeURIComponent(reference)}`)
      .then(async (res) => {
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || "Verification failed");
        setOrder(data.order);
        if (data.order?.status === "paid") clearCart();
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : "Could not verify payment");
      })
      .finally(() => setLoading(false));
  }, [reference, clearCart]);

  if (loading) {
    return (
      <div className="flex flex-col items-center py-20">
        <Loader2 className="h-10 w-10 animate-spin text-terracotta" />
        <p className="mt-4 text-warmgray">Confirming your payment…</p>
      </div>
    );
  }

  if (error || !order) {
    return (
      <div className="mx-auto max-w-lg px-4 py-20 text-center">
        <XCircle className="mx-auto h-14 w-14 text-rust" />
        <h1 className="mt-4 font-display text-2xl font-semibold">Payment issue</h1>
        <p className="mt-2 text-warmgray">{error || "We could not confirm your order."}</p>
        <p className="mt-2 text-sm text-warmgray">
          If you were charged, message us on WhatsApp with reference:{" "}
          <strong>{reference}</strong>
        </p>
        <Link href="/shop" className="mt-8 inline-block">
          <Button>Back to shop</Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-lg px-4 py-16 text-center sm:px-6">
      <CheckCircle className="mx-auto h-16 w-16 text-olive" />
      <h1 className="mt-6 font-display text-3xl font-semibold">Thank you!</h1>
      <p className="mt-2 text-warmgray">
        Hi {order.customerName}, your payment was successful. We&apos;ll pack your thrift finds and reach out on WhatsApp for delivery updates.
      </p>

      <div className="mt-8 rounded-2xl bg-white p-6 text-left ring-1 ring-sand/60">
        <p className="text-xs uppercase tracking-wide text-warmgray">Order reference</p>
        <p className="mt-1 font-mono text-sm font-medium">{order.reference}</p>
        <ul className="mt-4 space-y-2 border-t border-sand pt-4 text-sm">
          {order.items.map((item, i) => (
            <li key={i} className="flex justify-between">
              <span>
                {item.product.name} × {item.quantity} ({item.size})
              </span>
              <span>{formatNaira(item.price * item.quantity)}</span>
            </li>
          ))}
        </ul>
        <div className="mt-4 flex justify-between border-t border-sand pt-4 font-semibold">
          <span>Total paid</span>
          <span className="text-terracotta">{formatNaira(order.totalAmount)}</span>
        </div>
      </div>

      <Link href="/shop" className="mt-8 inline-block">
        <Button variant="outline">Continue shopping</Button>
      </Link>
    </div>
  );
}

export default function OrderSuccessPage() {
  return (
    <Suspense
      fallback={
        <div className="flex justify-center py-20">
          <Loader2 className="h-10 w-10 animate-spin text-terracotta" />
        </div>
      }
    >
      <SuccessContent />
    </Suspense>
  );
}
