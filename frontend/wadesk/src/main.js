import { createApp } from "vue";
import { createPinia } from "pinia";
import { registerSW } from "virtual:pwa-register";
import App from "./App.vue";
import router from "./router";
import { initTheme } from "./stores/theme";
import "./style.css";

initTheme();

// Cegah banner "Install app" di Chrome/Android (SW tetap jalan untuk auto-update).
window.addEventListener("beforeinstallprompt", (e) => {
  e.preventDefault();
});

createApp(App).use(createPinia()).use(router).mount("#app");

registerSW({
  immediate: true,
  onRegisteredSW(_swUrl, registration) {
    if (registration) {
      setInterval(() => registration.update(), 5 * 60 * 1000);
    }
  },
});

if ("serviceWorker" in navigator) {
  let refreshing = false;
  navigator.serviceWorker.addEventListener("controllerchange", () => {
    if (refreshing) return;
    refreshing = true;
    window.location.reload();
  });
}
