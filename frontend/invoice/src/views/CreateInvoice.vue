<template>
  <div class="page-enter space-y-5 pb-6">
    <section class="glass-strong p-5">
      <h2 class="section-title mb-4">Data Pelanggan</h2>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="field-label">Nama Pelanggan *</label>
          <input v-model="form.customer_name" class="field-input" required placeholder="Nama lengkap" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="field-label">Email</label>
            <input v-model="form.customer_email" class="field-input" type="email" placeholder="email@..." />
          </div>
          <div>
            <label class="field-label">Telepon</label>
            <input v-model="form.customer_phone" class="field-input" placeholder="08..." />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="field-label">Tanggal Invoice</label>
            <input v-model="form.issue_date" class="field-input" type="date" required />
          </div>
          <div>
            <label class="field-label">Jatuh Tempo</label>
            <input v-model="form.due_date" class="field-input" type="date" />
          </div>
        </div>
      </form>
    </section>

    <section class="glass-strong p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="section-title">Item Invoice</h2>
        <button type="button" class="btn-ghost px-3 py-2 text-sm" @click="addItem">+ Item</button>
      </div>

      <div class="space-y-4">
        <div
          v-for="(item, idx) in form.items"
          :key="idx"
          class="rounded-2xl border border-ink-200 bg-ink-50 p-4"
        >
          <div class="mb-3 flex items-center justify-between">
            <span class="text-sm font-semibold text-mist">Item {{ idx + 1 }}</span>
            <button
              v-if="form.items.length > 1"
              type="button"
              class="text-sm text-debit-dim"
              @click="removeItem(idx)"
            >
              Hapus
            </button>
          </div>
          <div class="space-y-3">
            <input
              v-model="item.description"
              class="field-input"
              placeholder="Deskripsi layanan/produk"
              required
            />
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="field-label">Qty</label>
                <input
                  v-model="item.quantity"
                  class="field-input"
                  type="number"
                  min="0.01"
                  step="0.01"
                />
              </div>
              <div>
                <label class="field-label">Harga Satuan</label>
                <AmountInput v-model="item.unit_price" />
              </div>
            </div>
            <p class="text-right text-sm font-semibold text-pearl">
              Subtotal: Rp {{ formatRupiah(itemSubtotal(item)) }}
            </p>
          </div>
        </div>
      </div>

      <div class="mt-4">
        <label class="field-label">Pajak (%)</label>
        <input v-model.number="form.tax_percent" class="field-input" type="number" min="0" max="100" step="0.01" />
      </div>

      <div class="mt-4 space-y-1 rounded-2xl bg-ledger/5 p-4">
        <div class="flex justify-between text-sm text-mist">
          <span>Subtotal</span>
          <span>Rp {{ formatRupiah(subtotal) }}</span>
        </div>
        <div class="flex justify-between text-sm text-mist">
          <span>Pajak ({{ form.tax_percent || 0 }}%)</span>
          <span>Rp {{ formatRupiah(taxAmount) }}</span>
        </div>
        <div class="flex justify-between border-t border-ink-200 pt-2">
          <span class="font-bold text-pearl">Total</span>
          <span class="money-display-sm text-ledger-dim">Rp {{ formatRupiah(total) }}</span>
        </div>
      </div>

      <div class="mt-4">
        <label class="field-label">Catatan</label>
        <textarea v-model="form.notes" class="field-input min-h-[80px]" placeholder="Catatan tambahan (opsional)" />
      </div>

      <button class="btn-primary mt-5 w-full" :disabled="saving" @click="onSubmit">
        {{ saving ? "Menyimpan..." : "Buat Invoice" }}
      </button>

      <AlertBanner class="mt-4" :message="message" :type="isError ? 'error' : 'success'" />
    </section>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import AmountInput from "../components/AmountInput.vue";
import AlertBanner from "../components/AlertBanner.vue";
import { amountInputToNumber, formatRupiah, todayISO } from "../utils/format";

const router = useRouter();

const form = ref({
  customer_name: "",
  customer_email: "",
  customer_phone: "",
  issue_date: todayISO(),
  due_date: "",
  tax_percent: 0,
  notes: "",
  items: [{ description: "", quantity: 1, unit_price: "" }],
});

const saving = ref(false);
const message = ref("");
const isError = ref(false);

function addItem() {
  form.value.items.push({ description: "", quantity: 1, unit_price: "" });
}

function removeItem(idx) {
  form.value.items.splice(idx, 1);
}

function itemSubtotal(item) {
  const qty = Number(item.quantity) || 0;
  const price = amountInputToNumber(item.unit_price);
  return qty * price;
}

const subtotal = computed(() =>
  form.value.items.reduce((sum, item) => sum + itemSubtotal(item), 0)
);

const taxAmount = computed(() => {
  const pct = Number(form.value.tax_percent) || 0;
  return subtotal.value * (pct / 100);
});

const total = computed(() => subtotal.value + taxAmount.value);

async function onSubmit() {
  if (total.value <= 0) {
    message.value = "Total invoice harus lebih dari 0";
    isError.value = true;
    return;
  }

  saving.value = true;
  message.value = "";
  isError.value = false;

  try {
    const payload = {
      customer_name: form.value.customer_name,
      customer_email: form.value.customer_email,
      customer_phone: form.value.customer_phone,
      issue_date: form.value.issue_date,
      due_date: form.value.due_date || null,
      tax_percent: form.value.tax_percent || 0,
      notes: form.value.notes,
      items: form.value.items.map((item) => ({
        description: item.description,
        quantity: Number(item.quantity) || 1,
        unit_price: amountInputToNumber(item.unit_price),
      })),
    };

    const res = await fetch("/api/Invoice/Invoices/create", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!res.ok || !data.status) {
      message.value = data.message || "Gagal membuat invoice";
      isError.value = true;
      return;
    }

    router.push(`/detail/${data.data.id}`);
  } catch {
    message.value = "Tidak dapat terhubung ke server";
    isError.value = true;
  } finally {
    saving.value = false;
  }
}
</script>
