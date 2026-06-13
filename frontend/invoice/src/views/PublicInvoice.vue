<template>
  <div class="relative min-h-screen pb-10">
    <MeshBackground />

    <div class="mx-auto max-w-md px-5 pt-8">
      <PageLoader v-if="loading" />

      <EmptyState
        v-else-if="!invoice"
        title="Invoice tidak ditemukan"
        subtitle="Link mungkin sudah tidak valid."
      />

      <template v-else>
        <div class="page-enter mb-6 text-center">
          <p class="label-caps text-ledger/80">Invoice Publik</p>
          <h1 class="page-title mt-2">{{ invoice.invoice_number }}</h1>
          <p class="mt-1 text-sm text-mist">Dari {{ invoice.issuer?.name }}</p>
        </div>

        <div class="glass-strong page-enter p-5">
          <div class="mb-4 flex items-center justify-between">
            <div>
              <p class="text-sm text-mist">Kepada</p>
              <p class="font-bold text-pearl">{{ invoice.customer_name }}</p>
            </div>
            <span class="chip" :class="invoice.payment_status === 'paid' ? 'chip-in' : 'chip-out'">
              {{ paymentLabel }}
            </span>
          </div>

          <div class="mb-4 grid grid-cols-2 gap-3 text-sm">
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
              class="flex justify-between gap-3"
            >
              <div>
                <p class="text-sm font-semibold text-pearl">{{ item.description }}</p>
                <p class="text-xs text-mist">{{ item.quantity }} × Rp {{ formatRupiah(item.unit_price) }}</p>
              </div>
              <p class="text-sm font-bold">Rp {{ formatRupiah(item.amount) }}</p>
            </div>
          </div>

          <div class="mt-4 space-y-1 border-t border-ink-200 pt-3">
            <div class="flex justify-between text-sm text-mist">
              <span>Subtotal</span>
              <span>Rp {{ formatRupiah(invoice.subtotal) }}</span>
            </div>
            <div v-if="invoice.tax_amount > 0" class="flex justify-between text-sm text-mist">
              <span>Pajak</span>
              <span>Rp {{ formatRupiah(invoice.tax_amount) }}</span>
            </div>
            <div class="flex justify-between pt-1">
              <span class="font-bold text-pearl">Total</span>
              <span class="money-display-sm text-ledger-dim">Rp {{ formatRupiah(invoice.total) }}</span>
            </div>
          </div>

          <p v-if="invoice.notes" class="mt-4 rounded-2xl bg-ink-100 p-3 text-sm text-mist">
            {{ invoice.notes }}
          </p>

          <div v-if="invoice.issuer?.phone || invoice.issuer?.address" class="mt-4 text-sm text-mist">
            <p v-if="invoice.issuer.phone">📞 {{ invoice.issuer.phone }}</p>
            <p v-if="invoice.issuer.address" class="mt-1">{{ invoice.issuer.address }}</p>
          </div>
        </div>

        <div v-if="invoice.can_pay" class="mt-5 page-enter">
          <button class="btn-primary w-full" :disabled="paying" @click="startPayment">
            {{ paying ? "Memproses..." : `Bayar Rp ${formatRupiah(invoice.total)}` }}
          </button>
        </div>

        <div v-else-if="invoice.payment_status === 'paid'" class="mt-5">
          <div class="alert-success text-center">Invoice ini sudah lunas. Terima kasih!</div>
        </div>

        <AlertBanner class="mt-4" :message="message" :type="isError ? 'error' : 'success'" />
      </template>
    </div>

    <Teleport to="body">
      <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
        <div class="w-full max-w-md rounded-2xl bg-ink-50 shadow-2xl">
          <div class="rounded-t-2xl bg-gradient-to-r from-ledger-dim via-ledger to-ledger-glow p-4">
            <h3 class="text-center text-lg font-bold text-white">Scan QRIS</h3>
          </div>

          <div class="space-y-3 p-4">
            <div class="flex justify-center">
              <div v-if="paymentData?.qr_string" class="rounded-xl border-2 border-ledger/20 bg-white p-2">
                <qrcode-vue :value="paymentData.qr_string" :size="180" level="H" />
              </div>
              <div v-else class="flex h-48 w-48 items-center justify-center rounded-xl bg-ink-100">
                <PageLoader />
              </div>
            </div>

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

            <p class="text-center text-xs text-mist">
              Scan dengan aplikasi e-wallet atau mobile banking Anda
            </p>

            <button
              class="btn-primary w-full"
              :disabled="checking"
              @click="checkPayment"
            >
              {{ checking ? "Mengecek..." : paymentSuccess ? "Pembayaran Berhasil!" : "Cek Status Pembayaran" }}
            </button>

            <button class="btn-ghost w-full" @click="closePaymentModal">Tutup</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import QrcodeVue from "qrcode.vue";
import MeshBackground from "../components/MeshBackground.vue";
import PageLoader from "../components/PageLoader.vue";
import EmptyState from "../components/EmptyState.vue";
import AlertBanner from "../components/AlertBanner.vue";
import { formatDate, formatRupiah } from "../utils/format";

const route = useRoute();

const loading = ref(true);
const invoice = ref(null);
const paying = ref(false);
const checking = ref(false);
const showPaymentModal = ref(false);
const paymentData = ref(null);
const paymentSuccess = ref(false);
const message = ref("");
const isError = ref(false);

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
    }
  } catch {
    /* ignore */
  } finally {
    loading.value = false;
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
      body: JSON.stringify({ token: route.params.token }),
    });
    const data = await res.json();

    if (res.ok && data.status) {
      paymentData.value = data.data;
      showPaymentModal.value = true;
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

async function checkPayment() {
  if (!paymentData.value?.payment_ref) return;

  checking.value = true;
  try {
    const res = await fetch(
      `/api/Invoice/PublicView/checkPayment?payment_ref=${paymentData.value.payment_ref}`
    );
    const data = await res.json();

    if (data.status && data.data?.payment_status === "paid") {
      paymentSuccess.value = true;
      invoice.value.payment_status = "paid";
      invoice.value.can_pay = false;
      message.value = "Pembayaran berhasil!";
      isError.value = false;
    } else if (data.data?.payment_status === "expired") {
      message.value = "Pembayaran kadaluarsa. Silakan buat pembayaran baru.";
      isError.value = true;
      showPaymentModal.value = false;
    } else {
      message.value = "Pembayaran belum diterima. Coba lagi setelah scan.";
      isError.value = true;
    }
  } catch {
    message.value = "Gagal cek status pembayaran";
    isError.value = true;
  } finally {
    checking.value = false;
  }
}

function closePaymentModal() {
  showPaymentModal.value = false;
  if (paymentSuccess.value) {
    loadInvoice();
  }
}

onMounted(loadInvoice);
</script>
