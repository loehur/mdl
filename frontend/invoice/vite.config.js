import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: "autoUpdate",
      manifest: {
        name: "Invoice",
        short_name: "Invoice",
        description: "Buat, bagikan, dan terima pembayaran invoice via QRIS.",
        start_url: "./",
        scope: "./",
        display: "standalone",
        orientation: "portrait",
        background_color: "#f5f3f9",
        theme_color: "#7c5cbf",
        lang: "id",
        icons: [
          {
            src: "./icon.svg",
            sizes: "any",
            type: "image/svg+xml",
            purpose: "any",
          },
          {
            src: "./icon.svg",
            sizes: "512x512",
            type: "image/svg+xml",
            purpose: "maskable",
          },
        ],
      },
      workbox: {
        navigateFallback: "index.html",
        cleanupOutdatedCaches: true,
        clientsClaim: true,
        skipWaiting: true,
        globPatterns: ["**/*.{js,css,html,svg,woff2,webmanifest}"],
      },
    }),
  ],
  base: "./",
  build: {
    outDir: "../../public/invoice",
    assetsDir: "assets",
    emptyOutDir: true,
  },
  server: {
    proxy: {
      "/api": {
        target: "http://localhost/mdl",
        changeOrigin: true,
      },
      "/Invoice": {
        target: "http://localhost/mdl/api",
        changeOrigin: true,
      },
    },
  },
});
