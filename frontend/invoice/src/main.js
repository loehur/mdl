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
    (url.startsWith("/api") || url.startsWith("/Invoice"));

  if (isApi) {
    url = apiUrl(url);
    const headers = { ...(options.headers || {}) };
    const token = getToken();
    if (token) {
      headers["X-Invoice-Token"] = token;
    }
    options = { ...options, credentials: "include", headers };
  }

  const res = await originalFetch(url, options);

  if (
    isApi &&
    res.status === 401 &&
    typeof url === "string" &&
    !url.includes("/Auth/login") &&
    !url.includes("/Invoice/PublicView/")
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
import CreateInvoice from "./views/CreateInvoice.vue";
import History from "./views/History.vue";
import InvoiceDetail from "./views/InvoiceDetail.vue";
import PublicInvoice from "./views/PublicInvoice.vue";

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: "/", redirect: "/dashboard" },
    { path: "/login", component: Login, meta: { public: true } },
    { path: "/i/:token", component: PublicInvoice, meta: { public: true } },
    {
      path: "/",
      component: AppLayout,
      children: [
        { path: "dashboard", component: Dashboard },
        { path: "buat", component: CreateInvoice },
        { path: "riwayat", component: History },
        { path: "detail/:id", component: InvoiceDetail },
      ],
    },
  ],
});

function refreshSessionInBackground() {
  const now = Date.now();
  if (now - lastAuthCheck < AUTH_CHECK_INTERVAL) return;
  lastAuthCheck = now;

  fetch("/api/Invoice/Auth/check")
    .then(async (res) => {
      if (res.ok) {
        const data = await res.json().catch(() => ({}));
        if (data?.data?.user) saveSession(data.data.user);
        return;
      }
      if (res.status === 401) {
        clearSession();
        if (!router.currentRoute.value.meta.public) {
          router.replace("/login");
        }
      }
    })
    .catch(() => {});
}

let lastAuthCheck = 0;
const AUTH_CHECK_INTERVAL = 2 * 60 * 1000;

router.beforeEach((to) => {
  if (to.meta.public) return true;

  const cached = getValidSession();
  if (cached) {
    extendSession();
    refreshSessionInBackground();
    return true;
  }

  return fetch("/api/Invoice/Auth/check")
    .then(async (res) => {
      if (res.ok) {
        const data = await res.json().catch(() => ({}));
        if (data?.data?.user) {
          saveSession(data.data.user);
          return true;
        }
      }
      return "/login";
    })
    .catch(() => "/login");
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
