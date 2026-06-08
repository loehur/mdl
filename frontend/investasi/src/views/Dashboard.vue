<template>
  <div class="space-y-6">
    <div v-if="loading" class="space-y-4">
      <div class="grid grid-cols-2 gap-3">
        <div class="skeleton h-24" />
        <div class="skeleton h-24" />
      </div>
      <div class="skeleton h-48" />
      <div class="skeleton h-44" />
    </div>

    <template v-else>
      <!-- Pemasukan — modul terpisah, tidak masuk portfolio -->
      <section>
        <div class="mb-3 flex items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-bold text-pearl">Pemasukan</h2>
            <p class="mt-0.5 text-xs text-mist">Catatan pendapatan harian — tidak mempengaruhi portfolio</p>
          </div>
          <router-link to="/pemasukan" class="btn-primary !px-4 !py-2 text-xs">+ Input</router-link>
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

      <!-- Portfolio vs modal investasi -->
      <section class="glass-strong relative overflow-hidden p-6 shadow-glow">
        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-ledger/10 blur-2xl" />
        <p class="label-caps relative">Portfolio</p>
        <p class="money-display relative mt-3 text-ledger-dim">
          {{ formatRupiah(summary.portfolio_amount) }}
        </p>
        <p class="relative mt-2 text-sm text-mist">
          Modal investasi: <span class="font-semibold text-pearl">{{ formatRupiah(summary.net_investment) }}</span>
          <span class="text-mist/70"> (deposit − penarikan)</span>
        </p>
        <p class="relative mt-1 text-xs text-mist">
          <template v-if="summary.portfolio?.record_date">
            Snapshot {{ formatDate(summary.portfolio.record_date) }}
          </template>
          <template v-else>Belum ada snapshot — update nilai portfolio secara berkala</template>
        </p>

        <GainLossBadge
          v-if="summary.portfolio_amount !== null && summary.gain_loss !== null"
          class="relative mt-4"
          :gain-loss="summary.gain_loss"
          :gain-loss-pct="summary.gain_loss_pct"
          :status="summary.status"
          :portfolio="summary.portfolio_amount"
          :invested="summary.net_investment"
        />

        <p
          v-else-if="summary.portfolio_amount === null && summary.net_investment > 0"
          class="relative mt-4 rounded-2xl border border-ink-200 bg-ink-100 px-4 py-3 text-xs text-mist"
        >
          Input deposit sudah ada. Update snapshot portfolio untuk melihat tumbuh/rugi.
        </p>

        <router-link to="/portfolio" class="btn-ghost relative mt-5 block w-full text-center">
          Update portfolio
        </router-link>
      </section>

      <!-- Deposit & penarikan manual -->
      <section>
        <div class="mb-3 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-bold text-pearl">Modal investasi</h2>
            <p class="mt-0.5 text-xs text-mist">Input deposit & penarikan secara manual</p>
          </div>
          <router-link to="/investasi" class="text-xs font-semibold text-ledger-dim">Kelola →</router-link>
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
                <p class="font-semibold text-pearl">{{ formatRupiah(summary.total_deposits) }}</p>
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
                <p class="font-semibold text-pearl">{{ formatRupiah(summary.total_withdrawals) }}</p>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-between bg-ink px-5 py-4">
            <p class="text-sm font-medium text-mist">Modal bersih</p>
            <p class="money-display-sm text-pearl">{{ formatRupiah(summary.net_investment) }}</p>
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
import GainLossBadge from "../components/GainLossBadge.vue";

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
  gain_loss: null,
  gain_loss_pct: null,
  status: null,
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
