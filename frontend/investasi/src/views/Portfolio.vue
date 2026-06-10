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
        <div>
          <label class="field-label">Catatan <span class="text-mist/60">(opsional)</span></label>
          <input
            v-model="form.note"
            class="field-input"
            type="text"
            placeholder="Rebalancing, update harga pasar..."
          />
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

      <ul v-else class="pl-1">
        <li v-for="(item, index) in history" :key="item.id" class="timeline-item">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="money-display-sm">{{ formatRupiah(item.amount) }}</p>
              <p class="mt-0.5 text-sm text-mist">{{ formatDate(item.record_date) }}</p>
              <p v-if="item.note" class="mt-1.5 text-sm text-pearl/55">{{ item.note }}</p>
            </div>
            <span
              v-if="index === 0"
              class="chip border-ledger/30 bg-ledger/10 text-ledger-glow"
            >
              Terbaru
            </span>
          </div>
        </li>
      </ul>
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

const form = ref({ record_date: todayISO(), amount: "", note: "" });
const current = ref(null);
const history = ref([]);
const loading = ref(true);
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
