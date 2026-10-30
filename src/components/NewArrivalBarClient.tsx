"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Sparkles, X } from "lucide-react";

const STORAGE_KEY = "tk-new-arrival-dismissed";

export function NewArrivalBarClient({ count, dropId }: { count: number; dropId: string }) {
  // Hidden until we know it wasn't dismissed for this drop (avoids a flash).
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    try {
      setVisible(localStorage.getItem(STORAGE_KEY) !== dropId);
    } catch {
      setVisible(true);
    }
  }, [dropId]);

  function dismiss() {
    setVisible(false);
    try {
      localStorage.setItem(STORAGE_KEY, dropId);
    } catch {}
  }

  if (!visible) return null;

  return (
    <div className="bg-terracotta text-cream">
      <div className="mx-auto flex max-w-6xl items-center justify-center gap-3 px-4 py-2 text-sm sm:px-6">
        <Sparkles className="h-4 w-4 shrink-0" aria-hidden />
        <p>
          <span className="font-semibold">New arrivals!</span>{" "}
          {count} fresh {count === 1 ? "piece" : "pieces"} just dropped.{" "}
          <Link href="/shop" onClick={dismiss} className="font-semibold underline underline-offset-2">
            Shop now
          </Link>
        </p>
        <button
          type="button"
          onClick={dismiss}
          aria-label="Dismiss new arrivals alert"
          className="ml-auto rounded p-1 hover:bg-white/10"
        >
          <X className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}
