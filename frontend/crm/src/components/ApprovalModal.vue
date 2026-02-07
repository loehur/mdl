<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  custId: { type: [Number, String], default: null },
  customerName: { type: String, default: '' },
  apiBase: { type: String, default: 'https://api.nalju.com' },
});

const emit = defineEmits(['close']);

const items = ref([]);
const isLoading = ref(false);
const error = ref('');
const actionLoading = ref(null); // ref_finance being processed
const rejectConfirm = ref(null); // { ref_finance, pelanggan } for modal

const fetchList = async () => {
  if (!props.custId) return;
  isLoading.value = true;
  error.value = '';
  try {
    const res = await fetch(`${props.apiBase}/CRM/Approval/list?cust_id=${props.custId}`);
    const data = await res.json();
    if (data.status && data.data?.items) {
      items.value = data.data.items;
    } else {
      items.value = [];
      error.value = data.message || 'Gagal memuat data';
    }
  } catch (e) {
    items.value = [];
    error.value = e.message || 'Network error';
  } finally {
    isLoading.value = false;
  }
};

const doOperasi = async (refFinance, tipe) => {
  actionLoading.value = refFinance;
  try {
    const res = await fetch(`${props.apiBase}/CRM/Approval/operasi`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: refFinance, tipe }),
    });
    const data = await res.json();
    if (data.status) {
      items.value = items.value.filter((i) => i.ref_finance !== refFinance);
      rejectConfirm.value = null;
    }
  } catch (e) {
    console.error(e);
  } finally {
    actionLoading.value = null;
  }
};

const terima = (item) => doOperasi(item.ref_finance, 3);
const tolak = (item) => {
  rejectConfirm.value = { ref_finance: item.ref_finance, pelanggan: item.pelanggan };
};
const konfirmasiTolak = () => {
  if (rejectConfirm.value) {
    doOperasi(rejectConfirm.value.ref_finance, 4);
  }
};
const batalTolak = () => {
  rejectConfirm.value = null;
};

const formatRupiah = (n) => {
  return new Intl.NumberFormat('id-ID', { style: 'decimal', minimumFractionDigits: 0 }).format(n);
};

watch(
  () => [props.show, props.custId],
  ([show, custId]) => {
    if (show && custId) fetchList();
    else {
      items.value = [];
      rejectConfirm.value = null;
    }
  },
  { immediate: true }
);
</script>

<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-[700] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
      @click.self="emit('close')"
    >
      <div
        class="bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-2xl shadow-2xl w-full max-w-md max-h-[85vh] flex flex-col"
        @click.stop
      >
        <!-- Header -->
        <div class="flex justify-between items-center px-5 py-4 border-b border-[var(--wa-border)]">
          <h2 class="text-lg font-semibold text-[var(--wa-text-primary)]">List Pembayaran</h2>
          <button
            @click="emit('close')"
            class="p-2 text-[var(--wa-icon-default)] hover:text-[var(--wa-text-primary)] rounded-lg hover:bg-[var(--wa-hover)]"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Customer -->
        <div v-if="customerName" class="px-5 py-2 bg-[var(--wa-bg-secondary)] border-b border-[var(--wa-border)]">
          <p class="text-sm text-[var(--wa-text-secondary)]">
            <span class="text-[var(--wa-text-tertiary)]">Customer:</span>
            <span class="font-medium text-[var(--wa-text-primary)] uppercase ml-1">{{ customerName }}</span>
          </p>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-4 space-y-2 min-h-[200px]">
          <!-- Loading -->
          <div v-if="isLoading" class="flex flex-col items-center justify-center py-12 text-[var(--wa-text-tertiary)]">
            <div class="w-8 h-8 border-2 border-[var(--wa-accent-green)] border-t-transparent rounded-full animate-spin mb-3"></div>
            <span class="text-sm">Memuat...</span>
          </div>

          <!-- Error -->
          <div v-else-if="error" class="text-center py-8 text-red-400 text-sm">{{ error }}</div>

          <!-- Empty -->
          <div
            v-else-if="items.length === 0"
            class="flex flex-col items-center justify-center py-12 text-[var(--wa-text-tertiary)]"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[var(--wa-accent-green)] mb-3 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm font-medium">Semua transaksi sudah dikonfirmasi</p>
          </div>

          <!-- List -->
          <div v-else class="space-y-2">
            <div
              v-for="item in items"
              :key="item.ref_finance"
              class="bg-[var(--wa-bg-secondary)] rounded-xl p-4 border border-[var(--wa-border)]"
            >
              <p class="text-xs text-[var(--wa-text-tertiary)] mb-3">
                {{ item.jenis_bill }} • {{ item.note }} • {{ item.karyawan }}
              </p>
              <div class="flex items-center justify-between gap-3">
                <button
                  @click="tolak(item)"
                  :disabled="actionLoading === item.ref_finance"
                  class="p-2 rounded-lg border border-red-500/50 text-red-400 hover:bg-red-500/10 disabled:opacity-50 flex-shrink-0"
                  title="Tolak"
                >
                  <svg v-if="actionLoading !== item.ref_finance" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  <div v-else class="w-4 h-4 border-2 border-red-400 border-t-transparent rounded-full animate-spin"></div>
                </button>
                <span class="font-bold text-[var(--wa-accent-green)] text-sm flex-1 text-center">{{ formatRupiah(item.total) }}</span>
                <button
                  @click="terima(item)"
                  :disabled="actionLoading === item.ref_finance"
                  class="p-2 rounded-lg bg-[var(--wa-accent-green)] text-black hover:opacity-90 disabled:opacity-50 flex-shrink-0"
                  title="Terima"
                >
                  <svg v-if="actionLoading !== item.ref_finance" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <div v-else class="w-4 h-4 border-2 border-black border-t-transparent rounded-full animate-spin"></div>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Konfirmasi Tolak -->
      <div
        v-if="rejectConfirm"
        class="absolute inset-0 flex items-center justify-center p-4 bg-black/70 z-10"
        @click.self="batalTolak"
      >
        <div class="bg-[var(--wa-bg-panel)] rounded-xl p-5 max-w-sm w-full border border-[var(--wa-border)] shadow-xl">
          <h3 class="text-red-400 font-semibold mb-2 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            Konfirmasi Tolak
          </h3>
          <p class="text-sm text-[var(--wa-text-secondary)] mb-4">
            Yakin ingin menolak transaksi dari <strong class="text-[var(--wa-text-primary)]">{{ rejectConfirm.pelanggan }}</strong>?
          </p>
          <div class="flex gap-2">
            <button
              @click="batalTolak"
              class="flex-1 py-2 px-3 rounded-lg bg-[var(--wa-bg-tertiary)] text-[var(--wa-text-primary)] text-sm font-medium"
            >
              Batal
            </button>
            <button
              @click="konfirmasiTolak"
              class="flex-1 py-2 px-3 rounded-lg bg-red-500 text-white text-sm font-medium hover:bg-red-600"
            >
              Ya, Tolak
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
