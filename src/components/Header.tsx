"use client";

import Link from "next/link";
import { ShoppingBag, Menu, X } from "lucide-react";
import { useEffect, useState } from "react";
import { useCart } from "@/store/cart";
import { StoreLogo } from "@/components/StoreLogo";
import type { SiteSettings } from "@prisma/client";

const nav = [
  { href: "/shop", label: "Shop" },
  { href: "/shop?category=featured", label: "New Drops" },
  { href: "/#about", label: "About" },
  { href: "/contact", label: "Contact" },
];

export function Header({ settings }: { settings: SiteSettings }) {
  const [open, setOpen] = useState(false);
  const totalItems = useCart((s) => s.totalItems());
  const hasLogo = Boolean(settings.logoUrl?.trim());

  // The cart persists to localStorage, which the server can't see — rendering
  // totalItems on the first client pass (before the store rehydrates) would
  // mismatch the server's always-zero render. Only show it post-mount.
  const [mounted, setMounted] = useState(false);
  useEffect(() => setMounted(true), []);

  return (
    <header className="sticky top-0 z-50 border-b border-sand/80 bg-cream/95 backdrop-blur-md">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 sm:py-4">
        <Link
          href="/"
          className="flex min-w-0 shrink items-center gap-2"
          aria-label={settings.storeName}
        >
          {hasLogo ? (
            <StoreLogo
              logoUrl={settings.logoUrl}
              storeName={settings.storeName}
              priority
            />
          ) : (
            <span className="font-display text-xl font-semibold tracking-tight text-charcoal sm:text-2xl">
              {settings.storeName}
            </span>
          )}
        </Link>

        <nav className="hidden items-center gap-8 md:flex">
          {nav.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className="text-sm font-medium text-warmgray transition-colors hover:text-charcoal"
            >
              {item.label}
            </Link>
          ))}
        </nav>

        <div className="flex shrink-0 items-center gap-3">
          <Link
            href="/cart"
            className="relative rounded-full p-2 text-charcoal transition-colors hover:bg-sand/50"
            aria-label="Shopping cart"
          >
            <ShoppingBag className="h-5 w-5" />
            {mounted && totalItems > 0 && (
              <span className="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-terracotta text-[10px] font-bold text-white">
                {totalItems > 9 ? "9+" : totalItems}
              </span>
            )}
          </Link>

          <button
            type="button"
            className="rounded-full p-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-terracotta md:hidden"
            onClick={() => setOpen(!open)}
            aria-label={open ? "Close menu" : "Open menu"}
          >
            {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>
        </div>
      </div>

      {open && (
        <nav className="border-t border-sand px-4 py-4 md:hidden">
          <div className="flex flex-col gap-3">
            {nav.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="py-2 text-sm font-medium"
                onClick={() => setOpen(false)}
              >
                {item.label}
              </Link>
            ))}
          </div>
        </nav>
      )}
    </header>
  );
}
