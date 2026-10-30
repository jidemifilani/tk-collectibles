"use client";

import { useRef, useState } from "react";
import Image from "next/image";
import { Upload, Loader2, X } from "lucide-react";
import { adminFetch } from "@/lib/adminFetch";

const MAX_IMAGES = 6;

type Props = {
  name: string;
  initialUrls?: string[];
};

export function GalleryUpload({ name, initialUrls = [] }: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [urls, setUrls] = useState<string[]>(initialUrls);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState("");

  async function handleFiles(files: FileList | null) {
    if (!files || files.length === 0) return;
    setError("");

    const remaining = MAX_IMAGES - urls.length;
    if (remaining <= 0) {
      setError(`You can add up to ${MAX_IMAGES} extra photos`);
      return;
    }

    setUploading(true);
    try {
      const selected = Array.from(files).slice(0, remaining);
      const uploaded: string[] = [];
      for (const file of selected) {
        const formData = new FormData();
        formData.append("file", file);
        formData.append("type", "product");
        const res = await adminFetch("/api/admin/upload", { method: "POST", body: formData });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || "Upload failed");
        uploaded.push(data.url);
      }
      setUrls((prev) => [...prev, ...uploaded]);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Upload failed");
    } finally {
      setUploading(false);
      if (inputRef.current) inputRef.current.value = "";
    }
  }

  function remove(index: number) {
    setUrls((prev) => prev.filter((_, i) => i !== index));
  }

  return (
    <div className="sm:col-span-2">
      <label className="block text-sm font-medium text-gray-700">Extra photos (optional)</label>
      <input type="hidden" name={name} value={JSON.stringify(urls)} />

      {urls.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-3">
          {urls.map((url, i) => (
            <div
              key={url + i}
              className="relative h-24 w-20 overflow-hidden rounded-lg border border-gray-200 bg-gray-100"
            >
              <Image
                src={url}
                alt=""
                fill
                className="object-cover"
                unoptimized={url.startsWith("/uploads/")}
              />
              <button
                type="button"
                onClick={() => remove(i)}
                className="absolute -right-1.5 -top-1.5 rounded-full bg-red-500 p-1 text-white shadow hover:bg-red-600"
                aria-label={`Remove photo ${i + 1}`}
              >
                <X className="h-3 w-3" />
              </button>
            </div>
          ))}
        </div>
      )}

      {urls.length < MAX_IMAGES && (
        <label
          className={`mt-3 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium transition-colors hover:bg-gray-50 ${
            uploading ? "pointer-events-none opacity-60" : ""
          }`}
        >
          {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
          {uploading ? "Uploading…" : "Add photos"}
          <input
            ref={inputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            multiple
            className="sr-only"
            onChange={(e) => handleFiles(e.target.files)}
            disabled={uploading}
          />
        </label>
      )}

      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
      <p className="mt-2 text-xs text-gray-500">
        Up to {MAX_IMAGES} extra angles/close-ups, shown in the shop gallery alongside the cover photo above.
      </p>
    </div>
  );
}
