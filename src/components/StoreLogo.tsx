import Image from "next/image";
import clsx from "clsx";

type Props = {
  logoUrl: string;
  storeName: string;
  className?: string;
  priority?: boolean;
};

/** Logo auto-fits within header space while keeping aspect ratio */
export function StoreLogo({ logoUrl, storeName, className, priority }: Props) {
  if (!logoUrl?.trim()) return null;

  return (
    <Image
      src={logoUrl}
      alt={`${storeName} logo`}
      width={320}
      height={96}
      priority={priority}
      className={clsx(
        "h-9 w-auto max-h-10 max-w-[160px] object-contain sm:h-11 sm:max-h-12 sm:max-w-[200px]",
        className
      )}
      unoptimized={logoUrl.startsWith("/uploads/")}
    />
  );
}
