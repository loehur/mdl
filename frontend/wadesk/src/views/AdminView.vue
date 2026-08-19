<template>
  <div class="min-h-full bg-ink-950">
    <header class="h-14 px-4 border-b border-white/10 flex items-center justify-between bg-ink-900/80 sticky top-0 z-10">
      <div class="flex items-center gap-3">
        <router-link to="/" class="text-slate-400 hover:text-slate-100">← Inbox</router-link>
        <span class="font-display font-semibold text-slate-100">Admin</span>
      </div>
      <div class="flex items-center gap-3">
        <span class="text-xs text-slate-500">{{ auth.user?.email }}</span>
        <ThemeToggle compact />
      </div>
    </header>

    <div class="max-w-5xl mx-auto p-4 space-y-6">
      <!-- Admin team operasional -->
      <section class="card space-y-3 border-accent/20">
        <h2 class="font-display font-semibold text-lg">Team operasional</h2>
        <p class="text-sm text-slate-400">
          Admin harus masuk team untuk kirim chat atau blast WA. Panel admin tetap bisa diakses tanpa team.
        </p>
        <div v-if="auth.hasTeam" class="flex flex-col sm:flex-row sm:items-center gap-3">
          <p class="text-sm text-slate-200">
            Aktif di team: <span class="font-semibold text-accent">{{ auth.user?.team_name || `#${auth.user?.team_id}` }}</span>
          </p>
          <button type="button" class="btn-sm shrink-0" :disabled="joiningTeam" @click="leaveOperationalTeam">
            {{ joiningTeam ? "..." : "Keluar dari team" }}
          </button>
        </div>
        <form v-else class="flex flex-col sm:flex-row gap-2" @submit.prevent="joinOperationalTeam">
          <select v-model="adminTeamPick" required class="field flex-1">
            <option disabled value="">Pilih team untuk operasional</option>
            <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <button type="submit" class="btn shrink-0" :disabled="joiningTeam || !teams.length">
            {{ joiningTeam ? "Masuk..." : "Masuk team" }}
          </button>
        </form>
        <p v-if="!teams.length" class="text-xs text-amber-300">Buat team dulu di tab Teams.</p>
      </section>

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
            <div class="min-w-0 flex-1">
              <template v-if="editingTeamId === t.id">
                <form class="flex flex-col sm:flex-row gap-2" @submit.prevent="saveTeamName(t)">
                  <input
                    v-model="editingTeamName"
                    required
                    maxlength="100"
                    class="field flex-1"
                    placeholder="Nama team"
                    @keydown.esc.prevent="cancelEditTeam"
                  />
                  <div class="flex gap-2 shrink-0">
                    <button type="submit" class="btn" :disabled="savingTeam">
                      {{ savingTeam ? "Menyimpan..." : "Simpan" }}
                    </button>
                    <button type="button" class="btn-sm" :disabled="savingTeam" @click="cancelEditTeam">
                      Batal
                    </button>
                  </div>
                </form>
              </template>
              <template v-else>
                <p class="font-medium truncate">{{ t.name }}</p>
                <p class="text-xs text-slate-500">
                  Leader: {{ t.leader_name || "—" }} · Agents: {{ t.agent_count }}
                </p>
              </template>
            </div>
            <div v-if="editingTeamId !== t.id" class="flex items-center gap-3 shrink-0">
              <button type="button" class="text-xs text-accent hover:underline" @click="startEditTeam(t)">
                Ubah
              </button>
              <button type="button" class="text-xs text-rose-400" @click="removeTeam(t.id)">Hapus</button>
            </div>
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
          <li v-for="u in users" :key="u.id" class="py-3">
            <template v-if="editingUserId === u.id">
              <form class="grid sm:grid-cols-2 gap-3 mt-1" @submit.prevent="saveUser(u)">
                <input v-model="editUserForm.name" required class="field" placeholder="Nama" />
                <input v-model="editUserForm.email" type="email" required class="field" placeholder="Email" />
                <input
                  v-model="editUserForm.password"
                  type="password"
                  minlength="6"
                  class="field"
                  placeholder="Password baru (kosongkan jika tidak diganti)"
                />
                <div class="field flex items-center gap-2 cursor-default select-none">
                  <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input v-model="editUserForm.is_active" type="checkbox" class="rounded" />
                    Aktif
                  </label>
                </div>

                <!-- Team leader: ganti team -->
                <template v-if="u.role === 'team_leader'">
                  <select v-model="editUserForm.team_id" class="field sm:col-span-2">
                    <option value="">— Tetap di team saat ini —</option>
                    <option v-for="t in teamsWithoutLeader" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </template>

                <!-- Agent: ganti team leader -->
                <template v-else>
                  <select v-model="editUserForm.team_leader_user_id" class="field sm:col-span-2">
                    <option value="">— Tetap di team saat ini —</option>
                    <option v-for="l in teamLeaders" :key="l.id" :value="l.id">
                      {{ l.name }} ({{ l.team_name || "tanpa team" }})
                    </option>
                  </select>
                </template>

                <div class="sm:col-span-2 flex gap-2">
                  <button type="submit" class="btn" :disabled="savingUser">{{ savingUser ? "Menyimpan..." : "Simpan" }}</button>
                  <button type="button" class="btn-sm" :disabled="savingUser" @click="cancelEditUser">Batal</button>
                </div>
              </form>
            </template>
            <template v-else>
              <div class="flex justify-between gap-2">
                <div>
                  <p class="font-medium">
                    {{ u.name }}
                    <span class="text-xs text-slate-500">({{ u.role }})</span>
                    <span v-if="!Number(u.is_active)" class="ml-1 text-xs text-rose-400">nonaktif</span>
                  </p>
                  <p class="text-xs text-slate-500">
                    {{ u.email }} · {{ u.team_name || "—" }}
                    <span v-if="u.role === 'agent' && u.team_leader_name"> · TL: {{ u.team_leader_name }}</span>
                  </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                  <button
                    type="button"
                    class="text-xs text-accent hover:underline"
                    @click="startEditUser(u)"
                  >
                    Ubah
                  </button>
                  <button
                    v-if="u.role === 'agent'"
                    type="button"
                    class="text-xs text-accent hover:underline"
                    @click="promoteAgent(u)"
                  >
                    Jadikan TL
                  </button>
                  <button type="button" class="text-xs text-rose-400" @click="removeUser(u)">Hapus</button>
                </div>
              </div>
            </template>
          </li>
        </ul>
      </section>

      <!-- Channels -->
      <section v-if="tab === 'keys'" class="card space-y-4">
        <h2 class="font-display font-semibold text-lg">Channel / Nomor (Kirimin)</h2>

        <form class="rounded-xl border border-white/10 bg-ink-950/40 p-3 space-y-3" @submit.prevent="saveKiriminKey">
          <p class="text-xs text-slate-400">
            API key Kirimin disimpan per tenant (1 admin = 1 key). Dipakai untuk sync device & template.
          </p>
          <div class="flex flex-col sm:flex-row gap-2">
            <input
              v-model="kiriminForm.api_key"
              type="password"
              class="field flex-1"
              :placeholder="kiriminForm.configured ? kiriminForm.api_key_masked : 'kc_live_...'"
              autocomplete="off"
            />
            <button type="submit" class="btn shrink-0" :disabled="savingKiriminKey">
              {{ savingKiriminKey ? "Menyimpan..." : "Simpan API key" }}
            </button>
          </div>
          <p v-if="kiriminForm.configured" class="text-xs text-emerald-400">
            Terkonfigurasi: {{ kiriminForm.api_key_masked }}
          </p>
        </form>

        <form class="rounded-xl border border-white/10 bg-ink-950/40 p-3 space-y-3" @submit.prevent="saveDailyLimit">
          <p class="text-xs text-slate-400">
            Limit harian nomor customer <strong>unik</strong> per tenant (semua channel/team digabung). Reset setiap hari.
          </p>
          <div class="flex flex-col sm:flex-row gap-2 items-end">
            <div class="flex-1 w-full">
              <label class="label">Maks. nomor unik per hari</label>
              <input
                v-model.number="dailyLimitForm.daily_unique_limit"
                type="number"
                min="1"
                max="100000"
                required
                class="field"
              />
            </div>
            <button type="submit" class="btn shrink-0" :disabled="savingDailyLimit">
              {{ savingDailyLimit ? "Menyimpan..." : "Simpan limit" }}
            </button>
          </div>
        </form>

        <p class="text-xs text-slate-400">
          Sync device dari Kirimin, lalu assign <strong>1 nomor = 1 team</strong>.
        </p>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-sm" :disabled="syncingDevices" @click="syncDevicesFromKirimin">
            {{ syncingDevices ? "Sync..." : "Sync device dari Kirimin" }}
          </button>
        </div>
        <form class="grid sm:grid-cols-2 gap-3" @submit.prevent="assignChannel">
          <select v-model="channelForm.device_id" required class="field sm:col-span-2">
            <option disabled value="">Pilih device (sync dulu jika kosong)</option>
            <option
              v-for="d in availableDevices"
              :key="d.device_id"
              :value="d.device_id"
              :disabled="!!d.assigned"
            >
              {{ d.label || d.device_id }} · {{ d.phone_number || "—" }}
              {{ d.assigned ? "(sudah di-assign)" : "" }}
            </option>
          </select>
          <input v-model="channelForm.label" required class="field" placeholder="Label tampilan" />
          <select v-model="channelForm.team_id" required class="field">
            <option disabled value="">Assign ke team</option>
            <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <button class="btn sm:col-span-2">Assign channel</button>
        </form>
        <ul class="divide-y divide-white/5">
          <li v-for="k in keys" :key="k.id" class="py-3">
            <template v-if="editingKeyId === k.id">
              <form class="grid sm:grid-cols-2 gap-3 mt-1" @submit.prevent="saveKey(k)">
                <input v-model="editKeyForm.label" required class="field" placeholder="Label" />
                <input v-model="editKeyForm.phone_number" class="field" placeholder="Nomor (opsional)" />
                <select v-model="editKeyForm.team_id" required class="field sm:col-span-2">
                  <option disabled value="">Assign ke team</option>
                  <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <div class="sm:col-span-2 flex gap-2">
                  <button type="submit" class="btn" :disabled="savingKey">{{ savingKey ? "Menyimpan..." : "Simpan" }}</button>
                  <button type="button" class="btn-sm" :disabled="savingKey" @click="cancelEditKey">Batal</button>
                </div>
              </form>
            </template>
            <template v-else>
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <p class="font-medium">{{ k.label }}</p>
                  <p class="text-xs text-slate-500">
                    {{ k.phone_number }}
                    <span v-if="k.device_id"> · device: {{ k.device_id }}</span>
                    · {{ k.team_name }} · {{ k.status }}
                  </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                  <button type="button" class="text-xs text-accent hover:underline" @click="startEditKey(k)">Ubah</button>
                  <button type="button" class="text-xs text-rose-400" @click="removeKey(k.id)">Hapus</button>
                </div>
              </div>
            </template>
          </li>
        </ul>
      </section>

      <!-- Templates -->
      <section v-if="tab === 'templates'" class="card space-y-4">
        <h2 class="font-display font-semibold text-lg">Templates</h2>

        <div class="rounded-xl border border-white/10 bg-ink-950/40 p-3 space-y-2">
          <p class="text-xs text-slate-400">
            Sinkron template <span class="text-slate-200">APPROVED</span> dari Kirimin.id.
            Template dibagikan ke <span class="text-slate-200">semua team</span> dalam tenant.
          </p>
          <div class="flex flex-col sm:flex-row gap-2">
            <button
              type="button"
              class="btn shrink-0 inline-flex items-center justify-center gap-2 min-w-[9rem]"
              :disabled="syncing"
              @click="syncTemplatesFromKirimin"
            >
              <svg
                v-if="syncing"
                class="h-4 w-4 animate-spin"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
              </svg>
              {{ syncing ? "Sinkron..." : "Sync dari Kirimin" }}
            </button>
          </div>
        </div>

        <ul class="divide-y divide-white/5">
          <li v-for="t in templates" :key="t.id" class="py-3">
            <!-- Header row -->
            <div class="flex items-start gap-3">
              <div class="flex-1 min-w-0">
                <p class="font-medium">
                  {{ t.template_name }}
                  <span class="text-xs text-slate-500 ml-1">{{ t.language }}</span>
                  <span class="text-xs text-slate-500 ml-1">· {{ t.key_label }}</span>
                </p>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ (t.params || []).length }} param ·
                  <span
                    v-for="comp in ['header','body','button']"
                    :key="comp"
                    class="mr-2"
                  >
                    <span v-if="(t.params||[]).some(p=>p.component===comp)">
                      <span class="font-medium capitalize">{{ comp }}</span>:
                      {{ (t.params||[]).filter(p=>p.component===comp).map(p=>p.param_name||('#'+p.param_index)).join(', ') }}
                    </span>
                  </span>
                </p>
                <p v-if="t.body_preview" class="mt-1 text-[11px] text-slate-400 whitespace-pre-wrap line-clamp-2">
                  {{ t.body_preview }}
                </p>
              </div>
              <div class="flex gap-1 shrink-0">
                <button
                  type="button"
                  class="btn-sm text-xs"
                  @click="expandedTemplate = expandedTemplate === t.id ? null : t.id"
                >
                  {{ expandedTemplate === t.id ? 'Tutup' : 'Detail' }}
                </button>
                <button
                  type="button"
                  class="btn-sm text-xs"
                  :disabled="resyncingId === t.id"
                  @click="resyncOneTemplate(t.template_name)"
                  title="Resync params template ini dari Kirimin"
                >
                  {{ resyncingId === t.id ? "..." : "Resync" }}
                </button>
              </div>
            </div>

            <!-- Detail params -->
            <div v-if="expandedTemplate === t.id" class="mt-3 rounded-lg overflow-hidden border border-white/10 text-xs">
              <table class="w-full">
                <thead>
                  <tr class="bg-white/5 text-left text-slate-400">
                    <th class="px-3 py-2">Component</th>
                    <th class="px-3 py-2">Index</th>
                    <th class="px-3 py-2">Param Name</th>
                    <th class="px-3 py-2">Label</th>
                    <th class="px-3 py-2">Example</th>
                    <th class="px-3 py-2">Maxlength</th>
                    <th class="px-3 py-2">Button SubType</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                  <tr v-if="!(t.params||[]).length">
                    <td colspan="7" class="px-3 py-3 text-center text-slate-500">Tidak ada params — klik Resync</td>
                  </tr>
                  <tr v-for="p in (t.params||[])" :key="p.component+'-'+p.param_index" class="hover:bg-white/5">
                    <td class="px-3 py-2">
                      <span
                        class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                        :class="{
                          'bg-blue-500/20 text-blue-400': p.component==='header',
                          'bg-green-500/20 text-green-400': p.component==='body',
                          'bg-purple-500/20 text-purple-400': p.component==='button',
                        }"
                      >{{ p.component }}</span>
                    </td>
                    <td class="px-3 py-2 text-slate-400">{{ p.param_index }}</td>
                    <td class="px-3 py-2 font-mono text-accent">{{ p.param_name || '-' }}</td>
                    <td class="px-3 py-2 text-slate-300">{{ p.label }}</td>
                    <td class="px-3 py-2 text-slate-500 italic">{{ p.example_value || '-' }}</td>
                    <td class="px-3 py-2">
                      <input
                        v-model.number="maxlengthDraft[maxlengthKey(t.id, p.id)]"
                        type="number"
                        min="1"
                        max="1024"
                        class="field py-1 px-2 text-xs w-20"
                      />
                    </td>
                    <td class="px-3 py-2 text-slate-500">{{ p.button_sub_type || '-' }}</td>
                  </tr>
                </tbody>
              </table>
              <div v-if="(t.params||[]).length" class="mt-2 flex justify-end">
                <button
                  type="button"
                  class="btn-sm text-xs"
                  :disabled="savingMaxlengthId === t.id"
                  @click="saveMaxlength(t)"
                >
                  {{ savingMaxlengthId === t.id ? 'Menyimpan...' : 'Simpan maxlength' }}
                </button>
              </div>
            </div>
          </li>
        </ul>
        <p v-if="!templates.length" class="text-sm text-slate-500 text-center py-2">
          Belum ada template. Klik Sync dari Kirimin.
        </p>
      </section>

      <!-- Quota -->
      <section v-if="tab === 'quota'" class="card space-y-4">
        <h2 class="font-display font-semibold text-lg">Kuota Template</h2>
        <p class="text-xs text-slate-500">
          Saldo per team (Team Leader). Dipakai bersama TL + semua agent di team tersebut.
          Potong 1 hanya jika kirim template sukses di YCloud.
        </p>

        <form class="grid sm:grid-cols-[1fr_8rem_1fr_auto] gap-2 items-end" @submit.prevent="doTopup">
          <div>
            <label class="label">Team</label>
            <select v-model="quotaForm.team_id" required class="field">
              <option disabled value="">Pilih team</option>
              <option v-for="q in quotas" :key="q.team_id" :value="q.team_id">
                {{ q.team_name }} — TL: {{ q.leader_name || "—" }} (saldo {{ q.balance }})
              </option>
            </select>
          </div>
          <div>
            <label class="label">Jumlah</label>
            <input v-model.number="quotaForm.amount" type="number" min="1" required class="field" placeholder="100" />
          </div>
          <div>
            <label class="label">Catatan</label>
            <input v-model="quotaForm.note" class="field" placeholder="Opsional" />
          </div>
          <button class="btn">Top-up</button>
        </form>

        <ul class="divide-y divide-white/5">
          <li v-for="q in quotas" :key="q.team_id" class="py-3 flex items-center justify-between gap-2">
            <div>
              <p class="font-medium">{{ q.team_name }}</p>
              <p class="text-xs text-slate-500">
                TL: {{ q.leader_name || "—" }}
                <span v-if="q.leader_email"> · {{ q.leader_email }}</span>
              </p>
            </div>
            <div class="text-right">
              <p class="text-lg font-semibold text-accent">{{ q.balance }}</p>
              <p class="text-[10px] text-slate-500">sisa kuota</p>
            </div>
          </li>
        </ul>
        <p v-if="!quotas.length" class="text-sm text-slate-500 text-center py-2">Belum ada team</p>
      </section>

      <p v-if="msg" class="text-sm text-emerald-400">{{ msg }}</p>
      <p v-if="err" class="text-sm text-rose-400">{{ err }}</p>
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
import { computed, onMounted, reactive, ref } from "vue";
import { api } from "../api";
import { useAuthStore } from "../stores/auth";
import ConfirmModal from "../components/ConfirmModal.vue";
import ThemeToggle from "../components/ThemeToggle.vue";

