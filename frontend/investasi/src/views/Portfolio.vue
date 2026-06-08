<template>
  <div class="space-y-6">
    <!-- Current value hero -->
    <section class="glass-strong relative overflow-hidden p-6 text-center">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_0%,rgba(91,155,213,0.12),transparent_60%)]" />
      <p class="label-caps relative">Nilai saat ini</p>
      <p class="money-display relative mx-auto mt-4 max-w-full break-words text-ledger-dim">
        {{ formatRupiah(current?.amount) }}
      </p>
      <p class="relative mt-3 text-sm text-mist">
        Modal investasi: <span class="font-semibold text-pearl">{{ formatRupiah(netInvestment) }}</span>
      </p>
      <p class="relative mt-1 text-xs text-mist">
        <template v-if="current?.record_date">
          Snapshot {{ formatDate(current.record_date) }}
        </template>
        <template v-else>Belum pernah diupdate</template>
      </p>

      <GainLossBadge
        v-if="current?.amount != null && gainLoss !== null"
        class="relative mx-auto mt-5 max-w-sm text-left"
        :gain-loss="gainLoss"
        :gain-loss-pct="gainLossPct"
        :status="status"
        :portfolio="current.amount"
        :invested="netInvestment"
      />
    </section>

    <!-- Update form -->
    <section class="glass-strong p-6">
      <div class="mb-6">
        <p class="label-caps">Perbarui</p>
        <h2 class="section-title mt-1">Snapshot portfolio</h2>
        <p class="mt-2 text-sm text-mist">
          Update nilai aset secara berkala. Selisih dengan modal investasi = tumbuh (+) atau rugi (−).
        </p>
      </div>

      <form class="space-y-4" @submit.prevent="submitForm">
        <div>
          <label class="field-label">Tanggal</label>
          <input v-model="form.record_date" class="field-input" type="date" required />
        </div>
        <div>
          <label class="field-label">Nilai portfolio (Rp)</label>
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

      <div v-if="loading" class="space-y-3 pl-4">
        <div v-for="n in 4" :key="n" class="skeleton h-16" />
      </div>

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
              <p class="mt-0.5 text-xs text-mist">{{ formatDate(item.record_date) }}</p>
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
import { formatDate, formatRupiah, todayISO } from "../utils/format";
import AlertBanner from "../components/AlertBanner.vue";
import EmptyState from "../components/EmptyState.vue";
import GainLossBadge from "../components/GainLossBadge.vue";

const form = ref({ record_date: todayISO(), amount: "", note: "" });
const current = ref(null);
const netInvestment = ref(0);
const gainLoss = ref(null);
const gainLossPct = ref(null);
const status = ref(null);
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
    netInvestment.value = currentData.data.net_investment ?? 0;
    gainLoss.value = currentData.data.gain_loss;
    gainLossPct.value = currentData.data.gain_loss_pct;
    status.value = currentData.data.status;
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
