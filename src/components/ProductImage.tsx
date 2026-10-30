"use client";

import { useState } from "react";
import Image, { ImageProps } from "next/image";
import { ImageOff } from "lucide-react";
import clsx from "clsx";

/**
 * next/image wrapper that fades in on load and shows a clear "photo
 * unavailable" state on error, instead of an indistinguishable empty box
 * (some seed/demo product photos point at dead external URLs).
 */
export function ProductImage({ className, alt, fill, ...props }: ImageProps) {
  const [loaded, setLoaded] = useState(false);
  const [errored, setErrored] = useState(false);

  if (errored) {
    return (
      <div
        className={clsx(
          fill && "absolute inset-0",
          "flex flex-col items-center justify-center gap-1 bg-sand text-warmgray"
        )}
      >
        <ImageOff className="h-6 w-6" />
        <span className="text-[11px]">Photo unavailable</span>
      </div>
    );
  }

  return (
    <Image
      {...props}
      alt={alt}
      fill={fill}
      className={clsx(className, "transition-opacity duration-500", loaded ? "opacity-100" : "opacity-0")}
      onLoad={(e) => {
        setLoaded(true);
        props.onLoad?.(e);
      }}
      onError={() => setErrored(true)}
    />
  );
}
