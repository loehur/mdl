<template>
  <div class="relative min-h-screen pb-10">
    <MeshBackground />

    <div class="mx-auto max-w-md px-4 pt-5">
      <PageLoader v-if="loading" />

      <EmptyState
        v-else-if="!invoice"
        title="Invoice tidak ditemukan"
        subtitle="Link mungkin sudah tidak valid."
      />

      <template v-else>
        <div class="page-enter mb-4 text-center">
          <p class="font-display text-2xl font-bold tracking-tight text-pearl">
            {{ invoice.issuer?.name || "Nalju Digital Solutions (NDS)" }}
          </p>
          <h1 class="mt-1.5 text-sm font-semibold text-mist">{{ invoice.invoice_number }}</h1>
          <p v-if="invoice.title" class="mt-1 font-semibold text-pearl">{{ invoice.title }}</p>
        </div>

        <div class="glass-strong page-enter p-3.5">
          <div class="mb-2.5 flex items-center justify-between">
            <div>
              <p class="text-sm text-mist">Kepada</p>
              <p class="font-bold text-pearl">{{ invoice.customer_name }}</p>
            </div>
            <span class="chip" :class="invoice.payment_status === 'paid' ? 'chip-in' : 'chip-out'">
              {{ paymentLabel }}
            </span>
          </div>

          <div class="mb-2.5 grid grid-cols-2 gap-3 text-sm">
            <div>
              <p class="text-mist">Tanggal</p>
              <p class="font-semibold">{{ formatDate(invoice.issue_date) }}</p>
            </div>
            <div>
              <p class="text-mist">Jatuh Tempo</p>
              <p class="font-semibold">{{ invoice.due_date ? formatDate(invoice.due_date) : '-' }}</p>
            </div>
          </div>

          <div class="hairline mb-4" />

          <div class="space-y-3">
            <div
              v-for="item in invoice.items"
              :key="item.id"
              class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3"
            >
              <div class="min-w-0">
                <p class="text-sm font-semibold text-pearl">{{ item.description }}</p>
                <p class="text-xs text-mist">
                  {{ item.quantity }} × {{ formatRupiahDisplay(item.unit_price) }}
                  <span v-if="item.amount_usd != null" class="text-mist/80">
                    ({{ formatUsdDisplay(item.unit_price_usd) }})
                  </span>
                </p>
              </div>
              <div class="min-w-max text-right">
                <p class="text-sm font-bold tabular-nums">
                  {{ formatRupiahDisplay(item.amount) }}
                </p>
                <p v-if="item.amount_usd != null" class="text-xs text-mist tabular-nums">
                  {{ formatUsdDisplay(item.amount_usd) }}
                </p>
              </div>
            </div>
          </div>

          <div class="mt-2.5 space-y-1 border-t border-ink-200 pt-3">
            <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 text-sm text-mist">
              <span>Subtotal</span>
              <span class="min-w-max whitespace-nowrap text-right tabular-nums">{{ formatRupiahDisplay(invoice.subtotal) }}</span>
            </div>
            <div v-if="invoice.tax_amount > 0" class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 text-sm text-mist">
              <span>Pajak</span>
              <span class="min-w-max whitespace-nowrap text-right tabular-nums">{{ formatRupiahDisplay(invoice.tax_amount) }}</span>
            </div>
            <div
              v-if="invoice.total_usd != null && Number(invoice.total_usd) > 0"
              class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 text-sm text-mist"
            >
              <span>Pedoman USD</span>
              <span class="min-w-max whitespace-nowrap text-right tabular-nums">{{ formatUsdDisplay(invoice.total_usd) }}</span>
            </div>
            <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 pt-1">
              <span class="font-bold text-pearl">Total dibayar</span>
              <span class="money-display-sm min-w-max whitespace-nowrap text-right tabular-nums text-ledger-dim">
                {{ formatRupiahDisplay(invoice.total) }}
              </span>
            </div>
            <p
              v-if="invoice.total_usd != null && Number(invoice.total_usd) > 0"
              class="pt-1 text-xs text-mist"
            >
              USD hanya pedoman. Pembayaran dalam rupiah sesuai total di atas.
            </p>
          </div>

          <p v-if="invoice.notes" class="mt-2.5 whitespace-pre-wrap rounded-xl bg-ink-100 p-2.5 text-sm text-mist">
            {{ invoice.notes }}
          </p>

          <div v-if="invoice.issuer?.phone || invoice.issuer?.address" class="mt-2.5 text-sm text-mist">
            <p v-if="invoice.issuer.phone">📞 {{ invoice.issuer.phone }}</p>
            <p v-if="invoice.issuer.address" class="mt-1 whitespace-pre-wrap">{{ invoice.issuer.address }}</p>
          </div>
        </div>

        <div v-if="invoice.can_pay" class="mt-3 page-enter space-y-2">
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              class="rounded-xl border px-3 py-2 text-sm font-semibold transition"
              :class="selectedPaymentMethod === 'qris' ? 'border-ledger bg-ledger/10 text-pearl' : 'border-ink-200 text-mist'"
              @click="onSelectPaymentMethod('qris')"
            >
              QRIS
            </button>
            <button
              type="button"
              class="rounded-xl border px-3 py-2 text-sm font-semibold transition"
              :class="selectedPaymentMethod === 'bca' ? 'border-ledger bg-ledger/10 text-pearl' : 'border-ink-200 text-mist'"
              @click="onSelectPaymentMethod('bca')"
            >
              Transfer BCA
            </button>
          </div>
          <button class="btn-primary w-full" :disabled="paying" @click="startPayment">
            {{ paying ? "Memproses..." : `Bayar Rp ${formatRupiah(invoice.total)}` }}
          </button>
          <button class="btn-ghost w-full" type="button" :disabled="downloading" @click="onDownloadPdf">
            {{ downloading ? "Menyiapkan PDF..." : "Unduh PDF / Cetak" }}
          </button>
        </div>

        <div v-else class="mt-3 page-enter space-y-2">
          <div
            v-if="invoice.payment_status === 'paid'"
            class="alert-success text-center"
          >
            Invoice ini sudah lunas. Terima kasih!
          </div>
          <button class="btn-ghost w-full" type="button" :disabled="downloading" @click="onDownloadPdf">
            {{ downloading ? "Menyiapkan PDF..." : "Unduh PDF / Cetak" }}
          </button>
        </div>

        <AlertBanner class="mt-4" :message="message" :type="isError ? 'error' : 'success'" />
      </template>
    </div>

    <Teleport to="body">
      <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
        <div class="w-full max-w-md rounded-2xl bg-ink-50 shadow-2xl">
          <div class="rounded-t-2xl bg-gradient-to-r from-ledger-dim via-ledger to-ledger-glow p-4">
            <h3 class="text-center text-lg font-bold text-white">
              {{ isBcaPayment ? "Transfer BCA" : "Scan QRIS" }}
            </h3>
          </div>

          <div class="space-y-3 p-4">
            <template v-if="isBcaPayment">
              <div class="space-y-2 rounded-xl border border-ledger/20 bg-white p-4 text-sm">
                <p class="font-semibold text-pearl">{{ paymentData?.bank_account?.label || "BCA" }}</p>
                <p class="font-mono text-lg font-bold tracking-wide text-ledger-dim">
                  {{ paymentData?.bank_account?.number }}
                </p>
                <p class="text-mist">a/n {{ paymentData?.bank_account?.name }}</p>
                <div class="mt-3 rounded-lg bg-ink-100 p-3">
                  <p class="text-xs text-mist">Nominal transfer (exact)</p>
                  <p class="text-xl font-bold tabular-nums text-ledger-dim">
                    Rp {{ formatRupiah(paymentData?.amount) }}
                  </p>
                  <p v-if="paymentData?.unique_nominal" class="mt-1 text-xs text-amber-600">
                    Nominal unik — transfer persis hingga digit terakhir
                  </p>
                </div>
              </div>
              <p class="text-center text-xs text-mist">
                Setelah transfer, konfirmasi otomatis dalam beberapa menit
              </p>
            </template>

            <template v-else>
              <div class="flex justify-center">
                <div v-if="paymentData?.qr_string" class="rounded-xl border-2 border-ledger/20 bg-white p-2">
                  <qrcode-vue :value="paymentData.qr_string" :size="180" level="H" />
                </div>
                <div v-else class="flex h-48 w-48 items-center justify-center rounded-xl bg-ink-100">
                  <PageLoader />
                </div>
              </div>

              <p class="text-center text-xs text-mist">
                Scan dengan aplikasi e-wallet atau mobile banking Anda
              </p>
            </template>

            <div class="space-y-1 rounded-lg bg-ink-100 p-3 text-xs">
              <div class="flex justify-between">
                <span class="text-mist">Invoice</span>
                <span class="font-semibold">{{ paymentData?.invoice_number }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-mist">Total</span>
                <span class="font-bold text-ledger-dim">Rp {{ formatRupiah(paymentData?.amount) }}</span>
              </div>
            </div>

            <button
              class="btn-primary w-full"
              :disabled="checking"
              @click="checkPayment"
            >
              {{ checking ? "Mengecek..." : paymentSuccess ? "Pembayaran Berhasil!" : "Cek Status Pembayaran" }}
            </button>

            <button
              v-if="!paymentSuccess"
              class="btn-ghost w-full text-red-500"
              :disabled="cancelling"
              @click="cancelActivePayment"
            >
              {{ cancelling ? "Membatalkan..." : "Batalkan Pembayaran" }}
            </button>

            <button class="btn-ghost w-full" @click="closePaymentModal">Tutup</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useRoute } from "vue-router";
