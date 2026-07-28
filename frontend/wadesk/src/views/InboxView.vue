<template>
  <div class="h-full flex flex-col bg-ink-950">
    <header class="shrink-0 h-14 px-4 border-b border-white/10 flex items-center justify-between bg-ink-900/80">
      <div class="flex items-center gap-3">
        <span class="font-display text-xl font-semibold text-white">WaDesk</span>
        <span class="text-xs px-2 py-0.5 rounded-full bg-white/5 text-slate-400">{{ auth.user?.role }}</span>
      </div>
      <div class="flex items-center gap-2 text-sm">
        <span
          v-if="templateQuotaBalance !== null && !auth.isAdmin"
          class="hidden sm:inline text-xs px-2 py-1 rounded-lg bg-white/5 text-slate-300"
          title="Sisa kuota template team"
        >
          Kuota template: <span class="font-semibold text-accent">{{ templateQuotaBalance }}</span>
        </span>
        <span class="hidden sm:inline text-slate-400">{{ auth.user?.name }}</span>
        <router-link
          v-if="auth.isAdmin"
          to="/blast"
          class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-200"
        >
          Blast
        </router-link>
        <router-link
          v-if="auth.isAdmin"
          to="/admin"
          class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-200"
        >
          Admin
        </router-link>
        <button class="px-3 py-1.5 rounded-lg text-rose-300 hover:bg-rose-500/10" @click="onLogout">Keluar</button>
      </div>
    </header>

    <div class="flex-1 min-h-0 flex">
      <!-- List -->
      <aside
        class="w-full sm:w-80 md:w-96 border-r border-white/10 flex flex-col bg-ink-900/40"
        :class="chat.activeId ? 'hidden sm:flex' : 'flex'"
      >
        <div class="p-3 space-y-2 border-b border-white/5">
          <div class="flex gap-2">
            <input
              v-model="chat.search"
              class="flex-1 rounded-xl bg-ink-950 border border-white/10 px-3 py-2 text-sm"
              placeholder="Cari chat..."
              @keyup.enter="chat.loadConversations()"
            />
            <button
              class="px-3 rounded-xl bg-accent text-white text-sm font-medium"
              title="Chat baru"
              @click="openNewChat"
            >
              +
            </button>
          </div>
        </div>
        <div class="flex-1 overflow-y-auto">
          <div v-if="chat.loadingList && !chat.conversations.length" class="p-4 text-sm text-slate-500">Memuat...</div>
          <button
            v-for="c in chat.conversations"
            :key="c.id"
            type="button"
            class="w-full text-left px-4 py-3 border-b border-white/5 hover:bg-white/5 transition"
            :class="Number(c.id) === Number(chat.activeId) ? 'bg-accent/10' : ''"
            @click="openChat(c.id)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-medium text-slate-100 truncate">{{ c.name || c.phone }}</p>
                <p class="text-xs text-slate-500 truncate mt-0.5">{{ c.last_message || "—" }}</p>
                <p class="text-[10px] text-slate-600 mt-1">
                  {{ c.team_name || "team" }} · {{ c.key_label || c.wa_number }}
                  <span :class="c.csw_open ? 'text-emerald-400' : 'text-amber-400'">
                    · {{ c.csw_open ? "CSW open" : "CSW closed" }}
                  </span>
                </p>
              </div>
              <span
                v-if="Number(c.unread) > 0"
                class="shrink-0 min-w-[1.25rem] h-5 px-1 rounded-full bg-accent text-[10px] font-bold flex items-center justify-center"
              >
                {{ c.unread }}
              </span>
            </div>
          </button>
          <p v-if="!chat.loadingList && !chat.conversations.length" class="p-6 text-center text-sm text-slate-500">
            Belum ada percakapan
          </p>
        </div>
      </aside>

      <!-- Thread -->
      <section
        class="flex-1 min-w-0 flex flex-col"
        :class="!chat.activeId ? 'hidden sm:flex' : 'flex'"
      >
        <div v-if="!chat.active" class="flex-1 flex items-center justify-center text-slate-500 text-sm">
          Pilih percakapan atau mulai chat baru dengan template
        </div>
        <template v-else>
          <div class="h-14 px-4 border-b border-white/10 flex items-center gap-3 bg-ink-900/50">
            <button class="sm:hidden text-slate-400" @click="chat.activeId = null">←</button>
            <div class="min-w-0">
              <p class="font-medium truncate">{{ chat.active.name || chat.active.phone }}</p>
              <p class="text-xs text-slate-500">
                {{ chat.active.phone }}
                ·
                <span :class="chat.active.csw_open ? 'text-emerald-400' : 'text-amber-400'">
                  {{ chat.active.csw_open ? "CSW terbuka" : "CSW tertutup" }}
                </span>
              </p>
            </div>
          </div>

          <div ref="scrollEl" class="flex-1 overflow-y-auto p-4 space-y-3">
            <div v-if="chat.loadingMessages && !chat.messages.length" class="text-sm text-slate-500">Memuat pesan...</div>
            <div
              v-for="m in chat.messages"
              :key="m.id"
              class="flex"
              :class="m.direction === 'out' ? 'justify-end' : 'justify-start'"
            >
              <div
                class="max-w-[80%] rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap"
                :class="m.direction === 'out' ? 'bg-accent text-white rounded-br-md' : 'bg-ink-800 text-slate-100 rounded-bl-md'"
              >
                <p v-if="m.type === 'template'" class="text-[10px] opacity-70 mb-1">template: {{ m.template_name }}</p>
                {{ m.body }}
                <div
                  class="mt-1 flex items-center justify-end gap-1 text-[10px]"
                  :class="m.direction === 'out' ? 'opacity-80' : 'opacity-60'"
                >
                  <span>{{ formatTime(m.created_at) }}</span>
                  <span v-if="m.direction === 'out'" class="inline-flex items-center" :title="m.status || 'sent'">
                    <!-- failed -->
                    <svg v-if="isFailed(m.status)" class="w-3.5 h-3.5 text-rose-300" viewBox="0 0 16 16" fill="currentColor">
                      <path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 10.5a.9.9 0 110-1.8.9.9 0 010 1.8zM7.25 4.5h1.5v5h-1.5v-5z"/>
                    </svg>
                    <!-- read: double blue -->
                    <svg v-else-if="isRead(m.status)" class="w-4 h-3" viewBox="0 0 16 11" fill="none">
                      <path d="M11.07 0.73L4.51 7.29L1.79 4.57L0.38 5.98L4.51 10.12L12.48 2.14L11.07 0.73Z" fill="#53bdeb"/>
                      <path d="M14.07 0.73L7.51 7.29L6.79 6.57L5.38 7.98L7.51 10.12L15.48 2.14L14.07 0.73Z" fill="#53bdeb"/>
                    </svg>
                    <!-- delivered: double grey -->
                    <svg v-else-if="isDelivered(m.status)" class="w-4 h-3 opacity-70" viewBox="0 0 16 11" fill="none">
                      <path d="M11.07 0.73L4.51 7.29L1.79 4.57L0.38 5.98L4.51 10.12L12.48 2.14L11.07 0.73Z" fill="currentColor"/>
                      <path d="M14.07 0.73L7.51 7.29L6.79 6.57L5.38 7.98L7.51 10.12L15.48 2.14L14.07 0.73Z" fill="currentColor"/>
                    </svg>
                    <!-- sent / default: single check -->
                    <svg v-else class="w-3 h-3 opacity-70" viewBox="0 0 12 11" fill="none">
                      <path d="M10.07 0.73L3.51 7.29L0.79 4.57L0 5.36L3.51 8.87L10.86 1.52L10.07 0.73Z" fill="currentColor"/>
                    </svg>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="p-3 border-t border-white/10 bg-ink-900/60">
            <div v-if="chat.active.csw_open" class="flex gap-2">
              <input
                v-model="draft"
                class="flex-1 rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm"
                placeholder="Tulis balasan..."
                @keyup.enter="sendFree"
              />
              <button
                class="px-4 rounded-xl bg-accent font-medium disabled:opacity-40"
                :disabled="sending || !draft.trim()"
                @click="sendFree"
              >
                Kirim
              </button>
            </div>
            <div v-else class="flex flex-col gap-2">
              <p class="text-xs text-amber-300/90">CSW tertutup. Mulai/lanjutkan dengan template WhatsApp.</p>
              <button
                class="w-full py-2.5 rounded-xl border border-accent/40 text-accent-soft hover:bg-accent/10 text-sm font-medium"
                @click="openTemplateForActive"
              >
                Kirim template
              </button>
            </div>
            <p v-if="sendError" class="text-xs text-rose-400 mt-2">{{ sendError }}</p>
          </div>
        </template>
      </section>
    </div>

    <TemplateModal
      v-if="showNew || showTpl"
      :keys="chat.keys"
      :templates="chat.templates"
      :fixed-key-id="tplKeyId"
      :fixed-phone="tplPhone"
      :busy="tplSending"
      :error="tplError"
      @close="closeModals"
      @load-templates="(id) => chat.loadTemplates(id)"
      @submit="onTemplateSubmit"
    />
  </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import { useChatStore } from "../stores/chat";
