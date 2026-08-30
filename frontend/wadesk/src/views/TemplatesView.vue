<template>
  <AppHeader page-title="Templates" active="templates" @logout="onLogout">
    <div class="flex-1 overflow-y-auto bg-ink-950">
      <div class="max-w-5xl mx-auto p-4 space-y-4">
        <section class="card space-y-3">
          <div>
            <h2 class="section-title">Template WhatsApp</h2>
            <p class="mt-1 text-sm text-slate-500">
              {{ auth.isAdmin && !auth.hasTeam ? "All templates in this tenant." : "Templates available to your team." }}
            </p>
          </div>
          <input
            v-model="search"
            class="field w-full"
            type="search"
            placeholder="Search template name, language, or content..."
            @input="onSearch"
          />
          <div v-if="auth.canManageTeam" class="flex flex-wrap gap-2"><button type="button" class="detail-button" :disabled="syncing" @click="syncTemplates">{{ syncing ? 'Sync...' : 'Sync template' }}</button><button type="button" class="detail-button" :disabled="templateActionBusy" @click="showCreate = !showCreate">{{ showCreate ? 'Tutup' : 'Tambah template' }}</button></div>
          <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="create-template-title" @click.self="closeCreateModal">
          <form class="template-create-card relative max-h-[calc(100vh-2rem)] w-full max-w-2xl space-y-4 overflow-y-auto rounded-2xl border border-accent/30 p-5 shadow-2xl" @submit.prevent="createTemplate">
            <div v-if="templateActionBusy" class="absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-ink-900/85 p-6 backdrop-blur-sm" role="status" aria-live="polite">
              <div class="max-w-sm text-center"><svg class="mx-auto h-9 w-9 animate-spin text-accent" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-4a6 6 0 0 0-6-6V2z"/></svg><p class="mt-3 text-sm font-semibold text-slate-100">{{ templateActionTitle }}</p><p class="mt-1 text-xs leading-5 text-slate-300">{{ templateActionHint }}</p></div>
            </div>
            <div class="flex items-start justify-between gap-4"><div><p id="create-template-title" class="font-medium text-slate-100">Buat template baru</p><p class="mt-1 text-xs text-slate-400">Target WABA dan team mengikuti team aktif Anda secara otomatis. Isi yang dikirim ke Meta wajib berasal dari AI.</p></div><button type="button" class="detail-button shrink-0" :disabled="creating || generating" @click="showCreate = false">Tutup</button></div>
            <div><label class="label block mb-1">Nama template</label><input v-model="createForm.template_name" class="field block w-full" placeholder="Contoh: pengingat_tagihan" /><p class="mt-1 text-[11px] text-slate-500">Gunakan huruf kecil, angka, dan underscore.</p></div>
            <div><label class="label block mb-1">Kategori</label><select v-model="createForm.category" class="field block w-full"><option value="UTILITY">Utility — notifikasi/transaksi</option><option value="MARKETING">Marketing — promosi</option></select></div>
            <section class="rounded-lg border border-white/10 bg-white/[0.03] p-3 space-y-3">
              <div class="flex items-center justify-between gap-3"><div><p class="text-sm font-medium text-slate-200">Tombol</p><p class="mt-0.5 text-[11px] text-slate-500">Opsional: maksimal 3 Quick Reply, atau 2 CTA (URL/Call). Konfigurasi dikunci saat Generate AI.</p></div><button type="button" class="detail-button" :disabled="!canAddTemplateButton" @click="addTemplateButton">Tambah tombol</button></div>
              <div v-for="(button, index) in templateButtons" :key="index" class="rounded-lg border border-white/10 p-3 space-y-2">
                <div class="flex gap-2"><select v-model="button.type" class="field flex-1" @change="onButtonTypeChange(index)"><option value="QUICK_REPLY" :disabled="isButtonTypeDisabled(index, 'QUICK_REPLY')">Quick Reply</option><option value="URL" :disabled="isButtonTypeDisabled(index, 'URL')">Buka URL</option><option value="PHONE_NUMBER" :disabled="isButtonTypeDisabled(index, 'PHONE_NUMBER')">Call</option></select><button type="button" class="rounded-lg px-3 text-sm text-rose-300 hover:bg-rose-500/10" @click="removeTemplateButton(index)">Hapus</button></div>
                <input v-model="button.text" class="field" maxlength="25" placeholder="Teks tombol, maksimal 25 karakter" @input="clearAiApproval" />
                <input v-if="button.type === 'URL'" v-model="button.url" class="field" placeholder="https://contoh.com" @input="clearAiApproval" />
                <input v-if="button.type === 'PHONE_NUMBER'" v-model="button.phone_number" class="field" placeholder="+628123456789" @input="clearAiApproval" />
              </div>
            </section>
            <div><label class="label block mb-1">Draf untuk AI</label><textarea v-model="createForm.draft" class="field block w-full p-3" style="min-height:8rem;resize:vertical" placeholder="Halo {{customer_name}}, kami dari {{company_name}} mengingatkan tagihan Anda..." @input="clearAiApproval" /><p class="mt-1 text-[11px] text-slate-500">Gunakan nama ramah seperti <code v-pre>{{customer_name}}</code>. AI wajib mempertahankan parameter tersebut.</p></div>
            <button type="button" class="detail-button" :disabled="generating" @click="generateTemplate">{{ generating ? 'AI sedang menyusun...' : approvalToken ? 'Generate ulang dengan AI' : 'Generate dengan AI' }}</button>
            <div v-if="generatedBody" class="rounded-lg border border-emerald-500/25 bg-emerald-500/5 p-3"><p class="text-xs font-medium text-emerald-300">Hasil AI — siap diajukan ke Meta</p><p class="mt-2 whitespace-pre-wrap text-sm text-slate-200">{{ generatedBody }}</p><div v-if="templateButtons.length" class="mt-3 flex flex-wrap gap-1.5"><span v-for="(button, index) in templateButtons" :key="index" class="team-chip">{{ button.type }} · {{ button.text || 'Tombol' }}</span></div><p class="mt-2 text-[11px] text-slate-500">Hasil dan tombol ini tidak dapat diedit setelah Generate. Ubah draf/tombol lalu generate ulang bila diperlukan.</p></div>
            <div class="rounded-lg border border-white/10 bg-white/[0.03] p-3"><p class="text-xs font-medium text-slate-300">Parameter terdeteksi</p><div v-if="namedParams.length" class="mt-2 flex flex-wrap gap-1.5"><span v-for="param in namedParams" :key="param" class="team-chip">{{ param }}</span></div><p v-else class="mt-1 text-xs text-slate-500">Tidak ada parameter. Tulis <code v-pre>{{nama_parameter}}</code> di draf bila diperlukan.</p></div>
            <button class="w-full sm:w-auto rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60" style="background:#0f766e" :disabled="creating || !approvalToken">{{ creating ? 'Mengirim ke Meta...' : 'Kirim hasil AI ke Meta' }}</button>
          </form>
          </div>
        </section>

        <p v-if="error" class="alert alert-error">{{ error }}</p>

        <section v-if="loading && !templates.length" class="card text-sm text-slate-500">Loading templates...</section>
        <section v-else-if="!templates.length" class="card text-sm text-slate-500">
          No templates to display.
        </section>

        <section v-else class="space-y-2">
          <article v-for="template in templates" :key="template.id" class="template-card">
            <div class="min-w-0 space-y-1">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-slate-100 break-all">{{ template.template_name }}</h3>
                <span v-if="template.language" class="badge">{{ template.language }}</span>
                <span v-if="template.meta_category" class="badge bg-violet-500/15 text-violet-300">{{ template.meta_category }}</span>
                <span v-if="template.meta_status" class="badge" :class="String(template.meta_status).toUpperCase() === 'APPROVED' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300'">{{ template.meta_status }}</span>
                <span
                  v-if="template.meta_quality_rating"
                  class="badge"
                  :class="String(template.meta_quality_rating).toUpperCase() === 'GREEN' ? 'bg-emerald-500/15 text-emerald-300' : String(template.meta_quality_rating).toUpperCase() === 'YELLOW' ? 'bg-amber-500/15 text-amber-300' : 'bg-rose-500/15 text-rose-300'"
                >Quality: {{ template.meta_quality_rating }}</span>
              </div>
              <p v-if="template.waba_label || template.waba_id" class="mt-1 text-xs text-slate-500 truncate">
                {{ template.waba_label || template.waba_id }}
              </p>
              <div v-if="auth.isAdmin && template.assigned_teams?.length" class="mt-2 flex flex-wrap gap-1.5">
                <span v-for="team in template.assigned_teams" :key="team.id" class="team-chip">{{ team.name }}</span>
              </div>
            </div>
            <div class="flex gap-2 shrink-0"><button type="button" class="detail-button" @click="toggleDetail(template.id)">{{ expandedId === template.id ? "Close" : "Detail" }}</button><button v-if="auth.canManageTeam && String(template.meta_status).toUpperCase() === 'REJECTED'" type="button" class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm font-medium text-rose-300 hover:bg-rose-500/20" @click="deleteTemplate(template)">Hapus</button></div>

            <div v-if="expandedId === template.id" class="template-detail">
              <p v-if="template.body_preview" class="whitespace-pre-wrap break-words text-sm text-slate-300">{{ template.body_preview }}</p>
              <p v-else class="text-sm text-slate-500">No template content preview.</p>
              <div v-if="template.params?.length" class="mt-3 border-t border-white/10 pt-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Parameters</p>
                <div class="mt-2 space-y-1.5">
                  <div v-for="param in template.params" :key="param.id" class="text-sm text-slate-300">
                    <span class="text-accent-soft">{{ param.component }}</span>
                    <span> · {{ param.label || param.param_name }}</span>
                    <span v-if="param.example_value" class="text-slate-500"> — {{ param.example_value }}</span>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </section>

        <div ref="loadMoreTarget" class="h-8" aria-hidden="true" />
        <p v-if="loading && templates.length" class="text-center text-sm text-slate-500">Loading more templates...</p>
        <p v-else-if="templates.length < total" class="text-center text-sm text-slate-500">Scroll to load more templates.</p>
      </div>

      <div v-if="templateToDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="delete-template-title" @click.self="cancelDelete">
        <section class="w-full max-w-md rounded-2xl border border-rose-500/30 bg-ink-900 p-5 shadow-2xl">
          <h2 id="delete-template-title" class="text-lg font-semibold text-slate-100">Hapus template?</h2>
          <p class="mt-2 text-sm leading-6 text-slate-300">
            Template <strong class="break-all text-slate-100">{{ templateToDelete.template_name }}</strong> akan dihapus dari Meta dan WaDesk. Tindakan ini tidak dapat dibatalkan.
          </p>
          <p v-if="deleteError" class="mt-3 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">{{ deleteError }}</p>
          <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="detail-button" :disabled="deleting" @click="cancelDelete">Batal</button>
            <button type="button" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-60" :disabled="deleting" @click="confirmDelete">
              {{ deleting ? 'Menghapus...' : 'Ya, hapus template' }}
            </button>
          </div>
        </section>
      </div>
    </div>
  </AppHeader>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api";
