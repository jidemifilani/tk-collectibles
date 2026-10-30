/**
 * HMAC signing for the admin session cookie, built on the Web Crypto API
 * (`crypto.subtle`) instead of `node:crypto` so the exact same code runs in
 * both Node.js (Server Components, route handlers) and the Edge runtime
 * (middleware) — `node:crypto`'s createHmac/timingSafeEqual aren't available
 * in Edge, and this cookie has to be verifiable from middleware to actually
 * gate /admin/* routes.
 */

function sessionSecret(): string {
  const secret = process.env.ADMIN_SESSION_SECRET;
  if (!secret) throw new Error("ADMIN_SESSION_SECRET is not configured");
  return secret;
}

function bufferToHex(buf: ArrayBuffer): string {
  return Array.from(new Uint8Array(buf))
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
}

async function importKey(): Promise<CryptoKey> {
  return crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(sessionSecret()),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"]
  );
}

async function sign(value: string): Promise<string> {
  const key = await importKey();
  const signature = await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(value));
  return bufferToHex(signature);
}

/** Constant-time-ish string compare without node:crypto (Edge-compatible). */
function timingSafeStringEqual(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) {
    diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }
  return diff === 0;
}

export async function buildSessionToken(maxAgeSeconds: number): Promise<string> {
  const expiry = Date.now() + maxAgeSeconds * 1000;
  return `${expiry}.${await sign(String(expiry))}`;
}

export async function verifySessionToken(value: string | undefined | null): Promise<boolean> {
  if (!value) return false;
  const [expiry, signature] = value.split(".");
  if (!expiry || !signature) return false;
  if (Date.now() > Number(expiry)) return false;
  return timingSafeStringEqual(signature, await sign(expiry));
}
