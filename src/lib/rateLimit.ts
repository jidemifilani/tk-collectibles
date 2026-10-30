import type { NextRequest } from "next/server";

/**
 * In-memory sliding-window limiter. Fine for a single Next.js server instance;
 * if this app ever runs multiple instances behind a load balancer, swap the
 * Map for a shared store (e.g. Redis) — each instance would otherwise track
 * its own count.
 */
const buckets = new Map<string, { count: number; resetAt: number }>();

// Periodically drop expired buckets so this map doesn't grow unbounded.
setInterval(() => {
  const now = Date.now();
  buckets.forEach((bucket, key) => {
    if (bucket.resetAt < now) buckets.delete(key);
  });
}, 5 * 60 * 1000).unref?.();

export function getClientIp(request: NextRequest): string {
  const forwarded = request.headers.get("x-forwarded-for");
  if (forwarded) return forwarded.split(",")[0].trim();
  return request.headers.get("x-real-ip") || "unknown";
}

export type RateLimitResult = {
  allowed: boolean;
  retryAfterSeconds: number;
};

export function checkRateLimit(
  key: string,
  limit: number,
  windowMs: number
): RateLimitResult {
  const now = Date.now();
  const bucket = buckets.get(key);

  if (!bucket || bucket.resetAt < now) {
    buckets.set(key, { count: 1, resetAt: now + windowMs });
    return { allowed: true, retryAfterSeconds: 0 };
  }

  if (bucket.count >= limit) {
    return {
      allowed: false,
      retryAfterSeconds: Math.ceil((bucket.resetAt - now) / 1000),
    };
  }

  bucket.count += 1;
  return { allowed: true, retryAfterSeconds: 0 };
}
