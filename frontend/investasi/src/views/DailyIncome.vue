<template>
  <div class="space-y-4">
    <section class="card p-4">
      <h2 class="mb-4 text-sm font-semibold text-slate-900">Tambah Pemasukan</h2>
      <form class="space-y-3" @submit.prevent="submitForm">
        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal</label>
          <input v-model="form.record_date" class="input" type="date" required />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah (Rp)</label>
          <input v-model="form.amount" class="input" type="number" min="1" step="1" placeholder="500000" required />
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</label>
          <input v-model="form.note" class="input" type="text" placeholder="Opsional" />
        </div>
        <button class="btn-primary w-full" type="submit" :disabled="saving">
          {{ saving ? "Menyimpan..." : editingId ? "Perbarui" : "Simpan Pemasukan" }}
        </button>
        <button v-if="editingId" class="btn-secondary w-full" type="button" @click="resetForm">Batal Edit</button>
      </form>
      <p v-if="message" class="mt-3 rounded-xl px-4 py-3 text-sm" :class="isError ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'">
        {{ message }}
      </p>
    </section>

    <section class="card p-4">
      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-900">Riwayat Bulan Ini</h2>
          <p class="text-xs text-slate-500">Total: {{ formatRupiah(total) }}</p>
        </div>
        <input v-model="month" class="input !w-auto !py-2" type="month" @change="loadItems" />
      </div>

      <div v-if="loading" class="py-6 text-center text-sm text-slate-500">Memuat data...</div>
      <div v-else-if="items.length === 0" class="py-6 text-center text-sm text-slate-500">Belum ada pemasukan</div>
      <ul v-else class="divide-y divide-slate-100">
        <li v-for="item in items" :key="item.id" class="flex items-start justify-between gap-3 py-3">
          <div>
            <p class="font-semibold text-slate-900">{{ formatRupiah(item.amount) }}</p>
            <p class="text-xs text-slate-500">{{ formatDate(item.record_date) }}</p>
            <p v-if="item.note" class="mt-1 text-xs text-slate-600">{{ item.note }}</p>
          </div>
          <div class="flex gap-2">
            <button class="text-xs font-semibold text-emerald-700" @click="startEdit(item)">Edit</button>
            <button class="text-xs font-semibold text-red-600" @click="removeItem(item.id)">Hapus</button>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { currentMonth, formatDate, formatRupiah, todayISO } from "../utils/format";

const form = ref({ record_date: todayISO(), amount: "", note: "" });
const editingId = ref(null);
const month = ref(currentMonth());
const items = ref([]);
const total = ref(0);
const loading = ref(false);
const saving = ref(false);
const message = ref("");
const isError = ref(false);

function resetForm() {
  editingId.value = null;
  form.value = { record_date: todayISO(), amount: "", note: "" };
}

async function loadItems() {
  loading.value = true;
  try {
    const res = await fetch(`/api/Investasi/DailyIncome/list?month=${month.value}`);
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal memuat data");
    items.value = data.data.items;
    total.value = data.data.total;
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

  const payload = {
    record_date: form.value.record_date,
    amount: Number(form.value.amount),
    note: form.value.note || null,
  };

  const endpoint = editingId.value
    ? "/api/Investasi/DailyIncome/update"
    : "/api/Investasi/DailyIncome/add";

  if (editingId.value) payload.id = editingId.value;

  try {
    const res = await fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal menyimpan");

    message.value = data.message || "Berhasil disimpan";
    resetForm();
    await loadItems();
  } catch (err) {
    message.value = err.message;
    isError.value = true;
  } finally {
    saving.value = false;
  }
}

function startEdit(item) {
  editingId.value = item.id;
  form.value = {
    record_date: item.record_date,
    amount: item.amount,
    note: item.note || "",
  };
}

async function removeItem(id) {
  if (!confirm("Hapus pemasukan ini?")) return;
  try {
    const res = await fetch("/api/Investasi/DailyIncome/delete", {
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
