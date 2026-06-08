<template>
  <div class="space-y-4">
    <div class="flex items-end justify-between gap-3">
      <div>
        <p class="label-caps">Periode</p>
        <p class="mt-1 text-sm font-semibold text-pearl">{{ monthLabel }}</p>
      </div>
      <input v-model="month" class="field-input !w-auto !py-2 !text-xs" type="month" @change="onMonthChange" />
    </div>

    <div v-if="loading" class="space-y-3">
      <div class="skeleton h-28" />
      <div class="skeleton h-40" />
    </div>

    <template v-else>
      <!-- Net utama -->
      <section class="glass-strong p-4">
        <p class="label-caps">Net</p>
        <p class="money-display mt-2 text-[1.75rem]" :class="netClass(recap.net)">
          {{ formatGainLoss(recap.net) ?? formatRupiah(0) }}
        </p>
        <p class="mt-1 text-xs text-mist">Income − Expense</p>

        <div class="mt-3 grid grid-cols-2 gap-2.5">
          <div class="rounded-xl border border-credit-dim/20 bg-credit-light/50 px-3 py-2.5">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-credit-dim">Income</p>
            <p class="mt-1 text-sm font-bold text-pearl">{{ formatRupiah(recap.total_income) }}</p>
          </div>
          <div class="rounded-xl border border-debit-dim/20 bg-debit-light/50 px-3 py-2.5">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-debit-dim">Expense</p>
            <p class="mt-1 text-sm font-bold text-debit-dim">{{ formatRupiah(recap.total_expense) }}</p>
          </div>
        </div>
      </section>

      <!-- Filter gabungan sumber -->
      <section class="glass p-4">
        <div class="mb-2 flex items-center justify-between gap-2">
          <div>
            <p class="label-caps">Gabungan sumber</p>
            <p class="mt-0.5 text-xs text-mist">Tap beberapa sumber untuk lihat total gabungan</p>
          </div>
          <button
            v-if="selectedIds.length > 0"
            class="text-[10px] font-semibold text-ledger-dim"
            type="button"
            @click="clearSelection"
          >
            Reset
          </button>
        </div>

        <div v-if="recap.income_by_source.length === 0" class="rounded-xl border border-dashed border-ink-200 px-3 py-5 text-center text-sm text-mist">
          Belum ada pemasukan di bulan ini.
        </div>

        <div v-else class="flex flex-wrap gap-2">
          <button
            v-for="src in recap.income_by_source"
            :key="src.source_id ?? 'none'"
            type="button"
            class="rounded-xl border px-3 py-2 text-left text-xs font-semibold transition"
            :class="isSelected(src.source_id)
              ? 'border-ledger bg-ledger/10 text-ledger-dim'
              : 'border-ink-200 bg-ink-50 text-pearl hover:border-ledger/30'"
            @click="toggleSource(src.source_id)"
          >
            {{ src.source_name }}
          </button>
        </div>

        <div v-if="recap.income_by_source.length > 0" class="mt-3 rounded-xl border border-ink-200 bg-ink-50 px-3 py-2.5">
          <p class="text-[10px] text-mist">
            {{ selectedIds.length > 0 ? `Gabungan ${selectedIds.length} sumber` : "Semua sumber" }}
          </p>
          <p class="money-display-sm mt-0.5 text-lg">{{ formatRupiah(filteredIncome) }}</p>
          <p v-if="selectedIds.length > 0" class="mt-1 text-xs text-mist">
            Net gabungan: <span :class="netClass(filteredNet)" class="font-semibold">{{ formatGainLoss(filteredNet) }}</span>
          </p>
        </div>
      </section>

      <!-- Breakdown per sumber -->
      <section>
        <p class="label-caps mb-2">Income per sumber</p>

        <ul v-if="recap.income_by_source.length > 0" class="space-y-2">
          <li
            v-for="src in recap.income_by_source"
            :key="src.source_id ?? 'none'"
            class="glass flex items-center justify-between gap-3 px-4 py-3"
          >
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-pearl">{{ src.source_name }}</p>
              <p v-if="recap.total_income > 0" class="text-[10px] text-mist">
                {{ sharePct(src.total) }}% dari total income
              </p>
            </div>
            <p class="shrink-0 text-sm font-bold text-pearl">{{ formatRupiah(src.total) }}</p>
          </li>
        </ul>

        <EmptyState
          v-else
          title="Belum ada data"
          subtitle="Input pemasukan dulu untuk melihat rekap."
        />
      </section>
    </template>

    <AlertBanner :message="error" type="error" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { currentMonth, formatGainLoss, formatRupiah } from "../utils/format";
import AlertBanner from "../components/AlertBanner.vue";
import EmptyState from "../components/EmptyState.vue";

const month = ref(currentMonth());
const loading = ref(true);
const error = ref("");
const selectedIds = ref([]);
const recap = ref({
  total_income: 0,
  total_expense: 0,
  net: 0,
  income_by_source: [],
  filtered_income: 0,
  filtered_net: 0,
});

const monthLabel = computed(() => {
  const [y, m] = month.value.split("-");
  const date = new Date(Number(y), Number(m) - 1, 1);
  return new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(date);
});

const filteredIncome = computed(() => {
  if (selectedIds.value.length === 0) return recap.value.total_income;
  return recap.value.income_by_source
    .filter((s) => s.source_id && selectedIds.value.includes(s.source_id))
    .reduce((sum, s) => sum + Number(s.total), 0);
});

const filteredNet = computed(() => filteredIncome.value - recap.value.total_expense);

function isSelected(id) {
  return id && selectedIds.value.includes(id);
}

function toggleSource(id) {
  if (!id) return;
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((x) => x !== id);
  } else {
    selectedIds.value = [...selectedIds.value, id];
  }
}

function clearSelection() {
  selectedIds.value = [];
}

function sharePct(amount) {
  if (!recap.value.total_income) return "0";
  return ((Number(amount) / recap.value.total_income) * 100).toFixed(1);
}

function netClass(value) {
  const n = Number(value);
  if (n > 0) return "text-credit-dim";
  if (n < 0) return "text-debit-dim";
  return "text-pearl";
}

function onMonthChange() {
  selectedIds.value = [];
  loadRecap();
}

async function loadRecap() {
  loading.value = true;
  error.value = "";

  try {
    const res = await fetch(`/api/Investasi/Recap/summary?month=${month.value}`);
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal memuat rekap");

    recap.value = data.data;
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

onMounted(loadRecap);
</script>
