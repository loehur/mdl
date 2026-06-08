<template>
  <div class="space-y-4">
    <div class="flex items-end justify-between gap-3">
      <div>
        <p class="label-caps">Periode</p>
        <p class="mt-1 text-sm font-semibold text-pearl">{{ monthLabel }}</p>
      </div>
      <input v-model="month" class="field-input !w-auto !py-2 !text-sm" type="month" @change="onMonthChange" />
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
        <p class="mt-1 text-sm text-mist">Income − Expense</p>

        <div class="mt-3 grid grid-cols-2 gap-2.5">
          <button
            type="button"
            class="rounded-xl border px-3 py-2.5 text-left transition active:scale-[0.98]"
            :class="activeGabungan === 'income'
              ? 'border-ledger bg-ledger/10 ring-2 ring-ledger/20'
              : 'border-credit-dim/20 bg-credit-light/50 hover:border-credit-dim/40'"
            @click="setGabungan('income')"
          >
            <p class="text-sm font-semibold uppercase tracking-wide text-credit-dim">Income</p>
            <p class="mt-1 text-sm font-bold text-pearl">{{ formatRupiah(recap.total_income) }}</p>
          </button>
          <button
            type="button"
            class="rounded-xl border px-3 py-2.5 text-left transition active:scale-[0.98]"
            :class="activeGabungan === 'expense'
              ? 'border-ledger bg-ledger/10 ring-2 ring-ledger/20'
              : 'border-debit-dim/20 bg-debit-light/50 hover:border-debit-dim/40'"
            @click="setGabungan('expense')"
          >
            <p class="text-sm font-semibold uppercase tracking-wide text-debit-dim">Expense</p>
            <p class="mt-1 text-sm font-bold text-debit-dim">{{ formatRupiah(recap.total_expense) }}</p>
          </button>
        </div>
      </section>

      <!-- Gabungan Income -->
      <section v-if="activeGabungan === 'income'" class="glass p-4">
        <div class="mb-2 flex items-center justify-between gap-2">
          <div>
            <p class="label-caps">Gabungan Income</p>
            <p class="mt-0.5 text-sm text-mist">Tap beberapa sumber untuk lihat total gabungan</p>
          </div>
          <button
            v-if="selectedIncomeIds.length > 0"
            class="text-sm font-semibold text-ledger-dim"
            type="button"
            @click="clearIncomeSelection"
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
            class="rounded-xl border px-3 py-2.5 text-left text-sm font-semibold transition"
            :class="isIncomeSelected(src.source_id)
              ? 'border-ledger bg-ledger/10 text-ledger-dim'
              : 'border-ink-200 bg-ink-50 text-pearl hover:border-ledger/30'"
            @click="toggleIncomeSource(src.source_id)"
          >
            {{ src.source_name }}
          </button>
        </div>

        <div v-if="recap.income_by_source.length > 0" class="mt-3 rounded-xl border border-ink-200 bg-ink-50 px-3 py-2.5">
          <p class="text-sm text-mist">
            {{ selectedIncomeIds.length > 0 ? `Gabungan ${selectedIncomeIds.length} sumber` : "Semua sumber" }}
          </p>
          <p class="money-display-sm mt-0.5 text-lg">{{ formatRupiah(filteredIncome) }}</p>
          <p v-if="selectedIncomeIds.length > 0" class="mt-1 text-sm text-mist">
            Net gabungan:
            <span :class="netClass(filteredNetIncome)" class="font-semibold">{{ formatGainLoss(filteredNetIncome) }}</span>
          </p>
        </div>
      </section>

      <!-- Gabungan Expense -->
      <section v-else-if="activeGabungan === 'expense'" class="glass p-4">
        <div class="mb-2 flex items-center justify-between gap-2">
          <div>
            <p class="label-caps">Gabungan Expense</p>
            <p class="mt-0.5 text-sm text-mist">Tap beberapa target untuk lihat total gabungan</p>
          </div>
          <button
            v-if="selectedExpenseIds.length > 0"
            class="text-sm font-semibold text-ledger-dim"
            type="button"
            @click="clearExpenseSelection"
          >
            Reset
          </button>
        </div>

        <div v-if="recap.expense_by_target.length === 0" class="rounded-xl border border-dashed border-ink-200 px-3 py-5 text-center text-sm text-mist">
          Belum ada pengeluaran di bulan ini.
        </div>

        <div v-else class="flex flex-wrap gap-2">
          <button
            v-for="tgt in recap.expense_by_target"
            :key="tgt.target_id ?? 'none'"
            type="button"
            class="rounded-xl border px-3 py-2.5 text-left text-sm font-semibold transition"
            :class="isExpenseSelected(tgt.target_id)
              ? 'border-debit-dim bg-debit-dim/10 text-debit-dim'
              : 'border-ink-200 bg-ink-50 text-pearl hover:border-debit-dim/30'"
            @click="toggleExpenseTarget(tgt.target_id)"
          >
            {{ tgt.target_name }}
          </button>
        </div>

        <div v-if="recap.expense_by_target.length > 0" class="mt-3 rounded-xl border border-ink-200 bg-ink-50 px-3 py-2.5">
          <p class="text-sm text-mist">
            {{ selectedExpenseIds.length > 0 ? `Gabungan ${selectedExpenseIds.length} target` : "Semua target" }}
          </p>
          <p class="money-display-sm mt-0.5 text-lg text-debit-dim">{{ formatRupiah(filteredExpense) }}</p>
          <p v-if="selectedExpenseIds.length > 0" class="mt-1 text-sm text-mist">
            Net gabungan:
            <span :class="netClass(filteredNetExpense)" class="font-semibold">{{ formatGainLoss(filteredNetExpense) }}</span>
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
              <p v-if="recap.total_income > 0" class="text-sm text-mist">
                {{ sharePct(src.total, recap.total_income) }}% dari total income
              </p>
            </div>
            <p class="shrink-0 text-base font-bold text-pearl">{{ formatRupiah(src.total) }}</p>
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
const activeGabungan = ref(null);
const selectedIncomeIds = ref([]);
const selectedExpenseIds = ref([]);
const recap = ref({
  total_income: 0,
  total_expense: 0,
  net: 0,
  income_by_source: [],
  expense_by_target: [],
});

