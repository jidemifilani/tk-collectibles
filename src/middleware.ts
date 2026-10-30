import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { verifySessionToken } from "@/lib/sessionToken";

const SESSION_COOKIE = "thrift_admin_session";

export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const isAdminRoute = pathname.startsWith("/admin");
  const isLogin = pathname === "/admin/login";
  const isAuthed = await verifySessionToken(request.cookies.get(SESSION_COOKIE)?.value);

  if (isAdminRoute && !isLogin && !isAuthed) {
    const login = new URL("/admin/login", request.url);
    login.searchParams.set("from", pathname);
    return NextResponse.redirect(login);
  }

  if (isLogin && isAuthed) {
    return NextResponse.redirect(new URL("/admin", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/admin/:path*"],
};
