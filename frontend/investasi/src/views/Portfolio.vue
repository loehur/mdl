<template>
  <div class="space-y-6">
    <!-- Update form -->
    <section class="glass-strong p-6">
      <form class="space-y-4" @submit.prevent="submitForm">
        <div>
          <label class="field-label">Tanggal</label>
          <input v-model="form.record_date" class="field-input" type="date" required />
        </div>
        <div>
          <label class="field-label">Nilai portfolio</label>
          <AmountInput v-model="form.amount" required />
        </div>
        <button class="btn-primary w-full" type="submit" :disabled="saving">
          {{ saving ? "Menyimpan..." : "Simpan snapshot" }}
        </button>
      </form>

      <AlertBanner class="mt-4" :message="message" :type="isError ? 'error' : 'success'" />
    </section>

    <!-- Timeline history -->
    <section>
      <div class="mb-4">
        <p class="label-caps">Riwayat</p>
        <h3 class="mt-1 text-sm font-bold text-pearl">Perjalanan nilai aset</h3>
      </div>

      <PageLoader v-if="loading" />

      <EmptyState
        v-else-if="history.length === 0"
        title="Riwayat masih kosong"
        subtitle="Setiap kali Anda update portfolio, snapshot akan muncul di sini."
      />

      <template v-else>
        <AlertBanner
          v-if="historyMessage"
          class="mb-4"
          :message="historyMessage"
          :type="historyError ? 'error' : 'success'"
        />

        <ul class="pl-1">
          <li v-for="(item, index) in history" :key="item.id" class="timeline-item">
            <div v-if="editingId === item.id" class="space-y-3 pr-1">
              <div>
                <label class="field-label">Tanggal</label>
                <input v-model="editForm.record_date" class="field-input" type="date" required />
              </div>
              <div>
                <label class="field-label">Nilai portfolio</label>
                <AmountInput v-model="editForm.amount" required />
              </div>
              <div class="flex gap-2">
                <button
                  class="btn-primary flex-1"
                  type="button"
                  :disabled="editSaving"
                  @click="saveEdit"
                >
                  {{ editSaving ? "Menyimpan..." : "Simpan" }}
                </button>
                <button class="btn-ghost" type="button" :disabled="editSaving" @click="cancelEdit">
                  Batal
                </button>
              </div>
            </div>

            <div v-else class="flex items-start justify-between gap-3">
              <div>
                <p class="money-display-sm">{{ formatRupiah(item.amount) }}</p>
                <p class="mt-0.5 text-sm text-mist">{{ formatDate(item.record_date) }}</p>
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <button class="btn-icon !h-8 !w-8" title="Edit" type="button" @click="startEdit(item)">
                  <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </button>
                <span
                  v-if="index === 0"
                  class="chip border-ledger/30 bg-ledger/10 text-ledger-glow"
                >
                  Terbaru
                </span>
              </div>
            </div>
          </li>
        </ul>
      </template>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { amountInputToNumber, formatDate, formatRupiah, todayISO, toAmountDigits } from "../utils/format";
import AlertBanner from "../components/AlertBanner.vue";
import AmountInput from "../components/AmountInput.vue";
import EmptyState from "../components/EmptyState.vue";
import PageLoader from "../components/PageLoader.vue";

const form = ref({ record_date: todayISO(), amount: "" });
const editForm = ref({ record_date: "", amount: "" });
const current = ref(null);
const history = ref([]);
const editingId = ref(null);
const loading = ref(true);
const saving = ref(false);
const editSaving = ref(false);
const message = ref("");
const isError = ref(false);
const historyMessage = ref("");
const historyError = ref(false);

async function loadData() {
  loading.value = true;
  try {
    const [currentRes, historyRes] = await Promise.all([
      fetch("/api/Investasi/Portfolio/current"),
      fetch("/api/Investasi/Portfolio/history?limit=3"),
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
      form.value.amount = toAmountDigits(current.value.amount);
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
        amount: amountInputToNumber(form.value.amount),
      }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal menyimpan");

    message.value = data.message || "Portfolio berhasil diperbarui";
    await loadData();
  } catch (err) {
    message.value = err.message;
    isError.value = true;
  } finally {
    saving.value = false;
  }
}

function startEdit(item) {
  editingId.value = item.id;
  editForm.value = {
    record_date: item.record_date,
    amount: toAmountDigits(item.amount),
  };
  historyMessage.value = "";
  historyError.value = false;
}

function cancelEdit() {
  editingId.value = null;
  editForm.value = { record_date: "", amount: "" };
}

async function saveEdit() {
  if (!editingId.value) return;

  editSaving.value = true;
  historyMessage.value = "";
  historyError.value = false;

  try {
    const res = await fetch("/api/Investasi/Portfolio/edit", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id: editingId.value,
        record_date: editForm.value.record_date,
        amount: amountInputToNumber(editForm.value.amount),
      }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal memperbarui");

    historyMessage.value = data.message || "Snapshot berhasil diperbarui";
    cancelEdit();
    await loadData();
  } catch (err) {
    historyMessage.value = err.message;
    historyError.value = true;
  } finally {
    editSaving.value = false;
  }
}

onMounted(loadData);
</script>
