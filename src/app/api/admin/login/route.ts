import { NextRequest, NextResponse } from "next/server";
import { createAdminSession, verifyAdminPassword } from "@/lib/auth";
import { checkRateLimit, getClientIp } from "@/lib/rateLimit";
import { loginSchema, zodErrorMessage } from "@/lib/validation";
import { logger } from "@/lib/logger";

const LOGIN_ATTEMPT_LIMIT = 5;
const LOGIN_WINDOW_MS = 15 * 60 * 1000;

export async function POST(request: NextRequest) {
  const ip = getClientIp(request);
  const { allowed, retryAfterSeconds } = checkRateLimit(
    `login:${ip}`,
    LOGIN_ATTEMPT_LIMIT,
    LOGIN_WINDOW_MS
  );

  if (!allowed) {
    logger.warn("Admin login rate limited", { ip });
    return NextResponse.json(
      { error: "Too many attempts. Please try again later." },
      { status: 429, headers: { "Retry-After": String(retryAfterSeconds) } }
    );
  }

  const body = await request.json().catch(() => null);
  const parsed = loginSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json({ error: zodErrorMessage(parsed.error) }, { status: 400 });
  }

  if (!verifyAdminPassword(parsed.data.password)) {
    return NextResponse.json({ error: "Invalid password" }, { status: 401 });
  }

  await createAdminSession();
  return NextResponse.json({ success: true });
}
