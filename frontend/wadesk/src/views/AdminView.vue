<template>
  <div class="min-h-full bg-ink-950">
    <header class="h-14 px-4 border-b border-white/10 flex items-center justify-between bg-ink-900/80 sticky top-0 z-10">
      <div class="flex items-center gap-3">
        <router-link to="/" class="text-slate-400 hover:text-white">← Inbox</router-link>
        <span class="font-display font-semibold">Admin</span>
      </div>
      <span class="text-xs text-slate-500">{{ auth.user?.email }}</span>
    </header>

    <div class="max-w-5xl mx-auto p-4 space-y-6">
      <nav class="flex flex-wrap gap-2">
        <button
          v-for="t in tabs"
          :key="t.id"
          type="button"
          class="px-3 py-1.5 rounded-lg text-sm"
          :class="tab === t.id ? 'bg-accent text-white' : 'bg-white/5 text-slate-300'"
          @click="tab = t.id"
        >
          {{ t.label }}
        </button>
      </nav>

      <!-- Teams -->
      <section v-if="tab === 'teams'" class="card space-y-4">
        <h2 class="font-display font-semibold text-lg">Teams</h2>
        <form class="flex flex-col sm:flex-row gap-2" @submit.prevent="createTeam">
          <input v-model="teamForm.name" required class="field flex-1" placeholder="Nama team" />
          <button class="btn">Tambah</button>
        </form>
        <ul class="divide-y divide-white/5">
          <li v-for="t in teams" :key="t.id" class="py-3 flex items-center justify-between gap-2">
            <div>
              <p class="font-medium">{{ t.name }}</p>
              <p class="text-xs text-slate-500">
                Leader: {{ t.leader_name || "—" }} · Agents: {{ t.agent_count }}
              </p>
            </div>
            <button class="text-xs text-rose-400" @click="removeTeam(t.id)">Hapus</button>
          </li>
        </ul>
      </section>

      <!-- Users -->
      <section v-if="tab === 'users'" class="card space-y-4">
        <h2 class="font-display font-semibold text-lg">Team Leader & Agent</h2>
        <form class="grid sm:grid-cols-2 gap-3" @submit.prevent="createUser">
          <input v-model="userForm.name" required class="field" placeholder="Nama" />
          <input v-model="userForm.email" type="email" required class="field" placeholder="Email" />
          <input v-model="userForm.password" type="password" required minlength="6" class="field" placeholder="Password" />
          <select v-model="userForm.role" required class="field" @change="onRoleChange">
            <option value="team_leader">Team Leader</option>
            <option value="agent">Agent</option>
          </select>

          <!-- Team Leader: pilih team -->
          <template v-if="userForm.role === 'team_leader'">
            <select
              v-model="userForm.team_id"
              required
              class="field sm:col-span-2"
            >
              <option disabled value="">Pilih team</option>
              <option v-for="t in teamsWithoutLeader" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
            <p v-if="!teamsWithoutLeader.length" class="sm:col-span-2 text-xs text-amber-300">
              Semua team sudah punya Team Leader. Buat team baru dulu.
            </p>
          </template>

          <!-- Agent: wajib pilih team leader -->
          <template v-else>
            <select
              v-model="userForm.team_leader_user_id"
              required
              class="field sm:col-span-2"
            >
              <option disabled value="">Pilih Team Leader</option>
              <option v-for="l in teamLeaders" :key="l.id" :value="l.id">
                {{ l.name }} ({{ l.team_name || "tanpa team" }})
              </option>
            </select>
            <p v-if="!teamLeaders.length" class="sm:col-span-2 text-xs text-amber-300">
              Belum ada Team Leader. Buat Team Leader dulu sebelum menambah Agent.
            </p>
          </template>

          <button class="btn sm:col-span-2" :disabled="!canSubmitUser">Tambah user</button>
        </form>
        <ul class="divide-y divide-white/5">
          <li v-for="u in users" :key="u.id" class="py-3 flex justify-between gap-2">
            <div>
              <p class="font-medium">{{ u.name }} <span class="text-xs text-slate-500">({{ u.role }})</span></p>
              <p class="text-xs text-slate-500">
                {{ u.email }} · {{ u.team_name || "—" }}
                <span v-if="u.role === 'agent' && u.team_leader_name"> · TL: {{ u.team_leader_name }}</span>
              </p>
            </div>
            <button class="text-xs text-rose-400" @click="removeUser(u.id)">Hapus</button>
          </li>
        </ul>
      </section>

      <!-- Keys -->
      <section v-if="tab === 'keys'" class="card space-y-4">
        <h2 class="font-display font-semibold text-lg">YCloud API Keys</h2>
        <form class="grid sm:grid-cols-2 gap-3" @submit.prevent="createKey">
          <input v-model="keyForm.label" required class="field" placeholder="Label" />
          <input v-model="keyForm.phone_number" required class="field" placeholder="Nomor WA bisnis 628..." />
          <input v-model="keyForm.api_key" required class="field sm:col-span-2" placeholder="API Key YCloud" />
          <input v-model="keyForm.ycloud_phone_id" class="field" placeholder="Phone Number ID (opsional)" />
          <select v-model="keyForm.team_id" required class="field">
            <option disabled value="">Assign ke team</option>
            <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <button class="btn sm:col-span-2">Simpan key</button>
        </form>
        <ul class="divide-y divide-white/5">
          <li v-for="k in keys" :key="k.id" class="py-3 flex justify-between gap-2">
            <div>
              <p class="font-medium">{{ k.label }}</p>
              <p class="text-xs text-slate-500">{{ k.phone_number }} · {{ k.team_name }} · {{ k.status }}</p>
            </div>
            <button class="text-xs text-rose-400" @click="removeKey(k.id)">Hapus</button>
          </li>
        </ul>
      </section>

      <!-- Templates -->
      <section v-if="tab === 'templates'" class="card space-y-4">
        <h2 class="font-display font-semibold text-lg">Templates</h2>
        <form class="space-y-3" @submit.prevent="createTemplate">
          <select v-model="tplForm.ycloud_key_id" required class="field">
            <option disabled value="">API key</option>
            <option v-for="k in keys" :key="k.id" :value="k.id">{{ k.label }} ({{ k.phone_number }})</option>
          </select>
          <div class="grid sm:grid-cols-2 gap-3">
            <input v-model="tplForm.template_name" required class="field" placeholder="Nama template YCloud" />
            <input v-model="tplForm.language" class="field" placeholder="Language (id)" />
          </div>
          <textarea
            v-model="tplForm.body_preview"
            rows="5"
            class="field"
            :placeholder="previewPlaceholder"
          />
          <p class="text-[11px] text-slate-500 -mt-1 whitespace-pre-wrap">{{ previewHint }}</p>
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <p class="text-sm text-slate-300">Parameter (sesuai YCloud)</p>
              <button type="button" class="text-xs text-accent-soft" @click="addParam">+ Param</button>
            </div>
            <div
              v-for="(p, idx) in tplForm.params"
              :key="idx"
              class="grid grid-cols-1 sm:grid-cols-[7rem_5rem_1fr_1fr_1fr_auto] gap-2 items-center"
            >
              <select v-model="p.component" class="field">
                <option value="header">header</option>
                <option value="body">body</option>
                <option value="button">button</option>
              </select>
              <input v-model.number="p.param_index" type="number" min="1" class="field" title="Urutan dalam komponen" />
              <input v-model="p.param_name" class="field" placeholder="Nama var (customer)" required />
              <input v-model="p.label" class="field" placeholder="Label form" required />
              <input v-model="p.example_value" class="field" placeholder="Contoh" />
              <button type="button" class="text-rose-400 text-sm" @click="tplForm.params.splice(idx, 1)">✕</button>
            </div>
          </div>
          <button class="btn w-full">Simpan template</button>
        </form>
        <ul class="divide-y divide-white/5">
          <li v-for="t in templates" :key="t.id" class="py-3 flex justify-between gap-2">
            <div>
              <p class="font-medium">{{ t.template_name }} <span class="text-xs text-slate-500">{{ t.language }}</span></p>
              <p class="text-xs text-slate-500">
                {{ t.key_label }} ·
                {{ (t.params || []).map((p) => `${p.component}:${p.param_name || p.param_index}`).join(", ") || "0 param" }}
              </p>
            </div>
            <button class="text-xs text-rose-400" @click="removeTemplate(t.id)">Hapus</button>
          </li>
        </ul>
      </section>

      <p v-if="msg" class="text-sm text-emerald-400">{{ msg }}</p>
      <p v-if="err" class="text-sm text-rose-400">{{ err }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { api } from "../api";
import { useAuthStore } from "../stores/auth";

const auth = useAuthStore();
const tab = ref("teams");
const tabs = [
  { id: "teams", label: "Teams" },
  { id: "users", label: "Users" },
  { id: "keys", label: "API Keys" },
  { id: "templates", label: "Templates" },
];

const teams = ref([]);
const users = ref([]);
const keys = ref([]);
const templates = ref([]);
const msg = ref("");
const err = ref("");
const previewPlaceholder =
  "Preview Body — teks lengkap seperti di WA. Pakai {{customer}} atau {{1}} di tempat variabel. Saat kirim diganti value di bubble chat.";
const previewHint =
  "Contoh (header named customer):\n{{customer}}\n\nMohon di perhatikan, tagihan anda pada aplikasi Pinjamin...";

const teamForm = reactive({ name: "" });
const userForm = reactive({
  name: "",
  email: "",
  password: "",
  role: "agent",
  team_id: "",
  team_leader_user_id: "",
});
const keyForm = reactive({ label: "", api_key: "", phone_number: "", ycloud_phone_id: "", team_id: "" });
const tplForm = reactive({
  ycloud_key_id: "",
  template_name: "",
  language: "id",
  body_preview: "",
  params: [{ component: "header", param_index: 1, param_name: "customer", label: "Nama customer", example_value: "", is_required: 1 }],
});

const teamLeaders = computed(() =>
  users.value.filter((u) => u.role === "team_leader" && Number(u.is_active) === 1)
);

const teamsWithoutLeader = computed(() =>
  teams.value.filter((t) => !t.team_leader_user_id)
);

const canSubmitUser = computed(() => {
  if (userForm.role === "team_leader") {
    return !!userForm.team_id && teamsWithoutLeader.value.length > 0;
  }
  return !!userForm.team_leader_user_id && teamLeaders.value.length > 0;
});

function onRoleChange() {
  userForm.team_id = "";
  userForm.team_leader_user_id = "";
}

function flash(ok, text) {
  msg.value = ok ? text : "";
  err.value = ok ? "" : text;
}

async function refresh() {
  const [t, u, k, tp] = await Promise.all([
    api("/WaDesk/Teams/list"),
    api("/WaDesk/Users/list"),
    api("/WaDesk/Keys/list"),
    api("/WaDesk/Templates/list"),
  ]);
  teams.value = t.data.teams || [];
  users.value = u.data.users || [];
  keys.value = k.data.keys || [];
  templates.value = tp.data.templates || [];
}

async function createTeam() {
  try {
    await api("/WaDesk/Teams/create", { method: "POST", body: { name: teamForm.name } });
    teamForm.name = "";
    flash(true, "Team dibuat");
    await refresh();
  } catch (e) {
    flash(false, e.message);
  }
}

async function removeTeam(id) {
  if (!confirm("Hapus team?")) return;
  try {
    await api("/WaDesk/Teams/delete", { method: "POST", body: { id } });
    flash(true, "Team dihapus");
    await refresh();
  } catch (e) {
    flash(false, e.message);
  }
}

async function createUser() {
  try {
    if (userForm.role === "agent" && !userForm.team_leader_user_id) {
      flash(false, "Agent wajib memilih Team Leader");
      return;
    }
    if (userForm.role === "team_leader" && !userForm.team_id) {
      flash(false, "Team Leader wajib memilih team");
      return;
    }

    const payload = {
      name: userForm.name,
      email: userForm.email,
      password: userForm.password,
      role: userForm.role,
    };
    if (userForm.role === "team_leader") {
      payload.team_id = Number(userForm.team_id);
    } else {
      payload.team_leader_user_id = Number(userForm.team_leader_user_id);
    }

    await api("/WaDesk/Users/create", {
      method: "POST",
      body: payload,
    });
    Object.assign(userForm, {
      name: "",
      email: "",
      password: "",
      role: "agent",
      team_id: "",
      team_leader_user_id: "",
    });
    flash(true, "User dibuat");
    await refresh();
  } catch (e) {
    flash(false, e.message);
  }
}

async function removeUser(id) {
  if (!confirm("Hapus user?")) return;
  try {
    await api("/WaDesk/Users/delete", { method: "POST", body: { id } });
    flash(true, "User dihapus");
    await refresh();
  } catch (e) {
    flash(false, e.message);
  }
}

async function createKey() {
  try {
    await api("/WaDesk/Keys/create", {
      method: "POST",
      body: {
        ...keyForm,
        team_id: Number(keyForm.team_id),
        ycloud_phone_id: keyForm.ycloud_phone_id || null,
      },
    });
    Object.assign(keyForm, { label: "", api_key: "", phone_number: "", ycloud_phone_id: "", team_id: "" });
    flash(true, "API key disimpan");
    await refresh();
  } catch (e) {
    flash(false, e.message);
  }
}

async function removeKey(id) {
  if (!confirm("Hapus API key?")) return;
  try {
    await api("/WaDesk/Keys/delete", { method: "POST", body: { id } });
    flash(true, "Key dihapus");
    await refresh();
  } catch (e) {
    flash(false, e.message);
  }
}

function addParam() {
  const next = (tplForm.params.at(-1)?.param_index || 0) + 1;
  tplForm.params.push({
    component: "body",
    param_index: next,
    param_name: "",
    label: "",
    example_value: "",
    is_required: 1,
  });
}

async function createTemplate() {
  try {
    await api("/WaDesk/Templates/create", {
      method: "POST",
      body: {
        ycloud_key_id: Number(tplForm.ycloud_key_id),
        template_name: tplForm.template_name,
        language: tplForm.language || "id",
        body_preview: tplForm.body_preview,
        params: tplForm.params,
      },
    });
    Object.assign(tplForm, {
      ycloud_key_id: "",
      template_name: "",
      language: "id",
      body_preview: "",
      params: [{ component: "header", param_index: 1, param_name: "customer", label: "Nama customer", example_value: "", is_required: 1 }],
    });
    flash(true, "Template disimpan");
    await refresh();
  } catch (e) {
    flash(false, e.message);
  }
}

async function removeTemplate(id) {
  if (!confirm("Hapus template?")) return;
  try {
    await api("/WaDesk/Templates/delete", { method: "POST", body: { id } });
    flash(true, "Template dihapus");
    await refresh();
  } catch (e) {
    flash(false, e.message);
  }
}

onMounted(refresh);
</script>

<style scoped>
.card {
  @apply rounded-2xl border border-white/10 bg-ink-900/50 p-4;
}
.field {
  @apply w-full rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-accent/40;
}
.btn {
  @apply px-4 py-2.5 rounded-xl bg-accent font-medium text-sm hover:bg-accent-soft transition;
}
</style>
