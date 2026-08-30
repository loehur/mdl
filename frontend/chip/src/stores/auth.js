import { reactive, computed } from "vue";
import { api } from "../api";

const state = reactive({
  user: "",
  saldo: 0,
  adminAuthed: false,
  ready: false,
});

export function useAuth() {
  const isLoggedIn = computed(() => state.user !== "");

  async function login(username) {
    const res = await api("/Chip/Room/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user: username }),
    });
    state.user = res.data?.user || username;
    state.saldo = Number(res.data?.saldo || 0);
    state.ready = true;
    return res;
  }

  async function logout() {
    try {
      await api("/Chip/Room/logout", { method: "POST" });
    } catch (_) {
      /* ignore */
    }
    state.user = "";
    state.saldo = 0;
    state.ready = false;
  }

  async function refreshMe() {
    const res = await api("/Chip/Room/me");
    state.user = res.data?.user || "";
    state.saldo = Number(res.data?.saldo || 0);
    state.ready = true;
    return res;
  }

  async function adminLogin(password) {
    const res = await api("/Chip/Admin/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ password }),
    });
    state.adminAuthed = true;
    return res;
  }

  async function adminLogout() {
    try {
      await api("/Chip/Admin/logout", { method: "POST" });
    } catch (_) {
      /* ignore */
    }
    state.adminAuthed = false;
  }

  async function checkAdmin() {
    const res = await api("/Chip/Admin/check");
    state.adminAuthed = !!res.data?.authed;
    return state.adminAuthed;
  }

  return {
    state,
    isLoggedIn,
    login,
    logout,
    refreshMe,
    adminLogin,
    adminLogout,
    checkAdmin,
  };
}
