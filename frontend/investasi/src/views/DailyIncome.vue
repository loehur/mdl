<template>
  <div class="space-y-6">
    <!-- Form panel -->
    <section class="glass-strong p-6">
      <div class="mb-6 flex items-start justify-between">
        <div>
          <p class="label-caps">{{ editingId ? "Edit entri" : "Entri baru" }}</p>
          <h2 class="section-title mt-1">Pemasukan harian</h2>
        </div>
        <span class="chip-in">Inflow</span>
      </div>

      <form class="space-y-4" @submit.prevent="submitForm">
        <div>
          <label class="field-label">Tanggal</label>
          <input v-model="form.record_date" class="field-input" type="date" required />
        </div>
        <div>
          <label class="field-label">Jumlah (Rp)</label>
          <input
            v-model="form.amount"
            class="field-input-lg"
            type="number"
            min="1"
            step="1"
            inputmode="numeric"
            placeholder="0"
            required
          />
        </div>
        <div>
          <label class="field-label">Catatan <span class="text-mist/60">(opsional)</span></label>
          <input v-model="form.note" class="field-input" type="text" placeholder="Sumber pemasukan..." />
        </div>

        <div class="flex gap-3 pt-1">
          <button class="btn-primary flex-1" type="submit" :disabled="saving">
            {{ saving ? "Menyimpan..." : editingId ? "Perbarui" : "Simpan" }}
          </button>
          <button v-if="editingId" class="btn-ghost" type="button" @click="resetForm">Batal</button>
        </div>
      </form>

      <AlertBanner class="mt-4" :message="message" :type="isError ? 'error' : 'success'" />
    </section>

    <!-- History -->
    <section>
      <div class="mb-4 flex items-end justify-between gap-3">
        <div>
          <p class="label-caps">Riwayat</p>
          <p class="money-display-sm mt-1">{{ formatRupiah(total) }}</p>
        </div>
        <input v-model="month" class="field-input !w-auto !py-2 !text-xs" type="month" @change="loadItems" />
      </div>

      <div v-if="loading" class="space-y-3">
        <div v-for="n in 3" :key="n" class="skeleton h-20" />
      </div>

      <EmptyState
        v-else-if="items.length === 0"
        title="Belum ada pemasukan"
        subtitle="Catat pemasukan harian pertama Anda di form di atas."
      />

      <ul v-else class="space-y-3">
        <li
          v-for="item in items"
          :key="item.id"
          class="glass group flex items-start justify-between gap-4 p-4 transition hover:border-ledger/20"
        >
          <div class="min-w-0 flex-1">
            <p class="money-display-sm">{{ formatRupiah(item.amount) }}</p>
            <p class="mt-1 text-xs text-mist">{{ formatDate(item.record_date) }}</p>
            <p v-if="item.note" class="mt-2 truncate text-sm text-pearl/60">{{ item.note }}</p>
          </div>
          <div class="flex shrink-0 gap-1 opacity-60 transition group-hover:opacity-100">
            <button class="btn-icon !h-8 !w-8" title="Edit" @click="startEdit(item)">
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
            <button class="btn-icon !h-8 !w-8 hover:border-debit-dim/30 hover:text-debit" title="Hapus" @click="removeItem(item.id)">
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { currentMonth, formatDate, formatRupiah, todayISO } from "../utils/format";
import AlertBanner from "../components/AlertBanner.vue";
import EmptyState from "../components/EmptyState.vue";

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
    isError.value = false;
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
  window.scrollTo({ top: 0, behavior: "smooth" });
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
