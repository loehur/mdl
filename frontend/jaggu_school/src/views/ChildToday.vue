<template>
  <div class="space-y-5 relative">
    <section class="rounded-3xl bg-jaggu-red text-white p-5 shadow-lg shadow-red-200/50">
      <p class="text-red-100 text-xs font-semibold uppercase tracking-wider">Dashboard</p>
      <h2 class="font-display text-2xl font-bold mt-1">Siap belajar, {{ userName }}!</h2>
      <p class="text-red-100 text-sm mt-2">Ceklist mapel paling cepat H−1, paling lambat hari H.</p>
    </section>

    <div v-if="loading" class="text-sm text-slate-500">Memuat...</div>
    <div v-else-if="error" class="rounded-2xl bg-red-50 text-red-700 text-sm px-4 py-3">{{ error }}</div>

    <template v-else>
      <section class="space-y-2 min-h-[4.5rem]">
        <h3 class="text-sm font-bold text-slate-700">Pemberitahuan</h3>
        <div
          v-for="(n, i) in notices"
          :key="i"
          class="rounded-2xl px-4 py-3 text-sm border flex items-start gap-2"
          :class="noticeClass(n.type)"
        >
          <span v-if="n.type === 'ok'" class="shrink-0 font-bold" aria-hidden="true">✓</span>
          <span>{{ n.text }}</span>
        </div>
      </section>

      <DayCard
        v-if="tomorrow"
        title="Besok — siapkan dari sore"
        :day="tomorrow"
        :busy-id="busyId"
        @toggle="onToggle"
      />
      <p v-else class="text-xs text-slate-500 text-center">
        Mapel besok muncul mulai jam {{ revealHour }}:00
      </p>

      <DayCard
        title="Hari ini"
        :day="today"
        :busy-id="busyId"
        @toggle="onToggle"
      />
    </template>

    <!-- Toast mengambang: tidak mengubah layout -->
    <Teleport to="body">
      <Transition name="jaggu-float">
        <div
          v-if="toast"
          class="pointer-events-none fixed left-1/2 z-[80] -translate-x-1/2 bottom-28"
        >
          <div
            class="rounded-full px-4 py-2.5 text-sm font-semibold shadow-xl shadow-red-200/60 border backdrop-blur-md"
            :class="toast.ok
              ? 'bg-jaggu-red/95 text-white border-red-700/30'
              : 'bg-white/95 text-red-700 border-red-200'"
          >
            {{ toast.text }}
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { getUser } from "../utils/session";
import DayCard from "./DayCard.vue";

const loading = ref(true);
const error = ref("");
const today = ref(null);
const tomorrow = ref(null);
const notices = ref([]);
const revealHour = ref(15);
const busyId = ref(null);
const toast = ref(null);
let toastTimer = null;
const userName = computed(() => getUser()?.name || "Jaggu");

onMounted(load);
onUnmounted(() => {
  if (toastTimer) clearTimeout(toastTimer);
});

async function load(silent = false) {
  if (!silent) {
    loading.value = true;
    error.value = "";
  }
  try {
    const res = await fetch("/api/Jaggu_School/Checklist/today");
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Gagal memuat");
    const d = data.data || data;
    today.value = d.today;
    tomorrow.value = d.tomorrow;
    notices.value = d.notices || [];
    revealHour.value = d.tomorrow_reveal_hour || 15;
  } catch (e) {
    if (!silent) error.value = e.message || "Gagal memuat";
  } finally {
    if (!silent) loading.value = false;
  }
}

function noticeClass(type) {
  if (type === "ok") return "bg-emerald-50 border-emerald-100 text-emerald-800";
  if (type === "warn") return "bg-amber-50 border-amber-100 text-amber-900";
  if (type === "prep") return "bg-sky-50 border-sky-100 text-sky-900";
  return "bg-slate-50 border-slate-100 text-slate-700";
}

function showToast(text, ok = true) {
  if (toastTimer) clearTimeout(toastTimer);
  toast.value = { text, ok };
  toastTimer = setTimeout(() => {
    toast.value = null;
  }, 1600);
}

function patchDayLocal(dayRef, itemId, checked) {
  if (!dayRef.value?.items) return;
  const items = dayRef.value.items.map((it) =>
    it.id === itemId
      ? { ...it, checked, checked_at: checked ? new Date().toISOString().slice(0, 19).replace("T", " ") : null }
      : it
  );
  const done = items.filter((it) => it.checked).length;
  const total = items.length;
  dayRef.value = {
    ...dayRef.value,
    items,
    done,
    pending: Math.max(0, total - done),
    complete: total > 0 && done === total,
  };
}

async function onToggle({ item, day, checked }) {
  if (busyId.value) return;
  busyId.value = item.id;

  // Optimistic: update lokal dulu agar layout stabil
  const target =
    today.value?.date === day.date
      ? today
      : tomorrow.value?.date === day.date
        ? tomorrow
        : null;
  if (target) patchDayLocal(target, item.id, checked);

  try {
    const res = await fetch("/api/Jaggu_School/Checklist/toggle", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        schedule_item_id: item.id,
        for_date: day.date,
        checked,
      }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Gagal menyimpan");

    const refreshed = data.data?.day;
    if (refreshed && target) {
      target.value = refreshed;
    } else {
      await load(true);
    }
    showToast(checked ? `✓ ${item.subject_name} siap` : `${item.subject_name} dibatalkan`, true);
  } catch (e) {
    // rollback via silent reload
    await load(true);
    showToast(e.message || "Gagal menyimpan", false);
  } finally {
    busyId.value = null;
  }
}
</script>

<style scoped>
.jaggu-float-enter-active,
.jaggu-float-leave-active {
  transition: opacity 0.28s ease, transform 0.28s ease;
}
.jaggu-float-enter-from {
  opacity: 0;
  transform: translate(-50%, 12px) scale(0.96);
}
.jaggu-float-leave-to {
  opacity: 0;
  transform: translate(-50%, -8px) scale(0.98);
}
.jaggu-float-enter-to,
.jaggu-float-leave-from {
  opacity: 1;
  transform: translate(-50%, 0) scale(1);
}
</style>
