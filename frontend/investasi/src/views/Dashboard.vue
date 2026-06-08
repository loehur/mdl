<template>
  <div class="space-y-6">
    <div v-if="loading" class="space-y-4">
      <div class="skeleton h-44" />
      <div class="grid grid-cols-2 gap-3">
        <div class="skeleton h-24" />
        <div class="skeleton h-24" />
        <div class="skeleton h-24" />
        <div class="skeleton h-24" />
      </div>
    </div>

    <template v-else>
      <!-- Hero portfolio card -->
      <section class="glass-strong relative overflow-hidden p-6 shadow-glow">
        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-ledger/10 blur-2xl" />
        <p class="label-caps relative">Portfolio terkini</p>
        <p class="money-display relative mt-3 text-gradient-accent">
          {{ formatRupiah(summary.portfolio_amount) }}
        </p>
        <p class="relative mt-3 text-sm text-mist">
          <template v-if="summary.portfolio?.record_date">
            Diperbarui {{ formatDate(summary.portfolio.record_date) }}
          </template>
          <template v-else>Belum ada snapshot portfolio</template>
        </p>

        <div class="relative mt-6 flex gap-3">
          <router-link to="/pemasukan" class="btn-primary flex-1 text-center">+ Pemasukan</router-link>
          <router-link to="/portfolio" class="btn-ghost flex-1 text-center">Update</router-link>
        </div>
      </section>

      <!-- Income row -->
      <section>
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-medium text-pearl">Pemasukan</h2>
          <span class="text-xs text-mist">bulan berjalan</span>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div class="stat-tile">
            <p class="label-caps">Hari ini</p>
            <p class="money-display-sm mt-2">{{ formatRupiah(summary.today_income) }}</p>
          </div>
          <div class="stat-tile">
            <p class="label-caps">Bulan ini</p>
            <p class="money-display-sm mt-2">{{ formatRupiah(summary.month_income) }}</p>
          </div>
        </div>
      </section>

      <!-- Investment flow -->
      <section>
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-medium text-pearl">Aliran investasi</h2>
        </div>
        <div class="glass divide-y divide-ink-200 overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4">
            <div class="flex items-center gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-credit-dim/10 text-credit">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 19V5M7 10l5-5 5 5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
              <div>
                <p class="text-xs text-mist">Total deposit</p>
                <p class="font-medium text-pearl">{{ formatRupiah(summary.total_deposits) }}</p>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-between px-5 py-4">
            <div class="flex items-center gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-debit-dim/10 text-debit">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 5v14M7 14l5 5 5-5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
              <div>
                <p class="text-xs text-mist">Total penarikan</p>
                <p class="font-medium text-pearl">{{ formatRupiah(summary.total_withdrawals) }}</p>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-between bg-ink px-5 py-4">
            <p class="text-sm text-mist">Net investasi</p>
            <p
              class="font-display text-xl"
              :class="summary.net_investment >= 0 ? 'text-credit' : 'text-debit'"
            >
              {{ formatRupiah(summary.net_investment) }}
            </p>
          </div>
        </div>
      </section>
    </template>

    <AlertBanner :message="error" type="error" />
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { formatDate, formatRupiah } from "../utils/format";
import AlertBanner from "../components/AlertBanner.vue";

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
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal memuat ringkasan");
    summary.value = data.data;
  } catch (err) {
    error.value = err.message || "Gagal memuat ringkasan";
  } finally {
    loading.value = false;
  }
}

onMounted(loadSummary);
</script>
