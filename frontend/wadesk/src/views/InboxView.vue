<template>
  <div class="h-full flex flex-col bg-ink-950">
    <AppHeader active="inbox" @logout="onLogout">
      <template #extra>
        <span
          v-if="templateQuotaBalance !== null"
          class="text-xs px-2 py-1 rounded-lg bg-white/5 text-slate-300 whitespace-nowrap"
          title="Sisa kuota template team"
        >
          Kuota: <span class="font-semibold text-accent">{{ templateQuotaBalance }}</span>
        </span>
        <span
          v-if="dailyLimitRemaining !== null"
          class="text-xs px-2 py-1 rounded-lg bg-white/5 text-slate-300 whitespace-nowrap"
          :title="dailyLimitTitle"
        >
          Daily sisa:
          <span
            class="font-semibold"
            :class="dailyLimitRemaining <= 0 ? 'text-rose-400' : dailyLimitRemaining <= 20 ? 'text-amber-300' : 'text-emerald-400'"
          >
            {{ dailyLimitRemaining }}
          </span>
        </span>
      </template>
    </AppHeader>

    <div
      v-if="auth.isAdmin && !auth.canSendWa"
      class="shrink-0 px-4 py-2 bg-amber-500/10 border-b border-amber-500/20 text-amber-200 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
    >
      <span>Anda belum masuk team — tidak bisa kirim chat atau blast WA.</span>
      <router-link to="/admin" class="text-accent-soft hover:underline shrink-0">Masuk team di Admin →</router-link>
    </div>

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
              class="px-3 rounded-xl bg-accent text-white text-sm font-medium disabled:opacity-40"
              title="Chat baru"
              :disabled="!auth.canSendWa"
              @click="openNewChat"
            >
              +
            </button>
          </div>
          <div class="flex gap-1">
            <button
              type="button"
              class="flex-1 py-1.5 rounded-lg text-xs font-medium transition"
              :class="chat.listFilter === 'all' ? 'bg-accent text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10'"
              @click="setListFilter('all')"
            >
              All
            </button>
            <button
              type="button"
              class="flex-1 py-1.5 rounded-lg text-xs font-medium transition inline-flex items-center justify-center gap-1.5"
              :class="chat.listFilter === 'open' ? 'bg-accent text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10'"
              @click="setListFilter('open')"
            >
              Open
              <span
                v-if="chat.openCount > 0"
                class="min-w-[1.125rem] h-[1.125rem] px-1 rounded-full text-[10px] font-bold leading-none inline-flex items-center justify-center"
                :class="chat.listFilter === 'open' ? 'bg-white/20 text-white' : 'bg-emerald-500 text-white'"
              >
                {{ chat.openCount > 99 ? '99+' : chat.openCount }}
              </span>
            </button>
            <button
              type="button"
              class="flex-1 py-1.5 rounded-lg text-xs font-medium transition inline-flex items-center justify-center gap-1.5"
              :class="chat.listFilter === 'unread' ? 'bg-accent text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10'"
              @click="setListFilter('unread')"
            >
              Unread
              <span
                v-if="chat.unreadCount > 0"
                class="min-w-[1.125rem] h-[1.125rem] px-1 rounded-full text-[10px] font-bold leading-none inline-flex items-center justify-center"
                :class="chat.listFilter === 'unread' ? 'bg-white/20 text-white' : 'bg-rose-500 text-white'"
              >
                {{ chat.unreadCount > 99 ? '99+' : chat.unreadCount }}
              </span>
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
              <div class="min-w-0 flex-1">
                <div class="flex items-baseline justify-between gap-2">
                  <p class="font-medium text-slate-100 truncate">{{ c.name || c.phone }}</p>
                  <span
                    v-if="formatConvTime(c)"
                    class="shrink-0 text-[10px] tabular-nums"
                    :class="Number(c.unread) > 0 ? 'text-accent-soft font-medium' : 'text-slate-500'"
                  >
                    {{ formatConvTime(c) }}
                  </span>
                </div>
                <p class="text-xs truncate mt-0.5" :class="Number(c.unread) > 0 ? 'text-slate-300 font-medium' : 'text-slate-500'">
                  {{ c.last_message || "—" }}
                </p>
                <p class="text-[10px] text-slate-600 mt-1">
                  {{ c.team_name || "team" }} · {{ c.key_label || c.wa_number }}
                  <span :class="c.csw_open ? 'text-emerald-400' : 'text-amber-400'">
                    · {{ c.csw_open ? "CSW open" : "CSW closed" }}
                  </span>
                </p>
              </div>
              <span
                v-if="Number(c.unread) > 0"
                class="shrink-0 min-w-[1.25rem] h-5 px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center self-center"
              >
                {{ c.unread }}
              </span>
            </div>
          </button>
          <p v-if="!chat.loadingList && !chat.conversations.length" class="p-6 text-center text-sm text-slate-500">
            {{
              chat.listFilter === 'unread'
                ? 'Tidak ada chat belum dibaca'
                : chat.listFilter === 'open'
                  ? 'Tidak ada chat CSW terbuka'
                  : 'Belum ada percakapan'
            }}
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
                class="max-w-[80%] rounded-2xl px-3 py-2 text-sm"
                :class="m.direction === 'out' ? 'bg-accent text-white rounded-br-md' : 'bg-ink-800 text-slate-100 rounded-bl-md'"
              >
                <p v-if="m.type === 'template'" class="text-[10px] opacity-70 mb-1">template: {{ m.template_name }}</p>
                <div class="whitespace-pre-wrap">{{ formatMessageBody(m) }}</div>
                <p
                  v-if="m.body_raw && m.body_raw !== m.body"
                  class="mt-1.5 pt-1.5 border-t border-white/10 text-[11px] opacity-70 whitespace-pre-wrap"
                  title="Draf asli sebelum AI rapikan"
                >
                  Draf: {{ m.body_raw }}
                </p>
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
            <div v-if="!auth.canSendWa" class="text-xs text-amber-300/90 py-2">
              Masuk team di Admin untuk mengirim pesan.
            </div>
            <div v-else-if="chat.active.csw_open" class="flex gap-2">
              <input
                v-model="draft"
                class="flex-1 rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm disabled:opacity-50"
                placeholder="Tulis balasan..."
                :disabled="sending"
                @keyup.enter="sendFree"
              />
              <button
                class="px-4 rounded-xl bg-accent font-medium disabled:opacity-40 inline-flex items-center justify-center gap-2 min-w-[5.5rem]"
                :disabled="sending || !draft.trim()"
                @click="sendFree"
              >
                <svg
                  v-if="sending"
                  class="h-4 w-4 animate-spin"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  />
                </svg>
                {{ sending ? "Memeriksa AI..." : "Kirim" }}
              </button>
            </div>
            <div v-else-if="auth.canSendWa" class="flex flex-col gap-2">
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
      :key="templateModalKey"
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

    <ConfirmModal
      v-if="freeReject.open"
      mode="alert"
      title="Pesan tidak dapat dikirim"
      :message="freeReject.message"
      confirm-label="Mengerti"
      @confirm="freeReject.open = false"
      @close="freeReject.open = false"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import { useChatStore } from "../stores/chat";
