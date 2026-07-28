import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: "autoUpdate",
      manifest: {
        name: "Jaggu School",
        short_name: "Jaggu",
        description: "Jadwal mapel & ceklist harian Jaggu School.",
        start_url: "./",
        scope: "./",
        display: "standalone",
        background_color: "#ffffff",
        theme_color: "#c1121f",
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
    outDir: "../../public/jaggu_school",
    assetsDir: "assets",
    emptyOutDir: true,
  },
  server: {
    proxy: {
      "/api": {
        target: "http://localhost/mdl",
        changeOrigin: true,
      },
      "/Jaggu_School": {
        target: "http://localhost/mdl/api",
        changeOrigin: true,
      },
    },
  },
});
