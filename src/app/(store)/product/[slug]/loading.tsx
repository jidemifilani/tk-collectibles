export default function ProductLoading() {
  return (
    <div className="mx-auto max-w-6xl animate-pulse px-4 py-8 sm:px-6 sm:py-12">
      <div className="h-4 w-24 rounded bg-sand/60" />

      <div className="mt-8 grid gap-10 lg:grid-cols-2">
        <div className="aspect-[3/4] rounded-2xl bg-sand/60" />

        <div className="flex flex-col gap-4">
          <div className="h-4 w-20 rounded bg-sand/60" />
          <div className="h-9 w-3/4 rounded bg-sand/70" />
          <div className="h-6 w-40 rounded bg-sand/60" />
          <div className="h-20 rounded-xl bg-sand/40" />
          <div className="h-16 rounded bg-sand/40" />
          <div className="mt-4 h-12 w-full rounded-full bg-sand/60" />
        </div>
      </div>
    </div>
  );
}
