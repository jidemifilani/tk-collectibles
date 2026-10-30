import { NextRequest, NextResponse } from "next/server";
import { requireAdmin } from "@/lib/adminGuard";
import { saveUploadedImage, saveUploadedLogo } from "@/lib/upload";
import { logger } from "@/lib/logger";

export async function POST(request: NextRequest) {
  const guardError = await requireAdmin(request, { mutating: true });
  if (guardError) return guardError;

  try {
    const formData = await request.formData();
    const file = formData.get("file");
    const type = String(formData.get("type") || "product");

    if (!file || !(file instanceof File)) {
      return NextResponse.json({ error: "No image file provided" }, { status: 400 });
    }

    const url =
      type === "logo"
        ? await saveUploadedLogo(file)
        : await saveUploadedImage(file);

    return NextResponse.json({ url });
  } catch (err) {
    const message = err instanceof Error ? err.message : "Upload failed";
    logger.error("Upload failed", err);
    return NextResponse.json({ error: message }, { status: 400 });
  }
}
