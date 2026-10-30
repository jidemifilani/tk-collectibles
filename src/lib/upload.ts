import { writeFile, mkdir } from "fs/promises";
import path from "path";
import sharp from "sharp";

export const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

/** Header logo fits within this box; aspect ratio preserved */
export const LOGO_MAX_WIDTH = 320;
export const LOGO_MAX_HEIGHT = 96;

const ALLOWED_TYPES = new Set([
  "image/jpeg",
  "image/jpg",
  "image/png",
  "image/webp",
  "image/gif",
]);

export function validateImageFile(file: File): string | null {
  if (!ALLOWED_TYPES.has(file.type)) {
    return "Only JPG, PNG, WebP, or GIF images are allowed";
  }
  if (file.size > MAX_IMAGE_SIZE) {
    return "Image must be 5MB or smaller";
  }
  return null;
}

const DECODABLE_FORMATS = new Set(["jpeg", "png", "webp", "gif"]);

export async function saveUploadedImage(file: File): Promise<string> {
  const validationError = validateImageFile(file);
  if (validationError) throw new Error(validationError);

  const uploadsDir = path.join(process.cwd(), "public", "uploads");
  await mkdir(uploadsDir, { recursive: true });

  const bytes = Buffer.from(await file.arrayBuffer());

  // Verify the bytes are actually a decodable image — a spoofed Content-Type
  // header alone isn't enough to get arbitrary bytes written to /public.
  let metadata;
  try {
    metadata = await sharp(bytes).metadata();
  } catch {
    throw new Error("File is not a valid image");
  }
  if (!metadata.format || !DECODABLE_FORMATS.has(metadata.format)) {
    throw new Error("File is not a valid image");
  }

  const safeBase =
    file.name
      .replace(/\.[^.]+$/, "")
      .replace(/[^a-zA-Z0-9-_]/g, "-")
      .slice(0, 40) || "image";

  // Animated GIFs would collapse to a single frame if re-encoded through
  // sharp's default (non-animated) pipeline, so only re-encode static formats.
  if (metadata.format === "gif") {
    const filename = `${Date.now()}-${safeBase}.gif`;
    await writeFile(path.join(uploadsDir, filename), bytes);
    return `/uploads/${filename}`;
  }

  const resized = sharp(bytes)
    .rotate() // apply EXIF orientation before metadata is stripped
    .resize(1600, 1600, { fit: "inside", withoutEnlargement: true });

  const { buffer: processed, ext } =
    metadata.format === "png"
      ? { buffer: await resized.png({ compressionLevel: 9 }).toBuffer(), ext: ".png" }
      : metadata.format === "webp"
        ? { buffer: await resized.webp({ quality: 85 }).toBuffer(), ext: ".webp" }
        : { buffer: await resized.jpeg({ quality: 85 }).toBuffer(), ext: ".jpg" };

  const filename = `${Date.now()}-${safeBase}${ext}`;
  await writeFile(path.join(uploadsDir, filename), processed);

  return `/uploads/${filename}`;
}

/** Resize logo to fit header; keeps transparency; auto-fits within max box */
export async function saveUploadedLogo(file: File): Promise<string> {
  const validationError = validateImageFile(file);
  if (validationError) throw new Error(validationError);

  const uploadsDir = path.join(process.cwd(), "public", "uploads");
  await mkdir(uploadsDir, { recursive: true });

  const filename = `logo-${Date.now()}.png`;
  const bytes = Buffer.from(await file.arrayBuffer());

  const processed = await sharp(bytes)
    .resize(LOGO_MAX_WIDTH, LOGO_MAX_HEIGHT, {
      fit: "inside",
      withoutEnlargement: true,
    })
    .png({ compressionLevel: 9, adaptiveFiltering: true })
    .toBuffer();

  await writeFile(path.join(uploadsDir, filename), processed);

  return `/uploads/${filename}`;
}
