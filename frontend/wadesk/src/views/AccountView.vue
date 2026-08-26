<template>
  <AppHeader page-title="Account" active="account" @logout="onLogout">
    <div class="flex-1 overflow-y-auto">
      <div class="max-w-3xl mx-auto p-4 space-y-6">
        <nav class="flex flex-wrap gap-2">
          <button
            v-for="t in visibleTabs"
            :key="t.id"
            type="button"
            class="px-3 py-1.5 rounded-lg text-sm"
            :class="tab === t.id ? 'bg-accent text-white' : 'bg-white/5 text-slate-300'"
            @click="switchTab(t.id)"
          >
            {{ t.label }}
          </button>
        </nav>

        <p v-if="error" class="text-sm text-red-400">{{ error }}</p>
        <p v-if="success" class="text-sm text-emerald-400">{{ success }}</p>

        <!-- Profil -->
        <section v-if="tab === 'profile'" class="card space-y-5">
          <h2 class="font-display font-semibold text-lg">Profil</h2>

          <div v-if="loadingProfile" class="text-sm text-slate-500">Memuat profil...</div>
          <template v-else-if="profile">
            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
              <div>
                <dt class="text-slate-500 mb-1">Organisasi</dt>
                <dd class="text-slate-100">{{ profile.tenant_name || "—" }}</dd>
              </div>
              <div>
                <dt class="text-slate-500 mb-1">Role</dt>
                <dd><span class="px-2 py-0.5 rounded-full bg-white/5">{{ roleLabel(profile.role) }}</span></dd>
              </div>
              <div>
                <dt class="text-slate-500 mb-1">Team</dt>
                <dd class="text-slate-100">{{ profile.team_name || "—" }}</dd>
              </div>
              <div>
                <dt class="text-slate-500 mb-1">Bergabung sejak</dt>
                <dd class="text-slate-100">{{ formatDate(profile.created_at) }}</dd>
              </div>
            </dl>

            <form class="space-y-3 pt-2 border-t border-white/10" @submit.prevent="saveProfile">
              <div>
                <label class="label">Nama</label>
                <input v-model="profileForm.name" required class="field" />
              </div>
              <div>
                <label class="label">Email</label>
                <input :value="profile.email" disabled class="field opacity-60 cursor-not-allowed" />
              </div>
              <button class="btn" :disabled="savingProfile">{{ savingProfile ? "Menyimpan..." : "Simpan profil" }}</button>
            </form>

            <form class="space-y-3 pt-4 border-t border-white/10" @submit.prevent="savePassword">
              <h3 class="font-medium text-slate-200">Ubah password</h3>
              <div>
                <label class="label">Password saat ini</label>
                <input v-model="passwordForm.current_password" type="password" required class="field" autocomplete="current-password" />
              </div>
              <div>
                <label class="label">Password baru</label>
                <input v-model="passwordForm.new_password" type="password" required minlength="6" class="field" autocomplete="new-password" />
              </div>
              <button class="btn" :disabled="savingPassword">{{ savingPassword ? "Menyimpan..." : "Ubah password" }}</button>
            </form>
          </template>
        </section>

        <!-- Team -->
        <section v-if="tab === 'team'" class="card space-y-4">
          <h2 class="font-display font-semibold text-lg">Team</h2>

          <div
            v-if="auth.isAdmin && !auth.hasTeam"
            class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"
          >
            Admin belum bergabung ke team.
            <router-link to="/admin" class="text-accent-soft hover:underline ml-1">Masuk team di Admin →</router-link>
          </div>

          <div v-else-if="loadingTeam" class="text-sm text-slate-500">Memuat data team...</div>
          <template v-else-if="teamData">
            <div class="rounded-xl border border-white/10 bg-ink-950/40 p-4 space-y-1">
              <p class="text-lg font-semibold text-slate-100">{{ teamData.team.name }}</p>
              <p class="text-sm text-slate-400">
                Team Leader: {{ teamData.team.leader_name || "—" }}
                <span v-if="teamData.team.leader_email"> · {{ teamData.team.leader_email }}</span>
              </p>
              <p class="text-xs text-slate-500">
                Agent: {{ teamData.agent_count }} / {{ teamData.max_agents }}
              </p>
            </div>

            <div class="rounded-xl border border-white/10 overflow-hidden divide-y divide-white/5">
              <div
                v-for="m in teamData.members"
                :key="m.id"
                class="px-4 py-3 flex items-center justify-between gap-3"
              >
                <div class="min-w-0">
                  <p class="font-medium text-slate-100 truncate">
                    {{ m.name }}
                    <span v-if="m.is_self" class="text-xs text-accent-soft font-normal">(Anda)</span>
                  </p>
                  <p class="text-xs text-slate-500 truncate">{{ m.email }}</p>
                </div>
                <span class="shrink-0 px-2 py-0.5 rounded-full text-xs bg-white/5 text-slate-400">
                  {{ roleLabel(m.role) }}
                </span>
              </div>
              <p v-if="!teamData.members.length" class="py-8 text-center text-sm text-slate-500">
                Belum ada anggota team.
              </p>
            </div>

            <form
              v-if="teamData.can_add_agent"
              class="space-y-3 pt-2 border-t border-white/10"
              @submit.prevent="addAgent"
            >
              <h3 class="font-medium text-slate-200">Tambah agent</h3>
              <p class="text-xs text-slate-500">Maksimal {{ teamData.max_agents }} agent per team.</p>
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
              <button class="btn" :disabled="addingAgent">{{ addingAgent ? "Menambah..." : "Tambah agent" }}</button>
            </form>
            <p v-else class="text-sm text-slate-500 pt-2 border-t border-white/10">
              Kuota agent team sudah penuh (maks. {{ teamData.max_agents }}).
            </p>
          </template>
        </section>

        <!-- Quota -->
        <section v-if="tab === 'quota'" class="card space-y-4">
          <h2 class="font-display font-semibold text-lg">Kuota Template</h2>

          <div
            v-if="auth.isAdmin && !auth.hasTeam"
            class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"
          >
            Admin belum bergabung ke team.
            <router-link to="/admin" class="text-accent-soft hover:underline ml-1">Masuk team di Admin →</router-link>
          </div>

          <div v-else-if="loadingQuota" class="text-sm text-slate-500">Memuat kuota...</div>
          <template v-else-if="quotaData">
            <div class="rounded-xl border border-accent/20 bg-accent/5 p-4 flex items-center justify-between gap-4">
              <div>
                <p class="text-sm text-slate-400">{{ quotaData.team_name }}</p>
                <p class="text-3xl font-semibold text-accent">{{ quotaData.balance }}</p>
                <p class="text-xs text-slate-500">sisa kuota template</p>
              </div>
            </div>

            <div>
              <h3 class="font-medium text-slate-200 mb-3">Riwayat</h3>
              <div class="rounded-xl border border-white/10 overflow-hidden">
                <div class="max-h-[min(50vh,24rem)] overflow-y-auto divide-y divide-white/5">
                  <div
                    v-if="!quotaData.logs.length"
                    class="py-10 text-center text-sm text-slate-500"
                  >
                    Belum ada riwayat kuota.
                  </div>
                  <div
                    v-for="log in quotaData.logs"
                    :key="log.id"
                    class="px-4 py-3 flex items-start justify-between gap-3 text-sm"
                  >
                    <div class="min-w-0">
                      <p class="font-medium text-slate-200">
                        <span :class="quotaTypeClass(log.type)">{{ quotaTypeLabel(log.type) }}</span>
                        <span class="ml-2" :class="log.amount >= 0 ? 'text-emerald-400' : 'text-red-400'">
                          {{ log.amount >= 0 ? "+" : "" }}{{ log.amount }}
                        </span>
                      </p>
                      <p v-if="log.note" class="text-xs text-slate-500 mt-0.5">{{ log.note }}</p>
                      <p class="text-xs text-slate-600 mt-0.5">
                        {{ formatDate(log.created_at) }}
                        <span v-if="log.user_name"> · {{ log.user_name }}</span>
                      </p>
                    </div>
                    <p class="shrink-0 text-slate-400 text-xs">saldo {{ log.balance_after }}</p>
                  </div>
                </div>
              </div>
              <div v-if="quotaHasMore" class="pt-3 text-center">
                <button type="button" class="px-3 py-1.5 rounded-lg text-sm bg-white/5 text-slate-300 hover:bg-white/10" :disabled="loadingMoreQuota" @click="loadMoreQuota">
                  {{ loadingMoreQuota ? "Memuat..." : "Muat lebih banyak" }}
                </button>
              </div>
            </div>
          </template>
        </section>
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
  if (type === "topup") return "text-emerald-400";
  if (type === "consume") return "text-amber-300";
  return "text-slate-300";
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
