<template>
  <div class="space-y-6">

    <!-- Form input pemasukan -->
    <section class="glass-strong p-6">
      <div class="mb-6 flex items-start justify-between">
        <div>
          <p class="label-caps">{{ editingId ? "Edit entri" : "Entri baru" }}</p>
          <h2 class="section-title mt-1">Pemasukan harian</h2>
        </div>
        <span class="chip-in">Terpisah</span>
      </div>

      <form class="space-y-4" @submit.prevent="submitForm">
        <div>
          <div class="mb-2 flex items-center justify-between gap-2">
            <label class="field-label !mb-0">Sumber pemasukan <span class="text-debit">*</span></label>
            <button class="btn-ghost !px-3 !py-1.5 !text-sm" type="button" @click="showSourceManager = true">
              Kelola
            </button>
          </div>
          <div v-if="sources.length === 0" class="rounded-2xl border border-debit-dim/20 bg-debit-light px-4 py-3 text-sm text-debit-dim">
            Tambahkan sumber lewat tombol Kelola.
          </div>
          <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <button
              v-for="src in sources"
              :key="src.id"
              type="button"
              class="rounded-xl border px-3 py-3 text-left text-sm font-semibold transition"
              :class="form.source_id === src.id
                ? 'border-ledger bg-ledger/10 text-ledger-dim shadow-inner'
                : 'border-ink-200 bg-ink-50 text-pearl hover:border-ledger/30'"
              @click="form.source_id = src.id"
            >
              {{ src.name }}
            </button>
          </div>
        </div>

        <div>
          <label class="field-label">Tanggal</label>
          <input v-model="form.record_date" class="field-input" type="date" required />
        </div>
        <div>
          <label class="field-label">Jumlah</label>
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
          <input v-model="form.note" class="field-input" type="text" placeholder="Keterangan tambahan..." />
        </div>

        <div class="flex gap-3 pt-1">
          <button
            class="btn-primary flex-1"
            type="submit"
            :disabled="saving || sources.length === 0 || !form.source_id"
          >
            {{ saving ? "Menyimpan..." : editingId ? "Perbarui" : "Simpan" }}
          </button>
          <button v-if="editingId" class="btn-ghost" type="button" @click="resetForm">Batal</button>
        </div>
      </form>

      <AlertBanner class="mt-4" :message="message" :type="isError ? 'error' : 'success'" />
    </section>

    <!-- Riwayat -->
    <section>
      <div class="mb-4 flex items-end justify-between gap-3">
        <div>
          <p class="label-caps">Riwayat</p>
          <p class="money-display-sm mt-1">{{ formatRupiah(total) }}</p>
        </div>
        <input v-model="month" class="field-input !w-auto !py-2 !text-sm" type="month" @change="loadItems" />
      </div>

      <div v-if="loading" class="space-y-3">
        <div v-for="n in 3" :key="n" class="skeleton h-20" />
      </div>

      <EmptyState
        v-else-if="items.length === 0"
        title="Belum ada pemasukan"
        subtitle="Pilih sumber, isi jumlah, lalu simpan."
      />

      <ul v-else class="space-y-3">
        <li
          v-for="item in items"
          :key="item.id"
          class="glass group flex items-start justify-between gap-4 p-4 transition hover:border-ledger/20"
        >
          <div class="min-w-0 flex-1">
            <span v-if="item.source_name" class="chip-in mb-2">{{ item.source_name }}</span>
            <div class="flex items-baseline justify-between gap-3">
              <p class="text-sm text-mist">{{ formatDate(item.record_date) }}</p>
              <p class="shrink-0 font-display text-lg font-bold tabular-nums text-pearl">{{ formatRupiah(item.amount) }}</p>
            </div>
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

    <Teleport to="body">
      <div
        v-if="showSourceManager"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/40 p-4"
        @click.self="showSourceManager = false"
      >
        <section class="glass-strong w-full max-w-md max-h-[80vh] overflow-y-auto p-5 shadow-panel">
        <div class="mb-4 flex items-center justify-between gap-3">
          <div>
            <p class="label-caps">Master data</p>
            <h2 class="section-title mt-1">Sumber pemasukan</h2>
          </div>
          <button class="btn-icon" type="button" title="Tutup" @click="showSourceManager = false">✕</button>
        </div>

        <form class="mb-4 flex gap-2" @submit.prevent="addSource">
          <input
            v-model="newSourceName"
            class="field-input flex-1"
            type="text"
            placeholder="Nama sumber, mis. Laundry"
            maxlength="100"
          />
          <button class="btn-primary shrink-0 !px-4" type="submit" :disabled="addingSource">
            {{ addingSource ? "..." : "Tambah" }}
          </button>
        </form>

        <div v-if="sourcesLoading" class="skeleton h-12" />

        <p v-else-if="sources.length === 0" class="rounded-2xl border border-dashed border-ink-200 px-4 py-6 text-center text-sm text-mist">
          Belum ada sumber. Tambahkan dulu agar bisa dipilih saat input pemasukan.
        </p>

        <div v-else class="flex max-h-52 flex-wrap gap-2 overflow-y-auto pr-1">
          <span
            v-for="src in sources"
            :key="src.id"
            class="inline-flex items-center gap-1.5 rounded-xl border border-ink-200 bg-ink-50 px-3 py-2 text-sm font-medium text-pearl"
          >
            {{ src.name }}
            <button
              type="button"
              class="ml-0.5 text-mist hover:text-debit"
              title="Hapus sumber"
              @click="removeSource(src.id)"
            >
              ×
            </button>
          </span>
        </div>

        <AlertBanner class="mt-3" :message="sourceMessage" :type="sourceError ? 'error' : 'success'" />
        </section>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import { currentMonth, formatDate, formatRupiah, todayISO } from "../utils/format";
