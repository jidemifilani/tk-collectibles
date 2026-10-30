import Link from "next/link";
import { Button } from "@/components/ui/Button";

export default function NotFound() {
  return (
    <div className="mx-auto max-w-lg px-4 py-24 text-center">
      <h1 className="font-display text-4xl font-semibold">404</h1>
      <p className="mt-2 text-warmgray">This piece may have already sold.</p>
      <Link href="/shop" className="mt-8 inline-block">
        <Button>Browse shop</Button>
      </Link>
    </div>
  );
}
