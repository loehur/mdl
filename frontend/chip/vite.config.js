import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: "autoUpdate",
      manifest: {
        name: "Chip",
        short_name: "Chip",
        description: "Transfer chip antar pemain secara real-time.",
        start_url: "./",
        scope: "./",
        display: "standalone",
        orientation: "portrait",
        background_color: "#070708",
        theme_color: "#6366f1",
        lang: "id",
        icons: [
          {
            src: "./icon.svg",
            sizes: "any",
            type: "image/svg+xml",
            purpose: "any",
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
    outDir: "../../public/chip",
    assetsDir: "assets",
    emptyOutDir: true,
  },
  server: {
    proxy: {
      "/api": {
        target: "http://localhost/mdl",
        changeOrigin: true,
      },
      "/Chip": {
        target: "http://localhost/mdl/api",
        changeOrigin: true,
      },
    },
  },
});
