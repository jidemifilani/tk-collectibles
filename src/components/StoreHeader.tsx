import { getSiteSettings } from "@/lib/settings";
import { Header } from "@/components/Header";

export async function StoreHeader() {
  const settings = await getSiteSettings();
  return <Header settings={settings} />;
}
