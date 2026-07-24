import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
  plugins: [vue()],
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
