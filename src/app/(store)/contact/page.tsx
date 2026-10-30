import Link from "next/link";
import { Phone, Mail, MapPin, Clock, MessageCircle, Instagram } from "lucide-react";
import { getSiteSettings, phoneTelLink, whatsappLink } from "@/lib/settings";
import { Button } from "@/components/ui/Button";

export const revalidate = 300;

export default async function ContactPage() {
  const s = await getSiteSettings();

  return (
    <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
      <div className="max-w-2xl">
        <h1 className="font-display text-4xl font-semibold sm:text-5xl">Contact us</h1>
        <p className="mt-4 text-lg text-warmgray leading-relaxed">
          Need help with an order, sizing, or a piece you saw on our socials? Our customer care team is here for you.
        </p>
      </div>

      <div className="mt-12 grid gap-8 lg:grid-cols-2">
        <div className="rounded-2xl bg-terracotta p-8 text-white sm:p-10">
          <p className="text-sm font-semibold uppercase tracking-wider text-white/80">
            Customer care hotline
          </p>
          <a
            href={phoneTelLink(s.customerCarePhone)}
            className="mt-4 flex items-center gap-4 font-display text-3xl font-semibold hover:underline sm:text-4xl"
          >
            <Phone className="h-10 w-10 shrink-0" />
            {s.customerCarePhone}
          </a>
          <p className="mt-4 flex items-center gap-2 text-white/90">
            <Clock className="h-4 w-4" />
            {s.customerCareHours}
          </p>
          <a href={phoneTelLink(s.customerCarePhone)} className="mt-8 inline-block">
            <Button className="bg-white text-terracotta hover:bg-cream">
              Call now
            </Button>
          </a>
        </div>

        <div className="space-y-6">
          <div className="rounded-2xl bg-white p-6 ring-1 ring-sand/60">
            <div className="flex items-start gap-4">
              <Mail className="h-6 w-6 shrink-0 text-terracotta" />
              <div>
                <p className="font-semibold">Email</p>
                <a
                  href={`mailto:${s.contactEmail}`}
                  className="mt-1 text-warmgray hover:text-terracotta"
                >
                  {s.contactEmail}
                </a>
              </div>
            </div>
          </div>

          <div className="rounded-2xl bg-white p-6 ring-1 ring-sand/60">
            <div className="flex items-start gap-4">
              <MapPin className="h-6 w-6 shrink-0 text-terracotta" />
              <div>
                <p className="font-semibold">Location</p>
                <p className="mt-1 text-warmgray">{s.contactAddress}</p>
              </div>
            </div>
          </div>

          <div className="rounded-2xl bg-white p-6 ring-1 ring-sand/60">
            <p className="font-semibold">Also reach us on</p>
            <div className="mt-4 flex flex-wrap gap-3">
              {s.whatsappNumber && (
                <a
                  href={whatsappLink(s.whatsappNumber)}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 rounded-full border border-sand px-4 py-2 text-sm hover:border-terracotta hover:text-terracotta"
                >
                  <MessageCircle className="h-4 w-4" />
                  WhatsApp
                </a>
              )}
              {s.instagramUrl && (
                <a
                  href={s.instagramUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 rounded-full border border-sand px-4 py-2 text-sm hover:border-terracotta hover:text-terracotta"
                >
                  <Instagram className="h-4 w-4" />
                  Instagram
                </a>
              )}
            </div>
          </div>
        </div>
      </div>

      <div className="mt-12 rounded-2xl bg-sand/50 p-8 text-center">
        <p className="text-warmgray">
          Prefer to shop first? Browse our latest thrift drops.
        </p>
        <Link href="/shop" className="mt-4 inline-block">
          <Button>Shop now</Button>
        </Link>
      </div>
    </div>
  );
}
