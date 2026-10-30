"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { AlertTriangle, CheckCircle2 } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { ImageUpload } from "@/components/admin/ImageUpload";
import { adminFetch } from "@/lib/adminFetch";
import { contrastRatio, WCAG_AA_NORMAL, WCAG_AA_LARGE } from "@/lib/contrast";
import type { SiteSettings } from "@prisma/client";

const COLOR_FIELDS: { key: keyof SiteSettings; label: string }[] = [
  { key: "colorCream", label: "Background" },
  { key: "colorSand", label: "Accent light" },
  { key: "colorTerracotta", label: "Primary" },
  { key: "colorRust", label: "Primary hover" },
  { key: "colorOlive", label: "Secondary" },
  { key: "colorCharcoal", label: "Text dark" },
  { key: "colorWarmgray", label: "Text muted" },
];

const COLOR_STATE_KEYS = [
  "colorCream",
  "colorCharcoal",
  "colorTerracotta",
  "colorWarmgray",
] as const;
type ColorStateKey = (typeof COLOR_STATE_KEYS)[number];

function isColorStateKey(key: string): key is ColorStateKey {
  return (COLOR_STATE_KEYS as readonly string[]).includes(key);
}

export function SettingsForm({ settings }: { settings: SiteSettings }) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");
  const [colors, setColors] = useState({
    colorCream: settings.colorCream,
    colorCharcoal: settings.colorCharcoal,
    colorTerracotta: settings.colorTerracotta,
    colorWarmgray: settings.colorWarmgray,
  });

  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setLoading(true);
    setMessage("");

    const form = new FormData(e.currentTarget);
    const payload: Record<string, string> = {};
    Array.from(form.entries()).forEach(([key, value]) => {
      payload[key] = String(value);
    });

    const res = await adminFetch("/api/admin/settings", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const data = await res.json();
      setMessage(data.error || "Failed to save");
      setLoading(false);
      return;
    }

    setMessage("Settings saved! Refresh the storefront to see theme changes.");
    setLoading(false);
    router.refresh();
  }

  const inputClass =
    "mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm";

  return (
    <form onSubmit={handleSubmit} className="space-y-10">
      <section className="space-y-4">
        <h2 className="text-lg font-semibold text-gray-900">Store identity</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className="text-sm font-medium">Store name</label>
            <input name="storeName" defaultValue={settings.storeName} className={inputClass} required />
            <p className="mt-1 text-xs text-gray-500">Shown when no logo is uploaded</p>
          </div>
          <div>
            <label className="text-sm font-medium">Tagline</label>
            <input name="tagline" defaultValue={settings.tagline} className={inputClass} />
          </div>
        </div>
        <ImageUpload
          name="logoUrl"
          label="Store logo"
          initialUrl={settings.logoUrl}
          variant="logo"
          required={false}
        />
      </section>

      <section className="space-y-4">
        <h2 className="text-lg font-semibold text-gray-900">Homepage content</h2>
        <div>
          <label className="text-sm font-medium">Hero title</label>
          <input name="heroTitle" defaultValue={settings.heroTitle} className={inputClass} />
        </div>
        <div>
          <label className="text-sm font-medium">Hero subtitle</label>
          <textarea name="heroSubtitle" rows={2} defaultValue={settings.heroSubtitle} className={inputClass} />
        </div>
        <ImageUpload
          name="heroImageUrl"
          label="Hero banner image"
          initialUrl={settings.heroImageUrl}
          variant="hero"
          required
        />
        <div>
          <label className="text-sm font-medium">About title</label>
          <input name="aboutTitle" defaultValue={settings.aboutTitle} className={inputClass} />
        </div>
        <div>
          <label className="text-sm font-medium">About text</label>
          <textarea name="aboutText" rows={4} defaultValue={settings.aboutText} className={inputClass} />
        </div>
      </section>

      <section className="space-y-4">
        <h2 className="text-lg font-semibold text-gray-900">Contact & customer care</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className="text-sm font-medium">Customer care phone</label>
            <input
              name="customerCarePhone"
              defaultValue={settings.customerCarePhone}
              className={inputClass}
              required
            />
          </div>
          <div>
            <label className="text-sm font-medium">Hours</label>
            <input name="customerCareHours" defaultValue={settings.customerCareHours} className={inputClass} />
          </div>
          <div>
            <label className="text-sm font-medium">Email</label>
            <input name="contactEmail" type="email" defaultValue={settings.contactEmail} className={inputClass} />
          </div>
          <div>
            <label className="text-sm font-medium">Address</label>
            <input name="contactAddress" defaultValue={settings.contactAddress} className={inputClass} />
          </div>
          <div>
            <label className="text-sm font-medium">Instagram URL</label>
            <input name="instagramUrl" defaultValue={settings.instagramUrl} className={inputClass} />
          </div>
          <div>
            <label className="text-sm font-medium">TikTok URL</label>
            <input name="tiktokUrl" defaultValue={settings.tiktokUrl} className={inputClass} />
          </div>
          <div>
            <label className="text-sm font-medium">WhatsApp number</label>
            <input name="whatsappNumber" defaultValue={settings.whatsappNumber} className={inputClass} />
          </div>
        </div>
      </section>

      <section className="space-y-4">
        <h2 className="text-lg font-semibold text-gray-900">Color palette</h2>
        <p className="text-sm text-gray-500">These colors apply across the entire storefront.</p>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {COLOR_FIELDS.map(({ key, label }) => (
            <div key={key} className="flex items-center gap-3">
              <input
                type="color"
                name={key}
                defaultValue={settings[key] as string}
                onChange={(e) => {
                  if (isColorStateKey(key)) {
                    setColors((prev) => ({ ...prev, [key]: e.target.value }));
                  }
                }}
                className="h-10 w-14 cursor-pointer rounded border border-gray-300"
              />
              <div>
                <p className="text-sm font-medium">{label}</p>
                <p className="text-xs text-gray-400">{settings[key] as string}</p>
              </div>
            </div>
          ))}
        </div>

        <ContrastCheckPanel colors={colors} />
      </section>

      {message && (
        <p className={`text-sm ${message.includes("Failed") ? "text-red-600" : "text-green-700"}`}>
          {message}
        </p>
      )}

      <Button type="submit" disabled={loading}>
        {loading ? "Saving…" : "Save all settings"}
      </Button>
    </form>
  );
}

