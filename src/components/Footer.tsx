import Link from "next/link";
import { Instagram, MessageCircle, Phone } from "lucide-react";
import type { SiteSettings } from "@prisma/client";
import { phoneTelLink, whatsappLink } from "@/lib/settings";
import { StoreLogo } from "@/components/StoreLogo";
import { NotifyButton } from "@/components/NotifyButton";

export function Footer({ settings }: { settings: SiteSettings }) {
  return (
    <footer className="mt-auto border-t border-sand bg-charcoal text-cream">
      <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            {settings.logoUrl?.trim() ? (
              <StoreLogo
                logoUrl={settings.logoUrl}
                storeName={settings.storeName}
                className="max-h-20 max-w-[240px] brightness-0 invert"
              />
            ) : (
              <p className="font-display text-xl font-semibold">{settings.storeName}</p>
            )}
            <p className="mt-3 max-w-xs text-sm text-sand/90">{settings.tagline}</p>
            <div className="mt-5 max-w-xs">
              <NotifyButton variant="dark" />
            </div>
          </div>

          <div>
            <p className="text-sm font-semibold uppercase tracking-wider text-sand">Shop</p>
            <ul className="mt-4 space-y-2 text-sm">
              <li>
                <Link href="/shop" className="hover:text-terracotta">
                  All items
                </Link>
              </li>
              <li>
                <Link href="/contact" className="hover:text-terracotta">
                  Contact & support
                </Link>
              </li>
              <li>
                <Link href="/cart" className="hover:text-terracotta">
                  Your cart
                </Link>
              </li>
            </ul>
          </div>

          <div>
            <p className="text-sm font-semibold uppercase tracking-wider text-sand">Connect</p>
            <div className="mt-4 flex flex-wrap gap-3">
              <a
                href={phoneTelLink(settings.customerCarePhone)}
                className="inline-flex items-center gap-2 rounded-full border border-sand/30 px-4 py-2 text-sm transition-colors hover:border-terracotta hover:text-terracotta"
              >
                <Phone className="h-4 w-4" />
                Call us
              </a>
              {settings.instagramUrl && (
                <a
                  href={settings.instagramUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 rounded-full border border-sand/30 px-4 py-2 text-sm transition-colors hover:border-terracotta hover:text-terracotta"
                >
                  <Instagram className="h-4 w-4" />
                  Instagram
                </a>
              )}
              {settings.whatsappNumber && (
                <a
                  href={whatsappLink(settings.whatsappNumber)}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 rounded-full border border-sand/30 px-4 py-2 text-sm transition-colors hover:border-terracotta hover:text-terracotta"
                >
                  <MessageCircle className="h-4 w-4" />
                  WhatsApp
                </a>
              )}
            </div>
          </div>
        </div>

        <p className="mt-10 border-t border-sand/20 pt-6 text-center text-xs text-sand/60">
          © {new Date().getFullYear()} {settings.storeName}. Secure checkout via Paystack.
        </p>
      </div>
    </footer>
  );
}
