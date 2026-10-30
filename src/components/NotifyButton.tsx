"use client";

import { useEffect, useState } from "react";
import { Bell, BellOff, BellRing } from "lucide-react";
import clsx from "clsx";

type Status = "loading" | "unsupported" | "ios-install" | "blocked" | "off" | "on" | "busy";

const VAPID_KEY = process.env.NEXT_PUBLIC_VAPID_PUBLIC_KEY ?? "";

function base64ToUint8(base64: string) {
  const padded = (base64 + "=".repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, "+").replace(/_/g, "/");
  return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

function isIos() {
  return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

function isInstalled() {
  return window.matchMedia("(display-mode: standalone)").matches;
}

/** Lets shoppers opt in to phone notifications when new items are posted. */
export function NotifyButton({ variant = "light" }: { variant?: "light" | "dark" }) {
  const [status, setStatus] = useState<Status>("loading");

  useEffect(() => {
    (async () => {
      if (!("serviceWorker" in navigator) || !("PushManager" in window) || !VAPID_KEY) {
        // iPhones only support web push once the site is added to the home screen.
        setStatus(isIos() && !isInstalled() ? "ios-install" : "unsupported");
        return;
      }
      if (Notification.permission === "denied") return setStatus("blocked");
      const reg = await navigator.serviceWorker.register("/sw.js");
      const sub = await reg.pushManager.getSubscription();
      setStatus(sub ? "on" : "off");
    })().catch(() => setStatus("unsupported"));
  }, []);

  async function subscribe() {
    setStatus("busy");
    try {
      const permission = await Notification.requestPermission();
      if (permission !== "granted") return setStatus(permission === "denied" ? "blocked" : "off");

      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: base64ToUint8(VAPID_KEY),
      });
      const res = await fetch("/api/push/subscribe", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(sub.toJSON()),
      });
      if (!res.ok) throw new Error("subscribe failed");
      setStatus("on");
    } catch {
      setStatus("off");
    }
  }

  async function unsubscribe() {
    setStatus("busy");
    try {
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.getSubscription();
      if (sub) {
        await fetch("/api/push/subscribe", {
          method: "DELETE",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ endpoint: sub.endpoint }),
        });
        await sub.unsubscribe();
      }
    } finally {
      setStatus("off");
    }
  }

  const hint = variant === "dark" ? "text-sand/80" : "text-warmgray";

  if (status === "loading" || status === "unsupported") return null;

  if (status === "ios-install") {
    return (
      <p className={clsx("text-sm", hint)}>
        <Bell className="mr-1 inline h-4 w-4" aria-hidden />
        On iPhone? Tap <strong>Share → Add to Home Screen</strong>, open the app, then turn on new-arrival alerts.
      </p>
    );
  }

  if (status === "blocked") {
    return (
      <p className={clsx("text-sm", hint)}>
        <BellOff className="mr-1 inline h-4 w-4" aria-hidden />
        Notifications are blocked. Allow them in your browser settings to get new-arrival alerts.
      </p>
    );
  }

  const on = status === "on";
  return (
    <button
      type="button"
      onClick={on ? unsubscribe : subscribe}
      disabled={status === "busy"}
      className={clsx(
        "inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition-colors disabled:opacity-60",
        on
          ? variant === "dark"
            ? "bg-white/10 text-cream hover:bg-white/20"
            : "bg-sand text-charcoal hover:bg-sand/70"
          : "bg-terracotta text-cream hover:bg-rust"
      )}
    >
      {on ? <BellRing className="h-4 w-4" /> : <Bell className="h-4 w-4" />}
      {status === "busy" ? "Please wait…" : on ? "Alerts on · Turn off" : "Notify me of new arrivals"}
    </button>
  );
}
