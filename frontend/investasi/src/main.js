import { createApp } from "vue";
import { createRouter, createWebHashHistory } from "vue-router";
import { registerSW } from "virtual:pwa-register";
import App from "./App.vue";
import "./styles.css";
import { apiUrl } from "./api";
import { extendSession, getValidSession, saveSession } from "./utils/session";

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
import DailyExpense from "./views/DailyExpense.vue";
import Investment from "./views/Investment.vue";
import Portfolio from "./views/Portfolio.vue";
import Rekap from "./views/Rekap.vue";

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
        { path: "pengeluaran", component: DailyExpense },
        { path: "rekap", component: Rekap },
        { path: "investasi", component: Investment },
        { path: "portfolio", component: Portfolio },
      ],
    },
  ],
});

router.beforeEach(async (to) => {
  if (to.meta.public) return true;

  const cached = getValidSession();
  if (cached) {
    extendSession();
    fetch("/api/Investasi/Auth/check").catch(() => {});
    return true;
  }

  try {
    const res = await fetch("/api/Investasi/Auth/check");
    if (res.ok) {
      const data = await res.json().catch(() => ({}));
      if (data?.data?.user) saveSession(data.data.user);
      return true;
    }
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
