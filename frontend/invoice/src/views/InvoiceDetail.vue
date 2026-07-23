<template>
  <div class="space-y-5 pb-6">
    <PageLoader v-if="loading" />

    <template v-else-if="invoice">
      <div class="glass-strong p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="label-caps">Invoice</p>
            <h2 class="section-title mt-1">{{ invoice.invoice_number }}</h2>
            <p class="text-sm text-mist">{{ invoice.customer_name }}</p>
          </div>
          <span class="chip" :class="invoiceStatusChipClass(invoice)">
            {{ invoiceStatusLabel(invoice) }}
          </span>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
          <div>
            <p class="text-mist">Tanggal</p>
            <p class="font-semibold text-pearl">{{ formatDate(invoice.issue_date) }}</p>
          </div>
          <div>
            <p class="text-mist">Jatuh Tempo</p>
            <p class="font-semibold text-pearl">{{ invoice.due_date ? formatDate(invoice.due_date) : '-' }}</p>
          </div>
        </div>
      </div>

      <section class="glass-strong p-5">
        <h3 class="section-title mb-3">Item</h3>
        <div class="space-y-3">
          <div
            v-for="item in invoice.items"
            :key="item.id"
            class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 border-b border-ink-200 pb-3 last:border-0"
          >
            <div class="min-w-0">
              <p class="text-sm font-semibold text-pearl">{{ item.description }}</p>
              <p class="text-xs text-mist">{{ item.quantity }} × {{ formatRupiahDisplay(item.unit_price) }}</p>
            </div>
            <p class="min-w-max whitespace-nowrap text-right text-sm font-bold tabular-nums text-pearl">
              {{ formatRupiahDisplay(item.amount) }}
            </p>
          </div>
        </div>

        <div class="mt-4 space-y-1 border-t border-ink-200 pt-3">
          <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 text-sm text-mist">
            <span>Subtotal</span>
            <span class="min-w-max whitespace-nowrap text-right tabular-nums">{{ formatRupiahDisplay(invoice.subtotal) }}</span>
          </div>
          <div v-if="invoice.tax_amount > 0" class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 text-sm text-mist">
            <span>Pajak ({{ invoice.tax_percent }}%)</span>
            <span class="min-w-max whitespace-nowrap text-right tabular-nums">{{ formatRupiahDisplay(invoice.tax_amount) }}</span>
          </div>
          <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 pt-1">
            <span class="font-bold text-pearl">Total</span>
            <span class="money-display-sm min-w-max whitespace-nowrap text-right tabular-nums text-ledger-dim">
              {{ formatRupiahDisplay(invoice.total) }}
            </span>
          </div>
        </div>
      </section>

      <section v-if="invoice.notes" class="glass-strong p-5">
        <h3 class="section-title mb-3">Catatan</h3>
        <p class="whitespace-pre-wrap text-sm text-mist">{{ invoice.notes }}</p>
      </section>

      <section v-if="invoice.status !== 'cancelled'" class="glass-strong p-5">
        <h3 class="section-title mb-3">Bagikan Invoice</h3>
        <p class="mb-3 text-sm text-mist">Salin teks berikut dan kirim ke pelanggan:</p>
        <pre class="whitespace-pre-wrap break-all rounded-2xl border border-ink-200 bg-ink-50 p-4 text-sm text-pearl">{{ invoice.share_text }}</pre>
        <div class="mt-3 flex gap-3">
          <button class="btn-primary flex-1" @click="copyShareText">
            {{ copied ? "Tersalin!" : "Salin Teks" }}
          </button>
          <button class="btn-ghost flex-1" @click="copyLink">Salin Link</button>
        </div>
      </section>

      <div v-if="invoice.status === 'cancelled'" class="alert border-mist/30 bg-ink-100 text-mist">
        Invoice ini telah dibatalkan dan tidak dapat dibagikan atau dibayar.
      </div>

      <button
        v-if="invoice.payment_status !== 'paid' && invoice.status !== 'cancelled'"
        class="btn-primary w-full"
        :disabled="actionLoading"
        @click="openConfirm('paid')"
      >
        Tandai Lunas
      </button>

      <router-link
        v-if="invoice.payment_status !== 'paid' && invoice.status !== 'cancelled'"
        :to="`/edit/${invoice.id}`"
        class="btn-ghost block w-full text-center"
      >
        Edit Invoice
      </router-link>

      <button
        v-if="invoice.payment_status !== 'paid' && invoice.status !== 'cancelled'"
        class="btn-debit w-full"
        :disabled="actionLoading"
        @click="openConfirm('cancel')"
      >
        Batalkan Invoice
      </button>

      <button
        v-if="invoice.status === 'cancelled'"
        class="btn-debit w-full"
        :disabled="actionLoading"
        @click="openConfirm('delete')"
      >
        Hapus Invoice
      </button>

      <AlertBanner :message="message" :type="isError ? 'error' : 'success'" />
    </template>

    <ConfirmModal
      :open="confirmOpen"
      :title="confirmConfig.title"
      :message="confirmConfig.message"
      :detail="confirmConfig.detail"
      :confirm-label="confirmConfig.confirmLabel"
      :variant="confirmConfig.variant"
      :loading="actionLoading"
      @confirm="runConfirmedAction"
      @cancel="closeConfirm"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import PageLoader from "../components/PageLoader.vue";
