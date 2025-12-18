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
import UserLayout from "./user_area/UserLayout.vue";
import Order from "./user_area/Order.vue";

import { registerSW } from "virtual:pwa-register";
const appIcon = "/icons/beauty_salon.png";

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: "/", redirect: "/login" },
    { path: "/login", component: Login, meta: { area: "public" } },
    { path: "/register", component: () => import("./public_area/Register.vue"), meta: { area: "public" } },
    { path: "/forgot-password", component: () => import("./public_area/ForgotPassword.vue"), meta: { area: "public" } },

    // User Area Routes wrapped in Layout
    {
      path: "/",
      component: UserLayout,
      meta: { area: "user" },
      children: [

        { path: "order", component: Order },
        { path: "worksteps", component: () => import("./user_area/WorkSteps.vue") },
        { path: "products", component: () => import("./user_area/Products.vue") },
        { path: "customers", component: () => import("./user_area/Customers.vue") },
        { path: "therapists", component: () => import("./user_area/Therapists.vue") },
        { path: "archive/orders", component: () => import("./user_area/ArchiveOrders.vue") },
        { path: "performance", component: () => import("./user_area/Performance.vue") },
        { path: "cash-flow", component: () => import("./user_area/CashFlow.vue") },
        { path: "cashier-cash", component: () => import("./user_area/CashierCash.vue") },
        { path: "main-cash", component: () => import("./user_area/MainCash.vue"), meta: { requiresAdmin: true } },
        { path: "users", component: () => import("./user_area/Users.vue") },
        { path: "settings", component: () => import("./user_area/Settings.vue") },
        { path: "user/profile", component: () => import("./user_area/Profile.vue") },
      ]
    }
  ],
});



router.beforeEach((to) => {
  if (to.meta && to.meta.area === "user") {
    try {
      const raw = localStorage.getItem("salon_user");
      const u = raw ? JSON.parse(raw) : null;
      if (!u) return "/login";

      // Check if route requires admin
      if (to.meta.requiresAdmin && u.role !== 'admin') {
        alert('Halaman ini hanya bisa diakses oleh Admin');
        return "/order";
      }
    } catch {
      return "/login";
    }
  }
});

const app = createApp(App).use(router);
app.config.globalProperties.$appIcon = appIcon;
app.mount("#app");

registerSW({ immediate: true });

// favicon configured in index.html
