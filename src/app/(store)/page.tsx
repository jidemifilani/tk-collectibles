import { NotifyButton } from "@/components/NotifyButton";
import Image from "next/image";
import Link from "next/link";
import { ArrowRight, Sparkles, Truck, Shield, Phone } from "lucide-react";
import { prisma } from "@/lib/prisma";
import { getSiteSettings, phoneTelLink } from "@/lib/settings";
import { ProductCard } from "@/components/ProductCard";
import { Button } from "@/components/ui/Button";
import { availabilityWhere } from "@/lib/availability";

export const revalidate = 60;

export default async function HomePage() {
  const [featured, settings] = await Promise.all([
    prisma.product.findMany({
      where: { featured: true, ...availabilityWhere() },
      take: 4,
      orderBy: { createdAt: "desc" },
    }),
    getSiteSettings(),
  ]);

  return (
    <>
      <section className="relative overflow-hidden bg-charcoal text-cream">
        <div className="absolute inset-0 opacity-40">
          <Image
            src={settings.heroImageUrl}
            alt=""
            fill
            className="object-cover"
            priority
          />
        </div>
        <div className="relative mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32 lg:py-40">
          <p className="inline-flex items-center gap-2 rounded-full bg-terracotta/90 px-3 py-1 text-xs font-medium uppercase tracking-wider">
            <Sparkles className="h-3.5 w-3.5" />
            {settings.tagline}
          </p>
          <h1 className="mt-6 max-w-2xl font-display text-4xl font-semibold leading-tight sm:text-5xl lg:text-6xl text-balance">
            {settings.heroTitle}
          </h1>
          <p className="mt-6 max-w-lg text-lg text-sand/90">{settings.heroSubtitle}</p>
          <div className="mt-10 flex flex-wrap gap-4">
            <Link href="/shop">
              <Button className="bg-terracotta hover:bg-rust">
                Shop the drop
                <ArrowRight className="h-4 w-4" />
              </Button>
            </Link>
            <Link href="/contact">
              <Button variant="outline" className="border-cream text-cream hover:bg-cream hover:text-charcoal">
                <Phone className="h-4 w-4" />
                Customer care
              </Button>
            </Link>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div className="grid gap-8 sm:grid-cols-3">
          {[
            { icon: Sparkles, title: "Curated picks", text: "Every piece hand-selected — no bulk warehouse vibes." },
            { icon: Truck, title: "Nationwide delivery", text: "We ship across Nigeria. Track your order via WhatsApp." },
            { icon: Shield, title: "Secure Paystack", text: "Pay with card, bank transfer, or USSD — safe & simple." },
          ].map(({ icon: Icon, title, text }) => (
            <div key={title} className="rounded-2xl bg-white p-6 ring-1 ring-sand/60">
              <Icon className="h-8 w-8 text-terracotta" />
              <h3 className="mt-4 font-display text-lg font-semibold">{title}</h3>
              <p className="mt-2 text-sm text-warmgray">{text}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="bg-sand/40 py-16">
        <div className="mx-auto max-w-6xl px-4 sm:px-6">
          <div className="flex items-end justify-between gap-4">
            <div>
              <h2 className="font-display text-3xl font-semibold">This week&apos;s drop</h2>
              <p className="mt-2 text-warmgray">Limited pieces — when they&apos;re gone, they&apos;re gone.</p>
              <div className="mt-4">
                <NotifyButton />
              </div>
            </div>
            <Link href="/shop" className="hidden text-sm font-medium text-terracotta hover:text-rust sm:block">
              View all →
            </Link>
          </div>
          <div className="mt-10 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
            {featured.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
          {featured.length === 0 && (
            <p className="py-12 text-center text-warmgray">No featured items yet. Add products in the admin panel.</p>
          )}
        </div>
      </section>

      <section id="about" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div className="grid items-center gap-12 lg:grid-cols-2">
          <div className="relative aspect-[4/5] overflow-hidden rounded-2xl bg-sand">
            <Image
              src="https://images.unsplash.com/photo-1445205170230-053b83016050?w=800&q=80"
              alt="Thrift clothing"
              fill
              className="object-cover"
            />
          </div>
          <div>
            <h2 className="font-display text-3xl font-semibold">{settings.aboutTitle}</h2>
            <p className="mt-4 text-warmgray leading-relaxed whitespace-pre-line">{settings.aboutText}</p>
            <div className="mt-8 flex flex-wrap gap-4">
              <Link href="/shop">
                <Button>Start shopping</Button>
              </Link>
              <a href={phoneTelLink(settings.customerCarePhone)}>
                <Button variant="outline">Call {settings.customerCarePhone}</Button>
              </a>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
