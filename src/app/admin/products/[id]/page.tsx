import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { ProductForm } from "@/components/admin/ProductForm";

export const dynamic = "force-dynamic";

type Props = { params: { id: string } };

export default async function EditProductPage({ params }: Props) {
  const product = await prisma.product.findUnique({ where: { id: params.id } });
  if (!product) notFound();

  return (
    <div>
      <h1 className="text-2xl font-semibold">Edit product</h1>
      <p className="mt-1 text-gray-500">{product.name}</p>
      <div className="mt-8">
        <ProductForm product={product} />
      </div>
    </div>
  );
}
