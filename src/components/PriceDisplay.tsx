import { formatNaira, discountPercent } from "@/lib/format";
import clsx from "clsx";

type Props = {
  originalPrice: number;
  salePrice: number;
  size?: "sm" | "md" | "lg";
  className?: string;
};

export function PriceDisplay({
  originalPrice,
  salePrice,
  size = "md",
  className,
}: Props) {
  const onSale = salePrice < originalPrice;
  const pct = onSale ? discountPercent(originalPrice, salePrice) : 0;

  const priceClass = {
    sm: "text-sm",
    md: "text-base",
    lg: "text-2xl",
  }[size];

  const originalClass = {
    sm: "text-xs",
    md: "text-sm",
    lg: "text-base",
  }[size];

  return (
    <div className={clsx("flex flex-wrap items-baseline gap-2", className)}>
      <span className={clsx("font-semibold text-terracotta", priceClass)}>
        {formatNaira(salePrice)}
      </span>
      {onSale && (
        <>
          <span
            className={clsx("text-warmgray line-through", originalClass)}
            aria-label={`Original price ${formatNaira(originalPrice)}`}
          >
            {formatNaira(originalPrice)}
          </span>
          {pct > 0 && (
            <span className="rounded-full bg-olive/15 px-2 py-0.5 text-xs font-semibold text-olive">
              -{pct}%
            </span>
          )}
        </>
      )}
    </div>
  );
}
