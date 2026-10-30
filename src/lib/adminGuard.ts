import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { isAdminAuthenticated, verifyCsrf } from "@/lib/auth";

/**
 * Single place every /api/admin/* route calls before doing any work.
 * Pass `mutating: true` for POST/PUT/DELETE handlers to also enforce the
 * double-submit CSRF check. Returns a NextResponse to send back immediately
 * on failure, or null to proceed.
 */
export async function requireAdmin(
  request: NextRequest,
  { mutating = false }: { mutating?: boolean } = {}
): Promise<NextResponse | null> {
  if (!(await isAdminAuthenticated())) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  if (mutating && !verifyCsrf(request)) {
    return NextResponse.json(
      { error: "Invalid or missing CSRF token" },
      { status: 403 }
    );
  }

  return null;
}
