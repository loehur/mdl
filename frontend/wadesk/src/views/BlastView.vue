<template>
  <div class="min-h-screen bg-ink-950 text-slate-100 font-body">
    <!-- Header -->
    <header class="h-14 px-4 border-b border-white/10 flex items-center justify-between bg-ink-900/80 sticky top-0 z-10">
      <div class="flex items-center gap-3">
        <router-link to="/" class="text-slate-400 hover:text-slate-100 text-sm">← Inbox</router-link>
        <span class="text-slate-400/40">|</span>
        <span class="font-display font-semibold text-lg text-slate-100">Blast</span>
      </div>
      <div class="flex items-center gap-2">
        <ThemeToggle compact />
        <router-link v-if="auth.isAdmin" to="/admin" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-200 text-sm">Admin</router-link>
      </div>
    </header>

    <div
      v-if="auth.isAdmin && !auth.canSendWa"
      class="px-4 py-2 bg-amber-500/10 border-b border-amber-500/20 text-amber-200 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
    >
      <span>Anda belum masuk team — tidak bisa membuat blast.</span>
      <router-link to="/admin" class="text-accent-soft hover:underline shrink-0">Masuk team di Admin →</router-link>
    </div>

    <div class="max-w-4xl mx-auto p-4 space-y-6">

      <!-- ================================================================
           SECTION 1: Setup blast (key, template, campaign name)
      ================================================================ -->
      <section class="card space-y-4">
        <h2 class="font-display font-semibold text-base">1. Setup blast</h2>

        <!-- API Key -->
        <div>
          <label class="label">Channel / nomor WA</label>
          <select v-model="form.channel_id" class="field" :disabled="!auth.canSendWa" @change="onKeyChange">
            <option disabled value="">Pilih channel</option>
            <option v-for="k in keys" :key="k.id" :value="k.id">
              {{ k.label }} ({{ k.phone_number }}) — {{ k.team_name }}
            </option>
          </select>
          <p v-if="keyQuota !== null" class="text-xs mt-1.5" :class="keyQuota.balance > 0 ? 'text-slate-400' : 'text-rose-400'">
            Kuota template team <span class="text-slate-200">{{ keyQuota.team_name }}</span>:
            <span class="font-semibold text-accent">{{ keyQuota.balance }}</span>
          </p>
        </div>

        <!-- Template -->
        <div>
          <label class="label">Template</label>
          <select v-model="form.template_id" class="field" :disabled="!form.channel_id" @change="onTemplateChange">
            <option disabled value="">Pilih template</option>
            <option v-for="t in filteredTemplates" :key="t.id" :value="t.id">
              {{ t.template_name }} ({{ t.language }})
            </option>
          </select>
          <div
            v-if="blastTemplatePreview"
            class="mt-2 text-xs text-slate-300 whitespace-pre-wrap rounded-lg bg-ink-950/60 p-3 border border-white/5"
          >
            <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-1.5">Preview teks (placeholder)</p>
            {{ blastTemplatePreview }}
          </div>
        </div>

        <!-- Campaign Name -->
        <div>
          <label class="label">Campaign name <span class="text-rose-400">*</span></label>
          <input
            v-model="form.campaign_name"
            class="field"
            placeholder="contoh: Promo Juli 2026"
            maxlength="150"
          />
          <p class="text-xs text-slate-500 mt-1">Nama pengelompokan blast ini. Satu campaign bisa punya banyak blast.</p>
        </div>

        <!-- CSV Columns info -->
        <div v-if="csvParams.length > 0" class="rounded-xl bg-ink-950/60 border border-white/5 p-4 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-300">Kolom CSV untuk template ini</p>
            <button type="button" class="btn-sm btn-sm-accent" @click="downloadSample">
              ↓ Download sample CSV
            </button>
          </div>
          <table class="w-full text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-white/5">
                <th class="text-left py-1 pr-3">Kolom</th>
                <th class="text-left py-1 pr-3">Label</th>
                <th class="text-left py-1 pr-3">Contoh</th>
                <th class="text-left py-1">Wajib</th>
              </tr>
            </thead>
            <tbody>
              <tr class="border-b border-white/5">
                <td class="py-1 pr-3 font-mono text-accent">phone</td>
                <td class="py-1 pr-3 text-slate-300">Nomor WhatsApp</td>
                <td class="py-1 pr-3 text-slate-400">62812xxxx</td>
                <td class="py-1 text-rose-400">✓</td>
              </tr>
              <tr v-for="p in csvParams" :key="p.key" class="border-b border-white/5">
                <td class="py-1 pr-3 font-mono text-accent">{{ p.key }}</td>
                <td class="py-1 pr-3 text-slate-300">{{ p.label }}</td>
                <td class="py-1 pr-3 text-slate-400">{{ p.example || '—' }}</td>
                <td class="py-1" :class="p.required ? 'text-rose-400' : 'text-slate-500'">
                  {{ p.required ? '✓' : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ================================================================
           SECTION 2: Upload CSV
      ================================================================ -->
      <section class="card space-y-4" v-if="csvParams.length > 0">
        <div class="flex items-center justify-between">
          <h2 class="font-display font-semibold text-base">2. Upload CSV</h2>
          <span class="text-xs text-slate-500">Maks. 250 baris per blast</span>
        </div>

        <div
          class="border-2 border-dashed border-white/10 rounded-xl p-8 text-center cursor-pointer hover:border-accent/40 transition"
          @click="triggerFileInput"
          @dragover.prevent
          @drop.prevent="onFileDrop"
        >
          <input ref="fileInput" type="file" accept=".csv" class="hidden" @change="onFileChange" />
          <p class="text-slate-400 text-sm">
            {{ uploadedFile ? uploadedFile.name : 'Klik atau drag & drop file CSV di sini' }}
          </p>
          <p v-if="uploadedFile" class="text-xs text-slate-500 mt-1">{{ parsedRows.length }} baris data</p>
        </div>

        <!-- Validation errors -->
        <div v-if="csvErrors.length > 0" class="rounded-xl bg-rose-500/10 border border-rose-500/20 p-3 space-y-1">
          <p class="text-sm font-medium text-rose-400">Validasi gagal:</p>
          <ul class="text-xs text-rose-300 space-y-0.5 list-disc list-inside">
            <li v-for="(e, i) in csvErrors" :key="i">{{ e }}</li>
          </ul>
        </div>

        <!-- Preview table -->
        <div v-if="parsedRows.length > 0 && csvErrors.length === 0" class="overflow-x-auto">
          <p class="text-xs text-slate-500 mb-2">Preview {{ Math.min(parsedRows.length, 10) }} dari {{ parsedRows.length }} baris</p>
          <table class="w-full text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-white/10">
                <th v-for="h in csvHeaders" :key="h" class="text-left py-1 pr-3 font-normal">{{ h }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in parsedRows.slice(0, 10)" :key="i" class="border-b border-white/5">
                <td v-for="h in csvHeaders" :key="h" class="py-1 pr-3 text-slate-300">{{ row[h] || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Submit -->
        <div v-if="parsedRows.length > 0 && csvErrors.length === 0 && form.campaign_name.trim()">
          <p
            v-if="keyQuota !== null && parsedRows.length > keyQuota.balance"
            class="text-sm text-rose-400 mb-2"
          >
            Kuota team tidak cukup: butuh {{ parsedRows.length }}, sisa {{ keyQuota.balance }}.
          </p>
          <button
            type="button"
            class="btn w-full py-3"
            :disabled="submitting || !auth.canSendWa || (keyQuota !== null && parsedRows.length > keyQuota.balance)"
            @click="submitBlast"
          >
            {{ submitting ? 'Membuat blast...' : `Mulai Blast (${parsedRows.length} penerima — maks. 250)` }}
          </button>
          <p v-if="submitError" class="text-sm text-rose-400 mt-2">{{ submitError }}</p>
        </div>
        <p v-else-if="parsedRows.length > 0 && !form.campaign_name.trim()" class="text-xs text-amber-400">
          Isi campaign name dulu sebelum blast.
        </p>
      </section>

      <!-- ================================================================
           SECTION 3: Riwayat blast
      ================================================================ -->
      <section class="card space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-display font-semibold text-base">3. Riwayat blast</h2>
          <button type="button" class="btn-sm" @click="loadBlasts">↻ Refresh</button>
        </div>

        <!-- Filter -->
        <div class="flex gap-2 items-center">
          <input v-model="filter.campaign" class="field flex-1 text-sm py-1.5" placeholder="Filter campaign..." @input="loadBlasts" />
          <select v-model="filter.campaign" class="field text-sm py-1.5 w-48" @change="loadBlasts">
            <option value="">Semua campaign</option>
            <option v-for="c in campaignOptions" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>

        <div v-if="blasts.length === 0 && !loadingBlasts" class="text-sm text-slate-500 text-center py-4">Belum ada blast</div>
        <div v-if="loadingBlasts" class="text-sm text-slate-500 text-center py-4">Memuat...</div>

        <div class="space-y-3">
          <div
            v-for="b in blasts"
            :key="b.id"
            class="rounded-xl border border-white/5 bg-ink-900/40 p-4 cursor-pointer hover:border-accent/30 transition"
            @click="openDetail(b)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-medium text-slate-100 truncate">{{ b.campaign_name }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ b.template_name }} · {{ b.key_label }} ({{ b.wa_number }})</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ b.created_by_name }} · {{ formatDate(b.created_at) }}</p>
              </div>
              <div class="shrink-0 text-right">
                <span class="status-badge" :class="statusClass(b.status)">{{ b.status }}</span>
                <p class="text-xs text-slate-400 mt-1">{{ b.sent }}/{{ b.total }} terkirim</p>
                <p v-if="Number(b.failed) > 0" class="text-xs text-rose-400">{{ b.failed }} gagal</p>
              </div>
            </div>
            <!-- Progress bar -->
            <div class="mt-3 h-1.5 rounded-full bg-white/5 overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="b.status === 'done' ? 'bg-emerald-400' : 'bg-accent'"
                :style="{ width: progressPct(b) + '%' }"
              />
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="blastsTotal > 30" class="flex gap-2 justify-center">
          <button :disabled="blastsPage <= 1" class="btn-sm" @click="changePage(-1)">‹ Prev</button>
          <span class="text-xs text-slate-500 self-center">Halaman {{ blastsPage }}</span>
          <button :disabled="blastsPage * 30 >= blastsTotal" class="btn-sm" @click="changePage(1)">Next ›</button>
        </div>
      </section>
    </div>

    <!-- ================================================================
         DETAIL MODAL
    ================================================================ -->
    <div v-if="detailBlast" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="w-full max-w-2xl rounded-2xl bg-ink-900 border border-white/10 shadow-2xl max-h-[90vh] flex flex-col">
        <div class="p-4 border-b border-white/10 flex items-center justify-between sticky top-0 bg-ink-900">
          <div>
            <p class="font-display font-semibold">{{ detailBlast.campaign_name }}</p>
            <p class="text-xs text-slate-400">{{ detailBlast.template_name }} · <span :class="statusClass(detailBlast.status)">{{ detailBlast.status }}</span></p>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="['pending','processing'].includes(detailBlast.status)"
              class="px-3 py-1.5 rounded-lg bg-rose-500/20 text-rose-300 hover:bg-rose-500/30 text-sm"
              :disabled="cancelling"
              @click="askCancelBlast"
            >
              {{ cancelling ? '...' : 'Batalkan' }}
            </button>
            <button class="text-slate-400 hover:text-slate-100 text-lg" @click="detailBlast = null; clearDetailPoll()">✕</button>
          </div>
        </div>

        <div class="overflow-y-auto flex-1 p-4 space-y-4">
          <!-- Stats -->
          <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-xl bg-ink-950/60 p-3">
              <p class="text-2xl font-bold">{{ detailBlast.total }}</p>
              <p class="text-xs text-slate-500">Total</p>
            </div>
            <div class="rounded-xl bg-emerald-500/10 p-3">
              <p class="text-2xl font-bold text-emerald-400">{{ detailBlast.sent }}</p>
              <p class="text-xs text-slate-500">Terkirim</p>
            </div>
            <div class="rounded-xl bg-rose-500/10 p-3">
              <p class="text-2xl font-bold text-rose-400">{{ detailBlast.failed }}</p>
              <p class="text-xs text-slate-500">Gagal</p>
            </div>
          </div>
          <!-- Progress -->
          <div class="h-2 rounded-full bg-white/5 overflow-hidden">
            <div
              class="h-full rounded-full transition-all"
              :class="detailBlast.status === 'done' ? 'bg-emerald-400' : 'bg-accent'"
              :style="{ width: progressPct(detailBlast) + '%' }"
            />
          </div>

          <!-- Recipients table -->
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead>
                <tr class="text-slate-500 border-b border-white/10">
                  <th class="text-left py-1 pr-3 font-normal">Phone</th>
                  <th class="text-left py-1 pr-3 font-normal">Status</th>
                  <th class="text-left py-1 font-normal">Keterangan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in detailRecipients" :key="r.id" class="border-b border-white/5">
                  <td class="py-1 pr-3 font-mono">{{ r.phone }}</td>
                  <td class="py-1 pr-3" :class="r.status === 'sent' ? 'text-emerald-400' : r.status === 'failed' ? 'text-rose-400' : 'text-slate-400'">
                    {{ r.status }}
                  </td>
                  <td class="py-1 text-slate-400">{{ r.error || (r.sent_at ? formatDate(r.sent_at) : '—') }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination detail -->
          <div v-if="detailTotal > 50" class="flex gap-2 justify-center">
            <button :disabled="detailPage <= 1" class="btn-sm" @click="changeDetailPage(-1)">‹</button>
            <span class="text-xs text-slate-500 self-center">Hal {{ detailPage }}</span>
            <button :disabled="detailPage * 50 >= detailTotal" class="btn-sm" @click="changeDetailPage(1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <ConfirmModal
      v-if="dialog.open"
      :title="dialog.title"
      :message="dialog.message"
      :mode="dialog.mode"
      :confirm-label="dialog.confirmLabel"
      :danger="dialog.danger"
      @confirm="onDialogConfirm"
      @close="closeDialog"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { api } from '../api';
import ConfirmModal from '../components/ConfirmModal.vue';
import ThemeToggle from '../components/ThemeToggle.vue';
import {
  buildTemplateHeaders,
  downloadSampleCsv,
  parseCsv,
  validateBlastRows,
  rowsToBlastPayload,
} from '../utils/csv.js';
import { buildFilledPreview } from '../utils/templatePreview.js';

const auth = useAuthStore();

const dialog = reactive({
  open: false,
  mode: 'confirm',
  title: 'Konfirmasi',
  message: '',
  confirmLabel: 'Ya',
  danger: false,
  action: null,
});

function askConfirm({ title, message, confirmLabel = 'Ya', danger = false, action }) {
  dialog.open = true;
  dialog.mode = 'confirm';
  dialog.title = title || 'Konfirmasi';
  dialog.message = message;
  dialog.confirmLabel = confirmLabel;
  dialog.danger = danger;
  dialog.action = action;
}

function showAlert(message, title = 'Info') {
  dialog.open = true;
  dialog.mode = 'alert';
  dialog.title = title;
  dialog.message = message;
  dialog.confirmLabel = 'OK';
  dialog.danger = false;
  dialog.action = null;
}

function closeDialog() {
  dialog.open = false;
  dialog.action = null;
}

async function onDialogConfirm() {
  const action = dialog.action;
  closeDialog();
  if (typeof action === 'function') {
    await action();
  }
}
// ---- state ----------------------------------------------------------------
const keys = ref([]);
const templates = ref([]);

const form = reactive({
  channel_id: '',
  template_id: '',
  campaign_name: '',
});

const csvParams = ref([]);       // from csvHeaders API
const csvHeaders = ref([]);      // derived
const uploadedFile = ref(null);
const parsedRows = ref([]);
const csvErrors = ref([]);
const fileInput = ref(null);
const keyQuota = ref(null);

const submitting = ref(false);
const submitError = ref('');

// blast list
const blasts = ref([]);
const blastsTotal = ref(0);
const blastsPage = ref(1);
const loadingBlasts = ref(false);
const campaignOptions = ref([]);
const filter = reactive({ campaign: '' });

// detail modal
const detailBlast = ref(null);
const detailRecipients = ref([]);
const detailTotal = ref(0);
const detailPage = ref(1);
const cancelling = ref(false);
let detailPollTimer = null;
let listPollTimer = null;

// ---- computed -------------------------------------------------------------
const filteredTemplates = computed(() => templates.value);

const selectedTemplate = computed(() =>
  filteredTemplates.value.find((t) => Number(t.id) === Number(form.template_id))
);

/** Template body with placeholders (no CSV values). */
const blastTemplatePreview = computed(() => {
  const tpl = selectedTemplate.value;
  if (!tpl) return "";
  return buildFilledPreview(tpl.body_preview, tpl.params, {}, {});
});

// ---- lifecycle ------------------------------------------------------------
onMounted(async () => {
  await loadKeys();
  await loadTemplates();
  await loadBlasts();
  // Poll list every 10s to refresh active blast progress
  listPollTimer = setInterval(() => {
    if (!detailBlast.value) loadBlasts();
  }, 10000);
});

onUnmounted(() => {
  clearDetailPoll();
  if (listPollTimer) clearInterval(listPollTimer);
});

// ---- API calls ------------------------------------------------------------
async function loadKeys() {
  try {
    const res = await api('/WaDesk/Channels/list');
    keys.value = res.data?.channels ?? res.data?.keys ?? [];
  } catch (_) {}
}

async function loadTemplates() {
  try {
    const res = await api('/WaDesk/Templates/list');
    templates.value = res.data?.templates ?? [];
  } catch (_) {}
}

async function loadCsvHeaders(templateId) {
  try {
    const res = await api(`/WaDesk/Blast/csvHeaders?template_id=${templateId}`);
    csvParams.value = res.data?.params ?? [];
    csvHeaders.value = buildTemplateHeaders(res.data?.params ?? []);
    if (parsedRows.value.length > 0) validateUpload();
  } catch (_) {
    csvParams.value = [];
    csvHeaders.value = [];
  }
}

async function loadBlasts() {
  loadingBlasts.value = true;
  try {
    const q = new URLSearchParams({ page: blastsPage.value });
    if (filter.campaign) q.set('campaign_name', filter.campaign);
    const res = await api(`/WaDesk/Blast/list?${q}`);
    blasts.value = res.data?.blasts ?? [];
    blastsTotal.value = res.data?.total ?? 0;
    campaignOptions.value = res.data?.campaigns ?? [];
  } catch (_) {}
  loadingBlasts.value = false;
}

async function loadDetail(blastId, page = 1) {
  try {
    const res = await api(`/WaDesk/Blast/detail?id=${blastId}&page=${page}`);
    detailBlast.value = res.data?.blast;
    detailRecipients.value = res.data?.recipients ?? [];
    detailTotal.value = res.data?.recipient_total ?? 0;
    detailPage.value = page;
  } catch (_) {}
}

// ---- event handlers -------------------------------------------------------
function onKeyChange() {
  form.template_id = '';
  csvParams.value = [];
  csvHeaders.value = [];
  keyQuota.value = null;
  resetUpload();
  if (form.channel_id) {
    loadKeyQuota(form.channel_id);
  }
}

async function loadKeyQuota(keyId) {
  try {
    const res = await api(`/WaDesk/Quota/forChannel?channel_id=${keyId}`);
    keyQuota.value = {
      team_id: res.data?.team_id,
      team_name: res.data?.team_name,
      balance: Number(res.data?.balance ?? 0),
    };
  } catch (_) {
    keyQuota.value = null;
  }
}

async function onTemplateChange() {
  resetUpload();
  if (form.template_id) {
    await loadCsvHeaders(form.template_id);
  }
}

function downloadSample() {
  const tpl = filteredTemplates.value.find((t) => Number(t.id) === Number(form.template_id));
  downloadSampleCsv(tpl?.template_name ?? 'template', csvParams.value);
}

function triggerFileInput() {
  fileInput.value?.click();
}

function onFileDrop(e) {
  const file = e.dataTransfer.files?.[0];
  if (file) handleFile(file);
}

function onFileChange(e) {
  const file = e.target.files?.[0];
  if (file) handleFile(file);
}

function handleFile(file) {
  if (!file.name.endsWith('.csv')) {
    csvErrors.value = ['Hanya file .csv yang diterima'];
    return;
  }
  uploadedFile.value = file;
  const reader = new FileReader();
  reader.onload = (e) => {
    const { headers, rows } = parseCsv(e.target.result);
    parsedRows.value = rows;
    csvHeaders.value = headers.length > 0 ? headers : buildTemplateHeaders(csvParams.value);
    validateUpload();
  };
  reader.readAsText(file);
}

function validateUpload() {
  csvErrors.value = validateBlastRows(parsedRows.value, csvHeaders.value, csvParams.value);
}

function resetUpload() {
  uploadedFile.value = null;
  parsedRows.value = [];
  csvErrors.value = [];
  if (fileInput.value) fileInput.value.value = '';
}

async function submitBlast() {
  submitError.value = '';
  if (!auth.canSendWa) {
    submitError.value = 'Masuk team di Admin dulu untuk blast WA.';
    return;
  }
  if (parsedRows.value.length > 250) {
    submitError.value = `Maksimal 250 baris per blast. File ini berisi ${parsedRows.value.length} baris.`;
    return;
  }
  if (keyQuota.value !== null && parsedRows.value.length > keyQuota.value.balance) {
    submitError.value = `Kuota team tidak cukup: butuh ${parsedRows.value.length}, sisa ${keyQuota.value.balance}.`;
    return;
  }
  submitting.value = true;
  try {
    const tpl = filteredTemplates.value.find((t) => Number(t.id) === Number(form.template_id));
    const rows = rowsToBlastPayload(parsedRows.value, csvParams.value);

    await api('/WaDesk/Blast/create', {
      method: 'POST',
      body: {
        campaign_name:  form.campaign_name.trim(),
        channel_id: Number(form.channel_id),
        template_id:    Number(form.template_id),
        rows,
      },
    });

    resetUpload();
    form.campaign_name = '';
    if (form.channel_id) await loadKeyQuota(form.channel_id);
    await loadBlasts();
  } catch (e) {
    submitError.value = e?.message ?? 'Gagal membuat blast';
  }
  submitting.value = false;
}

function openDetail(blast) {
  detailPage.value = 1;
  loadDetail(blast.id, 1);
  startDetailPoll(blast.id);
}

function startDetailPoll(blastId) {
  clearDetailPoll();
  detailPollTimer = setInterval(async () => {
    if (!detailBlast.value) return clearDetailPoll();
    await loadDetail(blastId, detailPage.value);
    if (!['pending', 'processing'].includes(detailBlast.value?.status)) {
      clearDetailPoll();
      loadBlasts();
    }
  }, 5000);
}

function clearDetailPoll() {
  if (detailPollTimer) {
    clearInterval(detailPollTimer);
    detailPollTimer = null;
  }
}

function askCancelBlast() {
  if (!detailBlast.value) return;
  askConfirm({
    title: 'Batalkan blast',
    message: `Batalkan campaign "${detailBlast.value.campaign_name}"? Penerima yang belum terkirim tidak akan dikirim.`,
    confirmLabel: 'Batalkan',
    danger: true,
    action: () => cancelBlast(),
  });
}

async function cancelBlast() {
  if (!detailBlast.value) return;
  cancelling.value = true;
  try {
    await api('/WaDesk/Blast/cancel', { method: 'POST', body: { blast_id: detailBlast.value.id } });
    await loadDetail(detailBlast.value.id, detailPage.value);
    await loadBlasts();
    clearDetailPoll();
  } catch (e) {
    showAlert(e?.message ?? 'Gagal membatalkan', 'Gagal');
  }
  cancelling.value = false;
}

function changePage(delta) {
  blastsPage.value += delta;
  loadBlasts();
}

function changeDetailPage(delta) {
  const newPage = detailPage.value + delta;
  if (detailBlast.value) loadDetail(detailBlast.value.id, newPage);
}

// ---- utils ----------------------------------------------------------------
function progressPct(b) {
  if (!b.total || b.total === 0) return 0;
  return Math.round(((Number(b.sent) + Number(b.failed)) / Number(b.total)) * 100);
}

function statusClass(status) {
  return {
    pending: 'text-amber-400',
    processing: 'text-blue-400',
    done: 'text-emerald-400',
    cancelled: 'text-slate-400',
  }[status] ?? 'text-slate-400';
}

function formatDate(dt) {
  if (!dt) return '—';
  return new Date(dt.replace(' ', 'T')).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}
</script>

<style scoped>
.card {
  @apply rounded-2xl bg-ink-900/50 border border-white/10 p-5;
}
.label {
  @apply block text-sm text-slate-400 mb-1;
}
.field {
  @apply w-full rounded-xl bg-ink-950 border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-accent/50;
}
.btn {
  @apply rounded-xl bg-accent text-white font-semibold text-sm disabled:opacity-40 cursor-pointer hover:bg-accent/90 transition;
}
.btn-sm {
  @apply px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-200 text-xs cursor-pointer transition;
}
.btn-sm-accent {
  @apply text-accent font-semibold hover:text-accent-soft bg-accent/10 hover:bg-accent/15;
}
.status-badge {
  @apply text-xs font-medium uppercase tracking-wide;
}
</style>
