/** Cover image first, followed by any extra gallery shots, de-duplicated. */
export function productImages(product: { imageUrl: string; images: unknown }): string[] {
  const extra = Array.isArray(product.images)
    ? product.images.filter((x): x is string => typeof x === "string" && x.trim().length > 0)
    : [];
  return Array.from(new Set([product.imageUrl, ...extra].filter(Boolean)));
}