import AlertBanner from "../components/AlertBanner.vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import { formatDate, formatRupiahDisplay } from "../utils/format";
import { invoiceStatusChipClass, invoiceStatusLabel } from "../utils/invoiceStatus";

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const invoice = ref(null);
const copied = ref(false);
const message = ref("");
const isError = ref(false);
const actionLoading = ref(false);
const confirmOpen = ref(false);
const confirmAction = ref(null);

const confirmConfig = computed(() => {
  const inv = invoice.value;
  const number = inv?.invoice_number || "";
  const total = inv ? formatRupiahDisplay(inv.total) : "";
  const customer = inv?.customer_name || "";

  if (confirmAction.value === "paid") {
    return {
      title: "Tandai Lunas?",
      message: "Gunakan ini jika pelanggan sudah membayar di luar QRIS (transfer, tunai, atau metode lain).",
      detail: `${number} · ${customer} · ${total}`,
      confirmLabel: "Ya, Tandai Lunas",
      variant: "success",
    };
  }

  if (confirmAction.value === "cancel") {
    return {
      title: "Batalkan Invoice?",
      message: "Invoice yang dibatalkan tidak bisa dibayar pelanggan. Anda masih bisa menghapusnya nanti.",
      detail: `${number} · ${customer}`,
      confirmLabel: "Ya, Batalkan",
      variant: "danger",
    };
  }

  if (confirmAction.value === "delete") {
    return {
      title: "Hapus Permanen?",
      message: "Tindakan ini tidak dapat dibatalkan. Data invoice akan dihapus selamanya.",
      detail: `${number} · ${customer}`,
      confirmLabel: "Ya, Hapus",
      variant: "danger",
    };
  }

  return {
    title: "Konfirmasi",
    message: "",
    detail: "",
    confirmLabel: "Ya",
    variant: "info",
  };
});

function openConfirm(action) {
  confirmAction.value = action;
  confirmOpen.value = true;
}

function closeConfirm() {
  if (actionLoading.value) return;
  confirmOpen.value = false;
  confirmAction.value = null;
}

async function loadDetail() {
  loading.value = true;
  try {
    const res = await fetch(`/api/Invoice/Invoices/detail?id=${route.params.id}`);
    const data = await res.json();
    if (res.ok && data.status) {
      invoice.value = data.data;
    } else {
      message.value = data.message || "Invoice tidak ditemukan";
      isError.value = true;
    }
  } catch {
    message.value = "Gagal memuat invoice";
    isError.value = true;
  } finally {
    loading.value = false;
  }
}

async function copyShareText() {
  if (!invoice.value?.share_text) return;
  try {
    await navigator.clipboard.writeText(invoice.value.share_text);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
  } catch {
    message.value = "Gagal menyalin teks";
    isError.value = true;
  }
}

async function copyLink() {
  if (!invoice.value?.public_url) return;
  try {
    await navigator.clipboard.writeText(invoice.value.public_url);
    message.value = "Link berhasil disalin";
    isError.value = false;
  } catch {
    message.value = "Gagal menyalin link";
    isError.value = true;
  }
}

async function runConfirmedAction() {
  if (confirmAction.value === "paid") {
    await doMarkAsPaid();
  } else if (confirmAction.value === "cancel") {
    await doCancelInvoice();
  } else if (confirmAction.value === "delete") {
    await doDeleteInvoice();
  }
}

async function doMarkAsPaid() {
  actionLoading.value = true;
  message.value = "";
  try {
    const res = await fetch("/api/Invoice/Invoices/markPaid", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: invoice.value.id }),
    });
    const data = await res.json();
    if (res.ok && data.status) {
      confirmOpen.value = false;
      confirmAction.value = null;
      await loadDetail();
      message.value = data.message || "Invoice berhasil ditandai lunas";
      isError.value = false;
    } else {
      message.value = data.message || "Gagal menandai lunas";
      isError.value = true;
    }
  } catch {
    message.value = "Gagal menandai lunas";
    isError.value = true;
  } finally {
    actionLoading.value = false;
  }
}

async function doCancelInvoice() {
  actionLoading.value = true;
  try {
    const res = await fetch("/api/Invoice/Invoices/cancel", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: invoice.value.id }),
    });
    const data = await res.json();
    if (res.ok && data.status) {
      confirmOpen.value = false;
      confirmAction.value = null;
      await loadDetail();
      message.value = "Invoice berhasil dibatalkan";
      isError.value = false;
    } else {
      message.value = data.message || "Gagal membatalkan";
      isError.value = true;
    }
  } catch {
    message.value = "Gagal membatalkan invoice";
    isError.value = true;
  } finally {
    actionLoading.value = false;
  }
}

async function doDeleteInvoice() {
  actionLoading.value = true;
  try {
    const res = await fetch("/api/Invoice/Invoices/delete", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: invoice.value.id }),
    });
    const data = await res.json();
    if (res.ok && data.status) {
      confirmOpen.value = false;
      confirmAction.value = null;
      router.push("/riwayat");
    } else {
      message.value = data.message || "Gagal menghapus invoice";
      isError.value = true;
    }
  } catch {
    message.value = "Gagal menghapus invoice";
    isError.value = true;
  } finally {
    actionLoading.value = false;
  }
}

onMounted(loadDetail);
</script>