type ColorSet = {
  colorCream: string;
  colorCharcoal: string;
  colorTerracotta: string;
  colorWarmgray: string;
};

function ContrastCheckPanel({ colors }: { colors: ColorSet }) {
  const checks = [
    {
      label: "Body text on background",
      fg: colors.colorCharcoal,
      bg: colors.colorCream,
      threshold: WCAG_AA_NORMAL,
    },
    {
      label: "Button text on primary color",
      fg: "#ffffff",
      bg: colors.colorTerracotta,
      threshold: WCAG_AA_NORMAL,
    },
    {
      label: "Price/link text on background",
      fg: colors.colorTerracotta,
      bg: colors.colorCream,
      threshold: WCAG_AA_LARGE,
    },
    {
      label: "Muted text on background",
      fg: colors.colorWarmgray,
      bg: colors.colorCream,
      threshold: WCAG_AA_LARGE,
    },
  ];

  return (
    <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
      <p className="text-sm font-medium text-gray-900">Readability check</p>
      <p className="mt-1 text-xs text-gray-500">
        WCAG contrast ratio for common text/background pairings in this palette.
      </p>
      <ul className="mt-3 space-y-2">
        {checks.map(({ label, fg, bg, threshold }) => {
          const ratio = contrastRatio(fg, bg);
          const passes = ratio !== null && ratio >= threshold;
          return (
            <li key={label} className="flex items-center gap-2 text-sm">
              {passes ? (
                <CheckCircle2 className="h-4 w-4 shrink-0 text-green-600" />
              ) : (
                <AlertTriangle className="h-4 w-4 shrink-0 text-amber-500" />
              )}
              <span className={passes ? "text-gray-700" : "text-amber-700"}>
                {label} — {ratio !== null ? `${ratio.toFixed(2)}:1` : "invalid color"}
                {!passes && " (below recommended contrast)"}
              </span>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
