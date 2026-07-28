<template>
  <div class="flex flex-col" style="min-height: calc(100vh - 9.5rem)">
    <section class="rounded-3xl bg-jaggu-red text-white p-4 shadow-lg shadow-red-200/40 mb-3 shrink-0">
      <p class="text-red-100 text-xs font-semibold uppercase tracking-wider">Tanya AI</p>
      <h2 class="font-display text-xl font-bold mt-0.5">Belajar bareng tutor</h2>
      <p class="text-red-100 text-xs mt-1">
        Sisa chat hari ini: {{ remaining }} pesan
      </p>
    </section>

    <div
      ref="listEl"
      class="flex-1 overflow-y-auto space-y-3 px-0.5 pb-3"
    >
      <div v-if="loading" class="text-sm text-slate-500 text-center py-8">Memuat chat...</div>
      <div v-else-if="error" class="rounded-2xl bg-red-50 text-red-700 text-sm px-4 py-3">{{ error }}</div>
      <template v-else>
        <p v-if="!messages.length" class="text-sm text-slate-500 text-center py-8 px-4">
          Halo! Tanya apa saja tentang pelajaran, PR, atau cara belajar. Aku siap membantu.
        </p>
        <div
          v-for="m in messages"
          :key="m.id"
          class="flex"
          :class="m.role === 'user' ? 'justify-end' : 'justify-start'"
        >
          <div
            class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-sm whitespace-pre-wrap break-words shadow-sm"
            :class="m.role === 'user'
              ? 'bg-jaggu-red text-white rounded-br-md'
              : 'bg-white border border-red-100 text-slate-800 rounded-bl-md'"
          >
            {{ m.content }}
          </div>
        </div>
        <div v-if="sending" class="flex justify-start">
          <div class="bg-white border border-red-100 text-slate-400 text-sm rounded-2xl rounded-bl-md px-3.5 py-2.5">
            Mengetik...
          </div>
        </div>
      </template>
    </div>

    <form
      class="shrink-0 sticky bottom-0 pt-2 bg-gradient-to-t from-jaggu-mist via-jaggu-mist to-transparent"
      @submit.prevent="send"
    >
      <div class="flex gap-2 items-end rounded-2xl bg-white border border-red-100 shadow-md p-2">
        <textarea
          v-model="draft"
          rows="2"
          maxlength="2000"
          :disabled="sending || remaining <= 0"
          placeholder="Tulis pertanyaanmu..."
          class="flex-1 resize-none rounded-xl border-0 px-2 py-1.5 text-sm focus:outline-none focus:ring-0 disabled:opacity-60"
          @keydown.enter.exact.prevent="send"
        />
        <button
          type="submit"
          :disabled="sending || !draft.trim() || remaining <= 0"
          class="rounded-xl bg-jaggu-red hover:bg-jaggu-crimson disabled:opacity-50 text-white text-sm font-semibold px-4 py-2.5"
        >
          Kirim
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from "vue";

const loading = ref(true);
const sending = ref(false);
const error = ref("");
const messages = ref([]);
const draft = ref("");
const remaining = ref(30);
const listEl = ref(null);

onMounted(load);

async function load() {
  loading.value = true;
  error.value = "";
  try {
    const res = await fetch("/api/Jaggu_School/Chat/today");
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Gagal memuat chat");
    const d = data.data || data;
    messages.value = d.messages || [];
    remaining.value = d.remaining_today ?? 30;
    await scrollBottom();
  } catch (e) {
    error.value = e.message || "Gagal memuat";
  } finally {
    loading.value = false;
  }
}

async function send() {
  const text = draft.value.trim();
  if (!text || sending.value || remaining.value <= 0) return;

  sending.value = true;
  error.value = "";
  draft.value = "";

  // Optimistic user bubble
  const tempId = "tmp-" + Date.now();
  messages.value.push({
    id: tempId,
    role: "user",
    content: text,
    created_at: new Date().toISOString(),
  });
  await scrollBottom();

  try {
    const res = await fetch("/api/Jaggu_School/Chat/send", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ message: text }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Gagal mengirim");

    const d = data.data || data;
    messages.value = messages.value.filter((m) => m.id !== tempId);
    if (d.user_message) messages.value.push(d.user_message);
    if (d.assistant_message) messages.value.push(d.assistant_message);
    if (typeof d.remaining_today === "number") remaining.value = d.remaining_today;
    await scrollBottom();
  } catch (e) {
    messages.value = messages.value.filter((m) => m.id !== tempId);
    draft.value = text;
    error.value = e.message || "Gagal mengirim";
  } finally {
    sending.value = false;
  }
}

async function scrollBottom() {
  await nextTick();
  const el = listEl.value;
  if (el) el.scrollTop = el.scrollHeight;
}
</script>
