import { NextRequest, NextResponse } from "next/server";
import { destroyAdminSession } from "@/lib/auth";
import { requireAdmin } from "@/lib/adminGuard";

export async function POST(request: NextRequest) {
  const guardError = await requireAdmin(request, { mutating: true });
  if (guardError) return guardError;

  await destroyAdminSession();
  return NextResponse.json({ success: true });
}
