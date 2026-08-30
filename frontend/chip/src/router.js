import { createRouter, createWebHashHistory } from "vue-router";
import { useAuth } from "./stores/auth";
import Login from "./views/Login.vue";
import Room from "./views/Room.vue";
import Watch from "./views/Watch.vue";
import AdminLogin from "./views/AdminLogin.vue";
import AdminMenu from "./views/AdminMenu.vue";

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: "/", redirect: "/room" },
    { path: "/login", component: Login, meta: { public: true } },
    { path: "/room", component: Room },
    { path: "/watch", component: Watch },
    { path: "/admin", component: AdminLogin, meta: { public: true } },
    { path: "/admin/menu", component: AdminMenu, meta: { admin: true } },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuth();

  if (to.meta.public) {
    return true;
  }

  if (to.meta.admin) {
    try {
      const ok = await auth.checkAdmin();
      if (ok) return true;
    } catch (_) {
      /* ignore */
    }
    return "/admin";
  }

  // User route — cek session via /me
  try {
    await auth.refreshMe();
    if (auth.isLoggedIn.value) return true;
  } catch (_) {
    /* ignore */
  }
  return "/login";
});

export default router;
