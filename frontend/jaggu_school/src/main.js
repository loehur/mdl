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
  getUser,
  saveSession,
} from "./utils/session";

const originalFetch = window.fetch;
window.fetch = async (url, options = {}) => {
  const isApi =
    typeof url === "string" &&
    (url.startsWith("/api") || url.startsWith("/Jaggu_School"));

  if (isApi) {
    url = apiUrl(url);
    const headers = { ...(options.headers || {}) };
    const token = getToken();
    if (token) headers["X-Jaggu-Token"] = token;
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
import ChildToday from "./views/ChildToday.vue";
import ParentMonitor from "./views/ParentMonitor.vue";
import ParentSchedule from "./views/ParentSchedule.vue";

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: "/", redirect: "/home" },
    { path: "/login", component: Login, meta: { public: true } },
    {
      path: "/",
      component: AppLayout,
      children: [
        { path: "home", redirect: () => (getUser()?.role === "parent" ? "/monitor" : "/today") },
        { path: "today", component: ChildToday, meta: { role: "child" } },
        { path: "monitor", component: ParentMonitor, meta: { role: "parent" } },
        { path: "jadwal", component: ParentSchedule, meta: { role: "parent" } },
      ],
    },
  ],
});

function refreshSessionInBackground() {
  const now = Date.now();
  if (now - lastAuthCheck < AUTH_CHECK_INTERVAL) return;
  lastAuthCheck = now;

  fetch("/api/Jaggu_School/Auth/check")
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

  const go = (user) => {
    if (to.meta.role && user?.role !== to.meta.role) {
      return user?.role === "parent" ? "/monitor" : "/today";
    }
    return true;
  };

  const cached = getValidSession();
  if (cached) {
    extendSession();
    refreshSessionInBackground();
    return go(cached.user);
  }

  return fetch("/api/Jaggu_School/Auth/check")
    .then(async (res) => {
      if (res.ok) {
        const data = await res.json().catch(() => ({}));
        if (data?.data?.user) {
          saveSession(data.data.user);
          return go(data.data.user);
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
