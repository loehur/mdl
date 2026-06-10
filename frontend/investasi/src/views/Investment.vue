<template>
  <div class="space-y-6">
    <section class="glass-strong p-6">
      <!-- Type selector -->
      <div class="mb-5 grid grid-cols-2 gap-2 rounded-2xl border border-ink-200 bg-ink-100 p-1">
        <button
          type="button"
          class="rounded-xl py-3 text-sm font-medium transition"
          :class="form.movement_type === 'deposit'
            ? 'bg-credit-light text-credit-dim shadow-inner'
            : 'text-mist hover:text-pearl'"
          @click="form.movement_type = 'deposit'"
        >
          ↑ Deposit
        </button>
        <button
          type="button"
          class="rounded-xl py-3 text-sm font-medium transition"
          :class="form.movement_type === 'withdrawal'
            ? 'bg-debit-light text-debit-dim shadow-inner'
            : 'text-mist hover:text-pearl'"
          @click="form.movement_type = 'withdrawal'"
        >
          ↓ Penarikan
        </button>
      </div>

      <form class="space-y-4" @submit.prevent="submitForm">
        <div>
          <label class="field-label">Tanggal</label>
          <input v-model="form.record_date" class="field-input" type="date" required />
        </div>
        <div>
          <label class="field-label">Jumlah</label>
          <AmountInput v-model="form.amount" required />
        </div>
        <div>
          <label class="field-label">Catatan <span class="text-mist/60">(opsional)</span></label>
          <input v-model="form.note" class="field-input" type="text" placeholder="Reksa dana, saham, dll." />
        </div>
        <button class="btn-primary w-full" type="submit" :disabled="saving">
          {{ saving ? "Menyimpan..." : "Catat transaksi" }}
        </button>
      </form>

      <AlertBanner class="mt-4" :message="message" :type="isError ? 'error' : 'success'" />
    </section>

    <section>
      <div class="mb-4 flex items-end justify-between gap-3">
        <div>
          <p class="label-caps">Ringkasan bulan</p>
          <p
            class="mt-1 font-display text-lg font-bold tabular-nums"
            :class="net >= 0 ? 'text-credit' : 'text-debit'"
          >
            Net {{ formatRupiah(net) }}
          </p>
        </div>
        <input v-model="month" class="field-input !w-auto !py-2 !text-sm" type="month" @change="loadItems" />
      </div>

      <PageLoader v-if="loading" />

      <template v-else>
        <div class="mb-4 grid grid-cols-2 gap-3">
          <div class="stat-tile border-credit-dim/10">
            <p class="label-caps text-credit-dim/70">Deposit</p>
            <p class="mt-2 font-display text-lg font-bold tabular-nums text-credit">{{ formatRupiah(depositTotal) }}</p>
          </div>
          <div class="stat-tile border-debit-dim/10">
            <p class="label-caps text-debit-dim/70">Penarikan</p>
            <p class="mt-2 font-display text-lg font-bold tabular-nums text-debit">{{ formatRupiah(withdrawalTotal) }}</p>
          </div>
        </div>

        <EmptyState
          v-if="items.length === 0"
          title="Belum ada transaksi"
          subtitle="Catat deposit atau penarikan investasi pertama."
        />

        <ul v-else class="space-y-3">
        <li
          v-for="item in items"
          :key="item.id"
          class="glass flex items-start justify-between gap-4 p-4"
        >
          <div class="flex gap-3">
            <span
              class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold"
              :class="item.movement_type === 'deposit'
                ? 'bg-credit-dim/10 text-credit'
                : 'bg-debit-dim/10 text-debit'"
            >
              {{ item.movement_type === 'deposit' ? '↑' : '↓' }}
            </span>
            <div>
              <div class="flex items-center gap-2">
                <span :class="item.movement_type === 'deposit' ? 'chip-in' : 'chip-out'">
                  {{ item.movement_type === 'deposit' ? 'Deposit' : 'Penarikan' }}
                </span>
              </div>
              <p class="mt-2 money-display-sm">{{ formatRupiah(item.amount) }}</p>
              <p class="mt-1 text-sm text-mist">{{ formatDate(item.record_date) }}</p>
              <p v-if="item.note" class="mt-1 text-sm text-pearl/60">{{ item.note }}</p>
            </div>
          </div>
          <button
            class="btn-icon !h-8 !w-8 shrink-0 hover:border-debit-dim/30 hover:text-debit"
            @click="removeItem(item.id)"
          >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
        </li>
      </ul>
      </template>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { currentMonth, amountInputToNumber, formatDate, formatRupiah, todayISO } from "../utils/format";
import AlertBanner from "../components/AlertBanner.vue";
import AmountInput from "../components/AmountInput.vue";
import EmptyState from "../components/EmptyState.vue";
import PageLoader from "../components/PageLoader.vue";

const form = ref({
  movement_type: "deposit",
  record_date: todayISO(),
  amount: "",
  note: "",
});
const month = ref(currentMonth());
const items = ref([]);
const depositTotal = ref(0);
const withdrawalTotal = ref(0);
const net = ref(0);
const loading = ref(true);
const saving = ref(false);
const message = ref("");
const isError = ref(false);

async function loadItems() {
  loading.value = true;
  try {
    const res = await fetch(`/api/Investasi/Investment/list?month=${month.value}`);
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal memuat data");
    items.value = data.data.items;
    depositTotal.value = data.data.deposit_total;
    withdrawalTotal.value = data.data.withdrawal_total;
    net.value = data.data.net;
  } catch (err) {
    message.value = err.message;
    isError.value = true;
  } finally {
    loading.value = false;
  }
}

async function submitForm() {
  saving.value = true;
  message.value = "";
  isError.value = false;

  try {
    const res = await fetch("/api/Investasi/Investment/add", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        movement_type: form.value.movement_type,
        record_date: form.value.record_date,
        amount: amountInputToNumber(form.value.amount),
        note: form.value.note || null,
      }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal menyimpan");

    message.value = data.message || "Berhasil disimpan";
    form.value = { ...form.value, amount: "", note: "" };
    await loadItems();
  } catch (err) {
    message.value = err.message;
    isError.value = true;
  } finally {
    saving.value = false;
  }
}

async function removeItem(id) {
  if (!confirm("Hapus transaksi ini?")) return;
  try {
    const res = await fetch("/api/Investasi/Investment/delete", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal menghapus");
    await loadItems();
  } catch (err) {
    message.value = err.message;
    isError.value = true;
  }
}

onMounted(loadItems);
</script>
