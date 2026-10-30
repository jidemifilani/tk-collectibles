/** Amounts stored in kobo (NGN × 100). */
export function formatNaira(amountKobo: number): string {
  return new Intl.NumberFormat("en-NG", {
    style: "currency",
    currency: "NGN",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amountKobo / 100);
}

export function nairaToKobo(naira: number): number {
  return Math.round(naira * 100);
}

export function koboToNaira(kobo: number): number {
  return kobo / 100;
}

export function discountPercent(original: number, sale: number): number {
  if (original <= sale) return 0;
  return Math.round(((original - sale) / original) * 100);
}