import { useAuthStore } from "../stores/auth";
import AppHeader from "../components/AppHeader.vue";

const auth = useAuthStore();
const router = useRouter();
const templates = ref([]);
const total = ref(0);
const page = ref(1);
const search = ref("");
const loading = ref(false);
const error = ref("");
const expandedId = ref(null);
const loadMoreTarget = ref(null);
const showCreate = ref(false);
const creating = ref(false);
const syncing = ref(false);
const templateToDelete = ref(null);
const deleting = ref(false);
const deleteError = ref("");
const createForm = reactive({ template_name: "", category: "UTILITY", draft: "" });
const templateButtons = ref([]);
const generatedBody = ref("");
const approvalToken = ref("");
const generating = ref(false);
const templateActionBusy = computed(() => generating.value || creating.value);
const templateActionTitle = computed(() => generating.value ? "AI sedang merapikan draf template" : "Template sedang dikirim ke Meta");
const templateActionHint = computed(() => generating.value
  ? "Biasanya selesai dalam beberapa detik. Jangan tutup halaman ini dahulu."
  : "Meta sedang menerima template. Biasanya selesai dalam beberapa detik.");
const namedParams = computed(() => [...new Set([...String(generatedBody.value || createForm.draft || "").matchAll(/\{\{\s*([^}]+?)\s*\}\}/g)].map((m) => m[1].trim()).filter(Boolean))]);
const hasQuickReplyButtons = computed(() => templateButtons.value.some((button) => button.type === "QUICK_REPLY"));
const hasCtaButtons = computed(() => templateButtons.value.some((button) => ["URL", "PHONE_NUMBER"].includes(button.type)));
const canAddTemplateButton = computed(() => templateButtons.value.length < (hasCtaButtons.value ? 2 : 3));
let observer;
let searchTimer;

