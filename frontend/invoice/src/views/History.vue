<template>
  <div class="space-y-3 pb-4">
    <input v-model="month" class="field-input w-full" type="month" @change="loadList" />

    <PageLoader v-if="loading" />

    <EmptyState
      v-else-if="!invoices.length"
      title="Belum ada riwayat"
      subtitle="Invoice lunas atau dibatalkan untuk bulan ini belum ada."
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
import { currentMonth, formatDate, formatRupiah } from "../utils/format";
import { invoiceStatusChipClass, invoiceStatusLabel } from "../utils/invoiceStatus";

const loading = ref(true);
const month = ref(currentMonth());
const invoices = ref([]);

async function loadList() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      month: month.value,
      status: "completed",
    });
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
