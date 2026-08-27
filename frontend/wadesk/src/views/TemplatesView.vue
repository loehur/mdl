<template>
  <AppHeader page-title="Templates" active="templates" @logout="onLogout">
    <div class="flex-1 overflow-y-auto bg-ink-950">
      <div class="max-w-5xl mx-auto p-4 space-y-4">
        <section class="card space-y-3">
          <div>
            <h2 class="section-title">Template WhatsApp</h2>
            <p class="mt-1 text-sm text-slate-500">
              {{ auth.isAdmin && !auth.hasTeam ? "Semua template dalam tenant." : "Template yang tersedia untuk team Anda." }}
            </p>
          </div>
          <input
            v-model="search"
            class="field w-full"
            type="search"
            placeholder="Cari nama, bahasa, atau isi template..."
            @input="onSearch"
          />
        </section>

        <p v-if="error" class="alert alert-error">{{ error }}</p>

        <section v-if="loading && !templates.length" class="card text-sm text-slate-500">Memuat template...</section>
        <section v-else-if="!templates.length" class="card text-sm text-slate-500">
          Tidak ada template yang dapat ditampilkan.
        </section>

        <section v-else class="space-y-2">
          <article v-for="template in templates" :key="template.id" class="template-card">
            <div class="min-w-0 space-y-1">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-slate-100 break-all">{{ template.template_name }}</h3>
                <span v-if="template.language" class="badge">{{ template.language }}</span>
              </div>
              <p v-if="template.waba_label || template.waba_id" class="mt-1 text-xs text-slate-500 truncate">
                {{ template.waba_label || template.waba_id }}
              </p>
              <div v-if="auth.isAdmin && template.assigned_teams?.length" class="mt-2 flex flex-wrap gap-1.5">
                <span v-for="team in template.assigned_teams" :key="team.id" class="team-chip">{{ team.name }}</span>
              </div>
            </div>
            <button type="button" class="detail-button shrink-0" @click="toggleDetail(template.id)">
              {{ expandedId === template.id ? "Close" : "Detail" }}
            </button>

            <div v-if="expandedId === template.id" class="template-detail">
              <p v-if="template.body_preview" class="whitespace-pre-wrap break-words text-sm text-slate-300">{{ template.body_preview }}</p>
              <p v-else class="text-sm text-slate-500">Tidak ada preview isi template.</p>
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
        <p v-if="loading && templates.length" class="text-center text-sm text-slate-500">Memuat template berikutnya...</p>
        <p v-else-if="templates.length < total" class="text-center text-sm text-slate-500">Scroll untuk memuat lebih banyak template.</p>
      </div>
    </div>
  </AppHeader>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from "vue";
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
    error.value = e.message || "Gagal memuat template.";
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
