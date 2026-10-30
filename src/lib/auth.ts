import { cookies } from "next/headers";
import { randomBytes, timingSafeEqual } from "crypto";
import type { NextRequest } from "next/server";
import { buildSessionToken, verifySessionToken } from "@/lib/sessionToken";

const SESSION_COOKIE = "thrift_admin_session";
const CSRF_COOKIE = "thrift_csrf_token";
const CSRF_HEADER = "x-csrf-token";
const SESSION_MAX_AGE = 60 * 60 * 24 * 7; // 7 days

function safeEqual(a: string, b: string): boolean {
  const bufA = Buffer.from(a);
  const bufB = Buffer.from(b);
  if (bufA.length !== bufB.length) return false;
  return timingSafeEqual(bufA, bufB);
}

export function verifyAdminPassword(password: string): boolean {
  const expected = process.env.ADMIN_PASSWORD;
  if (!expected || !password) return false;
  return safeEqual(password, expected);
}

export async function createAdminSession(): Promise<void> {
  const cookieStore = await cookies();
  const isProd = process.env.NODE_ENV === "production";

  cookieStore.set(SESSION_COOKIE, await buildSessionToken(SESSION_MAX_AGE), {
    httpOnly: true,
    secure: isProd,
    sameSite: "lax",
    path: "/",
    maxAge: SESSION_MAX_AGE,
  });

  // Non-httpOnly so client JS can echo it back as a CSRF header (double-submit pattern).
  cookieStore.set(CSRF_COOKIE, randomBytes(24).toString("hex"), {
    httpOnly: false,
    secure: isProd,
    sameSite: "lax",
    path: "/",
    maxAge: SESSION_MAX_AGE,
  });
}

export async function destroyAdminSession(): Promise<void> {
  const cookieStore = await cookies();
  cookieStore.delete(SESSION_COOKIE);
  cookieStore.delete(CSRF_COOKIE);
}

export async function isAdminAuthenticated(): Promise<boolean> {
  const cookieStore = await cookies();
  return verifySessionToken(cookieStore.get(SESSION_COOKIE)?.value);
}

/** Double-submit CSRF check: header must match the (non-httpOnly) CSRF cookie. */
export function verifyCsrf(request: NextRequest): boolean {
  const cookieToken = request.cookies.get(CSRF_COOKIE)?.value;
  const headerToken = request.headers.get(CSRF_HEADER);
  if (!cookieToken || !headerToken) return false;
  return safeEqual(cookieToken, headerToken);
}

export { SESSION_COOKIE, CSRF_COOKIE, CSRF_HEADER };
