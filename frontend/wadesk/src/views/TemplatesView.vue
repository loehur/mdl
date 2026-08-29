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
          <div v-if="auth.canManageTeam" class="flex flex-wrap gap-2"><button type="button" class="detail-button" :disabled="syncing" @click="syncTemplates">{{ syncing ? 'Sync...' : 'Sync template' }}</button><button type="button" class="detail-button" @click="showCreate = !showCreate">{{ showCreate ? 'Tutup' : 'Tambah template' }}</button></div>
          <form v-if="showCreate" class="space-y-4 rounded-xl border border-accent/20 bg-ink-950/40 p-4" @submit.prevent="createTemplate">
            <div><p class="font-medium text-slate-100">Buat template baru</p><p class="mt-1 text-xs text-slate-400">Target WABA dan team mengikuti team aktif Anda secara otomatis.</p></div>
            <div><label class="label block mb-1">Nama template</label><input v-model="createForm.template_name" class="field block w-full" placeholder="Contoh: pengingat_tagihan" /><p class="mt-1 text-[11px] text-slate-500">Gunakan huruf kecil, angka, dan underscore.</p></div>
            <div><label class="label block mb-1">Kategori</label><select v-model="createForm.category" class="field block w-full"><option value="UTILITY">Utility — notifikasi/transaksi</option><option value="MARKETING">Marketing — promosi</option></select></div>
            <div><label class="label block mb-1">Isi pesan</label><textarea v-model="createForm.body" class="field block w-full" style="min-height:8rem;resize:vertical" placeholder="Halo {{customer_name}}, tagihan Anda sudah jatuh tempo." /><p class="mt-1 text-[11px] text-slate-500">Gunakan parameter bernama seperti <code v-pre>{{customer_name}}</code>. Parameter angka seperti <code v-pre>{{1}}</code> tidak didukung.</p></div>
            <div class="rounded-lg border border-white/10 bg-white/[0.03] p-3"><p class="text-xs font-medium text-slate-300">Parameter terdeteksi</p><div v-if="namedParams.length" class="mt-2 flex flex-wrap gap-1.5"><span v-for="param in namedParams" :key="param" class="team-chip">{{ param }}</span></div><p v-else class="mt-1 text-xs text-slate-500">Tidak ada parameter. Tulis <code v-pre>{{nama_parameter}}</code> di isi pesan bila diperlukan.</p></div>
            <button class="w-full sm:w-auto rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed" style="background:#0f766e" :disabled="creating">{{ creating ? 'Mengirim ke Meta...' : 'Kirim template ke Meta' }}</button>
          </form>
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
                <span v-if="template.meta_status" class="badge" :class="String(template.meta_status).toUpperCase() === 'APPROVED' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300'">{{ template.meta_status }}</span>
              </div>
              <p v-if="template.waba_label || template.waba_id" class="mt-1 text-xs text-slate-500 truncate">
                {{ template.waba_label || template.waba_id }}
              </p>
              <div v-if="auth.isAdmin && template.assigned_teams?.length" class="mt-2 flex flex-wrap gap-1.5">
                <span v-for="team in template.assigned_teams" :key="team.id" class="team-chip">{{ team.name }}</span>
              </div>
            </div>
            <div class="flex gap-2 shrink-0"><button type="button" class="detail-button" @click="toggleDetail(template.id)">{{ expandedId === template.id ? "Close" : "Detail" }}</button><button v-if="auth.canManageTeam" type="button" class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm font-medium text-rose-300 hover:bg-rose-500/20" @click="deleteTemplate(template)">Hapus</button></div>

            <div v-if="expandedId === template.id" class="template-detail">
              <p v-if="template.body_preview" class="whitespace-pre-wrap break-words text-sm text-slate-300">{{ template.body_preview }}</p>
              <p v-else class="text-sm text-slate-500">No template content preview.</p>
              <div v-if="template.params?.length" class="mt-3 border-t border-white/10 pt-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Parameters</p>
                <div class="mt-2 space-y-1.5">
                  <div v-for="param in template.params" :key="param.id" class="text-sm text-slate-300">
                    <span class="text-accent-soft">{{ param.component }} {{ Number(param.param_index) + 1 }}</span>
                    <span v-if="param.label"> · {{ param.label }}</span>
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
const createForm = reactive({ template_name: "", category: "UTILITY", body: "" });
const namedParams = computed(() => [...new Set([...String(createForm.body || "").matchAll(/\{\{\s*([^}]+?)\s*\}\}/g)].map((m) => m[1].trim()).filter(Boolean))]);
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
    await api("/WaDesk/Templates/createForTeam", { method: "POST", body: { template_name: createForm.template_name, category: createForm.category, language: "id", body: createForm.body } });
    Object.assign(createForm, { template_name: "", category: "UTILITY", body: "" });
    showCreate.value = false;
    await loadTemplates({ reset: true });
  } catch (e) { error.value = e.message || "Gagal membuat template."; } finally { creating.value = false; }
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
.template-card { @apply grid grid-cols-[minmax(0,1fr)_auto] gap-3 items-start rounded-2xl border border-white/10 bg-ink-900 p-4 shadow-sm; }
.template-detail { @apply col-span-2 rounded-xl border border-white/10 bg-white/[0.03] p-3; }
.team-chip { @apply rounded-full bg-accent/10 px-2 py-0.5 text-xs text-accent-soft; }
.detail-button { @apply rounded-lg border border-accent/30 bg-accent/10 px-3 py-2 text-sm font-medium text-accent-soft transition hover:bg-accent/20; }
</style>
