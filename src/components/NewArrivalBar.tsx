import { prisma } from "@/lib/prisma";
import { NEW_ARRIVAL_DAYS } from "@/lib/newArrival";
import { NewArrivalBarClient } from "@/components/NewArrivalBarClient";

/** Top-of-store alert shown when items were added in the last few days. */
export async function NewArrivalBar() {
  const since = new Date(Date.now() - NEW_ARRIVAL_DAYS * 24 * 60 * 60 * 1000);
  const where = { inStock: true, createdAt: { gte: since } };

  const [count, latest] = await Promise.all([
    prisma.product.count({ where }),
    prisma.product.findFirst({ where, orderBy: { createdAt: "desc" }, select: { createdAt: true } }),
  ]);

  if (!count || !latest) return null;

  return <NewArrivalBarClient count={count} dropId={latest.createdAt.toISOString()} />;
}
