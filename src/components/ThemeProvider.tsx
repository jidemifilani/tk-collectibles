import { getSiteSettings, themeStyle } from "@/lib/settings";

export async function ThemeProvider({ children }: { children: React.ReactNode }) {
  const settings = await getSiteSettings();
  return <div style={themeStyle(settings)}>{children}</div>;
}
