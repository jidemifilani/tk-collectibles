import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getSiteSettings } from "@/lib/settings";
import {
  buildSystemPrompt,
  getAiReply,
  getFallbackReply,
  type ChatMessage,
} from "@/lib/ai";
import { checkRateLimit, getClientIp } from "@/lib/rateLimit";
import { logger } from "@/lib/logger";

const MAX_MESSAGES = 16;
const MAX_LENGTH = 1000;
const CHAT_LIMIT = 20;
const CHAT_WINDOW_MS = 60 * 1000;

export async function POST(request: NextRequest) {
  const ip = getClientIp(request);
  const { allowed, retryAfterSeconds } = checkRateLimit(
    `chat:${ip}`,
    CHAT_LIMIT,
    CHAT_WINDOW_MS
  );
  if (!allowed) {
    return NextResponse.json(
      { error: "You're sending messages a bit fast — please wait a moment." },
      { status: 429, headers: { "Retry-After": String(retryAfterSeconds) } }
    );
  }

  try {
    const body = (await request.json()) as { messages?: ChatMessage[] };
    const incoming = Array.isArray(body.messages) ? body.messages : [];

    const messages: ChatMessage[] = incoming
      .filter(
        (m) =>
          m &&
          (m.role === "user" || m.role === "assistant") &&
          typeof m.content === "string"
      )
      .slice(-MAX_MESSAGES)
      .map((m) => ({ role: m.role, content: m.content.slice(0, MAX_LENGTH) }));

    const lastUser = [...messages].reverse().find((m) => m.role === "user");
    if (!lastUser) {
      return NextResponse.json(
        { error: "No question provided" },
        { status: 400 }
      );
    }

    const [settings, products] = await Promise.all([
      getSiteSettings(),
      prisma.product.findMany({ orderBy: { createdAt: "desc" }, take: 60 }),
    ]);

    const systemPrompt = buildSystemPrompt(settings, products);

    let reply: string | null = null;
    try {
      reply = await getAiReply(systemPrompt, messages);
    } catch (err) {
      logger.error("AI provider error, using fallback", err);
      reply = null;
    }

    if (!reply) {
      reply = getFallbackReply(lastUser.content, settings, products);
    }

    return NextResponse.json({ reply });
  } catch (err) {
    logger.error("Chat error", err);
    return NextResponse.json(
      { error: "Sorry, I couldn't process that. Please try again." },
      { status: 500 }
    );
  }
}