import QrcodeVue from "qrcode.vue";
import MeshBackground from "../components/MeshBackground.vue";
import PageLoader from "../components/PageLoader.vue";
import EmptyState from "../components/EmptyState.vue";
import AlertBanner from "../components/AlertBanner.vue";
import { formatDate, formatRupiah, formatRupiahDisplay, formatUsdDisplay } from "../utils/format";

const route = useRoute();

const loading = ref(true);
const invoice = ref(null);
const paying = ref(false);
const cancelling = ref(false);
const checking = ref(false);
const downloading = ref(false);
const showPaymentModal = ref(false);
const paymentData = ref(null);
const paymentSuccess = ref(false);
const message = ref("");
const isError = ref(false);
const selectedPaymentMethod = ref("qris");
const pendingPayment = ref(null);
let pollInterval = null;

const activePendingRef = computed(() =>
  paymentData.value?.payment_ref || pendingPayment.value?.payment_ref || null
);

const isBcaPayment = computed(() => paymentData.value?.payment_method === "bca");

const paymentLabel = computed(() => {
  if (!invoice.value) return "";
  if (invoice.value.payment_status === "paid") return "Lunas";
  if (invoice.value.payment_status === "pending") return "Menunggu";
  return "Belum Bayar";
});

async function loadInvoice() {
  loading.value = true;
  try {
    const res = await fetch(`/api/Invoice/PublicView/view?token=${route.params.token}`);
    const data = await res.json();
    if (res.ok && data.status) {
      invoice.value = data.data;
      pendingPayment.value = data.data.pending_payment || null;
      if (pendingPayment.value?.payment_method) {
        selectedPaymentMethod.value = pendingPayment.value.payment_method;
      }
    }
  } catch {
    /* ignore */
  } finally {
    loading.value = false;
  }
}

