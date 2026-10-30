import webpush from "web-push";
import { prisma } from "@/lib/prisma";
import { logger } from "@/lib/logger";

const publicKey = process.env.NEXT_PUBLIC_VAPID_PUBLIC_KEY;
const privateKey = process.env.VAPID_PRIVATE_KEY;
const subject = process.env.VAPID_SUBJECT || "mailto:hello@example.com";

export const pushEnabled = Boolean(publicKey && privateKey);
if (pushEnabled) webpush.setVapidDetails(subject, publicKey!, privateKey!);

type PushPayload = { title: string; body: string; url: string; image?: string };

/** Sends a notification to every subscriber; removes ones that have expired. */
export async function notifyAllSubscribers(payload: PushPayload) {
  if (!pushEnabled) return;

  const subs = await prisma.pushSubscription.findMany();
  const message = JSON.stringify(payload);
  const expired: string[] = [];

  await Promise.all(
    subs.map((s) =>
      webpush
        .sendNotification({ endpoint: s.endpoint, keys: { p256dh: s.p256dh, auth: s.auth } }, message)
        .catch((err: { statusCode?: number }) => {
          if (err.statusCode === 404 || err.statusCode === 410) expired.push(s.id);
          else logger.error("Push send failed", err);
        })
    )
  );

  if (expired.length) {
    await prisma.pushSubscription.deleteMany({ where: { id: { in: expired } } });
  }
}
