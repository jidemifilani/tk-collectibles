import clsx from "clsx";
import { ButtonHTMLAttributes } from "react";

type Variant = "primary" | "secondary" | "outline" | "ghost";

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: Variant;
  fullWidth?: boolean;
};

const variants: Record<Variant, string> = {
  primary:
    "bg-terracotta text-white hover:bg-rust focus-visible:ring-terracotta",
  secondary:
    "bg-olive text-white hover:bg-olive/90 focus-visible:ring-olive",
  outline:
    "border-2 border-charcoal text-charcoal hover:bg-charcoal hover:text-cream focus-visible:ring-charcoal",
  ghost: "text-charcoal hover:bg-sand/60 focus-visible:ring-warmgray",
};

export function Button({
  className,
  variant = "primary",
  fullWidth,
  children,
  ...props
}: ButtonProps) {
  return (
    <button
      className={clsx(
        "inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50",
        variants[variant],
        fullWidth && "w-full",
        className
      )}
      {...props}
    >
      {children}
    </button>
  );
}
