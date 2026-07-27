<template>
  <div class="space-y-5 pb-6">
    <PageLoader v-if="loading" />

    <template v-else>
      <section class="glass-strong p-5">
        <h2 class="section-title mb-4">Data Langganan</h2>

        <form class="space-y-4" @submit.prevent="onSubmit">
          <div>
            <label class="field-label">Pilih Pelanggan *</label>
            <CustomerSelect v-model="form.customer_id" :options="customers" />
          </div>

          <div>
            <label class="field-label">Judul *</label>
            <input
              v-model="form.title"
              class="field-input"
              required
              placeholder="Judul tagihan berulang"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="field-label">Periode *</label>
              <select v-model="form.period" class="field-input">
                <option value="monthly">Bulanan</option>
                <option value="yearly">Tahunan</option>
              </select>
            </div>
            <div>
              <label class="field-label">Terbit berikutnya *</label>
              <input v-model="form.next_issue_date" class="field-input" type="date" required />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="field-label">Jatuh tempo (hari)</label>
              <input
                v-model.number="form.due_days"
                class="field-input"
                type="number"
                min="0"
                placeholder="mis. 7"
              />
            </div>
            <div>
              <label class="field-label">Pajak (%)</label>
              <input
                v-model.number="form.tax_percent"
                class="field-input"
                type="number"
                min="0"
                max="100"
                step="0.01"
              />
            </div>
          </div>

          <div>
            <label class="field-label">Subscription ID</label>
            <input
              v-model="form.subscription_id"
              class="field-input"
              placeholder="Kosongkan = otomatis"
            />
          </div>

          <label class="flex items-center gap-3 rounded-2xl border border-ink-200 bg-ink-50 p-4">
            <input
              v-model="form.is_active"
              type="checkbox"
              class="h-4 w-4 rounded border-ink-200"
            />
            <span class="text-sm font-semibold text-pearl">Langganan aktif</span>
          </label>
        </form>
      </section>

      <section class="glass-strong p-5">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="section-title">Item Tagihan</h2>
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
          <textarea
            v-model="form.notes"
            class="field-input min-h-[80px]"
            placeholder="Catatan tambahan (opsional)"
          />
        </div>

        <button class="btn-primary mt-5 w-full" :disabled="saving" @click="onSubmit">
          {{ saving ? "Menyimpan..." : "Simpan Perubahan" }}
        </button>

        <button class="btn-ghost mt-3 w-full" type="button" @click="router.push('/langganan')">
          Batal
        </button>

        <AlertBanner class="mt-4" :message="message" :type="isError ? 'error' : 'success'" />
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AmountInput from "../components/AmountInput.vue";
import AlertBanner from "../components/AlertBanner.vue";
import CustomerSelect from "../components/CustomerSelect.vue";
import PageLoader from "../components/PageLoader.vue";
import { amountInputToNumber, formatRupiah, todayISO, toAmountDigits } from "../utils/format";

const route = useRoute();
const router = useRouter();

const editId = computed(() => {
  const id = Number(route.params.id);
  return Number.isFinite(id) && id > 0 ? id : null;
});

const form = ref({
  customer_id: "",
  title: "",
  period: "monthly",
  next_issue_date: todayISO(),
  due_days: 7,
  tax_percent: 0,
  notes: "",
  subscription_id: "",
  is_active: true,
  items: [{ description: "", quantity: 1, unit_price: "" }],
});

const customers = ref([]);
const loading = ref(true);
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

async function loadCustomers() {
  try {
    const res = await fetch("/api/Invoice/Customers/list");
    const data = await res.json();
    if (res.ok && data.status) {
      customers.value = data.data.customers || [];
    }
  } catch {
    /* ignore */
  }
}

async function loadDetail() {
  if (!editId.value) {
    message.value = "ID langganan tidak valid";
    isError.value = true;
    loading.value = false;
    return;
  }

  loading.value = true;
  message.value = "";
  isError.value = false;

  try {
    const res = await fetch(`/api/Invoice/RecurringBills/detail?id=${editId.value}`);
    const data = await res.json();

    if (!res.ok || !data.status) {
      message.value = data.message || "Langganan tidak ditemukan";
      isError.value = true;
      return;
    }

    const bill = data.data;
    const items =
      Array.isArray(bill.items) && bill.items.length
        ? bill.items.map((item) => ({
            description: item.description || "",
            quantity: item.quantity || 1,
            unit_price: toAmountDigits(item.unit_price),
          }))
        : [{ description: "", quantity: 1, unit_price: "" }];

    form.value = {
      customer_id: bill.customer_id ? String(bill.customer_id) : "",
      title: bill.title || "",
      period: bill.period || "monthly",
      next_issue_date: bill.next_issue_date || todayISO(),
      due_days: bill.due_days ?? 7,
      tax_percent: bill.tax_percent || 0,
      notes: bill.notes || "",
      subscription_id: bill.subscription_id || "",
      is_active: !!bill.is_active,
      items,
    };
  } catch {
    message.value = "Gagal memuat langganan";
    isError.value = true;
  } finally {
    loading.value = false;
  }
}

async function onSubmit() {
  if (!form.value.customer_id) {
    message.value = "Pilih pelanggan terlebih dahulu";
    isError.value = true;
    return;
  }

  if (!form.value.title.trim()) {
    message.value = "Judul wajib diisi";
    isError.value = true;
    return;
  }

  if (!form.value.next_issue_date) {
    message.value = "Tanggal terbit berikutnya wajib diisi";
    isError.value = true;
    return;
  }

  if (total.value <= 0) {
    message.value = "Total harus lebih dari 0";
    isError.value = true;
    return;
  }

  saving.value = true;
  message.value = "";
  isError.value = false;

  try {
    const payload = {
      id: editId.value,
      customer_id: Number(form.value.customer_id),
      title: form.value.title.trim(),
      period: form.value.period || "monthly",
      next_issue_date: form.value.next_issue_date,
      due_days:
        form.value.due_days === "" || form.value.due_days === null
          ? null
          : Number(form.value.due_days),
      tax_percent: form.value.tax_percent || 0,
      notes: form.value.notes,
      subscription_id: form.value.subscription_id.trim() || null,
      is_active: !!form.value.is_active,
      items: form.value.items.map((item) => ({
        description: item.description,
        quantity: Number(item.quantity) || 1,
        unit_price: amountInputToNumber(item.unit_price),
      })),
    };

    const res = await fetch("/api/Invoice/RecurringBills/update", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!res.ok || !data.status) {
      message.value = data.message || "Gagal memperbarui langganan";
      isError.value = true;
      return;
    }

    router.push("/langganan");
  } catch {
    message.value = "Tidak dapat terhubung ke server";
    isError.value = true;
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  await loadCustomers();
  await loadDetail();
});
</script>
