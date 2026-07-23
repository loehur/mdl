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
        :disabled="markingPaid"
        @click="markAsPaid"
      >
        {{ markingPaid ? "Memproses..." : "Tandai Lunas" }}
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
        @click="cancelInvoice"
      >
        Batalkan Invoice
      </button>

      <button
        v-if="invoice.status === 'cancelled'"
        class="btn-debit w-full"
        @click="deleteInvoice"
      >
        Hapus Invoice
      </button>

      <AlertBanner :message="message" :type="isError ? 'error' : 'success'" />
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import PageLoader from "../components/PageLoader.vue";
import AlertBanner from "../components/AlertBanner.vue";
import { formatDate, formatRupiahDisplay } from "../utils/format";
import { invoiceStatusChipClass, invoiceStatusLabel } from "../utils/invoiceStatus";

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const invoice = ref(null);
const copied = ref(false);
const message = ref("");
const isError = ref(false);
const markingPaid = ref(false);

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

async function markAsPaid() {
  if (!confirm("Tandai invoice ini sebagai LUNAS secara manual?\n\nGunakan jika pelanggan sudah bayar di luar QRIS (transfer/tunai).")) {
    return;
  }

  markingPaid.value = true;
  message.value = "";
  try {
    const res = await fetch("/api/Invoice/Invoices/markPaid", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: invoice.value.id }),
    });
    const data = await res.json();
    if (res.ok && data.status) {
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
    markingPaid.value = false;
  }
}

async function cancelInvoice() {
  if (!confirm("Batalkan invoice ini?")) return;

  try {
    const res = await fetch("/api/Invoice/Invoices/cancel", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: invoice.value.id }),
    });
    const data = await res.json();
    if (res.ok && data.status) {
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
  }
}

async function deleteInvoice() {
  if (!confirm("Hapus invoice ini secara permanen?")) return;

  try {
    const res = await fetch("/api/Invoice/Invoices/delete", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: invoice.value.id }),
    });
    const data = await res.json();
    if (res.ok && data.status) {
      router.push("/riwayat");
    } else {
      message.value = data.message || "Gagal menghapus invoice";
      isError.value = true;
    }
  } catch {
    message.value = "Gagal menghapus invoice";
    isError.value = true;
  }
}

onMounted(loadDetail);
</script>
