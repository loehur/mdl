<template>
  <div class="space-y-4">
    <section class="card overflow-hidden">
      <div class="bg-gradient-to-br from-slate-800 to-slate-900 p-5 text-white">
        <p class="text-sm text-slate-300">Nilai Portfolio Saat Ini</p>
        <p class="mt-1 text-3xl font-bold">{{ formatRupiah(current?.amount) }}</p>
        <p v-if="current?.record_date" class="mt-1 text-xs text-slate-400">
          Diperbarui {{ formatDate(current.record_date) }}
        </p>
        <p v-else class="mt-1 text-xs text-slate-400">Belum ada update portfolio</p>
      </div>
    </section>

    <section class="card p-4">
      <h2 class="mb-4 text-sm font-semibold text-slate-900">Update Portfolio</h2>
      <form class="space-y-3" @submit.prevent="submitForm">
        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal</label>
          <input v-model="form.record_date" class="input" type="date" required />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nilai Portfolio (Rp)</label>
          <input v-model="form.amount" class="input" type="number" min="1" step="1" placeholder="100000000" required />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</label>
          <input v-model="form.note" class="input" type="text" placeholder="Contoh: rebalancing, update harga pasar" />
        </div>
        <button class="btn-primary w-full" type="submit" :disabled="saving">
          {{ saving ? "Menyimpan..." : "Simpan Update" }}
        </button>
      </form>
      <p v-if="message" class="mt-3 rounded-xl px-4 py-3 text-sm" :class="isError ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'">
        {{ message }}
      </p>
    </section>

    <section class="card p-4">
      <h2 class="mb-4 text-sm font-semibold text-slate-900">Riwayat Update</h2>
      <div v-if="loading" class="py-6 text-center text-sm text-slate-500">Memuat data...</div>
      <div v-else-if="history.length === 0" class="py-6 text-center text-sm text-slate-500">Belum ada riwayat</div>
      <ul v-else class="divide-y divide-slate-100">
        <li v-for="item in history" :key="item.id" class="py-3">
          <p class="font-semibold text-slate-900">{{ formatRupiah(item.amount) }}</p>
          <p class="text-xs text-slate-500">{{ formatDate(item.record_date) }}</p>
          <p v-if="item.note" class="mt-1 text-xs text-slate-600">{{ item.note }}</p>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { formatDate, formatRupiah, todayISO } from "../utils/format";

const form = ref({ record_date: todayISO(), amount: "", note: "" });
const current = ref(null);
const history = ref([]);
const loading = ref(false);
const saving = ref(false);
const message = ref("");
const isError = ref(false);

async function loadData() {
  loading.value = true;
  try {
    const [currentRes, historyRes] = await Promise.all([
      fetch("/api/Investasi/Portfolio/current"),
      fetch("/api/Investasi/Portfolio/history"),
    ]);
    const currentData = await currentRes.json();
    const historyData = await historyRes.json();

    if (!currentRes.ok || !currentData.status) {
      throw new Error(currentData.message || "Gagal memuat portfolio");
    }
    if (!historyRes.ok || !historyData.status) {
      throw new Error(historyData.message || "Gagal memuat riwayat");
    }

    current.value = currentData.data.current;
    history.value = historyData.data.items;

    if (current.value?.amount) {
      form.value.amount = current.value.amount;
    }
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
    const res = await fetch("/api/Investasi/Portfolio/update", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        record_date: form.value.record_date,
        amount: Number(form.value.amount),
        note: form.value.note || null,
      }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal menyimpan");

    message.value = data.message || "Portfolio berhasil diperbarui";
    form.value.note = "";
    await loadData();
  } catch (err) {
    message.value = err.message;
    isError.value = true;
  } finally {
    saving.value = false;
  }
}

onMounted(loadData);
</script>
