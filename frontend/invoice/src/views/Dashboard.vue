<template>
  <div class="space-y-5 pb-6">
    <PageLoader v-if="loading" />

    <template v-else>
      <div class="grid grid-cols-2 gap-3">
        <div class="stat-tile">
          <p class="label-caps">Total Invoice</p>
          <p class="money-display-sm mt-2 text-ledger-dim">{{ summary.total }}</p>
        </div>
        <div class="stat-tile">
          <p class="label-caps">Bulan Ini</p>
          <p class="money-display-sm mt-2">{{ summary.month_count }}</p>
        </div>
        <div class="stat-tile">
          <p class="label-caps">Sudah Dibayar</p>
          <p class="money-display-sm mt-2 text-credit-dim">Rp {{ formatRupiah(summary.paid_amount) }}</p>
        </div>
        <div class="stat-tile">
          <p class="label-caps">Belum Dibayar</p>
          <p class="money-display-sm mt-2 text-debit-dim">Rp {{ formatRupiah(summary.unpaid_amount) }}</p>
        </div>
        <div class="stat-tile col-span-2">
          <p class="label-caps">Lewat Jatuh Tempo</p>
          <div class="mt-2 flex items-end justify-between gap-3">
            <p class="money-display-sm text-debit-dim">{{ summary.overdue_count || 0 }}</p>
            <p class="text-sm font-semibold text-debit-dim">
              Rp {{ formatRupiah(summary.overdue_amount) }}
            </p>
          </div>
        </div>
      </div>

      <div class="flex gap-3">
        <router-link to="/buat" class="btn-primary flex-1">Buat Invoice</router-link>
        <router-link to="/riwayat" class="btn-ghost flex-1">Riwayat</router-link>
      </div>

      <section v-if="summary.overdue?.length" class="glass-strong border border-debit-dim/20 p-5">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 class="section-title text-debit-dim">Lewat Jatuh Tempo</h2>
          <span class="chip chip-out">{{ summary.overdue.length }}</span>
        </div>

        <div class="space-y-3">
          <router-link
            v-for="inv in summary.overdue"
            :key="inv.id"
            :to="`/detail/${inv.id}`"
            class="flex items-center justify-between rounded-2xl border border-debit-dim/15 bg-debit-light/40 px-4 py-3 transition hover:border-debit-dim/30"
          >
            <div class="min-w-0">
              <p class="text-sm font-bold text-pearl">{{ inv.invoice_number }}</p>
              <p v-if="inv.title" class="truncate text-sm font-semibold text-pearl">{{ inv.title }}</p>
              <p class="text-sm text-mist">{{ inv.customer_name }}</p>
              <p class="mt-0.5 text-xs text-debit-dim">
                Jatuh tempo {{ formatDate(inv.due_date) }}
              </p>
            </div>
            <div class="shrink-0 text-right">
              <p class="text-sm font-bold text-pearl">Rp {{ formatRupiah(inv.total) }}</p>
              <span class="chip chip-out">Terlambat</span>
            </div>
          </router-link>
        </div>
      </section>

      <section class="glass-strong p-5">
        <h2 class="section-title mb-4">Invoice Terbaru</h2>

        <EmptyState
          v-if="!summary.recent?.length"
          title="Belum ada invoice"
          subtitle="Buat invoice pertama Anda sekarang."
        />

        <div v-else class="space-y-3">
          <router-link
            v-for="inv in summary.recent"
            :key="inv.id"
            :to="`/detail/${inv.id}`"
            class="flex items-center justify-between rounded-2xl border border-ink-200 bg-ink-50 px-4 py-3 transition hover:border-ledger/30"
          >
            <div>
              <p class="text-sm font-bold text-pearl">{{ inv.invoice_number }}</p>
              <p v-if="inv.title" class="text-sm font-semibold text-pearl">{{ inv.title }}</p>
              <p class="text-sm text-mist">{{ inv.customer_name }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-bold text-pearl">Rp {{ formatRupiah(inv.total) }}</p>
              <span class="chip" :class="invoiceStatusChipClass(inv)">
                {{ invoiceStatusLabel(inv) }}
              </span>
            </div>
          </router-link>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import PageLoader from "../components/PageLoader.vue";
import EmptyState from "../components/EmptyState.vue";
import { formatDate, formatRupiah } from "../utils/format";
import { invoiceStatusChipClass, invoiceStatusLabel } from "../utils/invoiceStatus";

const loading = ref(true);
const summary = ref({
  total: 0,
  paid_amount: 0,
  unpaid_amount: 0,
  overdue_count: 0,
  overdue_amount: 0,
  month_count: 0,
  overdue: [],
  recent: [],
});

async function loadSummary() {
  loading.value = true;
  try {
    const res = await fetch("/api/Invoice/Dashboard/summary");
    const data = await res.json();
    if (res.ok && data.status) {
      summary.value = {
        ...summary.value,
        ...data.data,
      };
    }
  } catch {
    /* ignore */
  } finally {
    loading.value = false;
  }
}

onMounted(loadSummary);
</script>
