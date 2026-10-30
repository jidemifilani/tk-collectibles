import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { getSiteSettings } from "@/lib/settings";
import { formatNaira } from "@/lib/format";
import { Shirt, ShoppingBag, Palette, Phone } from "lucide-react";

export const dynamic = "force-dynamic";

export default async function AdminDashboard() {
  const [productCount, orderCount, paidOrders, settings, recentOrders] =
    await Promise.all([
      prisma.product.count(),
      prisma.order.count(),
      prisma.order.count({ where: { status: "paid" } }),
      getSiteSettings(),
      prisma.order.findMany({
        take: 5,
        orderBy: { createdAt: "desc" },
        include: { items: true },
      }),
    ]);

  const cards = [
    { label: "Products", value: productCount, href: "/admin/products", icon: Shirt },
    { label: "Orders", value: orderCount, href: "/admin/orders", icon: ShoppingBag },
    { label: "Paid orders", value: paidOrders, href: "/admin/orders", icon: ShoppingBag },
  ];

  return (
    <div>
      <h1 className="text-2xl font-semibold">Dashboard</h1>
      <p className="mt-1 text-gray-500">Welcome back — manage {settings.storeName}</p>

      <div className="mt-8 grid gap-4 sm:grid-cols-3">
        {cards.map(({ label, value, href, icon: Icon }) => (
          <Link
            key={label}
            href={href}
            className="rounded-xl border border-gray-200 bg-white p-5 transition-shadow hover:shadow-md"
          >
            <Icon className="h-5 w-5 text-gray-400" />
            <p className="mt-3 text-2xl font-semibold">{value}</p>
            <p className="text-sm text-gray-500">{label}</p>
          </Link>
        ))}
      </div>

      <div className="mt-8 flex flex-wrap gap-3">
        <Link
          href="/admin/products/new"
          className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
        >
          + Add product
        </Link>
        <Link
          href="/admin/settings"
          className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50"
        >
          <Palette className="h-4 w-4" />
          Edit theme & content
        </Link>
        <a
          href={`tel:${settings.customerCarePhone.replace(/\s/g, "")}`}
          className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50"
        >
          <Phone className="h-4 w-4" />
          {settings.customerCarePhone}
        </a>
      </div>

      <div className="mt-10">
        <h2 className="font-semibold">Recent orders</h2>
        {recentOrders.length === 0 ? (
          <p className="mt-4 text-sm text-gray-500">No orders yet.</p>
        ) : (
          <ul className="mt-4 divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white">
            {recentOrders.map((order) => (
              <li key={order.id} className="flex justify-between px-4 py-3 text-sm">
                <span>
                  {order.customerName} · {order.reference}
                </span>
                <span className="capitalize text-gray-500">
                  {order.status} · {formatNaira(order.totalAmount)}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
