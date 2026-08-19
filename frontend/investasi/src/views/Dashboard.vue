<template>
  <div class="space-y-4">
    <PageLoader v-if="loading" />

    <template v-else>
      <!-- Portfolio vs modal investasi -->
      <section class="glass-strong relative overflow-hidden p-4 text-center shadow-glow">
        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-ledger/10 blur-2xl" />
        <p class="relative">
          <span class="label-caps">Portfolio</span>
          <span
            v-if="summary.portfolio?.record_date"
            class="text-xs font-normal normal-case tracking-normal text-mist"
          >
            (Snapshot {{ snapshotDateLabel }})
          </span>
        </p>
        <router-link
          to="/portfolio"
          class="money-display relative mt-2 block text-[1.75rem] text-ledger-dim transition active:opacity-80"
        >
          {{ summary.portfolio_amount !== null ? formatRupiah(summary.portfolio_amount) : '—' }}
        </router-link>
        <p class="relative mt-1.5 text-sm leading-snug text-mist">
          Modal: <span class="font-semibold text-pearl">{{ formatRupiah(summary.net_investment) }}</span>
        </p>

        <GainLossBadge
          v-if="summary.portfolio_amount !== null && summary.gain_loss !== null"
          class="relative mx-auto mt-3 max-w-sm"
          :gain-loss="summary.gain_loss"
          :gain-loss-pct="summary.gain_loss_pct"
          :status="summary.status"
        />

        <p
          v-else-if="summary.portfolio_amount === null && summary.net_investment > 0"
          class="relative mt-3 rounded-2xl border border-ink-200 bg-ink-100 px-3 py-2.5 text-sm text-mist"
        >
          Input deposit sudah ada. Update snapshot portfolio untuk melihat tumbuh/rugi.
        </p>
      </section>

      <!-- Deposit & penarikan manual -->
      <section>
        <div class="mb-2 flex items-center justify-between">
          <h2 class="text-base font-bold text-pearl">Modal investasi</h2>
          <router-link to="/investasi" class="text-sm font-semibold text-ledger-dim">Kelola →</router-link>
        </div>
        <div class="glass divide-y divide-ink-200 overflow-hidden">
          <div class="grid grid-cols-2 divide-x divide-ink-200">
            <div class="px-4 py-3 text-center">
              <p class="text-sm text-mist">Total deposit</p>
              <p class="font-semibold text-credit-dim">{{ formatRupiah(summary.total_deposits) }}</p>
            </div>
            <div class="px-4 py-3 text-center">
              <p class="text-sm text-mist">Total penarikan</p>
              <p class="font-semibold text-debit-dim">{{ formatRupiah(summary.total_withdrawals) }}</p>
            </div>
          </div>
          <div class="flex items-center justify-between bg-ink px-4 py-3">
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
import { computed, onMounted, ref } from "vue";
import { formatRupiah } from "../utils/format";
import AlertBanner from "../components/AlertBanner.vue";
import GainLossBadge from "../components/GainLossBadge.vue";
import PageLoader from "../components/PageLoader.vue";

const loading = ref(true);
const error = ref("");
const summary = ref({
  total_deposits: 0,
  total_withdrawals: 0,
  net_investment: 0,
  portfolio_amount: null,
  portfolio: null,
  gain_loss: null,
  gain_loss_pct: null,
  status: null,
});

const snapshotDateLabel = computed(() => {
  const dateStr = summary.value.portfolio?.record_date;
  if (!dateStr) return "";
  const date = new Date(`${dateStr}T00:00:00`);
  return new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(date);
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
