"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { adminFetch } from "@/lib/adminFetch";

export function DeleteProductButton({ id, name }: { id: string; name: string }) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);

  async function handleDelete() {
    if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
    setLoading(true);
    await adminFetch(`/api/admin/products/${id}`, { method: "DELETE" });
    router.refresh();
    setLoading(false);
  }

  return (
    <button
      type="button"
      onClick={handleDelete}
      disabled={loading}
      className="text-sm text-red-600 hover:underline disabled:opacity-50"
    >
      {loading ? "…" : "Delete"}
    </button>
  );
}
