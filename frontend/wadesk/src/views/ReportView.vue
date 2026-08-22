<template>
  <div class="min-h-screen bg-ink-950 text-slate-100 font-body">
    <AppHeader page-title="Report" active="report" @logout="onLogout" />

    <div
      v-if="!auth.canSendWa"
      class="px-4 py-2 bg-amber-500/10 border-b border-amber-500/20 text-amber-200 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
    >
      <span>Anda belum masuk team — tidak bisa melihat report.</span>
      <router-link to="/admin" class="text-accent-soft hover:underline shrink-0">Masuk team di Admin →</router-link>
    </div>

    <div v-else class="max-w-5xl mx-auto p-4 space-y-6">
      <section class="card space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
          <div>
            <h2 class="font-display font-semibold text-base">Ringkasan harian</h2>
            <p class="text-xs text-slate-500 mt-1">
              Pesan keluar team: terkirim, gagal, delivered, dan read (maks. 7 hari).
            </p>
          </div>
          <button type="button" class="btn-sm shrink-0" :disabled="loading" @click="loadReport">
            {{ loading ? "Memuat..." : "Refresh" }}
          </button>
        </div>

        <form class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-end" @submit.prevent="loadReport">
          <div>
            <label class="label">Dari</label>
            <input v-model="filter.from" type="date" class="field" required :max="filter.to" @change="onFromChange" />
          </div>
          <div>
            <label class="label">Sampai</label>
            <input v-model="filter.to" type="date" class="field" required :min="filter.from" :max="maxToDate" @change="onToChange" />
          </div>
          <button type="submit" class="btn" :disabled="loading">Tampilkan</button>
        </form>
      </section>

      <section v-if="summary" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="rounded-xl border border-white/10 bg-ink-900/40 p-3">
          <p class="text-[10px] uppercase tracking-wide text-slate-500">Total pesan</p>
          <p class="text-xl font-semibold text-slate-100 mt-1">{{ summary.total }}</p>
          <p class="text-[10px] text-slate-600 mt-0.5">↑ {{ summary.total_in }} · ↓ {{ summary.total_out }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-ink-900/40 p-3">
          <p class="text-[10px] uppercase tracking-wide text-slate-500">Terkirim</p>
          <p class="text-xl font-semibold text-emerald-400 mt-1">{{ summary.sent }}</p>
          <p class="text-[10px] text-slate-600 mt-0.5">keluar, bukan gagal</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-ink-900/40 p-3">
          <p class="text-[10px] uppercase tracking-wide text-slate-500">Gagal</p>
          <p class="text-xl font-semibold text-rose-400 mt-1">{{ summary.failed }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-ink-900/40 p-3">
          <p class="text-[10px] uppercase tracking-wide text-slate-500">Delivered</p>
          <p class="text-xl font-semibold text-sky-400 mt-1">{{ summary.delivered }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-ink-900/40 p-3">
          <p class="text-[10px] uppercase tracking-wide text-slate-500">Read</p>
          <p class="text-xl font-semibold text-accent-soft mt-1">{{ summary.read }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-ink-900/40 p-3">
          <p class="text-[10px] uppercase tracking-wide text-slate-500">Periode</p>
          <p class="text-sm font-medium text-slate-200 mt-1">{{ formatDateShort(filter.from) }}</p>
          <p class="text-[10px] text-slate-600">s/d {{ formatDateShort(filter.to) }}</p>
        </div>
      </section>

      <section class="card overflow-hidden">
        <div v-if="loading && !days.length" class="text-sm text-slate-500 py-8 text-center">Memuat report...</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-slate-500 border-b border-white/10">
                <th class="py-3 pr-4 font-medium">Tanggal</th>
                <th class="py-3 pr-4 font-medium text-right">Total</th>
                <th class="py-3 pr-4 font-medium text-right hidden sm:table-cell">Masuk</th>
                <th class="py-3 pr-4 font-medium text-right hidden sm:table-cell">Keluar</th>
                <th class="py-3 pr-4 font-medium text-right">Terkirim</th>
                <th class="py-3 pr-4 font-medium text-right">Gagal</th>
                <th class="py-3 pr-4 font-medium text-right">Delivered</th>
                <th class="py-3 font-medium text-right">Read</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in days"
                :key="row.date"
                class="border-b border-white/5 hover:bg-white/[0.02]"
                :class="row.total_out === 0 && row.total_in === 0 ? 'opacity-50' : ''"
              >
                <td class="py-2.5 pr-4 whitespace-nowrap">{{ formatDateRow(row.date) }}</td>
                <td class="py-2.5 pr-4 text-right tabular-nums">{{ row.total }}</td>
                <td class="py-2.5 pr-4 text-right tabular-nums hidden sm:table-cell text-slate-400">{{ row.total_in }}</td>
                <td class="py-2.5 pr-4 text-right tabular-nums hidden sm:table-cell">{{ row.total_out }}</td>
                <td class="py-2.5 pr-4 text-right tabular-nums text-emerald-400">{{ row.sent }}</td>
                <td class="py-2.5 pr-4 text-right tabular-nums" :class="row.failed > 0 ? 'text-rose-400' : 'text-slate-500'">
                  {{ row.failed }}
                </td>
                <td class="py-2.5 pr-4 text-right tabular-nums text-sky-400">{{ row.delivered }}</td>
                <td class="py-2.5 text-right tabular-nums text-accent-soft">{{ row.read }}</td>
              </tr>
            </tbody>
            <tfoot v-if="summary && days.length">
              <tr class="border-t border-white/10 bg-white/[0.03] font-medium">
                <td class="py-3 pr-4">Total</td>
                <td class="py-3 pr-4 text-right tabular-nums">{{ summary.total }}</td>
                <td class="py-3 pr-4 text-right tabular-nums hidden sm:table-cell">{{ summary.total_in }}</td>
                <td class="py-3 pr-4 text-right tabular-nums hidden sm:table-cell">{{ summary.total_out }}</td>
                <td class="py-3 pr-4 text-right tabular-nums text-emerald-400">{{ summary.sent }}</td>
                <td class="py-3 pr-4 text-right tabular-nums text-rose-400">{{ summary.failed }}</td>
                <td class="py-3 pr-4 text-right tabular-nums text-sky-400">{{ summary.delivered }}</td>
                <td class="py-3 text-right tabular-nums text-accent-soft">{{ summary.read }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <p v-if="!loading && !days.length" class="text-sm text-slate-500 text-center py-6">Tidak ada data.</p>
      </section>

      <p v-if="error" class="text-sm text-rose-400">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api";
import { useAuthStore } from "../stores/auth";
import AppHeader from "../components/AppHeader.vue";

const auth = useAuthStore();
const router = useRouter();

const MAX_RANGE_DAYS = 7;

function todayStr() {
  return new Date().toISOString().slice(0, 10);
}

function addDays(iso, days) {
  const d = new Date(iso + "T12:00:00");
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

function defaultFrom() {
  return addDays(todayStr(), -(MAX_RANGE_DAYS - 1));
}

const filter = reactive({
  from: defaultFrom(),
  to: todayStr(),
});

const days = ref([]);
const summary = ref(null);
const teamName = ref("");
const loading = ref(false);
const error = ref("");

const maxToDate = computed(() => {
  const today = todayStr();
  const cap = addDays(filter.from, MAX_RANGE_DAYS - 1);
  return cap < today ? cap : today;
});

function daysBetween(from, to) {
  const a = new Date(from + "T12:00:00");
  const b = new Date(to + "T12:00:00");
  return Math.floor((b - a) / 86400000) + 1;
}

function clampFilterRange() {
  const today = todayStr();
  if (filter.to > today) filter.to = today;
  if (filter.from > filter.to) filter.from = filter.to;
  if (daysBetween(filter.from, filter.to) > MAX_RANGE_DAYS) {
    filter.from = addDays(filter.to, -(MAX_RANGE_DAYS - 1));
  }
}

function onFromChange() {
  clampFilterRange();
  if (filter.to > maxToDate.value) filter.to = maxToDate.value;
}

function onToChange() {
  clampFilterRange();
}

function formatDateShort(iso) {
  if (!iso) return "—";
  try {
    return new Date(iso + "T12:00:00").toLocaleDateString("id-ID", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    });
  } catch {
    return iso;
  }
}

function formatDateRow(iso) {
  if (!iso) return "—";
  try {
    const d = new Date(iso + "T12:00:00");
    return d.toLocaleDateString("id-ID", {
      weekday: "short",
      day: "2-digit",
      month: "short",
      year: "numeric",
    });
  } catch {
    return iso;
  }
}

async function loadReport() {
  if (!auth.canSendWa) return;
  clampFilterRange();
  loading.value = true;
  error.value = "";
  try {
    const qs = new URLSearchParams({
      from: filter.from,
      to: filter.to,
      _: String(Date.now()),
    });
    const res = await api(`/WaDesk/Report/daily?${qs}`, { cache: "no-store" });
    days.value = res.data?.days ?? [];
    summary.value = res.data?.summary ?? null;
    teamName.value = res.data?.team_name ?? auth.user?.team_name ?? "";
    if (res.data?.from) filter.from = res.data.from;
    if (res.data?.to) filter.to = res.data.to;
  } catch (e) {
    days.value = [];
    summary.value = null;
    error.value = e.message || "Gagal memuat report";
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  if (auth.canSendWa) loadReport();
});

async function onLogout() {
  await auth.logout();
  router.push({ name: "login" });
}
</script>

<style scoped>
.label {
  @apply block text-xs text-slate-400 mb-1;
}
.field {
  @apply w-full rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-accent/40;
}
.card {
  @apply rounded-2xl border border-white/10 bg-ink-900/40 p-4;
}
.btn {
  @apply px-4 py-2.5 rounded-xl bg-accent text-white text-sm font-medium hover:opacity-90 disabled:opacity-50;
}
.btn-sm {
  @apply px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-200 text-sm disabled:opacity-50;
}
</style>
