<template>
  <div class="page-enter space-y-5 pb-6">
    <div class="flex gap-2">
      <input v-model="month" class="field-input flex-1" type="month" @change="loadList" />
      <select v-model="statusFilter" class="field-input w-36" @change="loadList">
        <option value="">Semua</option>
        <option value="unpaid">Belum Bayar</option>
        <option value="paid">Lunas</option>
        <option value="cancelled">Dibatalkan</option>
      </select>
    </div>

    <PageLoader v-if="loading" />

    <EmptyState
      v-else-if="!invoices.length"
      title="Tidak ada invoice"
      subtitle="Invoice untuk periode ini belum dibuat."
    />

    <div v-else class="space-y-3">
      <router-link
        v-for="inv in invoices"
        :key="inv.id"
        :to="`/detail/${inv.id}`"
        class="glass block p-4 transition hover:border-ledger/30"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm font-bold text-pearl">{{ inv.invoice_number }}</p>
            <p class="text-sm text-mist">{{ inv.customer_name }}</p>
            <p class="mt-1 text-xs text-mist">{{ formatDate(inv.issue_date) }}</p>
          </div>
          <div class="text-right">
            <p class="text-sm font-bold text-pearl">Rp {{ formatRupiah(inv.total) }}</p>
            <span class="chip mt-1" :class="invoiceStatusChipClass(inv)">
              {{ invoiceStatusLabel(inv) }}
            </span>
          </div>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import PageLoader from "../components/PageLoader.vue";
import EmptyState from "../components/EmptyState.vue";
import { currentMonth, formatDate, formatRupiah } from "../utils/format";
import { invoiceStatusChipClass, invoiceStatusLabel } from "../utils/invoiceStatus";

const loading = ref(true);
const month = ref(currentMonth());
const statusFilter = ref("");
const invoices = ref([]);

async function loadList() {
  loading.value = true;
  try {
    const params = new URLSearchParams({ month: month.value });
    if (statusFilter.value) params.set("status", statusFilter.value);

    const res = await fetch(`/api/Invoice/Invoices/list?${params}`);
    const data = await res.json();
    if (res.ok && data.status) {
      invoices.value = data.data.invoices || [];
    }
  } catch {
    /* ignore */
  } finally {
    loading.value = false;
  }
}

onMounted(loadList);
</script>
