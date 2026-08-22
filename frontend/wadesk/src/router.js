import { createRouter, createWebHashHistory } from "vue-router";
import { useAuthStore } from "./stores/auth";
import LoginView from "./views/LoginView.vue";
import InboxView from "./views/InboxView.vue";
import AdminView from "./views/AdminView.vue";
import BlastView from "./views/BlastView.vue";
import ReportView from "./views/ReportView.vue";

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: "/login", name: "login", component: LoginView, meta: { guest: true } },
    { path: "/", name: "inbox", component: InboxView, meta: { auth: true } },
    { path: "/admin", name: "admin", component: AdminView, meta: { auth: true, admin: true } },
    { path: "/blast", name: "blast", component: BlastView, meta: { auth: true } },
    { path: "/report", name: "report", component: ReportView, meta: { auth: true } },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (auth.user && !auth._checked) {
    await auth.check();
    auth._checked = true;
  }
  if (to.meta.auth && !auth.isLoggedIn) return { name: "login" };
  if (to.meta.admin && !auth.isAdmin) return { name: "inbox" };
  if (to.meta.guest && auth.isLoggedIn) return { name: "inbox" };
  return true;
});

export default router;