import AppHeader from "../components/AppHeader.vue";
import TemplateModal from "../components/TemplateModal.vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import { api } from "../api";

const auth = useAuthStore();
const chat = useChatStore();
const router = useRouter();

const draft = ref("");
const sending = ref(false);
const sendError = ref("");
const showNew = ref(false);
const showTpl = ref(false);
const templateModalKey = ref(0);
const tplKeyId = ref(null);
const tplPhone = ref("");
const tplSending = ref(false);
const tplError = ref("");
const scrollEl = ref(null);
const templateQuotaBalance = ref(null);
const dailyLimitRemaining = ref(null);
const dailyLimitUsed = ref(null);
const dailyLimitMax = ref(null);
const dailyLimitTitle = computed(() => {
  if (dailyLimitMax.value === null) return "";
  return `Nomor unik terkirim hari ini: ${dailyLimitUsed.value ?? 0} / ${dailyLimitMax.value}`;
});
const freeReject = reactive({ open: false, message: "" });
let pollTimer = null;

function formatTime(v) {
  if (!v) return "";
  try {
    return new Date(v.replace(" ", "T")).toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" });
  } catch {
    return v;
  }
}

function parseConvDate(c) {
  const raw = c?.last_message_at || c?.updated_at || c?.created_at;
  if (!raw) return null;
  try {
    const d = new Date(String(raw).replace(" ", "T"));
    return Number.isNaN(d.getTime()) ? null : d;
  } catch {
    return null;
  }
}

