"use client";

import Image from "next/image";
import { useRef, useState } from "react";
import { Upload, Link as LinkIcon, Loader2, X } from "lucide-react";
import clsx from "clsx";
import { adminFetch } from "@/lib/adminFetch";

type UploadVariant = "product" | "logo" | "hero";

type Props = {
  name: string;
  label?: string;
  initialUrl?: string;
  required?: boolean;
  variant?: UploadVariant;
};

const VARIANT_CONFIG: Record<
  UploadVariant,
  { uploadType: string; hint: string; previewClass: string; previewWrap: string }
> = {
  product: {
    uploadType: "product",
    hint: "JPG, PNG, WebP, or GIF · max 5MB.",
    previewClass: "object-cover",
    previewWrap: "relative h-40 w-32 overflow-hidden rounded-lg border border-gray-200 bg-gray-100",
  },
  logo: {
    uploadType: "logo",
    hint: "Upload your store logo — it will auto-resize to fit the header (max 320×96px). PNG with transparent background works best.",
    previewClass: "object-contain",
    previewWrap:
      "relative flex h-20 w-72 items-center justify-center overflow-hidden rounded-lg border border-dashed border-gray-300 bg-[linear-gradient(45deg,#f3f4f6_25%,transparent_25%),linear-gradient(-45deg,#f3f4f6_25%,transparent_25%),linear-gradient(45deg,transparent_75%,#f3f4f6_75%),linear-gradient(-45deg,transparent_75%,#f3f4f6_75%)] bg-[length:12px_12px] bg-[position:0_0,0_6px,6px_-6px,-6px_0px]",
  },
  hero: {
    uploadType: "product",
    hint: "JPG, PNG, WebP, or GIF · max 5MB. Wide images work best for the banner.",
    previewClass: "object-cover",
    previewWrap: "relative h-32 w-full max-w-md overflow-hidden rounded-lg border border-gray-200 bg-gray-100",
  },
};

export function ImageUpload({
  name,
  label = "Image",
  initialUrl = "",
  required = true,
  variant = "product",
}: Props) {
  const config = VARIANT_CONFIG[variant];
  const inputRef = useRef<HTMLInputElement>(null);
  const [imageUrl, setImageUrl] = useState(initialUrl);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState("");
  const [showUrlInput, setShowUrlInput] = useState(
    Boolean(initialUrl && !initialUrl.startsWith("/uploads/"))
  );

  async function handleFileSelect(file: File | null) {
    if (!file) return;
    setError("");
    setUploading(true);

    const formData = new FormData();
    formData.append("file", file);
    formData.append("type", config.uploadType);

    try {
      const res = await adminFetch("/api/admin/upload", {
        method: "POST",
        body: formData,
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Upload failed");
      setImageUrl(data.url);
      setShowUrlInput(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Upload failed");
    } finally {
      setUploading(false);
      if (inputRef.current) inputRef.current.value = "";
    }
  }

  function clearImage() {
    setImageUrl("");
    setError("");
  }

  const hasImage = Boolean(imageUrl);
  const isLogo = variant === "logo";

  return (
    <div className={clsx(!isLogo && "sm:col-span-2")}>
      <label className="block text-sm font-medium text-gray-700">{label}</label>

      <input
        type="hidden"
        name={name}
        value={imageUrl}
        required={required && !imageUrl ? true : undefined}
      />

      {hasImage && (
        <div className="relative mt-3 inline-block">
          <div className={config.previewWrap}>
            {isLogo ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={imageUrl}
                alt="Logo preview"
                className={clsx("max-h-16 max-w-[280px] w-auto h-auto", config.previewClass)}
              />
            ) : (
              <Image
                src={imageUrl}
                alt="Preview"
                fill
                className={config.previewClass}
                unoptimized={imageUrl.startsWith("/uploads/")}
              />
            )}
          </div>
          {isLogo && (
            <p className="mt-2 text-xs text-gray-500">Preview: how your logo fits in the header</p>
          )}
          <button
            type="button"
            onClick={clearImage}
            className="absolute -right-2 -top-2 rounded-full bg-red-500 p-1 text-white shadow hover:bg-red-600"
            aria-label="Remove image"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      )}

      <div className="mt-3 flex flex-wrap gap-2">
        <label
          className={clsx(
            "inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium transition-colors hover:bg-gray-50",
            uploading && "pointer-events-none opacity-60"
          )}
        >
          {uploading ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Upload className="h-4 w-4" />
          )}
          {uploading
            ? "Uploading…"
            : hasImage
              ? "Replace from device"
              : "Upload from device"}
          <input
            ref={inputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            className="sr-only"
            onChange={(e) => handleFileSelect(e.target.files?.[0] ?? null)}
            disabled={uploading}
          />
        </label>

        {!isLogo && (
          <button
            type="button"
            onClick={() => setShowUrlInput(!showUrlInput)}
            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50"
          >
            <LinkIcon className="h-4 w-4" />
            {showUrlInput ? "Hide URL" : "Use image URL"}
          </button>
        )}
      </div>

      {showUrlInput && !isLogo && (
        <input
          type="url"
          placeholder="https://example.com/image.jpg"
          value={imageUrl.startsWith("/uploads/") ? "" : imageUrl}
          onChange={(e) => setImageUrl(e.target.value)}
          className="mt-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
        />
      )}

      <p className="mt-2 text-xs text-gray-500">{config.hint}</p>

      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
      {required && !hasImage && !isLogo && (
        <p className="mt-1 text-xs text-amber-600">Upload an image or paste a URL before saving.</p>
      )}
    </div>
  );
}
