import type { MetadataRoute } from "next";

// Lets shoppers "Add to Home Screen" — required for push notifications on iPhone.
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "T.K Collectibles",
    short_name: "T.K Collectibles",
    description: "Spend less, wear luxury",
    start_url: "/",
    display: "standalone",
    background_color: "#F6EDE8",
    theme_color: "#7A1F2B",
    icons: [{ src: "/tk-icon.png", sizes: "512x512", type: "image/png", purpose: "any" }],
  };
}
