import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();
const SETTINGS_ID = "default";

const DEFAULT_SETTINGS = {
  id: SETTINGS_ID,
  storeName: "Curated Thrift Co.",
  logoUrl: "",
  tagline: "Weekly drops from our socials",
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
  customerCarePhone: "+234 801 234 5678",
  customerCareHours: "Mon–Sat, 9am–6pm WAT",
  instagramUrl: "https://instagram.com/yourstore",
  tiktokUrl: "https://tiktok.com/@yourstore",
  whatsappNumber: "2348012345678",
  colorCream: "#F7F3EE",
  colorSand: "#E8DFD4",
  colorTerracotta: "#C45C3E",
  colorRust: "#9E3F2A",
  colorOlive: "#5C6B4A",
  colorCharcoal: "#2C2825",
  colorWarmgray: "#6B6560",
};

const products = [
  {
    name: "Vintage Denim Jacket",
    slug: "vintage-denim-jacket",
    description:
      "Classic 90s oversized denim jacket. Light fade, perfect for layering.",
    originalPrice: 1800000,
    salePrice: 1250000,
    category: "outerwear",
    size: "L",
    condition: "Excellent",
    imageUrl:
      "https://images.unsplash.com/photo-1576995853123-5a10305d93b0?w=800&q=80",
    featured: true,
  },
  {
    name: "Floral Midi Dress",
    slug: "floral-midi-dress",
    description: "Soft cotton midi with retro floral print.",
    originalPrice: 1200000,
    salePrice: 850000,
    category: "dresses",
    size: "M",
    condition: "Like New",
    imageUrl:
      "https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=800&q=80",
    featured: true,
  },
  {
    name: "Striped Linen Shirt",
    slug: "striped-linen-shirt",
    description: "Breathable linen blend, navy and cream stripes.",
    originalPrice: 650000,
    salePrice: 450000,
    category: "tops",
    size: "S",
    condition: "Good",
    imageUrl:
      "https://images.unsplash.com/photo-1596755094514-f87e34085b2b?w=800&q=80",
    featured: false,
  },
  {
    name: "High-Waist Mom Jeans",
    slug: "high-waist-mom-jeans",
    description: "Light wash mom jeans with a relaxed fit.",
    originalPrice: 950000,
    salePrice: 650000,
    category: "bottoms",
    size: "28",
    condition: "Excellent",
    imageUrl:
      "https://images.unsplash.com/photo-1542272604-787c3835535d?w=800&q=80",
    featured: true,
  },
  {
    name: "Leather Crossbody Bag",
    slug: "leather-crossbody-bag",
    description: "Genuine leather crossbody in cognac brown.",
    originalPrice: 800000,
    salePrice: 550000,
    category: "accessories",
    size: "One Size",
    condition: "Good",
    imageUrl:
      "https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=800&q=80",
    featured: false,
  },
  {
    name: "Oversized Knit Sweater",
    slug: "oversized-knit-sweater",
    description: "Cozy cream cable-knit for layering.",
    originalPrice: 1100000,
    salePrice: 750000,
    category: "tops",
    size: "XL",
    condition: "Excellent",
    imageUrl:
      "https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=800&q=80",
    featured: false,
  },
  {
    name: "Pleated Midi Skirt",
    slug: "pleated-midi-skirt",
    description: "Earth-tone pleated skirt with elastic waist.",
    originalPrice: 600000,
    salePrice: 400000,
    category: "bottoms",
    size: "M",
    condition: "Like New",
    imageUrl:
      "https://images.unsplash.com/photo-1583496663747-49c0e5e7c8f0?w=800&q=80",
    featured: false,
  },
  {
    name: "Retro Sunglasses",
    slug: "retro-sunglasses",
    description: "70s-inspired tortoise frames with UV lenses.",
    originalPrice: 400000,
    salePrice: 250000,
    category: "accessories",
    size: "One Size",
    condition: "Like New",
    imageUrl:
      "https://images.unsplash.com/photo-1572635196233-8b992bcac0c0?w=800&q=80",
    featured: false,
  },
];

async function main() {
  await prisma.orderItem.deleteMany();
  await prisma.order.deleteMany();
  await prisma.product.deleteMany();

  for (const p of products) {
    // Explicit `images: []` — SQLite's DDL for `@default("[]")` on a Json
    // column doesn't backfill correctly, so every create() must set it.
    await prisma.product.create({ data: { ...p, images: [] } });
  }

  await prisma.siteSettings.upsert({
    where: { id: SETTINGS_ID },
    update: {},
    create: DEFAULT_SETTINGS,
  });

  console.log(`Seeded ${products.length} products and site settings`);
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
