<template>
  <AppHeader page-title="Account" active="account" @logout="onLogout">
    <div class="flex-1 overflow-y-auto bg-ink-950">
      <div class="max-w-3xl mx-auto p-4 space-y-4">
        <!-- Tab bar -->
        <div class="card">
          <nav class="flex flex-wrap gap-2" aria-label="Tab account">
            <button
              v-for="t in visibleTabs"
              :key="t.id"
              type="button"
              class="account-tab"
              :class="{ 'account-tab-active': tab === t.id }"
              @click="switchTab(t.id)"
            >
              {{ t.label }}
            </button>
          </nav>
        </div>

        <div v-if="error" class="alert alert-error">{{ error }}</div>
        <div v-if="success" class="alert alert-success">{{ success }}</div>

        <!-- Profil -->
        <template v-if="tab === 'profile'">
          <div v-if="loadingProfile" class="card text-sm text-slate-500">Memuat profil...</div>
          <template v-else-if="profile">
            <div class="card space-y-4">
              <h2 class="section-title">Informasi akun</h2>
              <dl class="info-grid">
                <div class="info-item">
                  <dt>Organisasi</dt>
                  <dd>{{ profile.tenant_name || "—" }}</dd>
                </div>
                <div class="info-item">
                  <dt>Role</dt>
                  <dd><span class="badge">{{ roleLabel(profile.role) }}</span></dd>
                </div>
                <div class="info-item">
                  <dt>Team</dt>
                  <dd>{{ profile.team_name || "—" }}</dd>
                </div>
                <div class="info-item">
                  <dt>Bergabung sejak</dt>
                  <dd>{{ formatDate(profile.created_at) }}</dd>
                </div>
              </dl>
            </div>

            <div class="card space-y-4">
              <h2 class="section-title">Edit profil</h2>
              <form class="space-y-3" @submit.prevent="saveProfile">
                <div>
                  <label class="label">Nama</label>
                  <input v-model="profileForm.name" required class="field" />
                </div>
                <div>
                  <label class="label">Email</label>
                  <input :value="profile.email" disabled class="field field-disabled" />
                </div>
                <div class="pt-1">
                  <button class="btn" :disabled="savingProfile">
                    {{ savingProfile ? "Menyimpan..." : "Simpan profil" }}
                  </button>
                </div>
              </form>
            </div>

            <div class="card space-y-4">
              <h2 class="section-title">Ubah password</h2>
              <form class="space-y-3" @submit.prevent="savePassword">
                <div>
                  <label class="label">Password saat ini</label>
                  <input
                    v-model="passwordForm.current_password"
                    type="password"
                    required
                    class="field"
                    autocomplete="current-password"
                  />
                </div>
                <div>
                  <label class="label">Password baru</label>
                  <input
                    v-model="passwordForm.new_password"
                    type="password"
                    required
                    minlength="6"
                    class="field"
                    autocomplete="new-password"
                  />
                </div>
                <div class="pt-1">
                  <button class="btn" :disabled="savingPassword">
                    {{ savingPassword ? "Menyimpan..." : "Ubah password" }}
                  </button>
                </div>
              </form>
            </div>
          </template>
        </template>

        <!-- Team -->
        <template v-if="tab === 'team'">
          <div
            v-if="auth.isAdmin && !auth.hasTeam"
            class="card alert alert-warn"
          >
            Admin belum bergabung ke team.
            <router-link to="/admin" class="alert-link">Masuk team di Admin →</router-link>
          </div>

          <div v-else-if="loadingTeam" class="card text-sm text-slate-500">Memuat data team...</div>

          <template v-else-if="teamData">
            <div class="card space-y-3">
              <h2 class="section-title">Ringkasan team</h2>
              <p class="text-lg font-semibold text-slate-100">{{ teamData.team.name }}</p>
              <p class="text-sm text-slate-500">
                Team Leader: {{ teamData.team.leader_name || "—" }}
                <span v-if="teamData.team.leader_email"> · {{ teamData.team.leader_email }}</span>
              </p>
              <p class="text-xs text-slate-500">
                Agent: {{ teamData.agent_count }} / {{ teamData.max_agents }}
              </p>
            </div>

            <div class="card space-y-3">
              <h2 class="section-title">Anggota team</h2>
              <div class="subcard-list">
                <div
                  v-for="m in teamData.members"
                  :key="m.id"
                  class="subcard-row"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-slate-100 truncate">
                      {{ m.name }}
                      <span v-if="m.is_self" class="text-xs text-accent-soft font-normal">(Anda)</span>
                    </p>
                    <p class="text-xs text-slate-500 truncate">{{ m.email }}</p>
                  </div>
                  <span class="badge shrink-0">{{ roleLabel(m.role) }}</span>
                </div>
                <p v-if="!teamData.members.length" class="empty-state">
                  Belum ada anggota team.
                </p>
              </div>
            </div>

            <div v-if="teamData.can_add_agent" class="card space-y-4">
              <h2 class="section-title">Tambah agent</h2>
              <p class="text-xs text-slate-500 -mt-2">Maksimal {{ teamData.max_agents }} agent per team.</p>
              <form class="space-y-3" @submit.prevent="addAgent">
                <div class="grid sm:grid-cols-2 gap-3">
                  <div>
                    <label class="label">Nama</label>
                    <input v-model="agentForm.name" required class="field" />
                  </div>
                  <div>
                    <label class="label">Email</label>
                    <input v-model="agentForm.email" type="email" required class="field" />
                  </div>
                </div>
                <div>
                  <label class="label">Password awal</label>
                  <input v-model="agentForm.password" type="password" required minlength="6" class="field" />
                </div>
                <div class="pt-1">
                  <button class="btn" :disabled="addingAgent">
                    {{ addingAgent ? "Menambah..." : "Tambah agent" }}
                  </button>
                </div>
              </form>
            </div>

            <div v-else class="card text-sm text-slate-500">
              Kuota agent team sudah penuh (maks. {{ teamData.max_agents }}).
            </div>
          </template>
        </template>

        <!-- Quota -->
        <template v-if="tab === 'quota'">
          <div
            v-if="auth.isAdmin && !auth.hasTeam"
            class="card alert alert-warn"
          >
            Admin belum bergabung ke team.
            <router-link to="/admin" class="alert-link">Masuk team di Admin →</router-link>
          </div>

          <div v-else-if="loadingQuota" class="card text-sm text-slate-500">Memuat kuota...</div>

          <template v-else-if="quotaData">
            <div class="card space-y-2">
              <h2 class="section-title">Sisa kuota</h2>
              <p class="text-sm text-slate-500">{{ quotaData.team_name }}</p>
              <p class="text-4xl font-semibold text-accent tabular-nums">{{ quotaData.balance }}</p>
              <p class="text-xs text-slate-500">kuota template tersisa</p>
            </div>

            <div class="card space-y-4">
              <h2 class="section-title">Riwayat top-up & pemakaian</h2>
              <div class="subcard-list max-h-[min(50vh,24rem)] overflow-y-auto">
                <div
                  v-if="!quotaData.logs.length"
                  class="empty-state"
                >
                  Belum ada riwayat kuota.
                </div>
                <div
                  v-for="log in quotaData.logs"
                  :key="log.id"
                  class="subcard-row items-start"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-slate-200">
                      <span :class="quotaTypeClass(log.type)">{{ quotaTypeLabel(log.type) }}</span>
                      <span class="ml-2 tabular-nums" :class="log.amount >= 0 ? 'text-emerald-500' : 'text-red-500'">
                        {{ log.amount >= 0 ? "+" : "" }}{{ log.amount }}
                      </span>
                    </p>
                    <p v-if="log.note" class="text-xs text-slate-500 mt-0.5">{{ log.note }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">
                      {{ formatDate(log.created_at) }}
                      <span v-if="log.user_name"> · {{ log.user_name }}</span>
                    </p>
                  </div>
                  <p class="shrink-0 text-xs text-slate-500 tabular-nums">saldo {{ log.balance_after }}</p>
                </div>
              </div>
              <div v-if="quotaHasMore" class="text-center pt-1">
                <button type="button" class="btn-secondary" :disabled="loadingMoreQuota" @click="loadMoreQuota">
                  {{ loadingMoreQuota ? "Memuat..." : "Muat lebih banyak" }}
                </button>
              </div>
            </div>
          </template>
        </template>
      </div>
    </div>
  </AppHeader>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../stores/auth";
import { api } from "../api";
import AppHeader from "../components/AppHeader.vue";

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const tab = ref("profile");
const error = ref("");
const success = ref("");

const loadingProfile = ref(false);
const savingProfile = ref(false);
const savingPassword = ref(false);
const profile = ref(null);
const profileForm = ref({ name: "" });
const passwordForm = ref({ current_password: "", new_password: "" });

const loadingTeam = ref(false);
const addingAgent = ref(false);
const teamData = ref(null);
const agentForm = ref({ name: "", email: "", password: "" });

const loadingQuota = ref(false);
const loadingMoreQuota = ref(false);
const quotaData = ref(null);
const quotaPage = ref(1);

const allTabs = [
  { id: "profile", label: "Profil", roles: "all" },
  { id: "team", label: "Team", roles: "manager" },
  { id: "quota", label: "Quota", roles: "manager" },
];

const visibleTabs = computed(() =>
  allTabs.filter((t) => t.roles === "all" || auth.canManageTeam)
);

const quotaHasMore = computed(() => {
  if (!quotaData.value) return false;
  return (quotaData.value.logs?.length ?? 0) < (quotaData.value.total ?? 0);
});

function roleLabel(role) {
  const map = { admin: "Admin", team_leader: "Team Leader", agent: "Agent" };
  return map[role] || role;
}

function formatDate(iso) {
  if (!iso) return "—";
  try {
    return new Date(iso.replace(" ", "T")).toLocaleString("id-ID", {
      dateStyle: "medium",
      timeStyle: "short",
    });
  } catch {
    return iso;
  }
}

function quotaTypeLabel(type) {
  const map = { topup: "Top-up", consume: "Pakai", adjust: "Adjust" };
  return map[type] || type;
}

function quotaTypeClass(type) {
  if (type === "topup") return "text-emerald-500";
  if (type === "consume") return "text-amber-500";
  return "text-slate-400";
}

function clearMessages() {
  error.value = "";
  success.value = "";
}

function switchTab(id) {
  tab.value = id;
  clearMessages();
  if (id === "profile" && !profile.value) loadProfile();
  if (id === "team" && auth.canManageTeam) loadTeam();
  if (id === "quota" && auth.canManageTeam) loadQuota(true);
}

async function loadProfile() {
  loadingProfile.value = true;
  clearMessages();
  try {
    const res = await api("/WaDesk/Account/profile");
    profile.value = res.data.profile;
    profileForm.value.name = profile.value.name;
  } catch (e) {
    error.value = e.message;
  } finally {
    loadingProfile.value = false;
  }
}

async function saveProfile() {
  savingProfile.value = true;
  clearMessages();
  try {
    const res = await api("/WaDesk/Account/updateProfile", {
      method: "POST",
      body: { name: profileForm.value.name.trim() },
    });
    if (res.data?.user) {
      auth.user = res.data.user;
      auth.persist();
    }
    profile.value = { ...profile.value, name: profileForm.value.name.trim() };
    success.value = res.message || "Profil diperbarui";
  } catch (e) {
    error.value = e.message;
  } finally {
    savingProfile.value = false;
  }
}

async function savePassword() {
  savingPassword.value = true;
  clearMessages();
  try {
    const res = await api("/WaDesk/Account/changePassword", {
      method: "POST",
      body: { ...passwordForm.value },
    });
    passwordForm.value = { current_password: "", new_password: "" };
    success.value = res.message || "Password berhasil diubah";
  } catch (e) {
    error.value = e.message;
  } finally {
    savingPassword.value = false;
  }
}

async function loadTeam() {
  loadingTeam.value = true;
  clearMessages();
  try {
    const res = await api("/WaDesk/Account/team");
    teamData.value = res.data;
  } catch (e) {
    error.value = e.message;
    teamData.value = null;
  } finally {
    loadingTeam.value = false;
  }
}

async function addAgent() {
  addingAgent.value = true;
  clearMessages();
  try {
    const res = await api("/WaDesk/Account/addAgent", {
      method: "POST",
      body: {
        name: agentForm.value.name.trim(),
        email: agentForm.value.email.trim(),
        password: agentForm.value.password,
      },
    });
    agentForm.value = { name: "", email: "", password: "" };
    success.value = res.message || "Agent ditambahkan";
    await loadTeam();
  } catch (e) {
    error.value = e.message;
  } finally {
    addingAgent.value = false;
  }
}

async function loadQuota(reset = false) {
  if (reset) {
    quotaPage.value = 1;
    loadingQuota.value = true;
  } else {
    loadingMoreQuota.value = true;
  }
  clearMessages();
  try {
    const res = await api(`/WaDesk/Account/quota?page=${quotaPage.value}`);
    const data = res.data;
    if (reset || !quotaData.value) {
      quotaData.value = data;
    } else {
      quotaData.value = {
        ...data,
        logs: [...(quotaData.value.logs || []), ...(data.logs || [])],
      };
    }
  } catch (e) {
    error.value = e.message;
    if (reset) quotaData.value = null;
  } finally {
    loadingQuota.value = false;
    loadingMoreQuota.value = false;
  }
}

async function loadMoreQuota() {
  quotaPage.value += 1;
  await loadQuota(false);
}

async function onLogout() {
  await auth.logout();
  router.push({ name: "login" });
}

onMounted(() => {
  const q = route.query.tab;
  if (q === "team" && auth.canManageTeam) tab.value = "team";
  else if (q === "quota" && auth.canManageTeam) tab.value = "quota";
  else tab.value = "profile";
  loadProfile();
  if (tab.value === "team") loadTeam();
  if (tab.value === "quota") loadQuota(true);
});

watch(
  () => auth.canManageTeam,
  (can) => {
    if (!can && (tab.value === "team" || tab.value === "quota")) {
      tab.value = "profile";
    }
  }
);
</script>

<style scoped>
.card {
  @apply rounded-2xl border border-white/10 bg-ink-900 p-5 shadow-sm;
}

.section-title {
  @apply font-display font-semibold text-base text-slate-100;
}

.account-tab {
  @apply px-4 py-2 rounded-xl text-sm font-medium text-slate-400 transition;
  @apply hover:bg-white/5 hover:text-slate-200;
}

.account-tab-active {
  @apply bg-accent text-white shadow-sm hover:bg-accent hover:text-white;
}

.label {
  @apply block text-xs text-slate-500 mb-1.5 font-medium;
}

.field {
  @apply w-full rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm text-slate-100;
  @apply focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent/40 transition;
}

.field-disabled {
  @apply opacity-60 cursor-not-allowed;
}

.btn {
  @apply px-4 py-2.5 rounded-xl bg-accent font-medium text-sm text-white hover:opacity-90 transition disabled:opacity-50;
}

.btn-secondary {
  @apply px-4 py-2 rounded-xl text-sm font-medium bg-white/5 text-slate-300 hover:bg-white/10 transition disabled:opacity-50;
}

.info-grid {
  @apply grid sm:grid-cols-2 gap-4;
}

.info-item dt {
  @apply text-xs text-slate-500 mb-1;
}

.info-item dd {
  @apply text-sm text-slate-100 font-medium;
}

.badge {
  @apply inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/5 text-slate-400;
}

.subcard-list {
  @apply rounded-xl border border-white/10 overflow-hidden divide-y divide-white/5 bg-ink-950/30;
}

.subcard-row {
  @apply px-4 py-3 flex items-center justify-between gap-3;
}

.empty-state {
  @apply py-10 text-center text-sm text-slate-500;
}

.alert {
  @apply rounded-2xl border px-4 py-3 text-sm;
}

.alert-error {
  @apply border-red-500/30 bg-red-500/10 text-red-400;
}

.alert-success {
  @apply border-emerald-500/30 bg-emerald-500/10 text-emerald-400;
}

.alert-warn {
  @apply border-amber-500/30 bg-amber-500/10 text-amber-200;
}

.alert-link {
  @apply text-accent-soft hover:underline ml-1 font-medium;
}

/* Light theme — card putih */
[data-theme="light"] .card {
  @apply bg-white border-slate-200/80 shadow-sm;
}

[data-theme="light"] .subcard-list {
  @apply bg-slate-50 border-slate-200/80 divide-slate-200/60;
}

[data-theme="light"] .field {
  @apply bg-white border-slate-200;
}

[data-theme="light"] .account-tab {
  @apply text-slate-500 hover:bg-slate-100 hover:text-slate-800;
}

[data-theme="light"] .account-tab-active {
  @apply bg-accent text-white hover:bg-accent hover:text-white;
}

[data-theme="light"] .btn-secondary {
  @apply bg-slate-100 text-slate-700 hover:bg-slate-200;
}

[data-theme="light"] .badge {
  @apply bg-slate-100 text-slate-600;
}

[data-theme="light"] .alert-warn {
  @apply text-amber-800 bg-amber-50 border-amber-200;
}
</style>
