import { defineStore } from "pinia";
import { api, fetchEligibleTemplates, wsUrl } from "../api";

export const useChatStore = defineStore("chat", {
  state: () => ({
    conversations: [],
    activeId: null,
    messages: [],
    loadingList: false,
    loadingMessages: false,
    search: "",
    listFilter: "all",
    unreadCount: 0,
    openCount: 0,
    keys: [],
    templates: [],
    ws: null,
  }),
  getters: {
    active(state) {
      return state.conversations.find((c) => Number(c.id) === Number(state.activeId)) || null;
    },
  },
  actions: {
    async loadConversations({ silent = false } = {}) {
      // Jangan tampilkan "Memuat..." kalau list sudah ada (polling/WS)
      const showLoading = !silent && this.conversations.length === 0;
      if (showLoading) this.loadingList = true;
      try {
        const params = new URLSearchParams();
        if (this.search.trim()) params.set("q", this.search.trim());
        if (this.listFilter === "unread") params.set("filter", "unread");
        if (this.listFilter === "open") params.set("filter", "open");
        const qs = params.toString();
        const res = await api(`/WaDesk/Chat/getConversations${qs ? `?${qs}` : ""}`);
        this.conversations = res.data.conversations || [];
        this.unreadCount = Number(res.data.unread_count ?? 0);
        this.openCount = Number(res.data.open_count ?? 0);
      } finally {
        if (showLoading) this.loadingList = false;
      }
    },
    async selectConversation(id, { silent = false } = {}) {
      const prevId = this.activeId;
      this.activeId = id;
      // Loading hanya saat buka/ganti thread, bukan refresh WS di thread yang sama
      const showLoading =
        !silent && (Number(prevId) !== Number(id) || this.messages.length === 0);
      if (showLoading) this.loadingMessages = true;
      try {
        const res = await api(`/WaDesk/Chat/getMessages?conversation_id=${id}`);
        this.messages = res.data.messages || [];
        const conv = res.data.conversation;
        if (conv) {
          const idx = this.conversations.findIndex((c) => Number(c.id) === Number(id));
          if (idx >= 0) this.conversations[idx] = { ...this.conversations[idx], ...conv };
        }
        await api("/WaDesk/Chat/markRead", {
          method: "POST",
          body: { conversation_id: id },
        });
        const c = this.conversations.find((x) => Number(x.id) === Number(id));
        if (c && Number(c.unread) > 0) {
          c.unread = 0;
          if (this.unreadCount > 0) this.unreadCount -= 1;
        }
      } finally {
        if (showLoading) this.loadingMessages = false;
      }
    },
    async loadKeys() {
      const res = await api("/WaDesk/Channels/list");
      this.keys = res.data.channels || res.data.keys || [];
    },
    async loadTemplates(channelId = null) {
      this.templates = [];
      try {
        this.templates = await fetchEligibleTemplates(channelId);
      } catch {
        this.templates = [];
      }
    },
    async sendFree(message) {
      if (!this.activeId) throw new Error("Pilih percakapan");
      const res = await api("/WaDesk/Chat/send", {
        method: "POST",
        body: {
          conversation_id: this.activeId,
          mode: "free",
          message,
        },
      });
      await this.selectConversation(this.activeId);
      await this.loadConversations();
      return res;
    },
    async sendTemplate(payload) {
      const res = await api("/WaDesk/Chat/send", {
        method: "POST",
        body: { mode: "template", ...payload },
      });
      const convId = res.data.conversation_id;
      await this.loadConversations();
      await this.selectConversation(convId);
      return res;
    },
    connectWs(user) {
      this.disconnectWs();
      const url = wsUrl(user);
      if (!url) return;
      try {
        const ws = new WebSocket(url);
        this.ws = ws;
        ws.onmessage = async (ev) => {
          try {
            const msg = JSON.parse(ev.data);
            if (msg.type === "message_status") {
              const mid = Number(msg.message_id);
              const m = this.messages.find((x) => Number(x.id) === mid);
              if (m) m.status = msg.status;
              return;
            }
            if (msg.type === "message_in" || msg.type === "message_out") {
              await this.loadConversations({ silent: true });
              if (this.activeId && Number(msg.conversation_id) === Number(this.activeId)) {
                await this.selectConversation(this.activeId, { silent: true });
              }
            }
          } catch {
            /* ignore */
          }
        };
        ws.onclose = () => {
          this.ws = null;
        };
      } catch {
        /* ignore */
      }
    },
    disconnectWs() {
      if (this.ws) {
        try {
          this.ws.close();
        } catch {
          /* ignore */
        }
        this.ws = null;
      }
    },
  },
});
