import { defineStore } from "pinia";
import { api, wsUrl } from "../api";

export const useChatStore = defineStore("chat", {
  state: () => ({
    conversations: [],
    activeId: null,
    messages: [],
    loadingList: false,
    loadingMessages: false,
    search: "",
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
    async loadConversations() {
      this.loadingList = true;
      try {
        const q = this.search ? `?q=${encodeURIComponent(this.search)}` : "";
        const res = await api(`/WaDesk/Chat/getConversations${q}`);
        this.conversations = res.data.conversations || [];
      } finally {
        this.loadingList = false;
      }
    },
    async selectConversation(id) {
      this.activeId = id;
      this.loadingMessages = true;
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
        if (c) c.unread = 0;
      } finally {
        this.loadingMessages = false;
      }
    },
    async loadKeys() {
      const res = await api("/WaDesk/Keys/list");
      this.keys = res.data.keys || [];
    },
    async loadTemplates(ycloudKeyId) {
      const q = ycloudKeyId ? `?ycloud_key_id=${ycloudKeyId}` : "";
      const res = await api(`/WaDesk/Templates/list${q}`);
      this.templates = res.data.templates || [];
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
            if (msg.type === "message_in" || msg.type === "message_out") {
              await this.loadConversations();
              if (this.activeId && Number(msg.conversation_id) === Number(this.activeId)) {
                await this.selectConversation(this.activeId);
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
