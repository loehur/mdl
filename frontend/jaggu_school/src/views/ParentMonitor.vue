<template>
  <div class="space-y-5">
    <section class="rounded-3xl bg-white border border-red-100 p-5 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wider text-jaggu-red">Pantauan</p>
      <h2 class="font-display text-2xl font-bold text-jaggu-crimson mt-1">
        Progress {{ childName }}
      </h2>
      <p class="text-sm text-slate-500 mt-1">Mapel besok muncul di pantauan mulai sore (jam {{ revealHour }}:00).</p>
    </section>

    <div v-if="loading" class="text-sm text-slate-500">Memuat...</div>
    <div v-else-if="error" class="rounded-2xl bg-red-50 text-red-700 text-sm px-4 py-3">{{ error }}</div>

    <template v-else>
      <section class="space-y-2">
        <div
          v-for="(n, i) in summary"
          :key="i"
          class="rounded-2xl px-4 py-3 text-sm border"
          :class="noticeClass(n.type)"
        >
          {{ n.text }}
        </div>
      </section>

      <DayCard
        v-if="tomorrow"
        title="Besok — persiapan"
        :day="tomorrow"
        :interactive="false"
      />
      <p v-else class="text-xs text-slate-500 text-center">
        Pantauan mapel besok aktif mulai jam {{ revealHour }}:00
      </p>

      <DayCard title="Hari ini" :day="today" :interactive="false" />

      <button
        type="button"
        class="w-full text-sm font-semibold text-jaggu-red py-2"
        @click="load"
      >
        Muat ulang
      </button>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import DayCard from "./DayCard.vue";

const loading = ref(true);
const error = ref("");
const today = ref(null);
const tomorrow = ref(null);
const summary = ref([]);
const child = ref(null);
const revealHour = ref(15);
const childName = computed(() => child.value?.name || "anak");

onMounted(load);

async function load() {
  loading.value = true;
  error.value = "";
  try {
    const res = await fetch("/api/Jaggu_School/Monitor/index");
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Gagal memuat");
    const d = data.data || data;
    today.value = d.today;
    tomorrow.value = d.tomorrow;
    summary.value = d.summary || [];
    child.value = d.child;
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
</script>
