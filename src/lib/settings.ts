import { prisma } from "@/lib/prisma";
import type { SiteSettings } from "@prisma/client";

export const SETTINGS_ID = "default";

export const DEFAULT_SETTINGS = {
  id: SETTINGS_ID,
  storeName: "T.K Collectibles",
  logoUrl: "/tk-logo.png",
  tagline: "Spend less, wear luxury",
  heroTitle: "Unique thrift pieces, curated for you",
  heroSubtitle:
    "One-of-one secondhand fashion — the same items from Instagram and TikTok, now shoppable with secure Paystack checkout.",
  heroImageUrl:
    "https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=1600&q=80",
  aboutTitle: "From DMs to your doorstep",
  aboutText:
    "We started on social media — sharing daily thrift finds with our community. Each item is photographed honestly: real condition, real measurements.",
  contactEmail: "hello@curatedthrift.com",
  contactAddress: "Lagos, Nigeria",
  customerCarePhone: "+234 903 413 1872",
  customerCareHours: "Mon–Sat, 9am–6pm WAT",
  instagramUrl: "https://instagram.com/yourstore",
  tiktokUrl: "https://tiktok.com/@yourstore",
  whatsappNumber: "2349034131872",
  colorCream: "#F6EDE8",
  colorSand: "#EBDDD5",
  colorTerracotta: "#7A1F2B",
  colorRust: "#5E1620",
  colorOlive: "#5C6B4A",
  colorCharcoal: "#2C2825",
  colorWarmgray: "#6B6560",
};

export async function getSiteSettings(): Promise<SiteSettings> {
  const existing = await prisma.siteSettings.findUnique({
    where: { id: SETTINGS_ID },
  });
  if (existing) return existing;

  return prisma.siteSettings.create({ data: DEFAULT_SETTINGS });
}

export function themeStyle(settings: SiteSettings): Record<string, string> {
  return {
    ["--color-cream" as string]: settings.colorCream,
    ["--color-sand" as string]: settings.colorSand,
    ["--color-terracotta" as string]: settings.colorTerracotta,
    ["--color-rust" as string]: settings.colorRust,
    ["--color-olive" as string]: settings.colorOlive,
    ["--color-charcoal" as string]: settings.colorCharcoal,
    ["--color-warmgray" as string]: settings.colorWarmgray,
  };
}

export function phoneTelLink(phone: string): string {
  return `tel:${phone.replace(/\s/g, "")}`;
}

export function whatsappLink(number: string): string {
  return `https://wa.me/${number.replace(/\D/g, "")}`;
}