import AlertBanner from "../components/AlertBanner.vue";
import EmptyState from "../components/EmptyState.vue";

const form = ref({ record_date: todayISO(), amount: "", source_id: null, note: "" });
const editingId = ref(null);
const month = ref(currentMonth());
const items = ref([]);
const total = ref(0);
const sources = ref([]);
const newSourceName = ref("");
const loading = ref(false);
const sourcesLoading = ref(false);
const saving = ref(false);
const addingSource = ref(false);
const message = ref("");
const isError = ref(false);
const sourceMessage = ref("");
const sourceError = ref(false);
const showSourceManager = ref(false);

let previousBodyOverflow = "";
watch(showSourceManager, (open) => {
  if (typeof document === "undefined") return;
  if (open) {
    previousBodyOverflow = document.body.style.overflow || "";
    document.body.style.overflow = "hidden";
  } else {
    document.body.style.overflow = previousBodyOverflow;
  }
});

onUnmounted(() => {
  if (typeof document === "undefined") return;
  document.body.style.overflow = previousBodyOverflow;
});

function resetForm() {
  editingId.value = null;
  form.value = {
    record_date: todayISO(),
    amount: "",
    source_id: sources.value[0]?.id ?? null,
    note: "",
  };
}

async function loadSources() {
  sourcesLoading.value = true;
  sourceMessage.value = "";
  try {
    const res = await fetch("/api/Investasi/IncomeSource/list");
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal memuat sumber");
    sources.value = data.data.items;
    if (!form.value.source_id && sources.value.length > 0) {
      form.value.source_id = sources.value[0].id;
    }
  } catch (err) {
    sourceMessage.value = err.message;
    sourceError.value = true;
  } finally {
    sourcesLoading.value = false;
  }
}

async function addSource() {
  const name = newSourceName.value.trim();
  if (name.length < 2) {
    sourceMessage.value = "Nama minimal 2 karakter";
    sourceError.value = true;
    return;
  }

  addingSource.value = true;
  sourceMessage.value = "";
  sourceError.value = false;

  try {
    const res = await fetch("/api/Investasi/IncomeSource/add", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal menambah sumber");

    newSourceName.value = "";
    sourceMessage.value = data.message || "Sumber ditambahkan";
    await loadSources();
    if (data.data?.id) form.value.source_id = data.data.id;
  } catch (err) {
    sourceMessage.value = err.message;
    sourceError.value = true;
  } finally {
    addingSource.value = false;
  }
}

async function removeSource(id) {
  if (!confirm("Hapus sumber ini?")) return;
  try {
    const res = await fetch("/api/Investasi/IncomeSource/delete", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal menghapus");

    if (form.value.source_id === id) form.value.source_id = null;
    await loadSources();
    sourceMessage.value = data.message || "Sumber dihapus";
    sourceError.value = false;
  } catch (err) {
    sourceMessage.value = err.message;
    sourceError.value = true;
  }
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
  if (!form.value.source_id) {
    message.value = "Pilih sumber pemasukan";
    isError.value = true;
    return;
  }

  saving.value = true;
  message.value = "";
  isError.value = false;

  const payload = {
    record_date: form.value.record_date,
    amount: Number(form.value.amount),
    source_id: form.value.source_id,
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
    source_id: item.source_id || sources.value[0]?.id || null,
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

onMounted(async () => {
  await loadSources();
  await loadItems();
});
</script>