async function loadTemplates({ reset = false } = {}) {
  if (loading.value || (!reset && templates.value.length >= total.value && total.value > 0)) return;
  if (reset) {
    page.value = 1;
    templates.value = [];
    total.value = 0;
    expandedId.value = null;
  }
  loading.value = true;
  error.value = "";
  try {
    const params = new URLSearchParams({ page: String(page.value), limit: "20" });
    if (search.value.trim()) params.set("q", search.value.trim());
    const data = (await api(`/WaDesk/Templates/teamList?${params.toString()}`, { cache: "no-store" })).data || {};
    const rows = data.templates || [];
    templates.value.push(...rows);
    total.value = Number(data.total || 0);
    page.value += 1;
  } catch (e) {
    error.value = e.message || "Failed to load templates.";
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadTemplates({ reset: true }), 300);
}

function toggleDetail(id) {
  expandedId.value = expandedId.value === id ? null : id;
}

async function createTemplate() {
  creating.value = true; error.value = "";
  try {
    await api("/WaDesk/Templates/createForTeam", { method: "POST", timeout: 90000, body: { template_name: createForm.template_name, category: createForm.category, language: "id", approval_token: approvalToken.value } });
    Object.assign(createForm, { template_name: "", category: "UTILITY", draft: "" });
    templateButtons.value = [];
    generatedBody.value = ""; approvalToken.value = "";
    showCreate.value = false;
    await loadTemplates({ reset: true });
  } catch (e) { error.value = e.message || "Gagal membuat template."; } finally { creating.value = false; }
}

