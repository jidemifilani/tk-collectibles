"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import clsx from "clsx";
import {
  LayoutDashboard,
  Shirt,
  Palette,
  ShoppingBag,
  LogOut,
  ExternalLink,
} from "lucide-react";
import { adminFetch } from "@/lib/adminFetch";

const links = [
  { href: "/admin", label: "Dashboard", icon: LayoutDashboard },
  { href: "/admin/products", label: "Products", icon: Shirt },
  { href: "/admin/settings", label: "Store & Theme", icon: Palette },
  { href: "/admin/orders", label: "Orders", icon: ShoppingBag },
];

export function AdminNav() {
  const pathname = usePathname();
  const router = useRouter();

  async function logout() {
    await adminFetch("/api/admin/logout", { method: "POST" });
    router.push("/admin/login");
    router.refresh();
  }

  return (
    <aside className="flex w-56 shrink-0 flex-col border-r border-gray-200 bg-white">
      <div className="border-b border-gray-200 p-4">
        <p className="font-semibold text-gray-900">Admin</p>
        <p className="text-xs text-gray-500">Manage your thrift store</p>
      </div>
      <nav className="flex-1 space-y-1 p-3">
        {links.map(({ href, label, icon: Icon }) => (
          <Link
            key={href}
            href={href}
            className={clsx(
              "flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
              pathname === href
                ? "bg-gray-900 text-white"
                : "text-gray-600 hover:bg-gray-100"
            )}
          >
            <Icon className="h-4 w-4" />
            {label}
          </Link>
        ))}
        <a
          href="/"
          target="_blank"
          rel="noopener noreferrer"
          className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100"
        >
          <ExternalLink className="h-4 w-4" />
          View store
        </a>
      </nav>
      <button
        type="button"
        onClick={logout}
        className="m-3 flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50"
      >
        <LogOut className="h-4 w-4" />
        Log out
      </button>
    </aside>
  );
}
