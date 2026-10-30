import { getSiteSettings } from "@/lib/settings";
import { Footer } from "@/components/Footer";

export async function StoreFooter() {
  const settings = await getSiteSettings();
  return <Footer settings={settings} />;
}
