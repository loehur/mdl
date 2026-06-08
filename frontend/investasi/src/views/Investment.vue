<template>
  <div class="space-y-4">
    <section class="card p-4">
      <h2 class="mb-4 text-sm font-semibold text-slate-900">Catat Transaksi</h2>
      <form class="space-y-3" @submit.prevent="submitForm">
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            class="rounded-xl border px-3 py-3 text-sm font-semibold transition"
            :class="form.movement_type === 'deposit' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-600'"
            @click="form.movement_type = 'deposit'"
          >
            Deposit
          </button>
          <button
            type="button"
            class="rounded-xl border px-3 py-3 text-sm font-semibold transition"
            :class="form.movement_type === 'withdrawal' ? 'border-red-500 bg-red-50 text-red-700' : 'border-slate-200 text-slate-600'"
            @click="form.movement_type = 'withdrawal'"
          >
            Penarikan
          </button>
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal</label>
          <input v-model="form.record_date" class="input" type="date" required />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah (Rp)</label>
          <input v-model="form.amount" class="input" type="number" min="1" step="1" required />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</label>
          <input v-model="form.note" class="input" type="text" placeholder="Opsional" />
        </div>
        <button class="btn-primary w-full" type="submit" :disabled="saving">
          {{ saving ? "Menyimpan..." : "Simpan Transaksi" }}
        </button>
      </form>
      <p v-if="message" class="mt-3 rounded-xl px-4 py-3 text-sm" :class="isError ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'">
        {{ message }}
      </p>
    </section>

    <section class="card p-4">
      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-900">Riwayat Bulan Ini</h2>
          <p class="text-xs text-slate-500">
            Net: {{ formatRupiah(net) }}
          </p>
        </div>
        <input v-model="month" class="input !w-auto !py-2" type="month" @change="loadItems" />
      </div>

      <div class="mb-4 grid grid-cols-2 gap-3">
        <div class="rounded-xl bg-emerald-50 p-3">
          <p class="text-xs text-emerald-700">Deposit</p>
          <p class="font-bold text-emerald-800">{{ formatRupiah(depositTotal) }}</p>
        </div>
        <div class="rounded-xl bg-red-50 p-3">
          <p class="text-xs text-red-700">Penarikan</p>
          <p class="font-bold text-red-800">{{ formatRupiah(withdrawalTotal) }}</p>
        </div>
      </div>

      <div v-if="loading" class="py-6 text-center text-sm text-slate-500">Memuat data...</div>
      <div v-else-if="items.length === 0" class="py-6 text-center text-sm text-slate-500">Belum ada transaksi</div>
      <ul v-else class="divide-y divide-slate-100">
        <li v-for="item in items" :key="item.id" class="flex items-start justify-between gap-3 py-3">
          <div>
            <div class="flex items-center gap-2">
              <span
                class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                :class="item.movement_type === 'deposit' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
              >
                {{ item.movement_type === 'deposit' ? 'Deposit' : 'Penarikan' }}
              </span>
              <p class="font-semibold text-slate-900">{{ formatRupiah(item.amount) }}</p>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ formatDate(item.record_date) }}</p>
            <p v-if="item.note" class="mt-1 text-xs text-slate-600">{{ item.note }}</p>
          </div>
          <button class="text-xs font-semibold text-red-600" @click="removeItem(item.id)">Hapus</button>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { currentMonth, formatDate, formatRupiah, todayISO } from "../utils/format";

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
const loading = ref(false);
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
        amount: Number(form.value.amount),
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