const auth = useAuthStore();
const tab = ref("teams");
const adminTeamPick = ref("");
const joiningTeam = ref(false);
const tabs = [
  { id: "teams", label: "Teams" },
  { id: "users", label: "Users" },
  { id: "keys", label: "Channel" },
  { id: "templates", label: "Templates" },
  { id: "quota", label: "Quota" },
];

const teams = ref([]);
const users = ref([]);
const keys = ref([]);
const availableDevices = ref([]);
const channelForm = reactive({ device_id: "", label: "", team_id: "" });
const kiriminForm = reactive({ api_key: "", api_key_masked: "", configured: false });
const dailyLimitForm = reactive({ daily_unique_limit: 250 });
const savingKiriminKey = ref(false);
const savingDailyLimit = ref(false);
const syncingDevices = ref(false);
const editingKeyId = ref(null);
const templates = ref([]);
const quotas = ref([]);
const msg = ref("");
const err = ref("");

const dialog = reactive({
  open: false,
  mode: "confirm",
  title: "Konfirmasi",
  message: "",
  confirmLabel: "Hapus",
  danger: true,
  action: null,
});

function askConfirm({ title, message, confirmLabel = "Hapus", danger = true, mode = "confirm", action }) {
  dialog.open = true;
  dialog.mode = mode;
  dialog.title = title || "Konfirmasi";
  dialog.message = message;
  dialog.confirmLabel = confirmLabel;
  dialog.danger = danger;
  dialog.action = action;
}