const monthLabel = computed(() => {
  const [y, m] = month.value.split("-");
  const date = new Date(Number(y), Number(m) - 1, 1);
  return new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(date);
});

const filteredIncome = computed(() => {
  if (selectedIncomeIds.value.length === 0) return recap.value.total_income;
  return recap.value.income_by_source
    .filter((s) => s.source_id && selectedIncomeIds.value.includes(s.source_id))
    .reduce((sum, s) => sum + Number(s.total), 0);
});

const filteredExpense = computed(() => {
  if (selectedExpenseIds.value.length === 0) return recap.value.total_expense;
  return recap.value.expense_by_target
    .filter((t) => t.target_id && selectedExpenseIds.value.includes(t.target_id))
    .reduce((sum, t) => sum + Number(t.total), 0);
});

const filteredNetIncome = computed(() => filteredIncome.value - recap.value.total_expense);
const filteredNetExpense = computed(() => recap.value.total_income - filteredExpense.value);

function setGabungan(type) {
  activeGabungan.value = type;
}

function isIncomeSelected(id) {
  return id && selectedIncomeIds.value.includes(id);
}

function isExpenseSelected(id) {
  return id && selectedExpenseIds.value.includes(id);
}

function toggleIncomeSource(id) {
  if (!id) return;
  if (selectedIncomeIds.value.includes(id)) {
    selectedIncomeIds.value = selectedIncomeIds.value.filter((x) => x !== id);
  } else {
    selectedIncomeIds.value = [...selectedIncomeIds.value, id];
  }
}

function toggleExpenseTarget(id) {
  if (!id) return;
  if (selectedExpenseIds.value.includes(id)) {
    selectedExpenseIds.value = selectedExpenseIds.value.filter((x) => x !== id);
  } else {
    selectedExpenseIds.value = [...selectedExpenseIds.value, id];
  }
}

function clearIncomeSelection() {
  selectedIncomeIds.value = [];
}

function clearExpenseSelection() {
  selectedExpenseIds.value = [];
}

function sharePct(amount, total) {
  if (!total) return "0";
  return ((Number(amount) / total) * 100).toFixed(1);
}

function netClass(value) {
  const n = Number(value);
  if (n > 0) return "text-credit-dim";
  if (n < 0) return "text-debit-dim";
  return "text-pearl";
}

function onMonthChange() {
  activeGabungan.value = null;
  selectedIncomeIds.value = [];
  selectedExpenseIds.value = [];
  loadRecap();
}

async function loadRecap() {
  loading.value = true;
  error.value = "";

  try {
    const res = await fetch(`/api/Investasi/Recap/summary?month=${month.value}`);
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal memuat rekap");

    recap.value = {
      ...data.data,
      expense_by_target: data.data.expense_by_target || [],
    };
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

onMounted(loadRecap);
</script>
