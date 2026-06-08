<template>
  <div class="space-y-6">

    <!-- Form input pengeluaran -->
    <section class="glass-strong p-6">
      <div class="mb-6 flex items-start justify-between">
        <div>
          <p class="label-caps">{{ editingId ? "Edit entri" : "Entri baru" }}</p>
          <h2 class="section-title mt-1">Pengeluaran harian</h2>
        </div>
        <span class="chip-out">Terpisah</span>
      </div>

      <form class="space-y-4" @submit.prevent="submitForm">
        <div>
          <div class="mb-2 flex items-center justify-between gap-2">
            <label class="field-label !mb-0">Target pengeluaran <span class="text-debit">*</span></label>
            <button class="btn-ghost !px-3 !py-1.5 !text-sm" type="button" @click="showTargetManager = true">
              Kelola
            </button>
          </div>
          <div v-if="targets.length === 0" class="rounded-2xl border border-debit-dim/20 bg-debit-light px-4 py-3 text-sm text-debit-dim">
            Tambahkan target lewat tombol Kelola.
          </div>
          <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <button
              v-for="tgt in targets"
              :key="tgt.id"
              type="button"
              class="rounded-xl border px-3 py-3 text-left text-sm font-semibold transition"
              :class="form.target_id === tgt.id
                ? 'border-debit-dim bg-debit-dim/10 text-debit-dim shadow-inner'
                : 'border-ink-200 bg-ink-50 text-pearl hover:border-debit-dim/30'"
              @click="form.target_id = tgt.id"
            >
              {{ tgt.name }}
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
            class="btn-debit flex-1"
            type="submit"
            :disabled="saving || targets.length === 0 || !form.target_id"
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
          <p class="money-display-sm mt-1 text-debit-dim">{{ formatRupiah(total) }}</p>
        </div>
        <input v-model="month" class="field-input !w-auto !py-2 !text-sm" type="month" @change="loadItems" />
      </div>

      <div v-if="loading" class="space-y-3">
        <div v-for="n in 3" :key="n" class="skeleton h-20" />
      </div>

      <EmptyState
        v-else-if="items.length === 0"
        title="Belum ada pengeluaran"
        subtitle="Pilih target, isi jumlah, lalu simpan."
      />

      <ul v-else class="space-y-3">
        <li
          v-for="item in items"
          :key="item.id"
          class="glass group flex items-start justify-between gap-4 p-4 transition hover:border-debit-dim/20"
        >
          <div class="min-w-0 flex-1">
            <span v-if="item.target_name" class="chip-out mb-2">{{ item.target_name }}</span>
            <p class="money-display-sm text-debit-dim">{{ formatRupiah(item.amount) }}</p>
            <p class="mt-1 text-sm text-mist">{{ formatDate(item.record_date) }}</p>
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

    <div
      v-if="showTargetManager"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 p-4"
      @click.self="showTargetManager = false"
    >
      <section class="glass-strong w-full max-w-md max-h-[80vh] overflow-y-auto p-5">
        <div class="mb-4 flex items-center justify-between gap-3">
          <div>
            <p class="label-caps">Master data</p>
            <h2 class="section-title mt-1">Target pengeluaran</h2>
          </div>
          <button class="btn-icon" type="button" title="Tutup" @click="showTargetManager = false">✕</button>
        </div>

        <form class="mb-4 flex gap-2" @submit.prevent="addTarget">
          <input
            v-model="newTargetName"
            class="field-input flex-1"
            type="text"
            placeholder="Nama target, mis. Makan, Transport"
            maxlength="100"
          />
          <button class="btn-debit shrink-0 !px-4" type="submit" :disabled="addingTarget">
            {{ addingTarget ? "..." : "Tambah" }}
          </button>
        </form>

        <div v-if="targetsLoading" class="skeleton h-12" />

        <p v-else-if="targets.length === 0" class="rounded-2xl border border-dashed border-ink-200 px-4 py-6 text-center text-sm text-mist">
          Belum ada target. Tambahkan dulu agar bisa dipilih saat input pengeluaran.
        </p>

        <div v-else class="flex max-h-52 flex-wrap gap-2 overflow-y-auto pr-1">
          <span
            v-for="tgt in targets"
            :key="tgt.id"
            class="inline-flex items-center gap-1.5 rounded-xl border border-debit-dim/20 bg-debit-light px-3 py-2 text-sm font-medium text-debit-dim"
          >
            {{ tgt.name }}
            <button
              type="button"
              class="ml-0.5 text-mist hover:text-debit"
              title="Hapus target"
              @click="removeTarget(tgt.id)"
            >
              ×
            </button>
          </span>
        </div>

        <AlertBanner class="mt-3" :message="targetMessage" :type="targetError ? 'error' : 'success'" />
      </section>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import { currentMonth, formatDate, formatRupiah, todayISO } from "../utils/format";
