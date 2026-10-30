"use client";

import { usePathname } from "next/navigation";
import { AdminNav } from "@/components/admin/AdminNav";

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const isLogin = pathname === "/admin/login";

  if (isLogin) {
    return <>{children}</>;
  }

  return (
    <div className="flex min-h-screen bg-gray-50 text-gray-900">
      <AdminNav />
      <div className="flex-1 overflow-auto p-6 sm:p-8">{children}</div>
    </div>
  );
}
