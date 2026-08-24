<template>
  <div class="space-y-3 pb-4">
    <PageLoader v-if="loading" />

    <EmptyState
      v-else-if="!invoices.length"
      title="Belum ada riwayat"
      subtitle="Invoice yang sudah lunas atau dibatalkan akan muncul di sini."
    />

    <div v-else class="space-y-3">
      <router-link
        v-for="inv in invoices"
        :key="inv.id"
        :to="`/detail/${inv.id}`"
        class="glass block p-3 transition hover:border-ledger/30"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm font-bold text-pearl">{{ inv.invoice_number }}</p>
            <p v-if="inv.title" class="text-sm font-semibold text-pearl">{{ inv.title }}</p>
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
import { formatDate, formatRupiah } from "../utils/format";
import { invoiceStatusChipClass, invoiceStatusLabel } from "../utils/invoiceStatus";

const loading = ref(true);
const invoices = ref([]);

async function loadList() {
  loading.value = true;
  try {
    const params = new URLSearchParams({ all: "1", status: "completed" });
    const res = await fetch(`/api/Invoice/Invoices/list?${params}`);
    const data = await res.json();
    if (res.ok && data.status) {
      invoices.value = data.data.invoices || [];
    } else {
      invoices.value = [];
    }
  } catch {
    invoices.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(loadList);
</script>