import TemplateModal from "../components/TemplateModal.vue";
import { api } from "../api";

const auth = useAuthStore();
const chat = useChatStore();
const router = useRouter();

const draft = ref("");
const sending = ref(false);
const sendError = ref("");
const showNew = ref(false);
const showTpl = ref(false);
const tplKeyId = ref(null);
const tplPhone = ref("");
const tplSending = ref(false);
const tplError = ref("");
const scrollEl = ref(null);
const templateQuotaBalance = ref(null);
let pollTimer = null;

function formatTime(v) {
  if (!v) return "";
  try {
    return new Date(v.replace(" ", "T")).toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" });
  } catch {
    return v;
  }
}

function normStatus(s) {
  return String(s || "sent").toLowerCase();
}
function isFailed(s) {
  return ["failed", "undelivered", "error", "rejected"].includes(normStatus(s));
}
function isRead(s) {
  return ["read", "played"].includes(normStatus(s));
}
function isDelivered(s) {
  return ["delivered", "delivery"].includes(normStatus(s));
}

function scrollToBottom() {
  nextTick(() => {
    const el = scrollEl.value;
    if (!el) return;
    el.scrollTop = el.scrollHeight;
    // DOM bubble bisa belum selesai layout (tinggi berubah)
    requestAnimationFrame(() => {
      if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
    });
  });
}