function formatConvTime(c) {
  const d = parseConvDate(c);
  if (!d) return "";

  const now = new Date();
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const startOfTarget = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const dayDiff = Math.round((startOfToday - startOfTarget) / 86400000);

  if (dayDiff === 0) {
    return d.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" });
  }
  if (dayDiff === 1) {
    return "Kemarin";
  }
  if (dayDiff < 7) {
    return d.toLocaleDateString("id-ID", { weekday: "short" });
  }
  return d.toLocaleDateString("id-ID", { day: "2-digit", month: "2-digit", year: "2-digit" });
}

function escapeRegExp(s) {
  return String(s).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

/** Fill {{customer}} / {{1}} in template bubbles only when placeholders remain. */
function formatMessageBody(m) {
  const body = String(m?.body || "");
  if (m?.type !== "template" || !Array.isArray(m.params) || !m.params.length) {
    return body;
  }

  // Already filled when sent (server buildFilledPreview) — show as stored (matches WhatsApp)
  if (!/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/.test(body)) {
    return body;
  }

  const textParams = m.params.filter((p) => {
    const c = String(p?.component || "body").toLowerCase();
    return c === "body" || c === "header";
  });
  if (!textParams.length) return body;

  let out = body;
  for (const p of textParams) {
    const val = String(p.text ?? "");
    if (p.param_name) {
      out = out.replace(
        new RegExp(`\\{\\{\\s*${escapeRegExp(String(p.param_name))}\\s*\\}\\}`, "g"),
        val
      );
    } else if (p.param_index != null) {
      const idx = String(p.param_index);
      out = out.replace(
        new RegExp(`\\{\\{\\s*${escapeRegExp(idx)}\\s*\\}\\}`, "g"),
        val
      );
    }
  }
  return out;
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

function setListFilter(filter) {
  if (chat.listFilter === filter) return;
  chat.listFilter = filter;
  chat.loadConversations();
}

async function sendFree() {
  if (!draft.value.trim() || sending.value) return;
  sending.value = true;
  sendError.value = "";
  freeReject.open = false;
  try {
    await chat.sendFree(draft.value.trim());
    draft.value = "";
    scrollToBottom();
    await loadTemplateQuota();
  } catch (e) {
    if (e.status === 422 && (e.data?.status === false || e.data?.reason)) {
      freeReject.message = e.data.reason || e.message || "Pesan ditolak AI.";
      freeReject.open = true;
    } else {
      sendError.value = e.message;
    }
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
  if (!auth.canSendWa) return;
  templateModalKey.value += 1;
  tplKeyId.value = null;
  tplPhone.value = "";
  tplError.value = "";
  chat.templates = [];
  showTpl.value = false;
  showNew.value = true;
}

async function openTemplateForActive() {
  if (!auth.canSendWa) return;
  templateModalKey.value += 1;
  tplKeyId.value = chat.active?.channel_id || null;
  tplPhone.value = chat.active?.phone || "";
  tplError.value = "";
  chat.templates = [];
  showNew.value = false;
  showTpl.value = true;
  if (tplKeyId.value) {
    await chat.loadTemplates(tplKeyId.value);
  }
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
  if (!auth.canSendWa) {
    templateQuotaBalance.value = null;
    dailyLimitRemaining.value = null;
    dailyLimitUsed.value = null;
    dailyLimitMax.value = null;
    return;
  }
  try {
    const res = await api("/WaDesk/Quota/me");
    templateQuotaBalance.value =
      res.data?.balance === null || res.data?.balance === undefined
        ? null
        : Number(res.data.balance);
    const dl = res.data?.daily_limit;
    if (dl?.configured) {
      dailyLimitRemaining.value = Number(dl.remaining_today ?? 0);
      dailyLimitUsed.value = Number(dl.used_today ?? 0);
      dailyLimitMax.value = Number(dl.limit ?? 0);
    } else {
      dailyLimitRemaining.value = null;
      dailyLimitUsed.value = null;
      dailyLimitMax.value = null;
    }
  } catch (_) {
    templateQuotaBalance.value = null;
    dailyLimitRemaining.value = null;
    dailyLimitUsed.value = null;
    dailyLimitMax.value = null;
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
  pollTimer = setInterval(() => {
    chat.loadConversations({ silent: true });
    loadTemplateQuota();
  }, 20000);
});

onUnmounted(() => {
  chat.disconnectWs();
  if (pollTimer) clearInterval(pollTimer);
});

watch(showNew, (v) => {
  if (v) {
    chat.loadKeys();
    chat.templates = [];
  }
});
</script>
