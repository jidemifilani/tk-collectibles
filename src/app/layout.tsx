import type { Metadata } from "next";
import { DM_Sans, Fraunces } from "next/font/google";
import "./globals.css";
import { getSiteSettings } from "@/lib/settings";

const sans = DM_Sans({
  subsets: ["latin"],
  variable: "--font-sans",
});

const display = Fraunces({
  subsets: ["latin"],
  variable: "--font-display",
});

export async function generateMetadata(): Promise<Metadata> {
  const settings = await getSiteSettings();
  const appUrl = process.env.NEXT_PUBLIC_APP_URL || "http://localhost:3000";
  const title = {
    default: `${settings.storeName} | Thrift Fashion`,
    template: `%s | ${settings.storeName}`,
  };

  return {
    metadataBase: new URL(appUrl),
    title,
    description: settings.heroSubtitle,
    openGraph: {
      title: title.default,
      description: settings.heroSubtitle,
      siteName: settings.storeName,
      images: settings.heroImageUrl ? [{ url: settings.heroImageUrl }] : undefined,
      type: "website",
    },
    twitter: {
      card: "summary_large_image",
      title: title.default,
      description: settings.heroSubtitle,
      images: settings.heroImageUrl ? [settings.heroImageUrl] : undefined,
    },
  };
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" className={`${sans.variable} ${display.variable}`}>
      <body className="min-h-screen font-sans antialiased">{children}</body>
    </html>
  );
}
