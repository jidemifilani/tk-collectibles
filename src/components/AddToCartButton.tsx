"use client";

import { useState } from "react";
import { Product } from "@/types";
import { useCart } from "@/store/cart";
import { Button } from "@/components/ui/Button";
import { Check } from "lucide-react";

type Availability = "available" | "reserved" | "sold";

export function AddToCartButton({
  product,
  availability = "available",
}: {
  product: Product;
  availability?: Availability;
}) {
  const addItem = useCart((s) => s.addItem);
  const [added, setAdded] = useState(false);

  function handleAdd() {
    addItem({
      productId: product.id,
      name: product.name,
      slug: product.slug,
      salePrice: product.salePrice,
      originalPrice: product.originalPrice,
      imageUrl: product.imageUrl,
      size: product.size,
    });
    setAdded(true);
    setTimeout(() => setAdded(false), 2000);
  }

  if (availability === "sold") {
    return (
      <Button fullWidth disabled variant="outline">
        Sold out
      </Button>
    );
  }

  if (availability === "reserved") {
    return (
      <div>
        <Button fullWidth disabled variant="outline">
          Reserved — checkout in progress
        </Button>
        <p className="mt-2 text-center text-xs text-warmgray">
          Someone&apos;s completing checkout on this piece. Check back in a few minutes —
          it&apos;ll reopen if their payment doesn&apos;t go through.
        </p>
      </div>
    );
  }

  return (
    <Button fullWidth onClick={handleAdd} variant={added ? "secondary" : "primary"}>
      {added ? (
        <>
          <Check className="h-4 w-4" />
          Added to cart
        </>
      ) : (
        "Add to cart"
      )}
    </Button>
  );
}
