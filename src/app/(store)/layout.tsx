import { ThemeProvider } from "@/components/ThemeProvider";
import { StoreHeader } from "@/components/StoreHeader";
import { StoreFooter } from "@/components/StoreFooter";
import { StoreChat } from "@/components/StoreChat";
import { NewArrivalBar } from "@/components/NewArrivalBar";

export default function StoreLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <ThemeProvider>
      <div className="flex min-h-screen flex-col bg-cream text-charcoal">
        <NewArrivalBar />
        <StoreHeader />
        <main className="flex-1">{children}</main>
        <StoreFooter />
        <StoreChat />
      </div>
    </ThemeProvider>
  );
}
