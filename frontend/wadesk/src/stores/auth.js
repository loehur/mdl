import { defineStore } from "pinia";
import { api } from "../api";

export const useAuthStore = defineStore("auth", {
  state: () => ({
    user: JSON.parse(localStorage.getItem("wadesk_user") || "null"),
    token: localStorage.getItem("wadesk_token") || null,
    loading: false,
    error: "",
    _checked: false,
  }),
  getters: {
    isAdmin: (s) => s.user?.role === "admin",
    isTeamLeader: (s) => s.user?.role === "team_leader",
    canManageTeam: (s) => ["admin", "team_leader"].includes(s.user?.role),
    isLoggedIn: (s) => !!s.user,
    hasTeam: (s) => !!s.user?.team_id,
    canSendWa: (s) => s.user?.role !== "admin" || !!s.user?.team_id,
  },
  actions: {
    persist() {
      if (this.user) localStorage.setItem("wadesk_user", JSON.stringify(this.user));
      else localStorage.removeItem("wadesk_user");
      if (this.token) localStorage.setItem("wadesk_token", this.token);
      else localStorage.removeItem("wadesk_token");
    },
    async login(email, password) {
      this.loading = true;
      this.error = "";
      try {
        const res = await api("/WaDesk/Auth/login", {
          method: "POST",
          body: { email, password },
        });
        this.user = res.data.user;
        this.token = res.data.token;
        this.persist();
      } catch (e) {
        this.error = e.message;
        throw e;
      } finally {
        this.loading = false;
      }
    },
    async register(payload) {
      this.loading = true;
      this.error = "";
      try {
        const res = await api("/WaDesk/Auth/register", {
          method: "POST",
          body: payload,
        });
        this.user = res.data.user;
        this.token = res.data.token;
        this.persist();
      } catch (e) {
        this.error = e.message;
        throw e;
      } finally {
        this.loading = false;
      }
    },
    async check() {
      if (!this.token && !this.user) return false;
      try {
        const res = await api("/WaDesk/Auth/check");
        this.user = res.data.user;
        this.persist();
        return true;
      } catch {
        this.logoutLocal();
        return false;
      }
    },
    async logout() {
      try {
        await api("/WaDesk/Auth/logout", { method: "POST", body: {} });
      } catch {
        /* ignore */
      }
      this.logoutLocal();
    },
    logoutLocal() {
      this.user = null;
      this.token = null;
      this.persist();
    },
    async joinTeam(teamId) {
      const res = await api("/WaDesk/Auth/joinTeam", {
        method: "POST",
        body: { team_id: Number(teamId) },
      });
      this.user = res.data.user;
      this.persist();
      return res;
    },
    async leaveTeam() {
      const res = await api("/WaDesk/Auth/leaveTeam", {
        method: "POST",
        body: {},
      });
      this.user = res.data.user;
      this.persist();
      return res;
    },
  },
});
