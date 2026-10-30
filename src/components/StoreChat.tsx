import { getSiteSettings } from "@/lib/settings";
import { ChatWidget } from "@/components/ChatWidget";

export async function StoreChat() {
  const settings = await getSiteSettings();
  return <ChatWidget settings={settings} />;
}