async function onSelectPaymentMethod(method) {
  if (method === selectedPaymentMethod.value && !activePendingRef.value) {
    return;
  }

  const currentPending = paymentData.value || pendingPayment.value;
  if (currentPending?.payment_ref && currentPending.payment_method !== method) {
    const ok = await cancelActivePayment(currentPending.payment_ref, false);
    if (!ok) return;
  }

  selectedPaymentMethod.value = method;

  if (showPaymentModal.value && !paymentData.value) {
    showPaymentModal.value = false;
  }
}

async function cancelActivePayment(paymentRef = null, closeModal = true) {
  const ref = paymentRef || activePendingRef.value;
  if (!ref || cancelling.value) return false;

  cancelling.value = true;
  message.value = "";
  isError.value = false;

  try {
    const res = await fetch("/api/Invoice/PublicView/cancelPayment", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        token: route.params.token,
        payment_ref: ref,
      }),
    });
    const data = await res.json();

    if (!res.ok || !data.status) {
      if (data.data?.payment_status === 'paid') {
        paymentSuccess.value = true;
        await loadInvoice();
        stopPolling();
        showPaymentModal.value = false;
        message.value = "Pembayaran sudah berhasil";
        isError.value = false;
        return false;
      }
      message.value = data.message || "Gagal membatalkan pembayaran";
      isError.value = true;
      return false;
    }

    stopPolling();
    paymentData.value = null;
    pendingPayment.value = null;
    if (invoice.value) {
      invoice.value.payment_status = "unpaid";
    }
    if (closeModal) {
      showPaymentModal.value = false;
      message.value = "Pembayaran dibatalkan. Silakan pilih metode bayar.";
      isError.value = false;
    }
    await loadInvoice();
    return true;
  } catch {
    message.value = "Gagal membatalkan pembayaran";
    isError.value = true;
    return false;
  } finally {
    cancelling.value = false;
  }
}