function closeDialog() {
  dialog.open = false;
  dialog.action = null;
}

async function onDialogConfirm() {
  const action = dialog.action;
  closeDialog();
  if (typeof action === "function") {
    await action();
  }
}
const teamForm = reactive({ name: "" });
const editingTeamId = ref(null);
const editingTeamName = ref("");
const savingTeam = ref(false);
const userForm = reactive({
  name: "",
  email: "",
  password: "",
  role: "agent",
  team_id: "",
  team_leader_user_id: "",
});
const editingUserId = ref(null);
const editUserForm = reactive({
  name: "",
  email: "",
  password: "",
  is_active: true,
  team_id: "",
  team_leader_user_id: "",
});
const savingUser = ref(false);
const editKeyForm = reactive({ label: "", phone_number: "", team_id: "" });
const savingKey = ref(false);
const syncing = ref(false);
const resyncingId = ref(null);
const savingMaxlengthId = ref(null);
const maxlengthDraft = reactive({});
const expandedTemplate = ref(null);
const quotaForm = reactive({ team_id: "", amount: 100, note: "" });

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

async function joinOperationalTeam() {
  if (!adminTeamPick.value) return;
  joiningTeam.value = true;
  try {
    await auth.joinTeam(adminTeamPick.value);
    adminTeamPick.value = "";
    flash(true, `Masuk team ${auth.user?.team_name || ""}`.trim());
  } catch (e) {
    flash(false, e.message || "Gagal masuk team");
  } finally {
    joiningTeam.value = false;
  }
}

