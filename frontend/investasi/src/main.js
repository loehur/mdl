import { createApp } from "vue";
import { createRouter, createWebHashHistory } from "vue-router";
import { registerSW } from "virtual:pwa-register";
import App from "./App.vue";
import "./styles.css";
import { apiUrl } from "./api";

const originalFetch = window.fetch;
window.fetch = (url, options = {}) => {
  if (
    typeof url === "string" &&
    (url.startsWith("/api") || url.startsWith("/Investasi"))
  ) {
    url = apiUrl(url);
    options = { ...options, credentials: "include" };
  }
  return originalFetch(url, options);
};

import Login from "./public_area/Login.vue";
import AppLayout from "./views/AppLayout.vue";
import Dashboard from "./views/Dashboard.vue";
import DailyIncome from "./views/DailyIncome.vue";
import Investment from "./views/Investment.vue";
import Portfolio from "./views/Portfolio.vue";

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: "/", redirect: "/dashboard" },
    { path: "/login", component: Login, meta: { public: true } },
    {
      path: "/",
      component: AppLayout,
      children: [
        { path: "dashboard", component: Dashboard },
        { path: "pemasukan", component: DailyIncome },
        { path: "investasi", component: Investment },
        { path: "portfolio", component: Portfolio },
      ],
    },
  ],
});

router.beforeEach(async (to) => {
  if (to.meta.public) return true;

  const cached = localStorage.getItem("investasi_user");
  if (cached) {
    try {
      const parsed = JSON.parse(cached);
      if (parsed?.expiry && Date.now() < parsed.expiry) return true;
    } catch {
      localStorage.removeItem("investasi_user");
    }
  }

  try {
    const res = await fetch("/api/Investasi/Auth/check");
    if (res.ok) return true;
  } catch {
    /* offline or server down */
  }

  return "/login";
});

createApp(App).use(router).mount("#app");

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
