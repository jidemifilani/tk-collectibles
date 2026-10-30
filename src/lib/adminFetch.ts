"use client";

const CSRF_COOKIE = "thrift_csrf_token";
const CSRF_HEADER = "x-csrf-token";

function getCsrfToken(): string {
  const match = document.cookie.match(new RegExp(`(?:^|; )${CSRF_COOKIE}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : "";
}

/** fetch() wrapper for admin API calls — attaches the CSRF header on mutating requests. */
export function adminFetch(input: string, init: RequestInit = {}): Promise<Response> {
  const method = (init.method || "GET").toUpperCase();
  if (method === "GET" || method === "HEAD") {
    return fetch(input, init);
  }

  const headers = new Headers(init.headers);
  headers.set(CSRF_HEADER, getCsrfToken());
  return fetch(input, { ...init, headers });
}