async function startPayment() {
  paying.value = true;
  paymentSuccess.value = false;
  message.value = "";

  try {
    const res = await fetch("/api/Invoice/PublicView/pay", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        token: route.params.token,
        payment_method: selectedPaymentMethod.value,
      }),
    });
    const data = await res.json();

    if (res.ok && data.status) {
      paymentData.value = data.data;
      pendingPayment.value = {
        payment_ref: data.data.payment_ref,
        payment_method: data.data.payment_method,
        amount: data.data.amount,
      };
      showPaymentModal.value = true;
      startPolling();
    } else if (res.status === 409 && data.data?.pending_payment_ref) {
      message.value = data.message || "Batalkan pembayaran pending dulu.";
      isError.value = true;
      pendingPayment.value = {
        payment_ref: data.data.pending_payment_ref,
        payment_method: data.data.pending_payment_method || "qris",
      };
    } else {
      message.value = data.message || "Gagal membuat pembayaran";
      isError.value = true;
    }
  } catch {
    message.value = "Tidak dapat terhubung ke server";
    isError.value = true;
  } finally {
    paying.value = false;
  }
}

async function checkPayment(silent = false) {
  if (!paymentData.value?.payment_ref) return;

  if (!silent) checking.value = true;
  try {
    const res = await fetch(
      `/api/Invoice/PublicView/checkPayment?payment_ref=${paymentData.value.payment_ref}`
    );
    const data = await res.json();

    if (data.status && data.data?.payment_status === "paid") {
      paymentSuccess.value = true;
      invoice.value.payment_status = "paid";
      invoice.value.can_pay = false;
      stopPolling();
      showPaymentModal.value = false;
      paymentData.value = null;
      message.value = "";
      isError.value = false;
    } else if (data.data?.payment_status === "expired") {
      if (!silent) {
        message.value = "Pembayaran kadaluarsa. Silakan buat pembayaran baru.";
        isError.value = true;
      }
      stopPolling();
      showPaymentModal.value = false;
    } else if (!silent) {
      message.value = isBcaPayment.value
        ? "Transfer belum terdeteksi. Pastikan nominal exact, lalu coba lagi."
        : "Pembayaran belum diterima. Coba lagi setelah scan.";
      isError.value = true;
    }
  } catch {
    message.value = "Gagal cek status pembayaran";
    isError.value = true;
  } finally {
    if (!silent) checking.value = false;
  }
}

function closePaymentModal() {
  showPaymentModal.value = false;
  stopPolling();
  if (paymentSuccess.value) {
    loadInvoice();
  }
}

function startPolling() {
  stopPolling();
  if (!paymentData.value?.payment_ref) return;
  pollInterval = setInterval(() => {
    checkPayment(true);
  }, 4000);
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval);
    pollInterval = null;
  }
}

function onDownloadPdf() {
  if (!invoice.value || downloading.value) return;
  downloading.value = true;
  message.value = "";
  isError.value = false;
  import("../utils/invoicePdf")
    .then(({ downloadInvoicePdf }) => {
      downloadInvoicePdf(invoice.value);
      message.value = "PDF berhasil diunduh";
    })
    .catch(() => {
      message.value = "Gagal membuat PDF";
      isError.value = true;
    })
    .finally(() => {
      downloading.value = false;
    });
}

onMounted(loadInvoice);
onUnmounted(stopPolling);
</script>
