import { getSiteSettings } from "@/lib/settings";
import { SettingsForm } from "@/components/admin/SettingsForm";

export const dynamic = "force-dynamic";

export default async function AdminSettingsPage() {
  const settings = await getSiteSettings();

  return (
    <div>
      <h1 className="text-2xl font-semibold">Store & theme</h1>
      <p className="mt-1 text-gray-500">
        Edit homepage text, contact details, customer care number, and color palette.
      </p>
      <div className="mt-8 max-w-3xl">
        <SettingsForm settings={settings} />
      </div>
    </div>
  );
}
