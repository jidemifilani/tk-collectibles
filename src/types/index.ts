export type Product = {
  id: string;
  name: string;
  slug: string;
  description: string;
  originalPrice: number;
  salePrice: number;
  category: string;
  size: string;
  condition: string;
  imageUrl: string;
  images: unknown;
  featured: boolean;
  inStock: boolean;
  reservedUntil: Date | null;
  createdAt: Date;
};

export const CATEGORIES = [
  { id: "all", label: "All" },
  { id: "tops", label: "Tops" },
  { id: "bottoms", label: "Bottoms" },
  { id: "dresses", label: "Dresses" },
  { id: "outerwear", label: "Outerwear" },
  { id: "accessories", label: "Accessories" },
] as const;

export type SiteSettingsPayload = {
  storeName: string;
  logoUrl: string;
  tagline: string;
  heroTitle: string;
  heroSubtitle: string;
  heroImageUrl: string;
  aboutTitle: string;
  aboutText: string;
  contactEmail: string;
  contactAddress: string;
  customerCarePhone: string;
  customerCareHours: string;
  instagramUrl: string;
  tiktokUrl: string;
  whatsappNumber: string;
  colorCream: string;
  colorSand: string;
  colorTerracotta: string;
  colorRust: string;
  colorOlive: string;
  colorCharcoal: string;
  colorWarmgray: string;
};