import AlertBanner from "../components/AlertBanner.vue";
import EmptyState from "../components/EmptyState.vue";

const form = ref({ record_date: todayISO(), amount: "", target_id: null, note: "" });
const editingId = ref(null);
const month = ref(currentMonth());
const items = ref([]);
const total = ref(0);
const targets = ref([]);
const newTargetName = ref("");
const loading = ref(false);
const targetsLoading = ref(false);
const saving = ref(false);
const addingTarget = ref(false);
const message = ref("");
const isError = ref(false);
const targetMessage = ref("");
const targetError = ref(false);
const showTargetManager = ref(false);

let previousBodyOverflow = "";
watch(showTargetManager, (open) => {
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
    target_id: targets.value[0]?.id ?? null,
    note: "",
  };
}

async function loadTargets() {
  targetsLoading.value = true;
  targetMessage.value = "";
  try {
    const res = await fetch("/api/Investasi/ExpenseTarget/list");
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal memuat target");
    targets.value = data.data.items;
    if (!form.value.target_id && targets.value.length > 0) {
      form.value.target_id = targets.value[0].id;
    }
  } catch (err) {
    targetMessage.value = err.message;
    targetError.value = true;
  } finally {
    targetsLoading.value = false;
  }
}

async function addTarget() {
  const name = newTargetName.value.trim();
  if (name.length < 2) {
    targetMessage.value = "Nama minimal 2 karakter";
    targetError.value = true;
    return;
  }

  addingTarget.value = true;
  targetMessage.value = "";
  targetError.value = false;

  try {
    const res = await fetch("/api/Investasi/ExpenseTarget/add", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal menambah target");

    newTargetName.value = "";
    targetMessage.value = data.message || "Target ditambahkan";
    await loadTargets();
    if (data.data?.id) form.value.target_id = data.data.id;
  } catch (err) {
    targetMessage.value = err.message;
    targetError.value = true;
  } finally {
    addingTarget.value = false;
  }
}

async function removeTarget(id) {
  if (!confirm("Hapus target ini?")) return;
  try {
    const res = await fetch("/api/Investasi/ExpenseTarget/delete", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal menghapus");

    if (form.value.target_id === id) form.value.target_id = null;
    await loadTargets();
    targetMessage.value = data.message || "Target dihapus";
    targetError.value = false;
  } catch (err) {
    targetMessage.value = err.message;
    targetError.value = true;
  }
}

async function loadItems() {
  loading.value = true;
  try {
    const res = await fetch(`/api/Investasi/DailyExpense/list?month=${month.value}`);
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
  if (!form.value.target_id) {
    message.value = "Pilih target pengeluaran";
    isError.value = true;
    return;
  }

  saving.value = true;
  message.value = "";
  isError.value = false;

  const payload = {
    record_date: form.value.record_date,
    amount: Number(form.value.amount),
    target_id: form.value.target_id,
    note: form.value.note || null,
  };

  const endpoint = editingId.value
    ? "/api/Investasi/DailyExpense/update"
    : "/api/Investasi/DailyExpense/add";

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
    target_id: item.target_id || targets.value[0]?.id || null,
    note: item.note || "",
  };
  window.scrollTo({ top: 0, behavior: "smooth" });
}

async function removeItem(id) {
  if (!confirm("Hapus pengeluaran ini?")) return;
  try {
    const res = await fetch("/api/Investasi/DailyExpense/delete", {
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
  await loadTargets();
  await loadItems();
});
</script>
