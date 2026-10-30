"use client";

import { useState } from "react";
import { X, ZoomIn } from "lucide-react";
import { ProductImage } from "@/components/ProductImage";

export function ProductGallery({ images, alt }: { images: string[]; alt: string }) {
  const [active, setActive] = useState(0);
  const [lightbox, setLightbox] = useState(false);

  return (
    <div>
      <button
        type="button"
        onClick={() => setLightbox(true)}
        className="group relative block aspect-[3/4] w-full overflow-hidden rounded-2xl bg-sand"
        aria-label="Enlarge photo"
      >
        <ProductImage
          src={images[active]}
          alt={alt}
          fill
          priority
          className="object-cover"
          sizes="(max-width: 1024px) 100vw, 50vw"
        />
        <span className="absolute bottom-3 right-3 flex h-9 w-9 items-center justify-center rounded-full bg-charcoal/70 text-cream opacity-0 transition-opacity group-hover:opacity-100">
          <ZoomIn className="h-4 w-4" />
        </span>
      </button>

      {images.length > 1 && (
        <div className="mt-3 flex gap-2">
          {images.map((src, i) => (
            <button
              key={src + i}
              type="button"
              onClick={() => setActive(i)}
              aria-label={`View photo ${i + 1} of ${images.length}`}
              aria-current={i === active}
              className={`relative h-16 w-14 shrink-0 overflow-hidden rounded-lg bg-sand ring-2 transition-all ${
                i === active ? "ring-terracotta" : "ring-transparent hover:ring-warmgray/40"
              }`}
            >
              <ProductImage src={src} alt="" fill className="object-cover" sizes="56px" />
            </button>
          ))}
        </div>
      )}

      {lightbox && (
        <div
          className="fixed inset-0 z-[60] flex items-center justify-center bg-charcoal/95 p-4"
          role="dialog"
          aria-modal="true"
          aria-label={`${alt} — enlarged photo`}
          onClick={() => setLightbox(false)}
        >
          <button
            type="button"
            onClick={() => setLightbox(false)}
            className="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-cream hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cream"
            aria-label="Close enlarged photo"
          >
            <X className="h-5 w-5" />
          </button>
          <div
            className="relative h-[85vh] w-full max-w-3xl"
            onClick={(e) => e.stopPropagation()}
          >
            <ProductImage src={images[active]} alt={alt} fill className="object-contain" sizes="100vw" />
          </div>
        </div>
      )}
    </div>
  );
}
