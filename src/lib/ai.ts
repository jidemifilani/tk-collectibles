import type { Product, SiteSettings } from "@prisma/client";
import { formatNaira, discountPercent } from "@/lib/format";

export type ChatMessage = {
  role: "user" | "assistant";
  content: string;
};

const OPENAI_URL = "https://api.openai.com/v1/chat/completions";

/** Compact product catalog the agent can reason over. */
export function buildCatalogContext(products: Product[]): string {
  if (products.length === 0) return "No products are currently listed.";

  return products
    .map((p) => {
      const onSale = p.salePrice < p.originalPrice;
      const priceLine = onSale
        ? `${formatNaira(p.salePrice)} (was ${formatNaira(p.originalPrice)}, -${discountPercent(p.originalPrice, p.salePrice)}%)`
        : formatNaira(p.salePrice);
      return `- ${p.name} | ${p.category} | size ${p.size} | ${p.condition} | ${priceLine} | ${p.inStock ? "IN STOCK" : "SOLD OUT"} | link: /product/${p.slug}`;
    })
    .join("\n");
}

export function buildSystemPrompt(
  settings: SiteSettings,
  products: Product[]
): string {
  return `You are "${settings.storeName} Assistant", a friendly, helpful customer-care agent for an online thrift (secondhand) clothing store based in Nigeria.

YOUR JOB: Answer customer enquiries and requests about products, prices, sizes, availability, delivery, payment, and how to order. Be warm, concise, and persuasive — like a great shop attendant. Use a little personality and the occasional emoji, but stay professional.

STORE INFO:
- Store name: ${settings.storeName}
- Tagline: ${settings.tagline}
- About: ${settings.aboutText}
- Customer care phone: ${settings.customerCarePhone} (${settings.customerCareHours})
- Email: ${settings.contactEmail}
- Location: ${settings.contactAddress}
- WhatsApp: ${settings.whatsappNumber || "not provided"}
- Instagram: ${settings.instagramUrl || "not provided"}

PAYMENT & DELIVERY:
- Payment is via Paystack at checkout: card, bank transfer, or USSD.
- Items are one-of-one (usually a single piece per listing), so they can sell out fast.
- Delivery is nationwide across Nigeria; the delivery address is collected at checkout.
- To order: open a product, "Add to cart", go to cart, then "Proceed to checkout" and pay with Paystack.

CURRENT CATALOG (use ONLY this to answer product questions; do not invent items or prices):
${buildCatalogContext(products)}

RULES:
- If asked about a specific item, check the catalog. If it's SOLD OUT, say so kindly and suggest similar in-stock items.
- If an item isn't in the catalog, say it's not currently available and suggest browsing the shop or checking back for new drops.
- Never invent prices, sizes, or stock. If unsure, point them to the relevant product link or the customer care phone.
- For complaints, refunds, or anything you can't resolve, share the customer care phone (${settings.customerCarePhone}) and WhatsApp.
- Keep replies short (1-4 sentences) unless the customer asks for detail.
- When mentioning a product, you may include its link path (e.g. /product/the-slug).
- Encourage purchases gently and remind them pieces are limited.`;
}

/** Calls OpenAI if a key is configured. Returns null to signal fallback. */
export async function getAiReply(
  systemPrompt: string,
  messages: ChatMessage[]
): Promise<string | null> {
  const apiKey = process.env.OPENAI_API_KEY;
  if (!apiKey) return null;

  const model = process.env.OPENAI_MODEL || "gpt-4o-mini";

  const res = await fetch(OPENAI_URL, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${apiKey}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      model,
      temperature: 0.6,
      max_tokens: 350,
      messages: [{ role: "system", content: systemPrompt }, ...messages],
    }),
  });

  if (!res.ok) {
    const detail = await res.text();
    throw new Error(`OpenAI error ${res.status}: ${detail.slice(0, 200)}`);
  }

  const data = await res.json();
  const reply = data?.choices?.[0]?.message?.content;
  return typeof reply === "string" ? reply.trim() : null;
}

/**
 * Rule-based fallback used when no OPENAI_API_KEY is set, so the assistant
 * still gives useful answers about products, payment, delivery and contact.
 */
export function getFallbackReply(
  question: string,
  settings: SiteSettings,
  products: Product[]
): string {
  const q = question.toLowerCase();

  // Match if the question contains the product's full name, or at least one
  // "significant" word from it (skips short filler words like "the"/"and").
  const mentioned = products.filter((p) => {
    const name = p.name.toLowerCase();
    if (q.includes(name)) return true;
    return name.split(" ").some((word) => word.length >= 4 && q.includes(word));
  });

  if (/price|cost|how much|much is|amount/.test(q)) {
    if (mentioned.length) {
      return mentioned
        .map(
          (p) =>
            `${p.name} is ${formatNaira(p.salePrice)}${
              p.salePrice < p.originalPrice
                ? ` (was ${formatNaira(p.originalPrice)})`
                : ""
            }${p.inStock ? "" : " — but it's sold out right now"}. View it here: /product/${p.slug}`
        )
        .join(" ");
    }
    return "Each piece shows its price right on the product page — the sale price and the original price. Browse the shop to see them all 🙂";
  }

  if (/deliver|shipping|ship|send|location|where are you/.test(q)) {
    return `We deliver nationwide across Nigeria 🚚. You enter your delivery address at checkout. We're based in ${settings.contactAddress}.`;
  }

  if (/pay|payment|paystack|card|transfer|ussd|how do i (buy|order|pay)/.test(q)) {
    return "To order: open a product, tap Add to cart, go to your cart, then Proceed to checkout. Payment is secure via Paystack — card, bank transfer, or USSD 🔒.";
  }

  if (/size|fit|measurement/.test(q)) {
    if (mentioned.length) {
      return mentioned
        .map((p) => `${p.name} is size ${p.size} (${p.condition}).`)
        .join(" ");
    }
    return "Each product page lists the exact size and condition. Tell me which item you like and I'll check for you!";
  }

  if (/available|in stock|do you have|got any|sold/.test(q)) {
    if (mentioned.length) {
      return mentioned
        .map(
          (p) =>
            `${p.name} is ${p.inStock ? "in stock ✅" : "sold out 😢"}${p.inStock ? ` at ${formatNaira(p.salePrice)} — /product/${p.slug}` : ""}.`
        )
        .join(" ");
    }
    const inStock = products.filter((p) => p.inStock).slice(0, 4);
    return inStock.length
      ? `Here are a few pieces available now: ${inStock.map((p) => p.name).join(", ")}. Browse the shop for the full drop!`
      : "New drops are added weekly — check back soon or follow us on Instagram!";
  }

  if (/contact|call|phone|whatsapp|speak|human|agent|help/.test(q)) {
    return `You can reach our customer care on ${settings.customerCarePhone} (${settings.customerCareHours})${settings.whatsappNumber ? `, or WhatsApp us at ${settings.whatsappNumber}` : ""}. Happy to help! 💬`;
  }

  if (/hi|hello|hey|good (morning|afternoon|evening)/.test(q)) {
    return `Hi there! 👋 Welcome to ${settings.storeName}. Ask me about any item, prices, sizes, delivery, or how to order.`;
  }

  return `I can help with product info, prices, sizes, delivery, and how to order. For anything else, call our customer care on ${settings.customerCarePhone} or browse the shop to see this week's drop 🛍️.`;
}
