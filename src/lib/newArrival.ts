/** Items added within this many days count as "new arrivals". */
export const NEW_ARRIVAL_DAYS = 7;

export function isNewArrival(createdAt: Date | string): boolean {
  return Date.now() - new Date(createdAt).getTime() < NEW_ARRIVAL_DAYS * 24 * 60 * 60 * 1000;
}
