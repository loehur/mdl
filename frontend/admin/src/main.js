import { createApp } from "vue";
import { createRouter, createWebHashHistory } from "vue-router";
import App from "./App.vue";
import "./styles.css";
import { apiUrl } from './api';

// Global fetch override for API calls
const originalFetch = window.fetch;
window.fetch = (url, options) => {
  if (typeof url === 'string' && (url.startsWith('/api') || url.startsWith('/Admin'))) {
    return originalFetch(apiUrl(url), options);
  }
  return originalFetch(url, options);
};

import Login from "./public_area/Login.vue";
import AdminLayout from "./admin_area/AdminLayout.vue";
import Dashboard from "./admin_area/Dashboard.vue";
import WhatsApp from "./admin_area/WhatsApp.vue";
const appIconPng = "./icons/icon.png";

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: "/", redirect: "/login" },
    { path: "/login", component: Login },
    {
      path: "/",
      component: AdminLayout,
      children: [
        { path: "dashboard", component: Dashboard },
        { path: "whatsapp", component: WhatsApp },
      ],
    },
  ],
});

const app = createApp(App).use(router);
app.config.globalProperties.$appIcon = appIconPng;
app.mount("#app");

try {
  const ensureLink = (rel, type) => {
    let link = document.querySelector(`link[rel='${rel}']`);
    if (!link) {
      link = document.createElement("link");
      link.setAttribute("rel", rel);
      if (type) link.setAttribute("type", type);
      document.head.appendChild(link);
    }
    return link;
  };
  const favicon = ensureLink("icon", "image/png");
  favicon.setAttribute("href", appIconPng);
  const apple = ensureLink("apple-touch-icon");
  apple.setAttribute("href", appIconPng);
} catch { }