function clearAiApproval() {
  generatedBody.value = "";
  approvalToken.value = "";
}

async function generateTemplate() {
  generating.value = true; error.value = "";
  clearAiApproval();
  try {
    const res = await api("/WaDesk/Templates/generateForTeam", { method: "POST", timeout: 90000, body: { draft: createForm.draft, buttons: templateButtons.value } });
    generatedBody.value = res.data?.body || "";
    approvalToken.value = res.data?.approval_token || "";
    if (!generatedBody.value || !approvalToken.value) throw new Error("AI tidak menghasilkan persetujuan template yang valid.");
  } catch (e) { error.value = e.message || "Gagal membuat draf dengan AI."; }
  finally { generating.value = false; }
}

function closeCreateModal() {
  if (!templateActionBusy.value) showCreate.value = false;
}

function addTemplateButton() {
  if (!canAddTemplateButton.value) return;
  templateButtons.value.push({ type: hasCtaButtons.value ? (templateButtons.value.some((button) => button.type === "URL") ? "PHONE_NUMBER" : "URL") : "QUICK_REPLY", text: "", url: "", phone_number: "" });
  clearAiApproval();
}

function removeTemplateButton(index) {
  templateButtons.value.splice(index, 1);
  clearAiApproval();
}

function isButtonTypeDisabled(index, type) {
  const others = templateButtons.value.filter((_, otherIndex) => otherIndex !== index);
  if (type === "QUICK_REPLY") return others.some((button) => ["URL", "PHONE_NUMBER"].includes(button.type));
  if (["URL", "PHONE_NUMBER"].includes(type)) {
    return others.some((button) => button.type === "QUICK_REPLY" || button.type === type);
  }
  return false;
}

