import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { formatNaira } from "@/lib/format";
import { DeleteProductButton } from "@/components/admin/DeleteProductButton";
import { ProductImage } from "@/components/ProductImage";

export const dynamic = "force-dynamic";

const PAGE_SIZE = 20;

type Props = {
  searchParams: { page?: string };
};

export default async function AdminProductsPage({ searchParams }: Props) {
  const page = Math.max(1, Number(searchParams.page) || 1);

  const [products, total] = await Promise.all([
    prisma.product.findMany({
      orderBy: { createdAt: "desc" },
      skip: (page - 1) * PAGE_SIZE,
      take: PAGE_SIZE,
    }),
    prisma.product.count(),
  ]);

  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  return (
    <div>
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">Products</h1>
          <p className="text-gray-500">{total} items in catalog</p>
        </div>
        <Link
          href="/admin/products/new"
          className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
        >
          + Add product
        </Link>
      </div>

      <div className="mt-8 overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead className="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3">Item</th>
              <th className="px-4 py-3">Actual</th>
              <th className="px-4 py-3">Discount</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {products.map((p) => (
              <tr key={p.id}>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-3">
                    <div className="relative h-12 w-10 overflow-hidden rounded bg-gray-100">
                      <ProductImage src={p.imageUrl} alt="" fill className="object-cover" />
                    </div>
                    <div>
                      <p className="font-medium">{p.name}</p>
                      <p className="text-xs text-gray-400">{p.category} · {p.size}</p>
                    </div>
                  </div>
                </td>
                <td className="px-4 py-3 text-gray-500 line-through">
                  {formatNaira(p.originalPrice)}
                </td>
                <td className="px-4 py-3 font-medium">{formatNaira(p.salePrice)}</td>
                <td className="px-4 py-3">
                  <span
                    className={`rounded-full px-2 py-0.5 text-xs ${
                      p.inStock ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800"
                    }`}
                  >
                    {p.inStock ? "In stock" : "Sold out"}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <div className="flex gap-3">
                    <Link href={`/admin/products/${p.id}`} className="text-sm text-blue-600 hover:underline">
                      Edit
                    </Link>
                    <DeleteProductButton id={p.id} name={p.name} />
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {totalPages > 1 && (
        <div className="mt-6 flex items-center justify-center gap-3">
          <Link
            href={`/admin/products?page=${Math.max(1, page - 1)}`}
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
            href={`/admin/products?page=${Math.min(totalPages, page + 1)}`}
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
