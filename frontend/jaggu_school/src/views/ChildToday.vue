<template>
  <div class="space-y-5">
    <section class="rounded-3xl bg-jaggu-red text-white p-5 shadow-lg shadow-red-200/50">
      <p class="text-red-100 text-xs font-semibold uppercase tracking-wider">Dashboard</p>
      <h2 class="font-display text-2xl font-bold mt-1">Siap belajar, {{ userName }}!</h2>
      <p class="text-red-100 text-sm mt-2">Ceklist mapel paling cepat H−1, paling lambat hari H.</p>
    </section>

    <div v-if="loading" class="text-sm text-slate-500">Memuat...</div>
    <div v-else-if="error" class="rounded-2xl bg-red-50 text-red-700 text-sm px-4 py-3">{{ error }}</div>

    <template v-else>
      <section class="space-y-2">
        <h3 class="text-sm font-bold text-slate-700">Pemberitahuan</h3>
        <div
          v-for="(n, i) in notices"
          :key="i"
          class="rounded-2xl px-4 py-3 text-sm border"
          :class="noticeClass(n.type)"
        >
          {{ n.text }}
        </div>
      </section>

      <DayCard
        title="Hari ini"
        :day="today"
        @toggle="onToggle"
      />

      <DayCard
        v-if="tomorrow"
        title="Besok — siapkan dari sore"
        :day="tomorrow"
        @toggle="onToggle"
      />
      <p v-else class="text-xs text-slate-500 text-center">
        Mapel besok muncul mulai jam {{ revealHour }}:00
      </p>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { getUser } from "../utils/session";
import DayCard from "./DayCard.vue";

const loading = ref(true);
const error = ref("");
const today = ref(null);
const tomorrow = ref(null);
const notices = ref([]);
const revealHour = ref(15);
const userName = computed(() => getUser()?.name || "Jaggu");

onMounted(load);

async function load() {
  loading.value = true;
  error.value = "";
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
    error.value = e.message || "Gagal memuat";
  } finally {
    loading.value = false;
  }
}

function noticeClass(type) {
  if (type === "ok") return "bg-emerald-50 border-emerald-100 text-emerald-800";
  if (type === "warn") return "bg-amber-50 border-amber-100 text-amber-900";
  if (type === "prep") return "bg-sky-50 border-sky-100 text-sky-900";
  return "bg-slate-50 border-slate-100 text-slate-700";
}

async function onToggle({ item, day, checked }) {
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
  if (!res.ok) {
    alert(data.message || "Gagal menyimpan");
    await load();
    return;
  }
  await load();
}
</script>