async function openChat(id) {
  await chat.selectConversation(id);
  scrollToBottom();
}

async function sendFree() {
  if (!draft.value.trim()) return;
  sending.value = true;
  sendError.value = "";
  try {
    await chat.sendFree(draft.value.trim());
    draft.value = "";
    scrollToBottom();
  } catch (e) {
    sendError.value = e.message;
  } finally {
    sending.value = false;
  }
}

// Pesan baru (WS / reload) → auto scroll ke bawah selama thread aktif
watch(
  () => [chat.activeId, chat.messages.length, chat.messages[chat.messages.length - 1]?.id],
  () => {
    if (chat.activeId) scrollToBottom();
  }
);

function openNewChat() {
  tplKeyId.value = null;
  tplPhone.value = "";
  tplError.value = "";
  showTpl.value = false;
  showNew.value = true;
}

function openTemplateForActive() {
  tplKeyId.value = chat.active?.ycloud_key_id || null;
  tplPhone.value = chat.active?.phone || "";
  tplError.value = "";
  showTpl.value = true;
  chat.loadTemplates(tplKeyId.value);
}

function closeModals() {
  if (tplSending.value) return;
  showNew.value = false;
  showTpl.value = false;
  tplKeyId.value = null;
  tplPhone.value = "";
  tplError.value = "";
}

async function onTemplateSubmit(payload) {
  if (tplSending.value) return;
  tplSending.value = true;
  tplError.value = "";
  sendError.value = "";
  try {
    await chat.sendTemplate(payload);
    tplSending.value = false;
    closeModals();
    await loadTemplateQuota();
  } catch (e) {
    tplError.value = e.message || "Gagal mengirim template";
    sendError.value = tplError.value;
  } finally {
    tplSending.value = false;
  }
}

async function loadTemplateQuota() {
  if (auth.isAdmin) {
    templateQuotaBalance.value = null;
    return;
  }
  try {
    const res = await api("/WaDesk/Quota/me");
    templateQuotaBalance.value =
      res.data?.balance === null || res.data?.balance === undefined
        ? null
        : Number(res.data.balance);
  } catch (_) {
    templateQuotaBalance.value = null;
  }
}

async function onLogout() {
  chat.disconnectWs();
  await auth.logout();
  router.push({ name: "login" });
}

onMounted(async () => {
  await chat.loadConversations();
  await chat.loadKeys();
  await loadTemplateQuota();
  chat.connectWs(auth.user);
  pollTimer = setInterval(() => chat.loadConversations({ silent: true }), 20000);
});

onUnmounted(() => {
  chat.disconnectWs();
  if (pollTimer) clearInterval(pollTimer);
});

watch(showNew, (v) => {
  if (v) {
    chat.loadKeys();
    chat.loadTemplates();
  }
});
</script>
