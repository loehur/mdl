<template>
  <div class="space-y-3 pb-4">
    <div class="flex gap-2">
      <button
        type="button"
        class="flex-1 rounded-xl border px-3 py-2.5 text-xs font-bold tracking-wide transition"
        :class="
          filter === 'current'
            ? 'border-ledger/35 bg-ledger/10 text-ledger-dim'
            : 'border-ink-200 bg-ink-50 text-mist'
        "
        @click="setFilter('current')"
      >
        Belum Bayar
      </button>
      <button
        type="button"
        class="flex-1 rounded-xl border px-3 py-2.5 text-xs font-bold tracking-wide transition"
        :class="
          filter === 'overdue'
            ? 'border-debit-dim/35 bg-debit-light text-debit-dim'
            : 'border-ink-200 bg-ink-50 text-mist'
        "
        @click="setFilter('overdue')"
      >
        Telat Bayar
      </button>
    </div>

    <PageLoader v-if="loading" />

    <EmptyState
      v-else-if="!invoices.length"
      :title="emptyTitle"
      :subtitle="emptySubtitle"
    />

    <div v-else class="space-y-3">
      <router-link
        v-for="inv in invoices"
        :key="inv.id"
        :to="`/detail/${inv.id}`"
        class="glass block p-3 transition hover:border-ledger/30"
        :class="filter === 'overdue' ? 'border-debit-dim/15' : ''"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-bold text-pearl">{{ inv.invoice_number }}</p>
            <p v-if="inv.title" class="truncate text-sm font-semibold text-pearl">{{ inv.title }}</p>
            <p class="text-sm text-mist">{{ inv.customer_name }}</p>
            <p
              class="mt-1 text-xs"
              :class="filter === 'overdue' ? 'text-debit-dim' : 'text-mist'"
            >
              Jatuh tempo {{ inv.due_date ? formatDate(inv.due_date) : "—" }}
            </p>
          </div>
          <div class="shrink-0 text-right">
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
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import PageLoader from "../components/PageLoader.vue";
import EmptyState from "../components/EmptyState.vue";
import { formatDate, formatRupiah } from "../utils/format";
import { invoiceStatusChipClass, invoiceStatusLabel } from "../utils/invoiceStatus";

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const invoices = ref([]);
const filter = ref(normalizeFilter(route.query.filter));

function normalizeFilter(value) {
  return value === "overdue" || value === "telat" ? "overdue" : "current";
}

const emptyTitle = computed(() =>
  filter.value === "overdue" ? "Tidak ada yang telat" : "Tidak ada tagihan berjalan"
);

const emptySubtitle = computed(() =>
  filter.value === "overdue"
    ? "Semua invoice belum bayar masih dalam jatuh tempo."
    : "Belum ada invoice yang menunggu pembayaran."
);

function setFilter(next) {
  const value = normalizeFilter(next);
  router.replace({
    path: "/berjalan",
    query: { filter: value === "overdue" ? "telat" : "belum" },
  });
}

async function loadList() {
  loading.value = true;
  try {
    const status = filter.value === "overdue" ? "overdue" : "current";
    const params = new URLSearchParams({ status, all: "1" });
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

watch(
  () => route.query.filter,
  (value) => {
    filter.value = normalizeFilter(value);
    loadList();
  },
  { immediate: true }
);
</script>
