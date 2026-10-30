import { NextRequest, NextResponse } from "next/server";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { requireAdmin } from "@/lib/adminGuard";
import { getSiteSettings, SETTINGS_ID } from "@/lib/settings";
import { settingsSchema, zodErrorMessage } from "@/lib/validation";
import { logger } from "@/lib/logger";

export async function GET(request: NextRequest) {
  const guardError = await requireAdmin(request);
  if (guardError) return guardError;

  const settings = await getSiteSettings();
  return NextResponse.json(settings);
}

export async function PUT(request: NextRequest) {
  const guardError = await requireAdmin(request, { mutating: true });
  if (guardError) return guardError;

  try {
    const body = await request.json().catch(() => null);
    const parsed = settingsSchema.safeParse(body);
    if (!parsed.success) {
      return NextResponse.json({ error: zodErrorMessage(parsed.error) }, { status: 400 });
    }
    const data = parsed.data;

    const settings = await prisma.siteSettings.update({
      where: { id: SETTINGS_ID },
      data,
    });

    // Settings (theme colors, logo, contact info) render inside the shared
    // store layout on every storefront page, so revalidate the whole tree.
    revalidatePath("/", "layout");

    return NextResponse.json(settings);
  } catch (err) {
    logger.error("Failed to update settings", err);
    return NextResponse.json({ error: "Failed to save settings" }, { status: 500 });
  }
}
