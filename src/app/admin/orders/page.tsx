import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { formatNaira } from "@/lib/format";

export const dynamic = "force-dynamic";

const PAGE_SIZE = 20;

type Props = {
  searchParams: { page?: string };
};

export default async function AdminOrdersPage({ searchParams }: Props) {
  const page = Math.max(1, Number(searchParams.page) || 1);

  const [orders, total] = await Promise.all([
    prisma.order.findMany({
      orderBy: { createdAt: "desc" },
      include: {
        items: { include: { product: true } },
      },
      skip: (page - 1) * PAGE_SIZE,
      take: PAGE_SIZE,
    }),
    prisma.order.count(),
  ]);

  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  return (
    <div>
      <h1 className="text-2xl font-semibold">Orders</h1>
      <p className="text-gray-500">{total} total orders</p>

      <div className="mt-8 space-y-4">
        {orders.length === 0 ? (
          <p className="text-gray-500">No orders yet.</p>
        ) : (
          orders.map((order) => (
            <div
              key={order.id}
              className="rounded-xl border border-gray-200 bg-white p-5"
            >
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="font-semibold">{order.customerName}</p>
                  <p className="text-sm text-gray-500">{order.reference}</p>
                </div>
                <span
                  className={`rounded-full px-2.5 py-1 text-xs font-medium capitalize ${
                    order.status === "paid"
                      ? "bg-green-100 text-green-800"
                      : order.status === "pending"
                        ? "bg-yellow-100 text-yellow-800"
                        : "bg-red-100 text-red-800"
                  }`}
                >
                  {order.status}
                </span>
              </div>
              <p className="mt-2 text-sm text-gray-600">
                {order.customerPhone} · {order.shippingAddress}, {order.city}, {order.state}
              </p>
              <ul className="mt-3 space-y-1 text-sm text-gray-600">
                {order.items.map((item) => (
                  <li key={item.id}>
                    {item.product.name} × {item.quantity} — {formatNaira(item.price * item.quantity)}
                  </li>
                ))}
              </ul>
              <p className="mt-3 font-semibold">{formatNaira(order.totalAmount)}</p>
            </div>
          ))
        )}
      </div>

      {totalPages > 1 && (
        <div className="mt-6 flex items-center justify-center gap-3">
          <Link
            href={`/admin/orders?page=${Math.max(1, page - 1)}`}
            aria-disabled={page === 1}
            className={`rounded-lg border border-gray-300 px-3 py-1.5 text-sm ${
              page === 1 ? "pointer-events-none opacity-40" : "hover:bg-gray-50"
            }`}
          >
            Previous
          </Link>
          <span className="text-sm text-gray-500">
            Page {page} of {totalPages}
          </span>
          <Link
            href={`/admin/orders?page=${Math.min(totalPages, page + 1)}`}
            aria-disabled={page === totalPages}
            className={`rounded-lg border border-gray-300 px-3 py-1.5 text-sm ${
              page === totalPages ? "pointer-events-none opacity-40" : "hover:bg-gray-50"
            }`}
          >
            Next
          </Link>
        </div>
      )}
    </div>
  );
}
