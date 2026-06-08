<template>
  <div class="space-y-4">
    <div v-if="loading" class="card p-6 text-center text-sm text-slate-500">Memuat ringkasan...</div>

    <template v-else>
      <section class="card overflow-hidden">
        <div class="bg-gradient-to-br from-emerald-600 to-teal-600 p-5 text-white">
          <p class="text-sm text-emerald-100">Portfolio Terkini</p>
          <p class="mt-1 text-3xl font-bold">{{ formatRupiah(summary.portfolio_amount) }}</p>
          <p v-if="summary.portfolio?.record_date" class="mt-1 text-xs text-emerald-100">
            Per {{ formatDate(summary.portfolio.record_date) }}
          </p>
          <p v-else class="mt-1 text-xs text-emerald-100">Belum ada data portfolio</p>
        </div>
      </section>

      <section class="grid grid-cols-2 gap-3">
        <div class="card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hari Ini</p>
          <p class="mt-2 text-lg font-bold text-emerald-700">{{ formatRupiah(summary.today_income) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bulan Ini</p>
          <p class="mt-2 text-lg font-bold text-emerald-700">{{ formatRupiah(summary.month_income) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Deposit</p>
          <p class="mt-2 text-lg font-bold text-slate-900">{{ formatRupiah(summary.total_deposits) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Penarikan</p>
          <p class="mt-2 text-lg font-bold text-slate-900">{{ formatRupiah(summary.total_withdrawals) }}</p>
        </div>
      </section>

      <section class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Net Investasi</p>
        <p class="mt-2 text-2xl font-bold" :class="summary.net_investment >= 0 ? 'text-emerald-700' : 'text-red-600'">
          {{ formatRupiah(summary.net_investment) }}
        </p>
        <p class="mt-1 text-xs text-slate-500">Total deposit dikurangi penarikan</p>
      </section>

      <section class="grid grid-cols-2 gap-3">
        <router-link to="/pemasukan" class="btn-primary">+ Pemasukan</router-link>
        <router-link to="/portfolio" class="btn-secondary">Update Portfolio</router-link>
      </section>
    </template>

    <p v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</p>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { formatDate, formatRupiah } from "../utils/format";

const loading = ref(true);
const error = ref("");
const summary = ref({
  today_income: 0,
  month_income: 0,
  total_deposits: 0,
  total_withdrawals: 0,
  net_investment: 0,
  portfolio_amount: null,
  portfolio: null,
});

async function loadSummary() {
  loading.value = true;
  error.value = "";
  try {
    const res = await fetch("/api/Investasi/Dashboard/summary");
    const data = await res.json();
    if (!res.ok || !data.status) {
      throw new Error(data.message || "Gagal memuat ringkasan");
    }
    summary.value = data.data;
  } catch (err) {
    error.value = err.message || "Gagal memuat ringkasan";
  } finally {
    loading.value = false;
  }
}

onMounted(loadSummary);
</script>
