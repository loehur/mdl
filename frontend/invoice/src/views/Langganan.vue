<template>
  <div class="space-y-5 pb-6">
    <div class="flex gap-2">
      <input
        v-model="search"
        class="field-input flex-1"
        type="search"
        placeholder="Cari judul / pelanggan / ID"
      />
    </div>

    <div class="flex gap-2">
      <button
        v-for="opt in filterOptions"
        :key="opt.value"
        type="button"
        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
        :class="
          filter === opt.value
            ? 'bg-ledger text-white'
            : 'border border-ink-200 bg-ink-50 text-mist'
        "
        @click="filter = opt.value"
      >
        {{ opt.label }}
      </button>
    </div>

    <PageLoader v-if="loading" />

    <EmptyState
      v-else-if="!bills.length"
      title="Belum ada langganan"
      subtitle="Aktifkan tagihan berulang saat membuat invoice."
    />

    <EmptyState
      v-else-if="!filtered.length"
      title="Tidak ditemukan"
      subtitle="Coba kata kunci atau filter lain."
    />

    <div v-else class="space-y-3">
      <div
        v-for="bill in filtered"
        :key="bill.id"
        class="glass flex items-start justify-between gap-3 p-4"
      >
        <router-link :to="`/langganan/edit/${bill.id}`" class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <p class="font-bold text-pearl">{{ bill.title || "Tanpa judul" }}</p>
            <span
              class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
              :class="
                bill.is_active
                  ? 'bg-credit-light text-credit-dim'
                  : 'bg-ink-100 text-mist'
              "
            >
              {{ bill.is_active ? "Aktif" : "Nonaktif" }}
            </span>
          </div>
          <p class="mt-0.5 text-sm text-mist">
            {{ bill.customer_name || "Pelanggan dihapus" }}
          </p>
          <p class="mt-1 text-xs text-mist">
            {{ periodLabel(bill.period) }} · berikutnya
            {{ formatDate(bill.next_issue_date) }}
          </p>
          <p class="mt-1 text-sm font-semibold text-ledger-dim">
            Rp {{ formatRupiah(bill.total) }}
          </p>
        </router-link>
        <button
          type="button"
          class="shrink-0 text-sm"
          :class="bill.is_active ? 'text-debit-dim' : 'text-credit-dim'"
          @click="openToggle(bill)"
        >
          {{ bill.is_active ? "Nonaktifkan" : "Aktifkan" }}
        </button>
      </div>
    </div>

    <AlertBanner class="mt-2" :message="message" :type="isError ? 'error' : 'success'" />

    <ConfirmModal
      :open="confirmOpen"
      :title="pendingToggle?.is_active ? 'Nonaktifkan langganan?' : 'Aktifkan langganan?'"
      :message="
        pendingToggle?.is_active
          ? 'Tagihan otomatis tidak akan dibuat sampai diaktifkan kembali.'
          : 'Tagihan otomatis akan dibuat lagi sesuai jadwal.'
      "
      :detail="pendingToggle?.title || ''"
      :confirm-label="pendingToggle?.is_active ? 'Nonaktifkan' : 'Aktifkan'"
      :variant="pendingToggle?.is_active ? 'danger' : 'success'"
      :loading="toggling"
      @confirm="confirmToggle"
      @cancel="confirmOpen = false"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import AlertBanner from "../components/AlertBanner.vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import EmptyState from "../components/EmptyState.vue";
import PageLoader from "../components/PageLoader.vue";
import { formatDate, formatRupiah } from "../utils/format";

const loading = ref(true);
const bills = ref([]);
const search = ref("");
const filter = ref("all");
const message = ref("");
const isError = ref(false);
const confirmOpen = ref(false);
const pendingToggle = ref(null);
const toggling = ref(false);

const filterOptions = [
  { value: "all", label: "Semua" },
  { value: "active", label: "Aktif" },
  { value: "inactive", label: "Nonaktif" },
];

function periodLabel(period) {
  return period === "yearly" ? "Tahunan" : "Bulanan";
}

const filtered = computed(() => {
  let list = bills.value;

  if (filter.value === "active") {
    list = list.filter((b) => b.is_active);
  } else if (filter.value === "inactive") {
    list = list.filter((b) => !b.is_active);
  }

  const q = search.value.trim().toLowerCase();
  if (!q) return list;

  return list.filter((b) => {
    const hay = `${b.title || ""} ${b.customer_name || ""} ${b.subscription_id || ""}`.toLowerCase();
    return hay.includes(q);
  });
});

async function loadList() {
  loading.value = true;
  message.value = "";
  isError.value = false;
  try {
    const res = await fetch("/api/Invoice/RecurringBills/list");
    const data = await res.json();
    if (res.ok && data.status) {
      bills.value = data.data.bills || [];
    } else {
      message.value = data.message || "Gagal memuat langganan";
      isError.value = true;
    }
  } catch {
    message.value = "Tidak dapat terhubung ke server";
    isError.value = true;
  } finally {
    loading.value = false;
  }
}

function openToggle(bill) {
  pendingToggle.value = bill;
  confirmOpen.value = true;
}

async function confirmToggle() {
  if (!pendingToggle.value) return;
  toggling.value = true;
  message.value = "";
  isError.value = false;
  try {
    const res = await fetch("/api/Invoice/RecurringBills/setActive", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id: pendingToggle.value.id,
        is_active: !pendingToggle.value.is_active,
      }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) {
      message.value = data.message || "Gagal mengubah status";
      isError.value = true;
      return;
    }
    message.value = data.message || "Status diperbarui";
    confirmOpen.value = false;
    pendingToggle.value = null;
    await loadList();
  } catch {
    message.value = "Tidak dapat terhubung ke server";
    isError.value = true;
  } finally {
    toggling.value = false;
  }
}

onMounted(loadList);
</script>
