"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { ImageUpload } from "@/components/admin/ImageUpload";
import { GalleryUpload } from "@/components/admin/GalleryUpload";
import { adminFetch } from "@/lib/adminFetch";
import { CATEGORIES } from "@/types";
import { koboToNaira } from "@/lib/format";
import { CONDITIONS, MAX_ITEM_PRICE_NAIRA } from "@/lib/validation";
import type { Product } from "@prisma/client";

type Props = {
  product?: Product;
};

export function ProductForm({ product }: Props) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setLoading(true);
    setError("");

    const form = new FormData(e.currentTarget);
    const imageUrl = String(form.get("imageUrl") || "").trim();
    if (!imageUrl) {
      setError("Please upload an image or paste an image URL");
      setLoading(false);
      return;
    }

    let images: string[] = [];
    try {
      images = JSON.parse(String(form.get("images") || "[]"));
    } catch {
      images = [];
    }

    const payload = {
      name: form.get("name"),
      slug: form.get("slug"),
      description: form.get("description"),
      originalPriceNaira: Number(form.get("originalPriceNaira")),
      salePriceNaira: Number(form.get("salePriceNaira")),
      category: form.get("category"),
      size: form.get("size"),
      condition: form.get("condition"),
      imageUrl,
      images,
      featured: form.get("featured") === "on",
      inStock: form.get("inStock") === "on",
    };

    const url = product
      ? `/api/admin/products/${product.id}`
      : "/api/admin/products";
    const method = product ? "PUT" : "POST";

    const res = await adminFetch(url, {
      method,
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!res.ok) {
      setError(data.error || "Failed to save");
      setLoading(false);
      return;
    }

    router.push("/admin/products");
    router.refresh();
  }

  return (
    <form onSubmit={handleSubmit} className="max-w-2xl space-y-5">
      <div className="grid gap-5 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <label className="block text-sm font-medium text-gray-700">Name</label>
          <input
            name="name"
            required
            defaultValue={product?.name}
            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Slug (optional)</label>
          <input
            name="slug"
            defaultValue={product?.slug}
            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Category</label>
          <select
            name="category"
            defaultValue={product?.category || "tops"}
            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          >
            {CATEGORIES.filter((c) => c.id !== "all").map((c) => (
              <option key={c.id} value={c.id}>
                {c.label}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Actual price (₦)</label>
          <input
            name="originalPriceNaira"
            type="number"
            min={1}
            max={MAX_ITEM_PRICE_NAIRA}
            required
            defaultValue={product ? koboToNaira(product.originalPrice) : ""}
            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Discount price (₦)</label>
          <input
            name="salePriceNaira"
            type="number"
            min={1}
            max={MAX_ITEM_PRICE_NAIRA}
            required
            defaultValue={product ? koboToNaira(product.salePrice) : ""}
            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Size</label>
          <input
            name="size"
            required
            defaultValue={product?.size || "M"}
            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Condition</label>
          <select
            name="condition"
            defaultValue={product?.condition || "Good"}
            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          >
            {CONDITIONS.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </select>
        </div>
        <ImageUpload
          name="imageUrl"
          label="Cover photo"
          initialUrl={product?.imageUrl}
          required
        />
        <GalleryUpload
          name="images"
          initialUrls={Array.isArray(product?.images) ? (product.images as string[]) : []}
        />
        <div className="sm:col-span-2">
          <label className="block text-sm font-medium text-gray-700">Description</label>
          <textarea
            name="description"
            rows={4}
            defaultValue={product?.description}
            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
        </div>
        <label className="flex items-center gap-2 text-sm">
          <input name="featured" type="checkbox" defaultChecked={product?.featured} />
          Featured drop
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input name="inStock" type="checkbox" defaultChecked={product?.inStock ?? true} />
          In stock
        </label>
      </div>

      {error && <p className="text-sm text-red-600">{error}</p>}

      <div className="flex gap-3">
        <Button type="submit" disabled={loading}>
          {loading ? "Saving…" : product ? "Update product" : "Add product"}
        </Button>
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
      </div>
    </form>
  );
}