async function leaveOperationalTeam() {
  joiningTeam.value = true;
  try {
    await auth.leaveTeam();
    flash(true, "Keluar dari team operasional");
  } catch (e) {
    flash(false, e.message || "Gagal keluar team");
  } finally {
    joiningTeam.value = false;
  }
}

async function refresh() {
  const [t, u, k, tp, q, kir] = await Promise.all([
    api("/WaDesk/Teams/list"),
    api("/WaDesk/Users/list"),
    api("/WaDesk/Channels/list?scope=all"),
    api("/WaDesk/Templates/list"),
    api("/WaDesk/Quota/list"),
    api("/WaDesk/Settings/kirimin"),
  ]);
  teams.value = t.data.teams || [];
  users.value = u.data.users || [];
  keys.value = k.data.channels || k.data.keys || [];
  templates.value = tp.data.templates || [];
  quotas.value = q.data.quotas || [];
  initMaxlengthDrafts(templates.value);
  kiriminForm.configured = !!kir.data?.configured;
  kiriminForm.api_key_masked = kir.data?.api_key_masked || "";
  dailyLimitForm.daily_unique_limit = Number(kir.data?.daily_unique_limit) || 250;
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

function startEditTeam(t) {
  editingTeamId.value = t.id;
  editingTeamName.value = t.name || "";
}

function cancelEditTeam() {
  editingTeamId.value = null;
  editingTeamName.value = "";
  savingTeam.value = false;
}

async function saveTeamName(t) {
  const name = String(editingTeamName.value || "").trim();
  if (!name) {
    flash(false, "Nama team wajib diisi");
    return;
  }
  if (name === t.name) {
    cancelEditTeam();
    return;
  }
  savingTeam.value = true;
  try {
    await api("/WaDesk/Teams/update", {
      method: "POST",
      body: { id: t.id, name },
    });
    flash(true, "Nama team diubah");
    cancelEditTeam();
    await refresh();
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingTeam.value = false;
  }
}

async function removeTeam(id) {
  askConfirm({
    title: "Hapus team",
    message: "Team yang dihapus tidak bisa dikembalikan. Lanjutkan?",
    action: async () => {
      try {
        await api("/WaDesk/Teams/delete", { method: "POST", body: { id } });
        if (editingTeamId.value === id) cancelEditTeam();
        flash(true, "Team dihapus");
        await refresh();
      } catch (e) {
        flash(false, e.message);
      }
    },
  });
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

function startEditUser(u) {
  editingUserId.value = u.id;
  Object.assign(editUserForm, {
    name: u.name || "",
    email: u.email || "",
    password: "",
    is_active: Number(u.is_active) === 1,
    team_id: "",
    team_leader_user_id: "",
  });
}

function cancelEditUser() {
  editingUserId.value = null;
  savingUser.value = false;
}

async function saveUser(u) {
  savingUser.value = true;
  try {
    const body = {
      id: u.id,
      name: editUserForm.name,
      email: editUserForm.email,
      is_active: editUserForm.is_active ? 1 : 0,
    };
    if (editUserForm.password.trim()) {
      body.password = editUserForm.password.trim();
    }
    if (u.role === "team_leader" && editUserForm.team_id) {
      body.team_id = Number(editUserForm.team_id);
    }
    if (u.role === "agent" && editUserForm.team_leader_user_id) {
      body.team_leader_user_id = Number(editUserForm.team_leader_user_id);
    }
    await api("/WaDesk/Users/update", { method: "POST", body });
    flash(true, "User diupdate");
    cancelEditUser();
    await refresh();
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingUser.value = false;
  }
}

async function promoteAgent(u) {
  const currentTl = users.value.find(
    (x) => x.role === "team_leader" && Number(x.team_id) === Number(u.team_id)
  );
  const tlName = currentTl?.name || u.team_leader_name || "TL saat ini";

  askConfirm({
    title: "Jadikan Team Leader",
    message: currentTl
      ? `"${u.name}" akan jadi Team Leader. "${tlName}" otomatis turun jadi agent di team yang sama. Lanjutkan?`
      : `"${u.name}" akan jadi Team Leader team ini. Lanjutkan?`,
    confirmLabel: "Ya, promote",
    danger: false,
    action: async () => {
      try {
        await api("/WaDesk/Users/promoteToLeader", {
          method: "POST",
          body: { id: u.id },
        });
        flash(
          true,
          currentTl
            ? `${u.name} sekarang TL; ${tlName} turun jadi agent`
            : `${u.name} sekarang Team Leader`
        );
        await refresh();
      } catch (e) {
        flash(false, e.message);
      }
    },
  });
}

async function removeUser(u) {
  const isTl = u.role === "team_leader";
  const agentCount = isTl
    ? users.value.filter((x) => x.role === "agent" && Number(x.team_id) === Number(u.team_id)).length
    : 0;

  if (isTl && agentCount > 0) {
    askConfirm({
      title: "Tidak bisa hapus Team Leader",
      message: `Masih ada ${agentCount} agent di bawah TL ini. Hapus atau pindahkan agent dulu.`,
      mode: "alert",
      confirmLabel: "OK",
      danger: false,
      action: null,
    });
    return;
  }

  askConfirm({
    title: isTl ? "Hapus Team Leader" : "Hapus user",
    message: isTl
      ? "Team Leader yang dihapus tidak bisa dikembalikan. Team akan tanpa leader. Lanjutkan?"
      : "User yang dihapus tidak bisa dikembalikan. Lanjutkan?",
    action: async () => {
      try {
        await api("/WaDesk/Users/delete", { method: "POST", body: { id: u.id } });
        flash(true, "User dihapus");
        await refresh();
      } catch (e) {
        flash(false, e.message);
      }
    },
  });
}

async function saveKiriminKey() {
  const apiKey = String(kiriminForm.api_key || "").trim();
  if (!apiKey) {
    flash(false, "API key wajib diisi");
    return;
  }
  savingKiriminKey.value = true;
  try {
    const res = await api("/WaDesk/Settings/kirimin", {
      method: "POST",
      body: { api_key: apiKey },
    });
    kiriminForm.api_key = "";
    kiriminForm.configured = true;
    kiriminForm.api_key_masked = res.data?.api_key_masked || "";
    flash(true, "Kirimin API key disimpan");
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingKiriminKey.value = false;
  }
}

async function saveDailyLimit() {
  const limit = Number(dailyLimitForm.daily_unique_limit);
  if (!Number.isFinite(limit) || limit < 1) {
    flash(false, "Limit harian minimal 1");
    return;
  }
  savingDailyLimit.value = true;
  try {
    const res = await api("/WaDesk/Settings/dailyLimit", {
      method: "POST",
      body: { daily_unique_limit: limit },
    });
    dailyLimitForm.daily_unique_limit = Number(res.data?.daily_unique_limit) || limit;
    flash(true, "Limit harian tenant disimpan");
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingDailyLimit.value = false;
  }
}

async function syncDevicesFromKirimin() {
  syncingDevices.value = true;
  try {
    const res = await api("/WaDesk/Channels/syncFromKirimin");
    availableDevices.value = res.data.devices || [];
    flash(true, `Device Kirimin: ${availableDevices.value.length} ditemukan`);
  } catch (e) {
    flash(false, e.message);
  } finally {
    syncingDevices.value = false;
  }
}

async function assignChannel() {
  try {
    await api("/WaDesk/Channels/assign", {
      method: "POST",
      body: {
        device_id: channelForm.device_id,
        label: channelForm.label,
        team_id: Number(channelForm.team_id),
      },
    });
    Object.assign(channelForm, { device_id: "", label: "", team_id: "" });
    flash(true, "Channel di-assign");
    await refresh();
  } catch (e) {
    flash(false, e.message);
  }
}

function startEditKey(k) {
  editingKeyId.value = k.id;
  Object.assign(editKeyForm, {
    label: k.label || "",
    phone_number: k.phone_number || "",
    team_id: String(k.team_id || ""),
  });
}

function cancelEditKey() {
  editingKeyId.value = null;
  savingKey.value = false;
}

async function saveKey(k) {
  savingKey.value = true;
  try {
    const body = {
      id: k.id,
      label: editKeyForm.label,
      phone_number: editKeyForm.phone_number,
      team_id: Number(editKeyForm.team_id),
    };
    await api("/WaDesk/Channels/update", { method: "POST", body });
    flash(true, "Channel diupdate");
    cancelEditKey();
    await refresh();
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingKey.value = false;
  }
}

async function removeKey(id) {
  askConfirm({
    title: "Hapus channel",
    message: "Mapping channel yang dihapus tidak bisa dikembalikan. Lanjutkan?",
    action: async () => {
      try {
        await api("/WaDesk/Channels/delete", { method: "POST", body: { id } });
        flash(true, "Channel dihapus");
        await refresh();
      } catch (e) {
        flash(false, e.message);
      }
    },
  });
}

function maxlengthKey(templateId, paramId) {
  return `${templateId}:${paramId}`;
}

function initMaxlengthDrafts(list) {
  for (const t of list) {
    for (const p of t.params || []) {
      if (!p.id) continue;
      maxlengthDraft[maxlengthKey(t.id, p.id)] = Number(p.maxlength ?? 20);
    }
  }
}

async function saveMaxlength(t) {
  const params = (t.params || [])
    .filter((p) => p.id)
    .map((p) => ({
      id: Number(p.id),
      maxlength: Number(maxlengthDraft[maxlengthKey(t.id, p.id)] ?? p.maxlength ?? 20),
    }));
  if (!params.length) {
    flash(false, "Tidak ada param untuk disimpan");
    return;
  }
  savingMaxlengthId.value = t.id;
  try {
    await api("/WaDesk/Templates/updateMaxlength", {
      method: "POST",
      body: { template_id: t.id, params },
    });
    flash(true, "Maxlength param disimpan");
    await refresh();
    expandedTemplate.value = t.id;
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingMaxlengthId.value = null;
  }
}

async function resyncOneTemplate(templateName) {
  const tpl = templates.value.find((t) => t.template_name === templateName);
  if (!tpl) return;
  resyncingId.value = tpl.id;
  expandedTemplate.value = null;
  try {
    const res = await api("/WaDesk/Templates/resyncOne", {
      method: "POST",
      body: { template_name: templateName },
    });
    const count = res.data?.params_synced ?? 0;
    await refresh();
    flash(true, `Resync OK: ${templateName} — ${count} params diupdate`);
    // Re-open detail so user can see updated params
    const updated = templates.value.find((t) => t.template_name === templateName);
    if (updated) expandedTemplate.value = updated.id;
  } catch (e) {
    flash(false, e.message);
  } finally {
    resyncingId.value = null;
  }
}

async function syncTemplatesFromKirimin() {
  if (syncing.value) return;
  syncing.value = true;
  try {
    const res = await api("/WaDesk/Templates/syncFromKirimin", { method: "POST", body: {} });
    const created = res.data?.created ?? 0;
    const updated = res.data?.updated ?? 0;
    const deleted = res.data?.deleted ?? 0;
    const fetched = res.data?.fetched ?? 0;
    flash(true, `Sync OK: ${fetched} dari Kirimin → ${created} baru, ${updated} diupdate, ${deleted} dihapus`);
    await refresh();
  } catch (e) {
    flash(false, e.message);
  } finally {
    syncing.value = false;
  }
}

async function doTopup() {
  try {
    const res = await api("/WaDesk/Quota/topup", {
      method: "POST",
      body: {
        team_id: Number(quotaForm.team_id),
        amount: Number(quotaForm.amount),
        note: quotaForm.note || null,
      },
    });
    Object.assign(quotaForm, { amount: 100, note: "" });
    flash(true, `Top-up berhasil. Saldo sekarang: ${res.data?.balance ?? "—"}`);
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
.label {
  @apply block text-xs text-slate-400 mb-1;
}
.field {
  @apply w-full rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-accent/40;
}
.btn {
  @apply px-4 py-2.5 rounded-xl bg-accent font-medium text-sm hover:bg-accent-soft transition disabled:opacity-50;
}
.btn-sm {
  @apply px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-200 text-sm transition disabled:opacity-50;
}
</style>
