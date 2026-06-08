import { createApp } from "vue";
import { createRouter, createWebHashHistory } from "vue-router";
import { registerSW } from "virtual:pwa-register";
import App from "./App.vue";
import "./styles.css";
import { apiUrl } from "./api";
import {
  clearSession,
  extendSession,
  getToken,
  getValidSession,
  saveSession,
} from "./utils/session";

const originalFetch = window.fetch;
window.fetch = async (url, options = {}) => {
  const isApi =
    typeof url === "string" &&
    (url.startsWith("/api") || url.startsWith("/Investasi"));

  if (isApi) {
    url = apiUrl(url);
    const headers = { ...(options.headers || {}) };
    const token = getToken();
    if (token) {
      headers["X-Investasi-Token"] = token;
    }
    options = { ...options, credentials: "include", headers };
  }

  const res = await originalFetch(url, options);

  if (
    isApi &&
    res.status === 401 &&
    typeof url === "string" &&
    !url.includes("/Auth/login")
  ) {
    clearSession();
    if (window.location.hash !== "#/login") {
      window.location.hash = "#/login";
    }
  }

  return res;
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
    try {
      const res = await fetch("/api/Investasi/Auth/check");
      if (res.ok) {
        const data = await res.json().catch(() => ({}));
        if (data?.data?.user) {
          saveSession(data.data.user);
        }
        return true;
      }
      if (res.status === 401) {
        clearSession();
        return "/login";
      }
    } catch {
      return true;
    }
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