function onButtonTypeChange(index) {
  const button = templateButtons.value[index];
  if (!button) return;
  const otherButtons = templateButtons.value.filter((_, otherIndex) => otherIndex !== index);
  const conflictsWithQuickReply = button.type === "QUICK_REPLY" && otherButtons.some((item) => ["URL", "PHONE_NUMBER"].includes(item.type));
  const conflictsWithCta = ["URL", "PHONE_NUMBER"].includes(button.type) && otherButtons.some((item) => item.type === "QUICK_REPLY");
  const duplicatesCta = ["URL", "PHONE_NUMBER"].includes(button.type) && otherButtons.some((item) => item.type === button.type);
  if (conflictsWithQuickReply || conflictsWithCta || duplicatesCta) {
    button.type = otherButtons[0]?.type || "QUICK_REPLY";
    error.value = "Quick Reply tidak dapat dicampur dengan CTA; tombol URL dan Call masing-masing hanya satu.";
  }
  clearAiApproval();
}

async function syncTemplates() {
  syncing.value = true; error.value = "";
  try { await api("/WaDesk/Wabas/syncTemplatesForTeam", { method: "POST", body: {} }); await loadTemplates({ reset: true }); }
  catch (e) { error.value = e.message || "Gagal sync template."; }
  finally { syncing.value = false; }
}

function deleteTemplate(template) {
  deleteError.value = "";
  templateToDelete.value = template;
}

function cancelDelete() {
  if (!deleting.value) {
    templateToDelete.value = null;
    deleteError.value = "";
  }
}

async function confirmDelete() {
  if (!templateToDelete.value || deleting.value) return;
  deleting.value = true;
  deleteError.value = "";
  error.value = "";
  try {
    await api("/WaDesk/Templates/deleteForTeam", { method: "POST", body: { template_id: templateToDelete.value.id } });
    templateToDelete.value = null;
    await loadTemplates({ reset: true });
  }
  catch (e) {
    const message = e.message || "Gagal menghapus template.";
    deleteError.value = message;
    error.value = message;
  }
  finally { deleting.value = false; }
}

async function onLogout() {
  await auth.logout();
  router.push({ name: "login" });
}

onMounted(async () => {
  await loadTemplates({ reset: true });
  await nextTick();
  observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) loadTemplates();
  }, { rootMargin: "200px" });
  if (loadMoreTarget.value) observer.observe(loadMoreTarget.value);
});

onBeforeUnmount(() => {
  clearTimeout(searchTimer);
  observer?.disconnect();
});
</script>

<style scoped>
.template-create-card { background-color: rgb(var(--color-ink-900)); }
.field { @apply w-full rounded-xl border bg-ink-950 px-3 py-2.5 text-sm text-slate-100 outline-none transition focus:ring-2 focus:ring-accent/30; border-color: rgb(var(--theme-border) / 0.22); box-shadow: inset 0 1px 0 rgb(var(--theme-border) / 0.04); }
.field:hover { border-color: rgb(var(--theme-border) / 0.34); }
.field:focus { border-color: rgb(var(--color-accent) / 0.7); }
.template-card { @apply grid grid-cols-[minmax(0,1fr)_auto] gap-3 items-start rounded-2xl border border-white/10 bg-ink-900 p-4 shadow-sm; }
.template-detail { @apply col-span-2 rounded-xl border border-white/10 bg-white/[0.03] p-3; }
.team-chip { @apply rounded-full bg-accent/10 px-2 py-0.5 text-xs text-accent-soft; }
.detail-button { @apply rounded-lg border border-accent/30 bg-accent/10 px-3 py-2 text-sm font-medium text-accent-soft transition hover:bg-accent/20; }
</style>
