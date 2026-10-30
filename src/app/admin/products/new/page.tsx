import { ProductForm } from "@/components/admin/ProductForm";

export default function NewProductPage() {
  return (
    <div>
      <h1 className="text-2xl font-semibold">Add product</h1>
      <p className="mt-1 text-gray-500">Set actual price and discount price in Naira.</p>
      <div className="mt-8">
        <ProductForm />
      </div>
    </div>
  );
}
