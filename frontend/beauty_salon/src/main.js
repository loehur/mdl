import { createApp } from "vue";
import { createRouter, createWebHistory } from "vue-router";
import App from "./App.vue";
import "./styles.css";

import Login from "./public_area/Login.vue";
import UserLayout from "./user_area/UserLayout.vue";
import Order from "./user_area/Order.vue";

import { registerSW } from "virtual:pwa-register";
const appIcon = "/icons/beauty_salon.png";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: "/", redirect: "/login" },
    { path: "/login", component: Login, meta: { area: "public" } },
    { path: "/register", component: () => import("./public_area/Register.vue"), meta: { area: "public" } },

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
        { path: "users", component: () => import("./user_area/Users.vue") },
        { path: "settings", component: () => import("./user_area/Settings.vue") },
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
