import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: "autoUpdate",
      manifest: {
        name: "MDL Investasi",
        short_name: "Investasi",
        description: "Catat pemasukan harian, deposit/penarikan, dan portfolio investasi.",
        start_url: "./",
        scope: "./",
        display: "standalone",
        background_color: "#f0fdf4",
        theme_color: "#059669",
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
      },
    }),
  ],
  base: "./",
  build: {
    outDir: "../../public/investasi",
    assetsDir: "assets",
    emptyOutDir: true,
  },
  server: {
    proxy: {
      "/api": {
        target: "http://localhost/mdl",
        changeOrigin: true,
      },
      "/Investasi": {
        target: "http://localhost/mdl/api",
        changeOrigin: true,
      },
    },
  },
});
