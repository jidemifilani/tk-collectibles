export default function ShopLoading() {
  return (
    <div className="mx-auto max-w-6xl animate-pulse px-4 py-10 sm:px-6">
      <div className="h-9 w-48 rounded bg-sand/70" />
      <div className="mt-3 h-5 w-72 rounded bg-sand/50" />

      <div className="mt-8 flex gap-2">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="h-9 w-24 shrink-0 rounded-full bg-sand/60" />
        ))}
      </div>

      <div className="mt-10 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <div key={i} className="overflow-hidden rounded-2xl bg-white ring-1 ring-sand/60">
            <div className="aspect-[3/4] bg-sand/60" />
            <div className="space-y-2 p-4">
              <div className="h-3 w-16 rounded bg-sand/60" />
              <div className="h-4 w-28 rounded bg-sand/60" />
              <div className="h-4 w-20 rounded bg-sand/60" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
