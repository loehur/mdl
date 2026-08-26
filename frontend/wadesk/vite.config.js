import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: "autoUpdate",
      // Hanya service worker untuk auto-update — tanpa manifest agar tidak muncul prompt install di HP.
      manifest: false,
      workbox: {
        navigateFallback: "index.html",
        cleanupOutdatedCaches: true,
        clientsClaim: true,
        skipWaiting: true,
        globPatterns: ["**/*.{js,css,html,svg,woff2}"],
      },
    }),
  ],
  base: "./",
  build: {
    outDir: "../../public/wadesk",
    assetsDir: "assets",
    emptyOutDir: true,
  },
  server: {
    proxy: {
      "/WaDesk": {
        target: "http://localhost/mdl/api",
        changeOrigin: true,
      },
      "/Webhook": {
        target: "http://localhost/mdl/api",
        changeOrigin: true,
      },
    },
  },
});
