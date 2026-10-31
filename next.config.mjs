/** @type {import('next').NextConfig} */
const nextConfig = {
  // Lets a production build go to a separate folder without disturbing `npm run dev`.
  distDir: process.env.NEXT_DIST_DIR || ".next",
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "images.unsplash.com" },
    ],
  },
};

export default nextConfig;
