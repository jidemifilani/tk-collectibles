import { z } from "zod";
import { CATEGORIES } from "@/types";

const CATEGORY_IDS = CATEGORIES.filter((c) => c.id !== "all").map((c) => c.id) as [
  string,
  ...string[],
];

export const CONDITIONS = [
  "New with tags",
  "Like New",
  "Excellent",
  "Good",
  "Fair",
] as const;

/** ₦5,000,000 ceiling per item — generous for thrift resale, catches fat-finger typos. */
export const MAX_ITEM_PRICE_NAIRA = 5_000_000;

const priceNaira = z
  .number()
  .positive("Price must be greater than zero")
  .max(MAX_ITEM_PRICE_NAIRA, `Price must be ${MAX_ITEM_PRICE_NAIRA.toLocaleString()} or less`);

export const productSchema = z.object({
  name: z.string().trim().min(1, "Name is required").max(200),
  slug: z.string().trim().max(200).optional(),
  description: z.string().trim().max(4000).optional().default(""),
  originalPriceNaira: priceNaira,
  salePriceNaira: priceNaira,
  category: z.enum(CATEGORY_IDS, { error: "Invalid category" }),
  size: z.string().trim().min(1, "Size is required").max(40),
  condition: z.enum(CONDITIONS, { error: "Invalid condition" }),
  imageUrl: z.string().trim().min(1, "Image is required"),
  images: z.array(z.string().trim().min(1)).max(6, "Up to 6 extra photos").optional().default([]),
  featured: z.boolean().optional().default(false),
  inStock: z.boolean().optional().default(true),
});

export type ProductInput = z.infer<typeof productSchema>;

export const loginSchema = z.object({
  password: z.string().min(1, "Password is required"),
});

const NIGERIAN_PHONE_RE = /^(\+?234|0)[789][01]\d{8}$/;

export const checkoutSchema = z.object({
  customerName: z.string().trim().min(1, "Name is required").max(200),
  customerEmail: z.string().trim().email("Enter a valid email"),
  customerPhone: z
    .string()
    .trim()
    // Strip spaces/dashes/parens before matching — shoppers commonly format
    // as "0801 234 5678" or "+234-801-234-5678".
    .transform((v) => v.replace(/[\s\-()]/g, ""))
    .refine((v) => NIGERIAN_PHONE_RE.test(v), "Enter a valid Nigerian phone number"),
  shippingAddress: z.string().trim().min(1, "Address is required").max(500),
  city: z.string().trim().min(1, "City is required").max(100),
  state: z.string().trim().min(1, "State is required").max(100),
  items: z
    .array(
      z.object({
        productId: z.string().min(1),
        quantity: z.number().int().positive().max(20),
        size: z.string().trim().min(1).max(40),
      })
    )
    .min(1, "Your cart is empty"),
});

export const settingsSchema = z.object({
  storeName: z.string().trim().min(1).max(200),
  logoUrl: z.string().trim().max(2000).optional().default(""),
  tagline: z.string().trim().max(300).optional().default(""),
  heroTitle: z.string().trim().max(300).optional().default(""),
  heroSubtitle: z.string().trim().max(600).optional().default(""),
  heroImageUrl: z.string().trim().max(2000).optional().default(""),
  aboutTitle: z.string().trim().max(300).optional().default(""),
  aboutText: z.string().trim().max(4000).optional().default(""),
  contactEmail: z.string().trim().max(200).optional().default(""),
  contactAddress: z.string().trim().max(300).optional().default(""),
  customerCarePhone: z.string().trim().min(1).max(60),
  customerCareHours: z.string().trim().max(100).optional().default(""),
  instagramUrl: z.string().trim().max(500).optional().default(""),
  tiktokUrl: z.string().trim().max(500).optional().default(""),
  whatsappNumber: z.string().trim().max(60).optional().default(""),
  colorCream: z.string().trim().min(1),
  colorSand: z.string().trim().min(1),
  colorTerracotta: z.string().trim().min(1),
  colorRust: z.string().trim().min(1),
  colorOlive: z.string().trim().min(1),
  colorCharcoal: z.string().trim().min(1),
  colorWarmgray: z.string().trim().min(1),
});

export const chatMessageSchema = z.object({
  role: z.enum(["user", "assistant"]),
  content: z.string(),
});

export function zodErrorMessage(error: z.ZodError): string {
  return error.issues[0]?.message || "Invalid request";
}
