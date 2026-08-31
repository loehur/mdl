<template>
  <AppHeader page-title="Admin" active="admin" @logout="onLogout">
    <div class="flex-1 overflow-y-auto">
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
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <h2 class="font-display font-semibold text-lg">Teams</h2>
          <div class="flex items-center gap-3">
            <p v-if="teamBrowseTotal" class="text-xs text-slate-500">
              {{ teamBrowseRows.length }} / {{ teamBrowseTotal }} team
            </p>
            <button type="button" class="btn shrink-0" @click="addTeamModal = true">Tambah team</button>
          </div>
        </div>

        <div class="relative">
          <input
            v-model="teamBrowseQuery"
            type="search"
            class="field pl-9"
            placeholder="Cari team..."
            autocomplete="off"
          />
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500 pointer-events-none"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
          </svg>
        </div>

        <div class="rounded-xl border border-white/10 overflow-hidden">
          <div class="max-h-[min(60vh,32rem)] overflow-y-auto divide-y divide-white/5">
            <div
              v-if="loadingTeamBrowse && !teamBrowseRows.length"
              class="py-10 text-center text-sm text-slate-500"
            >
              Memuat team...
            </div>
            <p
              v-else-if="!teamBrowseRows.length"
              class="py-10 text-center text-sm text-slate-500"
            >
              {{ teamBrowseQuery.trim() ? "Team tidak ditemukan." : "Belum ada team." }}
            </p>

            <div
              v-for="t in teamBrowseRows"
              :key="t.id"
              class="px-4 py-3 flex items-start justify-between gap-3 hover:bg-white/[0.02]"
            >
              <div class="min-w-0 flex-1">
                <template v-if="editingTeamId === t.id">
                  <form class="grid gap-2 sm:grid-cols-[1fr_auto_auto_auto_auto]" @submit.prevent="saveTeamName(t)">
                    <input
                      v-model="editingTeamName"
                      required
                      maxlength="100"
                      class="field min-w-0"
                      placeholder="Nama team"
                      @keydown.esc.prevent="cancelEditTeam"
                    />
                    <select v-model="editingTeamCategory" class="field w-full sm:w-36">
                      <option value="UTILITY">Utility</option>
                      <option value="MARKETING">Marketing</option>
                    </select>
                    <input v-model.number="editingTeamDailyLimit" type="number" min="1" max="1000000" class="field w-full sm:w-28" title="Limit template per hari" placeholder="Limit/hari" aria-label="Limit template per hari" />
                    <input v-model="editingTeamExpiry" type="date" :min="teamExpiryMin" :max="teamExpiryMax" class="field w-full sm:w-40" title="Kadaluarsa akses template (kosong = tanpa kadaluarsa)" aria-label="Kadaluarsa akses template" />
                    <div class="flex gap-2 justify-end">
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
                  <div class="flex items-center gap-2 min-w-0">
                    <p class="font-medium truncate">{{ t.name }}</p>
                    <span
                      v-if="Number(t.is_default) === 1"
                      class="shrink-0 text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-accent/15 text-accent"
                    >
                      Default
                    </span>
                    <span class="shrink-0 text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-violet-500/15 text-violet-300">
                      {{ t.template_category || 'UTILITY' }}
                    </span>
                  </div>
                  <p class="text-xs text-slate-500 mt-0.5">
                    Leader: {{ t.leader_name || "—" }} · Agents: {{ t.agent_count }}
                  </p>
                  <p class="text-xs text-slate-500 mt-0.5">Limit template: {{ t.daily_template_limit || 250 }} / hari</p>
                  <p class="text-xs mt-0.5" :class="teamExpired(t) ? 'text-rose-400' : 'text-slate-500'">Akses template: {{ t.template_access_expires_at || 'Tanpa kadaluarsa' }}<template v-if="teamExpired(t)"> · Kadaluarsa</template></p>
                  <p class="text-xs text-slate-500 mt-0.5">
                    <template v-if="Number(t.channel_count) > 0">
                      {{ t.channel_count }} nomor
                      <span v-if="teamChannelPreview(t)" class="text-slate-600">
                        · {{ teamChannelPreview(t) }}
                      </span>
                    </template>
                    <span v-else class="text-amber-400/80">Belum ada nomor</span>
                  </p>
                  <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-400 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      class="rounded border-white/20"
                      :checked="Boolean(Number(t.mask_phone_numbers))"
                      :disabled="savingTeamMaskId === t.id"
                      @change="setTeamPhoneMasking(t, $event.target.checked)"
                    />
                    <span>Phone number masking</span>
                    <span :class="Boolean(Number(t.mask_phone_numbers)) ? 'text-emerald-400' : 'text-slate-600'">
                      {{ Boolean(Number(t.mask_phone_numbers)) ? 'Enabled' : 'Disabled' }}
                    </span>
                  </label>
                </template>
              </div>
              <div v-if="editingTeamId !== t.id" class="flex items-center gap-3 shrink-0 pt-0.5">
                <button type="button" class="text-xs text-accent hover:underline" @click="startEditTeam(t)">
                  Ubah
                </button>
                <button type="button" class="text-xs text-rose-400" @click="removeTeam(t.id)">Hapus</button>
              </div>
            </div>

            <div ref="teamBrowseSentinel" class="h-1" aria-hidden="true" />

            <div
              v-if="loadingTeamBrowse && teamBrowseRows.length"
              class="py-3 text-center text-xs text-slate-500"
            >
              Memuat lagi...
            </div>
            <p
              v-else-if="teamBrowseRows.length && !teamBrowseHasMore"
              class="py-3 text-center text-xs text-slate-600"
            >
              Semua team sudah dimuat
            </p>
          </div>
        </div>
      </section>

      <!-- Users -->
      <section v-if="tab === 'users'" class="card space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <h2 class="font-display font-semibold text-lg">Team Leader & Agent</h2>
          <div class="flex items-center gap-3">
            <p v-if="userBrowseTotal" class="text-xs text-slate-500">
              {{ userBrowseRows.length }} / {{ userBrowseTotal }} user
            </p>
            <button type="button" class="btn shrink-0" @click="addUserModal = true">Tambah user</button>
          </div>
        </div>

        <div class="relative">
          <input
            v-model="userBrowseQuery"
            type="search"
            class="field pl-9"
            placeholder="Cari nama, email, team..."
            autocomplete="off"
          />
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500 pointer-events-none"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
          </svg>
        </div>

        <div class="rounded-xl border border-white/10 overflow-hidden">
          <div class="max-h-[min(60vh,32rem)] overflow-y-auto divide-y divide-white/5">
            <div
              v-if="loadingUserBrowse && !userBrowseRows.length"
              class="py-10 text-center text-sm text-slate-500"
            >
              Memuat user...
            </div>
            <p
              v-else-if="!userBrowseRows.length"
              class="py-10 text-center text-sm text-slate-500"
            >
              {{ userBrowseQuery.trim() ? "User tidak ditemukan." : "Belum ada user." }}
            </p>

            <div v-for="u in userBrowseRows" :key="u.id" class="px-4 py-3">
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
                    <option v-for="l in userLeaders" :key="l.id" :value="l.id">
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
            </div>

            <div ref="userBrowseSentinel" class="h-1" aria-hidden="true" />

            <div
              v-if="loadingUserBrowse && userBrowseRows.length"
              class="py-3 text-center text-xs text-slate-500"
            >
              Memuat lagi...
            </div>
            <p
              v-else-if="userBrowseRows.length && !userBrowseHasMore"
              class="py-3 text-center text-xs text-slate-600"
            >
              Semua user sudah dimuat
            </p>
          </div>
        </div>
      </section>

      <!-- Numbers -->
      <section v-if="tab === 'numbers'" class="card flex flex-col space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h2 class="font-display font-semibold text-lg">Number</h2>
            <p class="text-xs text-slate-500 mt-1">Nomor WhatsApp Meta yang tersinkron per WABA.</p>
          </div>
          <button type="button" class="btn shrink-0" @click="openAddNumber">Add Number</button>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
          <select v-model="numberWabaFilter" class="field sm:max-w-md">
            <option value="" disabled>Pilih WABA terlebih dahulu</option>
            <option v-for="waba in wabas" :key="`number-${waba.id}`" :value="waba.meta_waba_id">{{ waba.name }}</option>
          </select>
          <button type="button" class="btn shrink-0" :disabled="syncingNumbers || !numberWabaFilter" @click="syncNumbers">
            {{ syncingNumbers ? 'Sinkron...' : 'Sync Number' }}
          </button>
        </div>
        <Teleport to="body">
        <div v-if="addingNumber" class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center bg-black/60 p-0 sm:p-4 backdrop-blur-sm" @click.self="addingNumber = false">
          <div class="w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl border border-white/10 bg-ink-900 p-4 space-y-3 shadow-2xl">
          <div class="flex items-center justify-between gap-3"><p class="font-medium">Add Number</p><button type="button" class="text-xs text-slate-400" @click="addingNumber = false">Tutup</button></div>
          <template v-if="numberFlow.step === 'add'">
            <select v-model="numberForm.waba_id" class="field"><option v-for="waba in wabas" :key="`add-${waba.id}`" :value="waba.meta_waba_id">{{ waba.name }}</option></select>
            <div class="flex gap-2"><input value="62" readonly tabindex="-1" class="field bg-ink-950/50 text-slate-400 cursor-not-allowed" style="width:4rem;flex:0 0 4rem" aria-label="Country code Indonesia" /><input v-model="numberForm.phone_number" class="field flex-1" style="min-width:0" placeholder="8xxxxxxxxxx" @input="normalizeAddPhone" /></div>
            <button type="button" class="btn" :disabled="numberFlow.loading" @click="addNumber">{{ numberFlow.loading ? 'Memproses...' : 'Tambah nomor' }}</button>
          </template>
          <template v-else>
            <p class="text-xs text-slate-400">Phone Number ID: <span class="font-mono text-accent">{{ numberFlow.phone_number_id }}</span></p>
            <p v-if="numberFlow.error" class="rounded-xl border-2 border-rose-500 bg-rose-100 px-3 py-2 text-sm font-medium !text-rose-950">
              {{ numberFlow.error }}<span v-if="numberFlow.otpLocked > 0"> Sisa waktu tunggu: {{ otpTimeLabel(numberFlow.otpLocked) }}.</span>
            </p>
            <template v-if="numberFlow.step === 'request'"><select v-model="numberForm.method" class="field"><option value="SMS">SMS</option><option value="VOICE">Voice call</option></select><div class="flex gap-2"><button type="button" class="btn" :disabled="numberFlow.loading || numberFlow.otpCooldown > 0 || numberFlow.otpLocked > 0" @click="requestOtp">{{ numberFlow.otpLocked > 0 ? `Tunggu ${otpTimeLabel(numberFlow.otpLocked)}` : (numberFlow.loading ? 'Meminta OTP...' : numberRequestLabel) }}</button><button type="button" class="btn ml-auto" :disabled="numberFlow.loading || numberFlow.otpLocked > 0" @click="numberForm.otp = ''; numberFlow.error = ''; numberFlow.step = 'verify'">Sudah terima OTP</button></div><p class="text-xs text-slate-500">Pilih ini jika OTP sudah dikirim sebelum halaman direfresh.</p></template>
            <template v-else-if="numberFlow.step === 'verify'"><p v-if="numberFlow.otpLocked > 0" class="text-sm text-amber-300">Terlalu banyak percobaan verify. Tunggu {{ otpTimeLabel(numberFlow.otpLocked) }} sebelum mencoba lagi.</p><template v-else><input v-model="numberForm.otp" class="field" inputmode="numeric" placeholder="Masukkan kode OTP" /><button type="button" class="btn" :disabled="numberFlow.loading || !numberForm.otp" @click="verifyOtp">Verify OTP</button><p class="text-xs text-slate-500">Sisa percobaan sesi ini: {{ Math.max(0, 3 - numberFlow.otpVerifyFails) }}.</p></template><div class="flex justify-end"><button type="button" class="btn" :disabled="numberFlow.loading" @click="numberForm.otp = ''; numberFlow.error = ''; numberFlow.step = 'request'">Kembali</button></div></template>
            <template v-else-if="numberFlow.step === 'register'"><p class="text-xs text-emerald-400">OTP terverifikasi. Nomor siap diregistrasikan.</p><button type="button" class="btn" :disabled="numberFlow.loading" @click="registerNumber">Register Number</button></template>
            <template v-else-if="numberFlow.step === 'done'"><p class="text-sm text-emerald-400">Registrasi berhasil dikirim ke Meta.</p><p class="text-xs text-slate-400">Sinkronkan WABA untuk memuat status terbaru nomor ini.</p><button type="button" class="btn" :disabled="syncingNumbers" @click="syncAfterRegistration">{{ syncingNumbers ? 'Sinkron...' : 'Sync nomor sekarang' }}</button></template>
          </template>
          </div>
        </div>
        </Teleport>
        <Teleport to="body">
          <div v-if="numberLimitModal.open" class="fixed inset-0 z-[90] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" role="dialog" aria-modal="true" @click.self="numberLimitModal.open=false">
            <div class="w-full max-w-md overflow-hidden rounded-3xl border border-amber-400/30 bg-ink-900 shadow-2xl shadow-amber-950/40">
              <div class="h-1.5 bg-gradient-to-r from-amber-400 via-orange-400 to-rose-500" />
              <div class="p-6 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-400/10 text-amber-300 ring-1 ring-amber-400/25">
                  <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636M12 8v4m0 4h.01" />
                  </svg>
                </div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">Batas WABA tercapai</p>
                <h2 class="mt-2 font-display text-xl font-semibold text-white">Maksimal 20 nomor per WABA</h2>
                <p class="mt-3 text-sm leading-6 text-slate-300">
                  WABA <strong class="text-slate-100">{{ numberLimitModal.wabaName }}</strong> sudah memiliki
                  <strong class="text-amber-300">{{ numberLimitModal.current }} nomor</strong>.
                  Tambahkan nomor baru setelah menghapus nomor yang tidak terpakai, atau gunakan WABA lain.
                </p>
                <div class="mt-5 flex justify-center">
                  <div class="w-full max-w-xs">
                    <div class="flex justify-between text-[11px] text-slate-500"><span>Terpakai</span><span>{{ numberLimitModal.current }} / 20</span></div>
                    <div class="mt-1 h-2.5 overflow-hidden rounded-full bg-white/5">
                      <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-rose-500" :style="{ width: Math.min(100, (numberLimitModal.current / 20) * 100) + '%' }" />
                    </div>
                  </div>
                </div>
                <button type="button" class="btn mt-6 w-full" @click="numberLimitModal.open=false">Mengerti</button>
              </div>
            </div>
          </div>
        </Teleport>
        <p v-if="!numberWabaFilter" class="rounded-xl border border-white/10 py-10 text-center text-sm text-slate-500">
          Sync WABA terlebih dahulu.
        </p>
        <div v-else class="grid grid-cols-3 gap-2">
          <div class="rounded-xl border border-white/10 bg-ink-950/40 px-3 py-2"><p class="text-[11px] text-slate-500">Total</p><p class="text-lg font-semibold">{{ numberStats.total }}</p></div>
          <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-3 py-2"><p class="text-[11px] text-emerald-300">Active</p><p class="text-lg font-semibold text-emerald-200">{{ numberStats.active }}</p></div>
          <div class="rounded-xl border border-slate-500/20 bg-slate-500/5 px-3 py-2"><p class="text-[11px] text-slate-400">Inactive</p><p class="text-lg font-semibold text-slate-200">{{ numberStats.inactive }}</p></div>
        </div>
        <div v-if="numberWabaFilter" class="h-[34rem] max-h-[60vh] overflow-y-auto rounded-xl border border-white/10 divide-y divide-white/5">
          <p v-if="loadingNumbers" class="py-10 text-center text-sm text-slate-500">Memuat nomor...</p>
          <p v-else-if="!numbers.length" class="py-10 text-center text-sm text-slate-500">Belum ada nomor pada WABA ini.</p>
          <div v-for="number in numbers" :key="number.id" class="px-4 py-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-medium">{{ number.label || number.phone_number }}</p>
                <p class="font-mono text-xs text-accent mt-1">+{{ number.phone_number || '—' }}</p>
                <p class="text-[11px] text-slate-500 mt-1">Phone Number ID: {{ number.meta_phone_number_id || number.device_id }}</p>
              </div>
              <div class="flex flex-wrap gap-1.5 justify-end text-xs">
                <span :title="'Status: ' + (number.status || 'unknown')" class="px-2 py-1 rounded" :class="String(number.status || '').toLowerCase() === 'active' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-white/5 text-slate-300'">{{ number.meta_provider_status || number.status || 'unknown' }}</span>
                <span v-if="number.meta_verification_status" class="px-2 py-1 rounded bg-sky-500/10 text-sky-300">{{ number.meta_verification_status }}</span>
                <span v-if="number.meta_display_name_status" class="px-2 py-1 rounded bg-violet-500/10 text-violet-300">Display: {{ number.meta_display_name_status }}</span>
                <span v-if="number.meta_quality_rating" class="px-2 py-1 rounded" :class="qualityBadgeClass(number.meta_quality_rating)">Quality: {{ number.meta_quality_rating }}</span>
                <button v-if="String(number.status || '').toLowerCase() !== 'active'" type="button" class="px-2 py-1 rounded bg-sky-500/10 text-sky-300 hover:bg-sky-500/20" @click="continueNumberRegistration(number)">{{ String(number.meta_verification_status || '').toUpperCase().startsWith('VERIFIED') ? 'Register Number' : 'Request OTP' }}</button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- WABA -->
      <section v-if="tab === 'wabas'" class="card space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h2 class="font-display font-semibold text-lg">WABA</h2>
            <p class="text-xs text-slate-500 mt-1">Daftar koneksi WhatsApp Business Account Meta.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="btn shrink-0" :disabled="syncingWabas" @click="syncWabas">
              {{ syncingWabas ? 'Sinkron...' : 'Sync WABA' }}
            </button>
            <button type="button" class="btn-danger shrink-0" :disabled="syncingWabas || resubscribing" @click="resetSubscription">
              {{ resubscribing ? 'Resubscribe...' : 'Resubscribe' }}
            </button>
          </div>
        </div>
        <p v-if="!wabas.length" class="rounded-xl border border-white/10 py-10 text-center text-sm text-slate-500">
          Belum ada WABA. Klik Sync WABA untuk mengambil data dari Meta.
        </p>
        <div v-else class="rounded-xl border border-white/10 divide-y divide-white/5 overflow-hidden">
          <div v-for="waba in wabas" :key="waba.id" class="px-4 py-3 space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-medium truncate">{{ waba.name }}</p>
                <p class="font-mono text-xs text-accent mt-1 truncate">{{ waba.meta_waba_id }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ waba.phone_count }} nomor · {{ waba.template_count }} template</p>
                <p class="text-xs text-slate-400 mt-2">Team: {{ waba.team_names || 'Belum ada team' }}</p>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs px-2 py-1 rounded bg-emerald-500/10 text-emerald-400">{{ waba.status || 'active' }}</span>
                <button type="button" class="btn-sm text-xs" @click="openWabaTeamEditor(waba)">
                  Assign team
                </button>
              </div>
            </div>
            <div v-if="editingWabaTeamId === waba.id" class="rounded-lg border border-white/10 bg-ink-950/40 p-3 space-y-3">
              <p class="text-xs text-slate-400">Team yang dipilih bisa memakai seluruh nomor di WABA ini. Satu team hanya dapat berada pada satu WABA.</p>
              <div class="grid sm:grid-cols-2 gap-2">
                <label v-for="team in teams" :key="`waba-${waba.id}-${team.id}`" class="flex items-center gap-2 text-sm text-slate-300">
                  <input v-model="wabaTeamDraft" type="checkbox" :value="team.id" class="rounded" />
                  {{ team.name }}
                </label>
              </div>
              <p v-if="!teams.length" class="text-xs text-amber-300">Buat team terlebih dahulu.</p>
              <div class="flex justify-end gap-2">
                <button type="button" class="btn-sm text-xs" @click="editingWabaTeamId = null">Batal</button>
                <button type="button" class="btn-sm text-xs" :disabled="savingWabaTeamId === waba.id" @click="saveWabaTeams(waba)">
                  {{ savingWabaTeamId === waba.id ? 'Menyimpan...' : 'Simpan' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Assign -->
      <section v-if="false && tab === 'assign'" class="card space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <h2 class="font-display font-semibold text-lg">Assign</h2>
          <p v-if="channelBrowseTotal" class="text-xs text-slate-500">
            {{ channelBrowseRows.length }} / {{ channelBrowseTotal }} nomor
          </p>
        </div>

        <div class="rounded-xl border border-sky-500/20 bg-sky-500/5 p-4 space-y-2">
          <p class="text-sm font-medium text-slate-200">Satu nomor WA bisa di-assign ke beberapa team</p>
          <ul class="text-xs text-slate-400 space-y-1.5 list-disc pl-4">
            <li>
              <strong class="text-slate-300">Inbox privat per team</strong> — chat hanya terlihat di team masing-masing.
            </li>
            <li>
              <strong class="text-slate-300">Default team</strong> — customer baru masuk ke team default. Atur di tab Config.
            </li>
          </ul>
        </div>

        <form class="rounded-xl border border-white/10 bg-ink-950/40 p-4 space-y-4" @submit.prevent="assignChannel">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-slate-200">Assign nomor ke team</p>
            <button
              type="button"
              class="btn-sm shrink-0 inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/25 hover:text-emerald-300 disabled:opacity-50"
              :disabled="syncingDevices"
              title="Sync nomor dari Meta WABA"
              @click="syncDevicesFromKirimin"
            >
              <svg
                class="h-4 w-4 shrink-0"
                :class="{ 'animate-spin': syncingDevices }"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                />
              </svg>
              {{ syncingDevices ? "Sync..." : "Sync" }}
            </button>
          </div>

          <div>
            <label class="label">Device / nomor</label>
            <input
              v-model="deviceQuery"
              type="search"
              class="field mb-2"
              placeholder="Cari label, nomor, device ID..."
              autocomplete="off"
            />
            <div class="rounded-lg border border-white/10 bg-ink-950/30 max-h-52 overflow-y-auto">
              <button
                v-for="d in filteredDevices"
                :key="d.device_id"
                type="button"
                class="w-full text-left px-3 py-2.5 text-sm border-b border-white/5 last:border-0 hover:bg-white/5 transition"
                :class="channelForm.device_id === d.device_id ? 'bg-accent/10 text-accent' : 'text-slate-300'"
                @click="selectAssignDevice(d)"
              >
                <span class="font-medium">{{ d.label || d.device_id }}</span>
                <span class="text-slate-500"> · {{ d.phone_number || "—" }}</span>
                <span v-if="d.assigned" class="block text-[11px] text-slate-500 mt-0.5">
                  Team: {{ d.assigned.team_names || "—" }}
                </span>
              </button>
              <p v-if="!availableDevices.length && !syncingDevices" class="px-3 py-6 text-xs text-slate-500 text-center">
                Klik Sync untuk ambil nomor dari Meta WABA.
              </p>
              <p v-else-if="!filteredDevices.length" class="px-3 py-6 text-xs text-slate-500 text-center">
                Device tidak ditemukan.
              </p>
            </div>
            <p v-if="selectedAssignDevice?.assigned" class="text-[11px] text-slate-500 mt-1.5">
              Nomor sudah terdaftar — centang team tambahan yang juga ingin memakai nomor ini.
            </p>
          </div>

          <div>
            <label class="label">Label tampilan</label>
            <input v-model="channelForm.label" required class="field" placeholder="Contoh: CS Utama" />
          </div>

          <div class="rounded-lg border border-white/10 bg-ink-950/30 p-3 space-y-2">
            <p class="text-xs font-medium text-slate-300">Team yang di-assign</p>
            <p v-if="channelForm.team_ids.length" class="text-[11px] text-slate-500">
              Terpilih: {{ channelForm.team_ids.length }} team
            </p>
            <input
              v-model="assignTeamQuery"
              type="search"
              class="field py-2 text-sm"
              placeholder="Cari team..."
              autocomplete="off"
            />
            <div class="max-h-40 overflow-y-auto rounded-lg border border-white/10 divide-y divide-white/5">
              <label
                v-for="t in assignTeamRows"
                :key="'pick-' + t.id"
                class="flex items-center gap-2 px-3 py-2 text-sm text-slate-300 cursor-pointer hover:bg-white/5"
              >
                <input
                  v-model="channelForm.team_ids"
                  type="checkbox"
                  :value="t.id"
                  class="rounded"
                />
                {{ t.name }}
              </label>
              <div ref="assignTeamSentinel" class="h-1" aria-hidden="true" />
              <p v-if="loadingAssignTeams && !assignTeamRows.length" class="px-3 py-4 text-xs text-slate-500 text-center">
                Memuat team...
              </p>
              <p v-else-if="!assignTeamRows.length" class="px-3 py-4 text-xs text-slate-500 text-center">
                {{ assignTeamQuery.trim() ? "Team tidak ditemukan." : "Buat team dulu di tab Teams." }}
              </p>
              <p v-else-if="loadingAssignTeams" class="px-3 py-2 text-[11px] text-slate-500 text-center">Memuat lagi...</p>
            </div>
            <p v-if="!channelForm.team_ids.length" class="text-[11px] text-amber-300/90">Pilih minimal satu team.</p>
          </div>

          <button class="btn w-full sm:w-auto" :disabled="!channelForm.device_id || !channelForm.team_ids.length">
            Assign nomor
          </button>
        </form>

        <div class="relative">
          <input
            v-model="channelBrowseQuery"
            type="search"
            class="field pl-9"
            placeholder="Cari nomor terdaftar..."
            autocomplete="off"
          />
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500 pointer-events-none"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
          </svg>
        </div>

        <div class="rounded-xl border border-white/10 overflow-hidden">
          <p class="px-4 pt-3 pb-1 text-sm font-medium text-slate-200">Nomor terdaftar</p>
          <div class="max-h-[min(55vh,28rem)] overflow-y-auto divide-y divide-white/5">
            <div
              v-if="loadingChannelBrowse && !channelBrowseRows.length"
              class="py-10 text-center text-sm text-slate-500"
            >
              Memuat nomor...
            </div>
            <p v-else-if="!channelBrowseRows.length" class="py-10 text-center text-sm text-slate-500">
              {{ channelBrowseQuery.trim() ? "Nomor tidak ditemukan." : "Belum ada nomor terdaftar." }}
            </p>

            <div v-for="k in channelBrowseRows" :key="k.id" class="px-4 py-3">
              <template v-if="editingKeyId === k.id">
                <form class="grid sm:grid-cols-2 gap-3 rounded-xl border border-white/10 bg-ink-950/30 p-4" @submit.prevent="saveKey(k)">
                  <input v-model="editKeyForm.label" required class="field sm:col-span-2" placeholder="Label" />
                  <div>
                    <label class="label">Nomor</label>
                    <input
                      :value="k.phone_number || ''"
                      class="field bg-ink-950/50 text-slate-400 cursor-not-allowed"
                      readonly
                      tabindex="-1"
                      placeholder="Belum tersedia — sync dari Meta"
                    />
                    <p class="text-[11px] text-slate-500 mt-1">Otomatis dari sinkronisasi Meta WABA, tidak bisa diubah manual.</p>
                  </div>
                  <div>
                    <label class="label">WABA ID</label>
                    <input
                      :value="k.waba_id || ''"
                      class="field bg-ink-950/50 text-slate-400 cursor-not-allowed"
                      readonly
                      tabindex="-1"
                      placeholder="Belum tersedia — sync dari Meta"
                    />
                    <p class="text-[11px] text-slate-500 mt-1">Otomatis dari sinkronisasi Meta WABA, tidak bisa diubah manual.</p>
                  </div>
                  <div class="sm:col-span-2 rounded-lg border border-white/10 bg-ink-950/40 p-3 space-y-2">
                    <p class="text-xs font-medium text-slate-300">Team yang di-assign</p>
                    <input
                      v-model="assignTeamQuery"
                      type="search"
                      class="field py-2 text-sm"
                      placeholder="Cari team..."
                      autocomplete="off"
                    />
                    <div class="max-h-36 overflow-y-auto rounded-lg border border-white/10 divide-y divide-white/5">
                      <label
                        v-for="t in assignTeamRows"
                        :key="'edit-' + t.id"
                        class="flex items-center gap-2 px-3 py-2 text-sm text-slate-300 cursor-pointer hover:bg-white/5"
                      >
                        <input v-model="editKeyForm.team_ids" type="checkbox" :value="t.id" class="rounded" />
                        {{ t.name }}
                      </label>
                    </div>
                    <p class="text-[11px] text-amber-300/80">
                      Menghapus team ikut menghapus riwayat chat team tersebut.
                    </p>
                  </div>
                  <div class="sm:col-span-2 flex gap-2">
                    <button type="submit" class="btn" :disabled="savingKey">{{ savingKey ? "Saving..." : "Save" }}</button>
                    <button type="button" class="btn-sm" :disabled="savingKey" @click="cancelEditKey">Cancel</button>
                  </div>
                </form>
              </template>
              <template v-else>
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <p class="font-medium truncate">{{ k.label }}</p>
                    <p class="text-xs text-slate-500 mt-0.5 truncate">
                      {{ k.phone_number }}
                      <span v-if="k.device_id"> · {{ k.device_id }}</span>
                      <span v-if="k.waba_id"> · WABA {{ k.waba_id }}</span>
                      <span v-else class="text-amber-400"> · WABA not set</span>
                      · {{ k.status }}
                    </p>
                    <p class="text-xs text-slate-400 mt-1">
                      <span class="text-slate-500">{{ k.team_count || 0 }} team:</span>
                      <span class="text-slate-300">{{ k.team_names || "—" }}</span>
                    </p>
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                      <input
                        type="checkbox"
                        class="accent-teal-500"
                        :checked="Number(k.template_sending_enabled ?? 1) === 1"
                        :disabled="savingTemplateSendChannelId === k.id"
                        @change="setChannelTemplateSending(k, $event.target.checked)"
                      />
                      <span>Allow template sending</span>
                      <span :class="Number(k.template_sending_enabled ?? 1) === 1 ? 'text-emerald-400' : 'text-amber-300'">
                        {{ Number(k.template_sending_enabled ?? 1) === 1 ? 'Enabled' : 'Disabled' }}
                      </span>
                    </label>
                  </div>
                  <div class="flex items-center gap-3 shrink-0">
                    <button type="button" class="text-xs text-accent hover:underline" @click="startEditKey(k)">Edit</button>
                    <button type="button" class="text-xs text-rose-400" @click="removeKey(k.id)">Delete</button>
                  </div>
                </div>
              </template>
            </div>

            <div ref="channelBrowseSentinel" class="h-1" aria-hidden="true" />
            <div
              v-if="loadingChannelBrowse && channelBrowseRows.length"
              class="py-3 text-center text-xs text-slate-500"
            >
              Memuat lagi...
            </div>
            <p
              v-else-if="channelBrowseRows.length && !channelBrowseHasMore"
              class="py-3 text-center text-xs text-slate-600"
            >
              Semua nomor sudah dimuat
            </p>
          </div>
        </div>
      </section>

      <!-- Templates -->
      <section v-if="tab === 'templates'" class="card space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <h2 class="font-display font-semibold text-lg">Templates</h2>
          <p v-if="templateBrowseTotal" class="text-xs text-slate-500">
            {{ templateBrowseRows.length }} / {{ templateBrowseTotal }} template
          </p>
        </div>

        <div class="rounded-xl border border-white/10 bg-ink-950/40 p-3 space-y-2">
          <p class="text-xs text-slate-400">
            Template dari seluruh WABA Meta yang sudah disinkronkan.
            Template perlu <span class="text-slate-200">di-assign ke team</span> yang memakai WABA yang sama
            sebelum bisa dipakai kirim/blast — termasuk jika WABA hanya dipakai 1 team.
          </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2">
          <select v-model="templateWabaFilter" class="field sm:max-w-xs">
            <option value="" disabled>Pilih WABA terlebih dahulu</option>
            <option v-for="waba in wabas" :key="'filter-' + waba.id" :value="waba.meta_waba_id">{{ waba.name }}</option>
          </select>
          <button
            type="button"
            class="btn shrink-0 inline-flex items-center justify-center gap-2 min-w-[9rem]"
            :disabled="syncingTemplates || !templateWabaFilter"
            @click="syncTemplates"
          >
            <svg v-if="syncingTemplates" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            {{ syncingTemplates ? "Sinkron..." : "Sync Template" }}
          </button>
          <div class="relative flex-1">
          <input
            v-model="templateBrowseQuery"
            type="search"
            class="field pl-9"
            placeholder="Cari template, WABA, nomor..."
            autocomplete="off"
          />
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500 pointer-events-none"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
          </svg>
          </div>
        </div>

        <div class="rounded-xl border border-white/10 overflow-hidden">
          <div class="max-h-[min(65vh,36rem)] overflow-y-auto">
            <div
              v-if="loadingTemplateBrowse && !templateBrowseRows.length"
              class="py-10 text-center text-sm text-slate-500"
            >
              Memuat template...
            </div>
            <p
              v-else-if="!templateBrowseRows.length"
              class="py-10 text-center text-sm text-slate-500"
            >
              {{ !templateWabaFilter ? "Pilih WABA terlebih dahulu." : (templateBrowseQuery.trim() ? "Template tidak ditemukan." : "Belum ada template. Klik Sync WABA.") }}
            </p>

            <div v-for="group in templateGroups" :key="group.waba_id || '__none__'" class="border-b border-white/5 last:border-0">
              <div class="sticky top-0 z-10 px-4 py-2.5 bg-ink-900/95 backdrop-blur border-b border-white/10">
                <p class="font-mono text-sm text-accent truncate">
                  {{ group.waba_id || "Belum terhubung WABA" }}
                </p>
                <p class="text-[11px] text-slate-500 mt-0.5">
                  <span v-if="group.waba_label && group.waba_id">{{ group.waba_label }} · </span>
                  {{ group.templates.length }} template
                </p>
              </div>

              <ul class="divide-y divide-white/5">
                <li v-for="t in group.templates" :key="t.id" class="px-4 py-3">
                  <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                      <p class="font-medium">
                        {{ t.template_name }}
                        <span class="text-xs text-slate-500 ml-1">{{ t.language }}</span>
                        <span v-if="(t.channels || []).length" class="text-xs text-slate-500 ml-1">
                          · {{ (t.channels || []).map((c) => c.label || c.phone_number).join(', ') }}
                        </span>
                      </p>
                      <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[10px] font-medium">
                        <span
                          v-if="t.meta_category"
                          class="rounded-full bg-violet-500/15 px-2 py-0.5 text-violet-300"
                        >
                          {{ t.meta_category }}
                        </span>
                        <span
                          v-if="t.meta_status"
                          class="rounded-full px-2 py-0.5"
                          :class="String(t.meta_status).toUpperCase() === 'APPROVED' ? 'bg-emerald-500/15 text-emerald-300' : String(t.meta_status).toUpperCase() === 'REJECTED' ? 'bg-rose-500/15 text-rose-300' : 'bg-amber-500/15 text-amber-300'"
                        >
                          {{ t.meta_status }}
                        </span>
                        <span
                          v-if="t.meta_quality_rating"
                          class="rounded-full px-2 py-0.5"
                          :class="String(t.meta_quality_rating).toUpperCase() === 'GREEN' ? 'bg-emerald-500/15 text-emerald-300' : String(t.meta_quality_rating).toUpperCase() === 'YELLOW' ? 'bg-amber-500/15 text-amber-300' : 'bg-rose-500/15 text-rose-300'"
                        >
                          Quality: {{ t.meta_quality_rating }}
                        </span>
                      </div>
                  <div v-if="(t.assigned_teams || []).length" class="mt-2 flex flex-wrap gap-1.5 items-center">
                    <span
                      v-for="tm in (t.assigned_teams || [])"
                      :key="'asg-' + tm.id"
                      class="px-2 py-0.5 rounded-full text-[10px] bg-accent/10 text-accent-soft"
                    >
                      {{ tm.name }}
                    </span>
                  </div>
                  <div v-else class="mt-2">
                    <span class="text-[10px] text-amber-500">
                      Belum di-assign ke team manapun
                    </span>
                  </div>
                </div>
                <div class="flex gap-1 shrink-0 flex-wrap justify-end">
                  <button
                    type="button"
                    class="btn-sm text-xs"
                    @click="openTemplateAssign(t, group.waba_id)"
                  >
                    {{ assignTemplateId === t.id ? "Tutup assign" : "Assign" }}
                  </button>
                      <button
                        type="button"
                        class="btn-sm text-xs"
                        @click="expandedTemplate = expandedTemplate === t.id ? null : t.id"
                      >
                        {{ expandedTemplate === t.id ? 'Tutup' : 'Detail' }}
                      </button>
                    </div>
                  </div>

                  <div
                    v-if="assignTemplateId === t.id && assignMeta"
                    class="mt-3 rounded-lg border border-white/10 bg-ink-950/40 p-3 space-y-3 text-xs"
                  >
                    <p class="text-slate-300 font-medium">Assign ke team (WABA sama)</p>
                    <p v-if="!assignMeta.eligible_teams?.length" class="text-amber-500">
                      Tidak ada team pada WABA ini. Assign team ke channel dulu di tab Channel.
                    </p>
                    <div v-else class="space-y-2">
                      <label
                        v-for="tm in assignMeta.eligible_teams"
                        :key="'pick-' + tm.id"
                        class="flex items-center gap-2 cursor-pointer"
                      >
                        <input
                          v-model="assignDraft"
                          type="checkbox"
                          class="rounded border-white/20"
                          :value="Number(tm.id)"
                        />
                        <span>{{ tm.name }}</span>
                      </label>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                      <button type="button" class="btn-sm text-xs" @click="assignTemplateId = null">Batal</button>
                      <button
                        type="button"
                        class="btn-sm text-xs"
                        :disabled="savingAssignId === t.id || !assignMeta.eligible_teams?.length"
                        @click="saveTemplateAssign(t, group.waba_id)"
                      >
                        {{ savingAssignId === t.id ? "Menyimpan..." : "Simpan assign" }}
                      </button>
                    </div>
                  </div>

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
                          <td colspan="7" class="px-3 py-3 text-center text-slate-500">Tidak ada params</td>
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
                    <div v-if="(t.params||[]).length" class="mt-2 flex justify-end px-3 pb-3">
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
            </div>

            <div ref="templateBrowseSentinel" class="h-1" aria-hidden="true" />

            <div
              v-if="loadingTemplateBrowse && templateBrowseRows.length"
              class="py-3 text-center text-xs text-slate-500"
            >
              Memuat lagi...
            </div>
            <p
              v-else-if="templateBrowseRows.length && !templateBrowseHasMore"
              class="py-3 text-center text-xs text-slate-600"
            >
              Semua template sudah dimuat
            </p>
          </div>
        </div>
      </section>

      <!-- Config -->
      <section v-if="tab === 'config'" class="card space-y-6">
        <h2 class="font-display font-semibold text-lg">Config</h2>

        <form
          class="rounded-xl border border-accent/20 bg-accent/5 p-4 space-y-3"
          @submit.prevent="switchOperationalTeam"
        >
          <div>
            <p class="text-sm font-medium text-slate-200">Team operasional</p>
            <p class="text-xs text-slate-500 mt-1">Wajib masuk team untuk kirim chat dan blast WA.</p>
          </div>

          <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
            <TeamSearchSelect
              v-model="adminTeamPick"
              allow-empty
              class="flex-1 sm:max-w-sm"
              :fallback-label="auth.user?.team_name || ''"
              :active="tab === 'config'"
            />
            <button type="submit" class="btn-sm shrink-0 w-full sm:w-auto" :disabled="joiningTeam || !teams.length">
              {{ joiningTeam ? "Menyimpan..." : "Terapkan" }}
            </button>
          </div>

          <p v-if="auth.hasTeam" class="text-xs text-slate-500">
            Aktif sekarang:
            <span class="text-accent font-medium">{{ auth.user?.team_name || `#${auth.user?.team_id}` }}</span>
          </p>
          <p v-else class="text-xs text-amber-300/90">Belum masuk team operasional.</p>
          <p v-if="!teams.length" class="text-xs text-amber-300/90">Buat team dulu di tab Teams.</p>
        </form>

        <form class="rounded-xl border border-white/10 bg-ink-950/40 p-4 space-y-3" @submit.prevent="saveDefaultTeam">
          <p class="text-sm font-medium text-slate-200">Default team</p>
          <p class="text-xs text-slate-400">
            Customer baru — belum pernah ada riwayat chat — masuk ke team ini. Hanya satu default team.
          </p>
          <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
            <TeamSearchSelect
              v-model="defaultTeamDraft"
              class="flex-1 sm:max-w-md"
              placeholder="Pilih default team..."
              :fallback-label="defaultTeamLabel"
              :active="tab === 'config'"
            />
            <button type="submit" class="btn-sm shrink-0" :disabled="!defaultTeamDraft || savingDefaultTeam">
              {{ savingDefaultTeam ? "Menyimpan..." : "Simpan" }}
            </button>
          </div>
          <p v-if="!teams.length" class="text-xs text-amber-300/90">Buat team dulu di tab Teams.</p>
        </form>

        <form class="rounded-xl border border-white/10 bg-ink-950/40 p-4 space-y-3" @submit.prevent="saveDailyLimit">
          <p class="text-sm font-medium text-slate-200">Limit harian tenant</p>
          <p class="text-xs text-slate-400">
            Maks. nomor customer unik terkirim (status sent+) per hari untuk seluruh tenant.
            Semua channel/WABA dalam tenant berbagi kuota ini. Pengiriman gagal tidak dihitung. Reset setiap hari.
          </p>
          <div class="flex flex-col sm:flex-row gap-2 items-end">
            <div class="flex-1 w-full sm:max-w-xs">
              <label class="label">Maks. nomor unik / hari</label>
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
              {{ savingDailyLimit ? "Menyimpan..." : "Simpan" }}
            </button>
          </div>
        </form>

        <div class="rounded-xl border border-white/10 bg-ink-950/40 p-4 space-y-2">
          <p class="text-sm font-medium text-slate-200">Koneksi WhatsApp Meta</p>
          <p class="text-xs text-slate-400">
            Nomor dan template disinkronkan dari WABA Meta. Access token disimpan di environment backend,
            bukan di konfigurasi tenant WaDesk.
          </p>
        </div>

        <form class="rounded-xl border border-white/10 bg-ink-950/40 p-4 space-y-3" @submit.prevent="saveOpenAiKey">
          <p class="text-sm font-medium text-slate-200">OpenAI</p>
          <p class="text-xs text-slate-400">
            API key OpenAI disimpan per tenant. Dipakai fitur AI WaDesk.
          </p>
          <div class="flex flex-col sm:flex-row gap-2">
            <input
              v-model="openaiForm.api_key"
              type="password"
              class="field flex-1"
              :placeholder="openaiForm.configured ? openaiForm.api_key_masked : 'sk-...'"
              autocomplete="off"
            />
            <button type="submit" class="btn shrink-0" :disabled="savingOpenAiKey">
              {{ savingOpenAiKey ? "Menyimpan..." : openaiForm.configured ? "Update" : "Simpan" }}
            </button>
          </div>
          <p v-if="openaiForm.configured" class="text-xs text-emerald-400">
            Terkonfigurasi: {{ openaiForm.api_key_masked }}
          </p>
          <div v-if="openaiForm.configured" class="flex justify-end">
            <button
              type="button"
              class="text-xs text-rose-400 hover:text-rose-300"
              :disabled="deletingOpenAiKey"
              @click="askDeleteOpenAiKey"
            >
              {{ deletingOpenAiKey ? "Menghapus..." : "Hapus API key" }}
            </button>
          </div>
        </form>
      </section>

      <!-- Dev Fee -->
      <section v-if="tab === 'dev-fee'" class="card space-y-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="font-display font-semibold text-lg">Dev Fee</h2>
            <p class="text-xs text-slate-500 mt-1">Pemakaian template gabungan untuk seluruh team dan channel pada tenant ini.</p>
          </div>
          <button type="button" class="btn-sm shrink-0" :disabled="loadingDevFee" @click="loadDevFee(true)">
            {{ loadingDevFee ? 'Memuat...' : 'Refresh' }}
          </button>
        </div>

        <p v-if="!devFeeReady" class="text-sm text-amber-300/90 rounded-lg border border-amber-500/20 bg-amber-500/10 p-3">
          Tabel Dev Fee belum tersedia. Jalankan migration <code class="text-amber-100">026_tenant_dev_fee.sql</code> dan <code class="text-amber-100">027_tenant_dev_fee_bca_payments.sql</code>.
        </p>

        <template v-else>
          <form class="rounded-xl border border-white/10 bg-ink-950/40 p-4 space-y-3" @submit.prevent="createDevFeeTopup">
            <div class="flex flex-col sm:flex-row sm:items-end gap-3">
              <div class="flex-1">
                <label class="label">Jumlah quota</label>
                <input v-model.number="devFeeTopupQuota" type="number" min="1000" max="150000" required class="field" placeholder="Contoh: 1000" />
              </div>
              <div class="text-xs text-slate-400 sm:pb-3">Rp50 per quota · pembayaran BCA saja</div>
              <button class="btn shrink-0" :disabled="creatingDevFeeTopup">
                {{ creatingDevFeeTopup ? 'Membuat...' : 'Buat pembayaran BCA' }}
              </button>
            </div>
          </form>
          <div v-if="devFeePendingPayment" class="rounded-xl border border-sky-400/30 bg-sky-500/10 p-4">
            <p class="font-semibold text-sky-100">BCA transfer is awaiting confirmation</p>
            <p class="mt-1 text-sm text-slate-300">Your Dev Fee top-up is ready. Review the payment details or cancel this order.</p>
            <div class="mt-4 flex flex-wrap gap-2">
              <button type="button" class="btn-sm border border-sky-400/30 bg-sky-400/10 text-sky-100 hover:bg-sky-400/20" @click="devFeePaymentModal = 'check'">Check</button>
              <button type="button" class="btn-sm border border-rose-400/30 bg-rose-400/10 text-rose-200 hover:bg-rose-400/20" @click="devFeePaymentModal = 'cancel'">Cancel</button>
            </div>
          </div>
          <div class="grid sm:grid-cols-3 gap-3">
            <div class="rounded-xl border border-white/10 bg-ink-950/40 p-4">
              <p class="text-xs text-slate-500">Quota total</p>
              <p class="mt-1 text-2xl font-semibold">{{ devFeeSummary.quota_total ?? 'Belum diatur' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-ink-950/40 p-4">
              <p class="text-xs text-slate-500">Template terpakai</p>
              <p class="mt-1 text-2xl font-semibold text-accent">{{ devFeeSummary.quota_used }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-ink-950/40 p-4">
              <p class="text-xs text-slate-500">Sisa quota</p>
              <p class="mt-1 text-2xl font-semibold">{{ devFeeSummary.quota_remaining ?? '—' }}</p>
            </div>
          </div>
          <p class="text-xs text-slate-500">Setiap template yang berhasil dikirim akan mengurangi quota tenant dan tercatat di bawah.</p>

          <div class="space-y-4">
            <div class="rounded-2xl border border-white/10 bg-ink-950/25 p-4 space-y-3">
              <div class="flex items-center justify-between gap-2"><h3 class="font-medium">Riwayat top-up</h3><span class="text-xs text-slate-500">{{ devFeePayments.length }} transaksi</span></div>
              <div class="rounded-xl border border-white/10 overflow-hidden"><div class="max-h-64 overflow-y-auto divide-y divide-white/5">
                <p v-if="!devFeePayments.length" class="py-8 text-center text-sm text-slate-500">Belum ada riwayat top-up.</p>
                <div v-for="payment in devFeePayments" :key="payment.id" class="px-4 py-3 flex items-start justify-between gap-3">
                  <div><p class="font-medium text-emerald-400">Top-up <span class="ml-2 tabular-nums">+{{ payment.quota_amount }} quota</span></p><p class="text-xs text-slate-500 mt-0.5">BCA · Rp{{ formatCurrency(payment.amount) }} · {{ formatLogTime(payment.created_at) }}</p></div>
                  <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px]" :class="payment.payment_status === 'success' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400'">{{ payment.payment_status === 'success' ? 'Success' : 'Pending' }}</span>
                </div>
              </div></div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-ink-950/25 p-4 space-y-3">
              <div class="flex items-center justify-between gap-2"><h3 class="font-medium">Riwayat pakai & refund</h3><span class="text-xs text-slate-500">{{ devFeeLogs.length }} / {{ devFeeLogsTotal }} entri</span></div>
              <div class="rounded-xl border border-white/10 overflow-hidden"><div class="max-h-[min(60vh,32rem)] overflow-y-auto divide-y divide-white/5">
                <p v-if="loadingDevFee && !devFeeLogs.length" class="py-8 text-center text-sm text-slate-500">Memuat riwayat...</p>
                <p v-else-if="!devFeeLogs.length" class="py-8 text-center text-sm text-slate-500">Belum ada pemakaian quota.</p>
                <div v-for="log in devFeeLogs" :key="log.id" class="px-4 py-3 flex items-start justify-between gap-3"><div class="min-w-0"><p class="font-medium" :class="log.type === 'refund' ? 'text-emerald-400' : 'text-rose-400'">{{ log.type === 'refund' ? 'Refund +1 quota' : 'Template sent −1 quota' }}</p><p class="text-xs text-slate-300 mt-0.5 truncate">{{ log.template_name }}</p><p class="text-xs text-slate-500 mt-0.5">{{ log.phone || '—' }} · {{ log.source === 'blast' ? 'Blast' : 'Chat' }} · {{ log.team_name || '—' }}</p></div><p class="shrink-0 text-[10px] text-slate-600">{{ formatLogTime(log.created_at) }}</p></div>
                <div v-if="devFeeLogs.length" ref="devFeeSentinel" class="h-1" aria-hidden="true" />
                <p v-if="devFeeLogs.length" class="px-4 py-3 text-center text-xs text-slate-500"><span v-if="loadingDevFee">Memuat lagi...</span><span v-else-if="devFeeHasMore">Scroll untuk memuat lebih banyak</span><span v-else>Semua riwayat dimuat</span></p>
              </div></div>
            </div>
          </div>
        </template>
      </section>

      <!-- Quota -->
      <section v-if="tab === 'quota'" class="card space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <h2 class="font-display font-semibold text-lg">Kuota Template</h2>
          <p v-if="quotaBrowseTotal" class="text-xs text-slate-500">
            {{ quotaBrowseRows.length }} / {{ quotaBrowseTotal }} team
          </p>
        </div>
        <p class="text-xs text-slate-500">
          Saldo per team (Team Leader). Dipakai bersama TL + semua agent di team tersebut.
          Potong 1 hanya jika kirim template sukses di YCloud.
        </p>

        <form class="space-y-3 rounded-xl border border-white/10 bg-ink-950/40 p-4" @submit.prevent="doTopup">
          <p class="text-sm font-medium text-slate-200">Top-up kuota</p>
          <div class="grid sm:grid-cols-[1fr_8rem_1fr_auto] gap-2 items-end">
            <div class="sm:col-span-4 lg:col-span-1">
              <label class="label">Team</label>
              <p v-if="selectedQuotaTeam" class="text-xs text-slate-400 mb-2">
                Terpilih: <span class="text-slate-200">{{ selectedQuotaTeam.team_name }}</span>
                · saldo {{ selectedQuotaTeam.balance }}
              </p>
              <p v-else class="text-xs text-amber-300/90 mb-2">Pilih team dari daftar di bawah.</p>
            </div>
            <div>
              <label class="label">Jumlah</label>
              <input v-model.number="quotaForm.amount" type="number" min="1" required class="field" placeholder="100" />
            </div>
            <div>
              <label class="label">Catatan</label>
              <input v-model="quotaForm.note" class="field" placeholder="Opsional" />
            </div>
            <button class="btn" :disabled="!quotaForm.team_id">Top-up</button>
          </div>
        </form>

        <div class="relative">
          <input
            v-model="quotaBrowseQuery"
            type="search"
            class="field pl-9"
            placeholder="Cari team, TL, email..."
            autocomplete="off"
          />
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500 pointer-events-none"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
          </svg>
        </div>

        <div class="rounded-xl border border-white/10 overflow-hidden">
          <div class="max-h-[min(60vh,32rem)] overflow-y-auto divide-y divide-white/5">
            <div
              v-if="loadingQuotaBrowse && !quotaBrowseRows.length"
              class="py-10 text-center text-sm text-slate-500"
            >
              Memuat kuota...
            </div>
            <p
              v-else-if="!quotaBrowseRows.length"
              class="py-10 text-center text-sm text-slate-500"
            >
              {{ quotaBrowseQuery.trim() ? "Team tidak ditemukan." : "Belum ada team." }}
            </p>

            <button
              v-for="q in quotaBrowseRows"
              :key="q.team_id"
              type="button"
              class="w-full px-4 py-3 flex items-center justify-between gap-2 text-left hover:bg-white/[0.02] transition"
              :class="Number(quotaForm.team_id) === Number(q.team_id) ? 'bg-accent/10' : ''"
              @click="quotaForm.team_id = q.team_id"
            >
              <div>
                <p class="font-medium">{{ q.team_name }}</p>
                <p class="text-xs text-slate-500">
                  TL: {{ q.leader_name || "—" }}
                  <span v-if="q.leader_email"> · {{ q.leader_email }}</span>
                </p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-lg font-semibold text-accent">{{ q.balance }}</p>
                <p class="text-[10px] text-slate-500">sisa kuota</p>
              </div>
            </button>

            <div ref="quotaBrowseSentinel" class="h-1" aria-hidden="true" />

            <div
              v-if="loadingQuotaBrowse && quotaBrowseRows.length"
              class="py-3 text-center text-xs text-slate-500"
            >
              Memuat lagi...
            </div>
            <p
              v-else-if="quotaBrowseRows.length && !quotaBrowseHasMore"
              class="py-3 text-center text-xs text-slate-600"
            >
              Semua team sudah dimuat
            </p>
          </div>
        </div>
      </section>

      <!-- Report -->
      <section v-if="tab === 'report'">
        <DailyReportPanel
          admin-mode
          :teams="teams"
          v-model:team-id="reportTeamId"
          :active="tab === 'report'"
        />
      </section>

      <!-- Template fail logs -->
      <section v-if="tab === 'log'" class="card space-y-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="font-display font-semibold text-lg">Log Template Gagal</h2>
            <p class="text-xs text-slate-500 mt-1">
              Kegagalan langsung dari API Meta, atau delivery gagal via webhook (sent → failed).
            </p>
          </div>
          <button type="button" class="btn-sm shrink-0" :disabled="loadingFailLogs" @click="loadFailLogs(true)">
            {{ loadingFailLogs ? "Memuat..." : "Refresh" }}
          </button>
        </div>

        <p v-if="!failLogsReady" class="text-sm text-amber-300/90 rounded-lg border border-amber-500/20 bg-amber-500/10 p-3">
          Tabel log belum lengkap. Jalankan migration
          <code class="text-amber-100">016_template_fail_logs.sql</code>
          dan
          <code class="text-amber-100">017_template_fail_logs_webhook.sql</code>
          di database <code class="text-amber-100">mdl_wadesk</code>.
        </p>

        <div v-else-if="loadingFailLogs && !failLogs.length" class="text-sm text-slate-500 py-4 text-center">
          Memuat log...
        </div>
        <p v-else-if="!failLogs.length" class="text-sm text-slate-500 text-center py-4">
          Belum ada log kegagalan template.
        </p>

        <div v-else class="rounded-xl border border-white/10 overflow-hidden">
          <div class="max-h-[min(60vh,32rem)] overflow-y-auto divide-y divide-white/5">
          <ul class="space-y-0">
            <li v-for="row in failLogs" :key="row.id" class="px-4 py-3">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="font-medium text-rose-300 truncate" :title="row.error_message">
                  {{ row.error_message }}
                </p>
                <p class="text-xs text-slate-400 mt-1">
                  {{ row.template_name }} ({{ row.language }}) → {{ row.phone }}
                  · {{ row.source === 'webhook' ? 'Webhook' : row.source === 'blast' ? 'Blast' : 'Chat' }}
                  <span v-if="row.error_code" class="text-rose-400/80"> · #{{ row.error_code }}</span>
                </p>
                <p class="text-[10px] text-slate-600 mt-1">
                  {{ formatLogTime(row.created_at) }}
                  · {{ row.channel_label || row.channel_phone || 'channel' }}
                  · {{ row.team_name || 'team' }}
                  <span v-if="row.user_name"> · {{ row.user_name }}</span>
                </p>
                <p v-if="row.preview" class="text-xs text-slate-500 mt-1 line-clamp-2 whitespace-pre-wrap">
                  {{ row.preview }}
                </p>
              </div>
              <button
                type="button"
                class="btn-sm text-xs shrink-0"
                @click="expandedFailLog = expandedFailLog === row.id ? null : row.id"
              >
                {{ expandedFailLog === row.id ? 'Tutup' : 'Detail' }}
              </button>
            </div>
            <div
              v-if="expandedFailLog === row.id"
              class="mt-3 rounded-lg border border-white/10 bg-ink-950/60 p-3 space-y-3 text-xs"
            >
              <div class="grid sm:grid-cols-2 gap-2 text-slate-400">
                <p><span class="text-slate-500">Device:</span> {{ row.device_id || '—' }}</p>
                <p><span class="text-slate-500">HTTP:</span> {{ row.http_code || '—' }}</p>
                <p v-if="row.message_id"><span class="text-slate-500">Msg:</span> #{{ row.message_id }}</p>
                <p v-if="row.conversation_id"><span class="text-slate-500">Conv:</span> #{{ row.conversation_id }}</p>
                <p v-if="row.blast_id"><span class="text-slate-500">Blast:</span> #{{ row.blast_id }}</p>
              </div>
              <div>
                <p class="text-slate-500 mb-1 font-medium">Request</p>
                <pre class="overflow-x-auto whitespace-pre-wrap break-all text-slate-300">{{ prettyJson(row.request) }}</pre>
              </div>
              <div>
                <p class="text-slate-500 mb-1 font-medium">Response provider</p>
                <pre class="overflow-x-auto whitespace-pre-wrap break-all text-slate-300">{{ prettyJson(row.response) }}</pre>
              </div>
            </div>
            </li>
          </ul>

          <div v-if="failLogs.length" ref="failLogsSentinel" class="h-1" aria-hidden="true" />
          <div v-if="failLogs.length" class="px-4 py-3 text-center text-xs text-slate-500">
            <span v-if="loadingFailLogs">Memuat log berikutnya...</span>
            <span v-else-if="failLogsHasMore">Scroll untuk memuat lebih banyak</span>
            <span v-else>Semua {{ failLogsTotal }} log sudah dimuat</span>
          </div>
        </div>
        </div>
      </section>

      <p v-if="msg" class="text-sm text-emerald-400">{{ msg }}</p>
      <p v-if="err" class="text-sm text-rose-400">{{ err }}</p>
    </div>
    </div>

    <ConfirmModal
      v-if="dialog.open"
      :title="dialog.title"
      :message="dialog.message"
      :mode="dialog.mode"
      :confirm-label="dialog.confirmLabel"
      :danger="dialog.danger"
      :required-text="dialog.requiredText"
      @confirm="onDialogConfirm"
      @close="closeDialog"
    />

    <Teleport to="body">
      <div v-if="addTeamModal" class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center bg-black/70 p-0 sm:p-4 backdrop-blur-sm" @click.self="addTeamModal = false">
        <form class="w-full sm:max-w-xl rounded-t-2xl sm:rounded-2xl border border-white/10 bg-ink-900 p-5 shadow-2xl space-y-4" @submit.prevent="createTeam">
          <div class="flex items-center justify-between gap-3">
            <div><h3 class="font-display text-lg font-semibold">Tambah team</h3><p class="mt-1 text-xs text-slate-400">Atur kategori, limit harian, dan masa akses template.</p></div>
            <button type="button" class="text-sm text-slate-400 hover:text-slate-200" @click="addTeamModal = false">Tutup</button>
          </div>
          <input v-model="teamForm.name" required class="field" placeholder="Nama team baru" />
          <div class="grid gap-3 sm:grid-cols-2">
            <select v-model="teamForm.template_category" class="field"><option value="UTILITY">Utility</option><option value="MARKETING">Marketing</option></select>
            <input v-model.number="teamForm.daily_template_limit" type="number" min="1" max="1000000" class="field" placeholder="Limit template per hari" aria-label="Limit template per hari" />
          </div>
          <div><label class="label">Kadaluarsa akses template (opsional)</label><input v-model="teamForm.template_access_expires_at" type="date" :min="teamExpiryMin" :max="teamExpiryMax" class="field" /></div>
          <div class="flex justify-end gap-2"><button type="button" class="btn-sm" @click="addTeamModal = false">Batal</button><button class="btn">Tambah team</button></div>
        </form>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="addUserModal" class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center bg-black/70 p-0 sm:p-4 backdrop-blur-sm" @click.self="addUserModal = false">
        <form class="w-full sm:max-w-xl rounded-t-2xl sm:rounded-2xl border border-white/10 bg-ink-900 p-5 shadow-2xl space-y-4" @submit.prevent="createUser">
          <div class="flex items-center justify-between gap-3"><div><h3 class="font-display text-lg font-semibold">Tambah user</h3><p class="mt-1 text-xs text-slate-400">Buat akun Team Leader atau Agent.</p></div><button type="button" class="text-sm text-slate-400 hover:text-slate-200" @click="addUserModal = false">Tutup</button></div>
          <div class="grid gap-3 sm:grid-cols-2"><input v-model="userForm.name" required class="field" placeholder="Nama" /><input v-model="userForm.email" type="email" required class="field" placeholder="Email" /><input v-model="userForm.password" type="password" required minlength="6" class="field" placeholder="Password (min. 6 karakter)" /><select v-model="userForm.role" required class="field" @change="onRoleChange"><option value="team_leader">Team Leader</option><option value="agent">Agent</option></select></div>
          <template v-if="userForm.role === 'team_leader'">
            <select v-model="userForm.team_id" required class="field"><option disabled value="">Pilih team</option><option v-for="t in teamsWithoutLeader" :key="t.id" :value="t.id">{{ t.name }}</option></select>
            <p v-if="!teamsWithoutLeader.length" class="text-xs text-amber-300">Semua team sudah punya Team Leader. Buat team baru dulu.</p>
          </template>
          <template v-else>
            <select v-model="userForm.team_leader_user_id" required class="field"><option disabled value="">Pilih Team Leader</option><option v-for="l in userLeaders" :key="l.id" :value="l.id">{{ l.name }} ({{ l.team_name || "tanpa team" }})</option></select>
            <p v-if="!userLeaders.length" class="text-xs text-amber-300">Belum ada Team Leader. Buat Team Leader dulu sebelum menambah Agent.</p>
          </template>
          <div class="flex justify-end gap-2"><button type="button" class="btn-sm" @click="addUserModal = false">Batal</button><button class="btn" :disabled="!canSubmitUser">Tambah user</button></div>
        </form>
      </div>
    </Teleport>

    <div v-if="devFeePaymentModal" class="fixed inset-0 z-[80] flex items-center justify-center bg-ink-950/80 p-4 backdrop-blur-sm">
      <div class="w-full max-w-md rounded-3xl border border-white/10 bg-ink-900 p-6 shadow-2xl">
        <template v-if="devFeePaymentModal === 'check'">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-300">BCA Payment Details</p>
          <h3 class="mt-2 font-display text-xl font-semibold">Transfer exactly this amount</h3>
          <p class="mt-5 text-4xl font-semibold tabular-nums text-sky-200">Rp{{ formatCurrency(devFeePendingPayment?.amount) }}</p>
          <p class="mt-1 text-sm text-slate-400">Credits {{ devFeePendingPayment?.quota_amount }} Dev Fee quota.</p>
          <div class="mt-5 rounded-2xl border border-white/10 bg-ink-950/50 p-4 text-sm">
            <p class="text-slate-500">Transfer to</p>
            <p class="mt-1 font-medium text-slate-100">{{ devFeePendingPayment?.bank_account?.label || 'BCA' }}</p>
            <p class="mt-1 text-lg font-semibold tracking-wide text-slate-100">{{ devFeePendingPayment?.bank_account?.number || '—' }}</p>
            <p class="mt-1 text-slate-400">a.n. {{ devFeePendingPayment?.bank_account?.name || '—' }}</p>
          </div>
          <p class="mt-4 text-xs leading-5 text-slate-500">Confirmation is automatic after the BCA transaction appears in our records.</p>
          <button type="button" class="btn mt-6 w-full" @click="devFeePaymentModal = null">Done</button>
        </template>
        <template v-else>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-rose-300">Cancel BCA Payment</p>
          <h3 class="mt-2 font-display text-xl font-semibold">Cancel this pending top-up?</h3>
          <p class="mt-3 text-sm leading-6 text-slate-400">The payment instruction will be cancelled. Do not transfer the displayed amount after cancelling.</p>
          <div class="mt-6 flex gap-2"><button type="button" class="btn-sm flex-1" @click="devFeePaymentModal = null">Keep it</button><button type="button" class="btn flex-1 bg-rose-500 hover:bg-rose-400" :disabled="cancellingDevFeeTopup" @click="cancelDevFeeTopup">{{ cancellingDevFeeTopup ? 'Cancelling...' : 'Cancel payment' }}</button></div>
        </template>
      </div>
    </div>
  </AppHeader>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api";
import { useAuthStore } from "../stores/auth";
import ConfirmModal from "../components/ConfirmModal.vue";
import AppHeader from "../components/AppHeader.vue";
import DailyReportPanel from "../components/DailyReportPanel.vue";
import TeamSearchSelect from "../components/TeamSearchSelect.vue";

const auth = useAuthStore();
const router = useRouter();
const tab = ref("teams");
const adminTeamPick = ref("");
const joiningTeam = ref(false);
const reportTeamId = ref("");
const tabs = [
  { id: "teams", label: "Teams" },
  { id: "users", label: "Users" },
  { id: "wabas", label: "WABA" },
  { id: "numbers", label: "Number" },
  { id: "templates", label: "Templates" },
  { id: "config", label: "Config" },
  { id: "dev-fee", label: "Dev Fee" },
  { id: "quota", label: "Quota" },
  { id: "report", label: "Report" },
  { id: "log", label: "Log" },
];

const teams = ref([]);
const TEAM_BROWSE_LIMIT = 20;
const teamBrowseRows = ref([]);
const teamBrowseTotal = ref(0);
const teamBrowsePage = ref(1);
const teamBrowseQuery = ref("");
const loadingTeamBrowse = ref(false);
const teamBrowseHasMore = ref(true);
const teamBrowseSentinel = ref(null);
let teamBrowseObserver = null;
let teamSearchTimer = null;
const USER_BROWSE_LIMIT = 20;
const userBrowseRows = ref([]);
const userBrowseTotal = ref(0);
const userBrowsePage = ref(1);
const userBrowseQuery = ref("");
const loadingUserBrowse = ref(false);
const userBrowseHasMore = ref(true);
const userBrowseSentinel = ref(null);
const userLeaders = ref([]);
let userBrowseObserver = null;
let userSearchTimer = null;
const QUOTA_BROWSE_LIMIT = 20;
const quotaBrowseRows = ref([]);
const quotaBrowseTotal = ref(0);
const quotaBrowsePage = ref(1);
const quotaBrowseQuery = ref("");
const loadingQuotaBrowse = ref(false);
const quotaBrowseHasMore = ref(true);
const quotaBrowseSentinel = ref(null);
let quotaBrowseObserver = null;
let quotaSearchTimer = null;
const availableDevices = ref([]);
const wabas = ref([]);
const numbers = ref([]);
const numberStats = computed(() => {
  const total = numbers.value.length;
  const active = numbers.value.filter((number) => String(number.status || "").toLowerCase() === "active").length;
  return { total, active, inactive: total - active };
});
const qualityBadgeClass = (q) => ({ GREEN: "bg-emerald-500/15 text-emerald-300", YELLOW: "bg-amber-500/15 text-amber-300", RED: "bg-rose-500/15 text-rose-300" }[String(q || "").toUpperCase()] || "bg-white/5 text-slate-300");
const loadingNumbers = ref(false);
const numberWabaFilter = ref("");
const addingNumber = ref(false);
const numberLimitModal = reactive({ open: false, current: 0, wabaName: "" });
const numberForm = reactive({ waba_id: "", country_code: "62", phone_number: "", verified_name: "", method: "SMS", otp: "" });
const numberFlow = reactive({ step: "add", phone_number_id: "", loading: false, error: "", otpCooldown: 0, otpLocked: 0, otpVerifyFails: 0, otpRequested: false });
let numberOtpTimer = null;
const numberRequestLabel = computed(() => numberFlow.otpCooldown > 0 ? `Minta ulang (${numberFlow.otpCooldown}s)` : (numberFlow.otpRequested ? "Minta ulang OTP" : "Request OTP"));
const editingWabaTeamId = ref(null);
const wabaTeamDraft = ref([]);
const savingWabaTeamId = ref(null);
const syncingWabas = ref(false);
const resubscribing = ref(false);
const syncingNumbers = ref(false);
const syncingTemplates = ref(false);
const deviceQuery = ref("");
const CHANNEL_BROWSE_LIMIT = 20;
const channelBrowseRows = ref([]);
const channelBrowseTotal = ref(0);
const channelBrowsePage = ref(1);
const channelBrowseQuery = ref("");
const loadingChannelBrowse = ref(false);
const channelBrowseHasMore = ref(true);
const channelBrowseSentinel = ref(null);
const savingTemplateSendChannelId = ref(null);
const ASSIGN_TEAM_LIMIT = 20;
const assignTeamRows = ref([]);
const assignTeamTotal = ref(0);
const assignTeamPage = ref(1);
const assignTeamQuery = ref("");
const loadingAssignTeams = ref(false);
const assignTeamHasMore = ref(true);
const assignTeamSentinel = ref(null);
let channelBrowseObserver = null;
let assignTeamObserver = null;
let channelSearchTimer = null;
let assignTeamSearchTimer = null;
const channelForm = reactive({ device_id: "", label: "", team_ids: [] });
const kiriminForm = reactive({ api_key: "", api_key_masked: "", configured: false });
const dailyLimitForm = reactive({ daily_unique_limit: 250 });
const openaiForm = reactive({ api_key: "", api_key_masked: "", configured: false });
const savingKiriminKey = ref(false);
const savingDailyLimit = ref(false);
const savingOpenAiKey = ref(false);
const deletingOpenAiKey = ref(false);
const syncingDevices = ref(false);
const editingKeyId = ref(null);
const TEMPLATE_BROWSE_LIMIT = 20;
const templateBrowseRows = ref([]);
const templateBrowseTotal = ref(0);
const templateBrowsePage = ref(1);
const templateBrowseQuery = ref("");
const templateWabaFilter = ref("");
const loadingTemplateBrowse = ref(false);
const templateBrowseHasMore = ref(true);
const templateBrowseSentinel = ref(null);
let templateBrowseObserver = null;
let templateSearchTimer = null;
const assignTemplateId = ref(null);
const assignWabaId = ref("");
const assignDraft = ref([]);
const assignMeta = ref(null);
const savingAssignId = ref(null);
const msg = ref("");
const err = ref("");

const dialog = reactive({
  open: false,
  mode: "confirm",
  title: "Konfirmasi",
  message: "",
  confirmLabel: "Hapus",
  danger: true,
  requiredText: "",
  action: null,
});

function askConfirm({ title, message, confirmLabel = "Hapus", danger = true, mode = "confirm", requiredText = "", action }) {
  dialog.open = true;
  dialog.mode = mode;
  dialog.title = title || "Konfirmasi";
  dialog.message = message;
  dialog.confirmLabel = confirmLabel;
  dialog.danger = danger;
  dialog.requiredText = requiredText;
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
const teamForm = reactive({ name: "", template_category: "UTILITY", daily_template_limit: 250, template_access_expires_at: "" });
const addTeamModal = ref(false);
const editingTeamId = ref(null);
const editingTeamName = ref("");
const editingTeamCategory = ref("UTILITY");
const editingTeamDailyLimit = ref(250);
const editingTeamExpiry = ref("");
const teamExpiryMin = new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Jakarta" });
const teamExpiryMax = (() => { const d = new Date(); d.setFullYear(d.getFullYear() + 1); return d.toLocaleDateString("en-CA", { timeZone: "Asia/Jakarta" }); })();
const savingTeam = ref(false);
const savingTeamMaskId = ref(null);
const defaultTeamDraft = ref("");
const savingDefaultTeam = ref(false);
const userForm = reactive({
  name: "",
  email: "",
  password: "",
  role: "agent",
  team_id: "",
  team_leader_user_id: "",
});
const addUserModal = ref(false);
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
const editKeyForm = reactive({ label: "", team_ids: [] });
const savingKey = ref(false);
const syncing = ref(false);
const savingMaxlengthId = ref(null);
const maxlengthDraft = reactive({});
const expandedTemplate = ref(null);
const quotaForm = reactive({ team_id: "", amount: 100, note: "" });
const DEV_FEE_LIMIT = 20;
const devFeeReady = ref(true);
const devFeeSummary = reactive({ quota_total: null, quota_used: 0, quota_remaining: null });
const devFeeTopupQuota = ref(1000);
const devFeePendingPayment = ref(null);
const creatingDevFeeTopup = ref(false);
const cancellingDevFeeTopup = ref(false);
const devFeePaymentModal = ref(null);
const devFeePayments = ref([]);
const devFeeLogs = ref([]);
const devFeeLogsTotal = ref(0);
const devFeePage = ref(1);
const devFeeHasMore = ref(true);
const loadingDevFee = ref(false);
const devFeeSentinel = ref(null);
let devFeeObserver = null;
const failLogs = ref([]);
const failLogsTotal = ref(0);
const failLogsPage = ref(1);
const FAIL_LOGS_LIMIT = 20;
const failLogsLimit = ref(FAIL_LOGS_LIMIT);
const failLogsReady = ref(true);
const loadingFailLogs = ref(false);
const expandedFailLog = ref(null);
const failLogsHasMore = ref(true);
const failLogsSentinel = ref(null);
let failLogsObserver = null;

const teamsWithoutLeader = computed(() =>
  teams.value.filter((t) => !t.team_leader_user_id)
);

const defaultTeamLabel = computed(() => {
  const hit = teams.value.find((t) => String(t.id) === String(defaultTeamDraft.value));
  return hit?.name || "";
});

const selectedQuotaTeam = computed(() =>
  quotaBrowseRows.value.find((q) => Number(q.team_id) === Number(quotaForm.team_id)) || null
);

const templateGroups = computed(() => {
  const map = new Map();
  for (const t of templateBrowseRows.value) {
    const wabaId = String(t.waba_id || "").trim();
    const key = wabaId || "__none__";
    if (!map.has(key)) {
      map.set(key, {
        waba_id: wabaId,
        waba_label: t.waba_label || "",
        templates: [],
      });
    }
    map.get(key).templates.push(t);
  }
  return [...map.values()].sort((a, b) => {
    if (!a.waba_id && b.waba_id) return 1;
    if (a.waba_id && !b.waba_id) return -1;
    return String(a.waba_id).localeCompare(String(b.waba_id));
  });
});

const selectedAssignDevice = computed(() =>
  availableDevices.value.find((d) => d.device_id === channelForm.device_id) || null
);

const filteredDevices = computed(() => {
  const q = deviceQuery.value.trim().toLowerCase();
  if (!q) return availableDevices.value;
  return availableDevices.value.filter((d) => {
    const label = String(d.label || d.device_id || "").toLowerCase();
    const phone = String(d.phone_number || "").toLowerCase();
    const id = String(d.device_id || "").toLowerCase();
    return label.includes(q) || phone.includes(q) || id.includes(q);
  });
});

const canSubmitUser = computed(() => {
  if (userForm.role === "team_leader") {
    return !!userForm.team_id && teamsWithoutLeader.value.length > 0;
  }
  return !!userForm.team_leader_user_id && userLeaders.value.length > 0;
});

function onRoleChange() {
  userForm.team_id = "";
  userForm.team_leader_user_id = "";
}

function flash(ok, text) {
  msg.value = ok ? text : "";
  err.value = ok ? "" : text;
}

function syncAdminTeamPick() {
  adminTeamPick.value = auth.user?.team_id ? String(auth.user.team_id) : "";
}

async function switchOperationalTeam() {
  const pick = adminTeamPick.value ? Number(adminTeamPick.value) : 0;
  const current = Number(auth.user?.team_id || 0);
  joiningTeam.value = true;
  try {
    if (pick <= 0) {
      if (current > 0) {
        await auth.leaveTeam();
        flash(true, "Keluar dari team operasional");
      }
      return;
    }
    if (pick === current) {
      flash(true, `Sudah aktif di team ${auth.user?.team_name || ""}`.trim());
      return;
    }
    await auth.joinTeam(pick);
    flash(true, `Masuk team ${auth.user?.team_name || ""}`.trim());
  } catch (e) {
    flash(false, e.message || "Gagal ganti team");
    syncAdminTeamPick();
  } finally {
    joiningTeam.value = false;
  }
}

async function loadChannelBrowse(reset = false) {
  if (loadingChannelBrowse.value) return;
  if (!reset && !channelBrowseHasMore.value) return;

  loadingChannelBrowse.value = true;
  try {
    if (reset) {
      channelBrowsePage.value = 1;
      channelBrowseHasMore.value = true;
    }
    const page = reset ? 1 : channelBrowsePage.value;
    const q = channelBrowseQuery.value.trim();
    const res = await api(
      `/WaDesk/Channels/list?scope=all&page=${page}&limit=${CHANNEL_BROWSE_LIMIT}&q=${encodeURIComponent(q)}&_=${Date.now()}`,
      { cache: "no-store" }
    );
    const rows = res.data?.channels || res.data?.keys || [];
    channelBrowseTotal.value = Number(res.data?.total ?? rows.length);
    if (reset) {
      channelBrowseRows.value = rows;
    } else {
      const seen = new Set(channelBrowseRows.value.map((r) => r.id));
      for (const row of rows) {
        if (!seen.has(row.id)) {
          channelBrowseRows.value.push(row);
          seen.add(row.id);
        }
      }
    }
    channelBrowseHasMore.value = channelBrowseRows.value.length < channelBrowseTotal.value;
    channelBrowsePage.value = page + 1;
  } catch (e) {
    if (reset) {
      channelBrowseRows.value = [];
      channelBrowseTotal.value = 0;
    }
    flash(false, e.message || "Gagal memuat nomor");
  } finally {
    loadingChannelBrowse.value = false;
    await nextTick();
    setupChannelBrowseObserver();
  }
}

async function loadAssignTeams(reset = false) {
  if (loadingAssignTeams.value) return;
  if (!reset && !assignTeamHasMore.value) return;

  loadingAssignTeams.value = true;
  try {
    if (reset) {
      assignTeamPage.value = 1;
      assignTeamHasMore.value = true;
    }
    const page = reset ? 1 : assignTeamPage.value;
    const q = assignTeamQuery.value.trim();
    const res = await api(
      `/WaDesk/Teams/list?page=${page}&limit=${ASSIGN_TEAM_LIMIT}&q=${encodeURIComponent(q)}&_=${Date.now()}`,
      { cache: "no-store" }
    );
    const rows = res.data?.teams || [];
    assignTeamTotal.value = Number(res.data?.total ?? rows.length);
    if (reset) {
      assignTeamRows.value = rows;
    } else {
      const seen = new Set(assignTeamRows.value.map((t) => t.id));
      for (const row of rows) {
        if (!seen.has(row.id)) {
          assignTeamRows.value.push(row);
          seen.add(row.id);
        }
      }
    }
    assignTeamHasMore.value = assignTeamRows.value.length < assignTeamTotal.value;
    assignTeamPage.value = page + 1;
  } catch (e) {
    if (reset) {
      assignTeamRows.value = [];
      assignTeamTotal.value = 0;
    }
    flash(false, e.message || "Gagal memuat team");
  } finally {
    loadingAssignTeams.value = false;
    await nextTick();
    setupAssignTeamObserver();
  }
}

function setupChannelBrowseObserver() {
  channelBrowseObserver?.disconnect();
  if (tab.value !== "assign" || !channelBrowseSentinel.value || !channelBrowseHasMore.value) return;
  channelBrowseObserver = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) loadChannelBrowse(false);
    },
    { rootMargin: "160px" }
  );
  channelBrowseObserver.observe(channelBrowseSentinel.value);
}

function setupAssignTeamObserver() {
  assignTeamObserver?.disconnect();
  if (tab.value !== "assign" || !assignTeamSentinel.value || !assignTeamHasMore.value) return;
  assignTeamObserver = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) loadAssignTeams(false);
    },
    { rootMargin: "120px" }
  );
  assignTeamObserver.observe(assignTeamSentinel.value);
}

async function loadAssignTab() {
  await Promise.all([loadChannelBrowse(true), loadAssignTeams(true)]);
  if (!availableDevices.value.length) {
    await syncDevicesFromKirimin();
  }
}

async function loadUserLeaders() {
  try {
    const res = await api("/WaDesk/Users/leaders", { cache: "no-store" });
    userLeaders.value = res.data?.leaders || [];
  } catch {
    userLeaders.value = [];
  }
}

async function loadUserBrowse(reset = false) {
  if (loadingUserBrowse.value) return;
  if (!reset && !userBrowseHasMore.value) return;

  loadingUserBrowse.value = true;
  try {
    if (reset) {
      userBrowsePage.value = 1;
      userBrowseHasMore.value = true;
    }
    const page = reset ? 1 : userBrowsePage.value;
    const q = userBrowseQuery.value.trim();
    const res = await api(
      `/WaDesk/Users/list?page=${page}&limit=${USER_BROWSE_LIMIT}&q=${encodeURIComponent(q)}&_=${Date.now()}`,
      { cache: "no-store" }
    );
    const rows = res.data?.users || [];
    userBrowseTotal.value = Number(res.data?.total ?? rows.length);
    if (reset) {
      userBrowseRows.value = rows;
    } else {
      const seen = new Set(userBrowseRows.value.map((u) => u.id));
      for (const row of rows) {
        if (!seen.has(row.id)) {
          userBrowseRows.value.push(row);
          seen.add(row.id);
        }
      }
    }
    userBrowseHasMore.value = userBrowseRows.value.length < userBrowseTotal.value;
    userBrowsePage.value = page + 1;
  } catch (e) {
    if (reset) {
      userBrowseRows.value = [];
      userBrowseTotal.value = 0;
    }
    flash(false, e.message || "Gagal memuat user");
  } finally {
    loadingUserBrowse.value = false;
    await nextTick();
    setupUserBrowseObserver();
  }
}

function setupUserBrowseObserver() {
  userBrowseObserver?.disconnect();
  if (tab.value !== "users" || !userBrowseSentinel.value || !userBrowseHasMore.value) return;
  userBrowseObserver = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) loadUserBrowse(false);
    },
    { rootMargin: "160px" }
  );
  userBrowseObserver.observe(userBrowseSentinel.value);
}

async function loadUsersTab() {
  await Promise.all([loadUserBrowse(true), loadUserLeaders(), loadTeamOptions()]);
}

async function loadTemplateBrowse(reset = false) {
  if (loadingTemplateBrowse.value) return;
  if (!reset && !templateBrowseHasMore.value) return;

  loadingTemplateBrowse.value = true;
  try {
    if (reset) {
      templateBrowsePage.value = 1;
      templateBrowseHasMore.value = true;
    }
    const page = reset ? 1 : templateBrowsePage.value;
    const q = templateBrowseQuery.value.trim();
    const waba = templateWabaFilter.value.trim();
    if (!waba) {
      templateBrowseRows.value = [];
      templateBrowseTotal.value = 0;
      templateBrowseHasMore.value = false;
      return;
    }
    const res = await api(
      `/WaDesk/Templates/list?page=${page}&limit=${TEMPLATE_BROWSE_LIMIT}&q=${encodeURIComponent(q)}${waba ? `&waba_id=${encodeURIComponent(waba)}` : ""}&_=${Date.now()}`,
      { cache: "no-store" }
    );
    const rows = res.data?.templates || [];
    templateBrowseTotal.value = Number(res.data?.total ?? rows.length);
    if (reset) {
      templateBrowseRows.value = rows;
    } else {
      const seen = new Set(templateBrowseRows.value.map((t) => t.id));
      for (const row of rows) {
        if (!seen.has(row.id)) {
          templateBrowseRows.value.push(row);
          seen.add(row.id);
        }
      }
    }
    initMaxlengthDrafts(rows);
    templateBrowseHasMore.value = templateBrowseRows.value.length < templateBrowseTotal.value;
    templateBrowsePage.value = page + 1;
  } catch (e) {
    if (reset) {
      templateBrowseRows.value = [];
      templateBrowseTotal.value = 0;
    }
    flash(false, e.message || "Gagal memuat template");
  } finally {
    loadingTemplateBrowse.value = false;
    await nextTick();
    setupTemplateBrowseObserver();
  }
}

async function openTemplateAssign(t, wabaId = "") {
  if (assignTemplateId.value === t.id) {
    assignTemplateId.value = null;
    assignMeta.value = null;
    return;
  }
  assignTemplateId.value = t.id;
  assignWabaId.value = String(wabaId || t.waba_id || "").trim();
  assignMeta.value = null;
  try {
    const params = new URLSearchParams({ template_id: String(t.id) });
    if (assignWabaId.value) params.set("waba_id", assignWabaId.value);
    const res = await api(`/WaDesk/Templates/teamOptions?${params.toString()}`, { cache: "no-store" });
    assignMeta.value = res.data;
    assignDraft.value = [...(res.data?.assigned_team_ids || [])].map(Number);
  } catch (e) {
    assignTemplateId.value = null;
    flash(false, e.message || "Gagal memuat opsi team");
  }
}

function pickAssignWabaId(fallback = "") {
  const ids = (assignMeta.value?.waba_ids || []).map((x) => String(x).trim()).filter(Boolean);
  const fb = String(fallback || assignWabaId.value || "").trim();
  if (ids.length === 1) return ids[0];
  if (fb && ids.includes(fb)) return fb;
  return fb || ids[0] || "";
}

async function saveTemplateAssign(t, wabaId = "") {
  savingAssignId.value = t.id;
  err.value = "";
  try {
    const scopeWaba = pickAssignWabaId(wabaId || t.waba_id || "");
    const res = await api("/WaDesk/Templates/assignTeams", {
      method: "POST",
      body: {
        template_id: Number(t.id),
        team_ids: assignDraft.value.map(Number),
        ...(scopeWaba ? { waba_id: scopeWaba } : {}),
      },
    });
    const row = templateBrowseRows.value.find((x) => Number(x.id) === Number(t.id));
    if (row) {
      row.assigned_teams = res.data?.assigned_teams || [];
    }
    assignTemplateId.value = null;
    assignMeta.value = null;
    flash(true, res.message || "Assign template disimpan");
  } catch (e) {
    flash(false, e.message || "Gagal menyimpan assign");
  } finally {
    savingAssignId.value = null;
  }
}

function setupTemplateBrowseObserver() {
  templateBrowseObserver?.disconnect();
  if (tab.value !== "templates" || !templateBrowseSentinel.value || !templateBrowseHasMore.value) return;
  templateBrowseObserver = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) loadTemplateBrowse(false);
    },
    { rootMargin: "160px" }
  );
  templateBrowseObserver.observe(templateBrowseSentinel.value);
}

async function loadQuotaBrowse(reset = false) {
  if (loadingQuotaBrowse.value) return;
  if (!reset && !quotaBrowseHasMore.value) return;

  loadingQuotaBrowse.value = true;
  try {
    if (reset) {
      quotaBrowsePage.value = 1;
      quotaBrowseHasMore.value = true;
    }
    const page = reset ? 1 : quotaBrowsePage.value;
    const q = quotaBrowseQuery.value.trim();
    const res = await api(
      `/WaDesk/Quota/list?page=${page}&limit=${QUOTA_BROWSE_LIMIT}&q=${encodeURIComponent(q)}&_=${Date.now()}`,
      { cache: "no-store" }
    );
    const rows = res.data?.quotas || [];
    quotaBrowseTotal.value = Number(res.data?.total ?? rows.length);
    if (reset) {
      quotaBrowseRows.value = rows;
    } else {
      const seen = new Set(quotaBrowseRows.value.map((r) => r.team_id));
      for (const row of rows) {
        if (!seen.has(row.team_id)) {
          quotaBrowseRows.value.push(row);
          seen.add(row.team_id);
        }
      }
    }
    quotaBrowseHasMore.value = quotaBrowseRows.value.length < quotaBrowseTotal.value;
    quotaBrowsePage.value = page + 1;
  } catch (e) {
    if (reset) {
      quotaBrowseRows.value = [];
      quotaBrowseTotal.value = 0;
    }
    flash(false, e.message || "Gagal memuat kuota");
  } finally {
    loadingQuotaBrowse.value = false;
    await nextTick();
    setupQuotaBrowseObserver();
  }
}

function setupQuotaBrowseObserver() {
  quotaBrowseObserver?.disconnect();
  if (tab.value !== "quota" || !quotaBrowseSentinel.value || !quotaBrowseHasMore.value) return;
  quotaBrowseObserver = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) loadQuotaBrowse(false);
    },
    { rootMargin: "160px" }
  );
  quotaBrowseObserver.observe(quotaBrowseSentinel.value);
}

async function loadReportTab() {
  await loadTeamOptions();
  if (!reportTeamId.value && teams.value.length) {
    reportTeamId.value = String(teams.value[0].id);
  }
}

async function loadTeamOptions() {
  const res = await api("/WaDesk/Teams/options");
  teams.value = res.data?.teams || [];
  if (res.data?.default_team_id) {
    defaultTeamDraft.value = String(res.data.default_team_id);
  } else {
    syncDefaultTeamDraft();
  }
}

async function loadTeamBrowse(reset = false) {
  if (loadingTeamBrowse.value) return;
  if (!reset && !teamBrowseHasMore.value) return;

  loadingTeamBrowse.value = true;
  try {
    if (reset) {
      teamBrowsePage.value = 1;
      teamBrowseHasMore.value = true;
    }
    const page = reset ? 1 : teamBrowsePage.value;
    const q = teamBrowseQuery.value.trim();
    const res = await api(
      `/WaDesk/Teams/list?page=${page}&limit=${TEAM_BROWSE_LIMIT}&q=${encodeURIComponent(q)}&_=${Date.now()}`,
      { cache: "no-store" }
    );
    const rows = res.data?.teams || [];
    teamBrowseTotal.value = Number(res.data?.total ?? 0);
    if (reset) {
      teamBrowseRows.value = rows;
    } else {
      const seen = new Set(teamBrowseRows.value.map((t) => t.id));
      for (const row of rows) {
        if (!seen.has(row.id)) {
          teamBrowseRows.value.push(row);
          seen.add(row.id);
        }
      }
    }
    teamBrowseHasMore.value = teamBrowseRows.value.length < teamBrowseTotal.value;
    teamBrowsePage.value = page + 1;
    if (res.data?.default_team_id) {
      defaultTeamDraft.value = String(res.data.default_team_id);
    }
  } catch (e) {
    if (reset) {
      teamBrowseRows.value = [];
      teamBrowseTotal.value = 0;
    }
    flash(false, e.message || "Gagal memuat team");
  } finally {
    loadingTeamBrowse.value = false;
    await nextTick();
    setupTeamBrowseObserver();
  }
}

function setupTeamBrowseObserver() {
  teamBrowseObserver?.disconnect();
  if (tab.value !== "teams" || !teamBrowseSentinel.value || !teamBrowseHasMore.value) return;
  teamBrowseObserver = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) {
        loadTeamBrowse(false);
      }
    },
    { rootMargin: "160px" }
  );
  teamBrowseObserver.observe(teamBrowseSentinel.value);
}

function teamChannelPreview(t) {
  const channels = Array.isArray(t.channels) ? t.channels : [];
  if (!channels.length) return "";
  const labels = channels.slice(0, 2).map((c) => c.label || c.phone_number).filter(Boolean);
  const extra = Number(t.channel_count || channels.length) - labels.length;
  if (extra > 0) return `${labels.join(", ")} +${extra}`;
  return labels.join(", ");
}

async function reloadTeamsTab() {
  await Promise.all([loadTeamOptions(), loadTeamBrowse(true)]);
}

async function refresh() {
  const [oai, daily] = await Promise.all([
    api("/WaDesk/Settings/openai"),
    api("/WaDesk/Settings/dailyLimit"),
    loadTeamOptions(),
  ]);
  if (tab.value === "teams") {
    await loadTeamBrowse(true);
  }
  if (tab.value === "assign") {
    await loadAssignTab();
  }
  if (tab.value === "users") {
    await loadUsersTab();
  }
  if (tab.value === "wabas") {
    await loadWabas();
  }
  if (tab.value === "numbers") {
    await loadWabas();
    await loadNumbers();
  }
  if (tab.value === "quota") {
    await loadQuotaBrowse(true);
  }
  if (tab.value === "templates") {
    await loadWabas();
    await loadTemplateBrowse(true);
  }
  dailyLimitForm.daily_unique_limit = Number(daily.data?.daily_unique_limit) || 250;
  openaiForm.configured = !!oai.data?.configured;
  openaiForm.api_key_masked = oai.data?.api_key_masked || "";
  if (tab.value === "log") {
    await loadFailLogs(true);
  }
}

function formatLogTime(v) {
  if (!v) return "—";
  try {
    return new Date(String(v).replace(" ", "T")).toLocaleString("id-ID", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return v;
  }
}

function prettyJson(obj) {
  if (obj == null) return "—";
  try {
    return JSON.stringify(obj, null, 2);
  } catch {
    return String(obj);
  }
}

function setupDevFeeObserver() {
  devFeeObserver?.disconnect();
  if (tab.value !== "dev-fee" || !devFeeSentinel.value || !devFeeHasMore.value) return;
  devFeeObserver = new IntersectionObserver(
    (entries) => {
      if (entries.some((entry) => entry.isIntersecting)) loadDevFee(false);
    },
    { rootMargin: "160px" }
  );
  devFeeObserver.observe(devFeeSentinel.value);
}

async function loadDevFee(reset = false) {
  if (loadingDevFee.value || (!reset && !devFeeHasMore.value)) return;
  const page = reset ? 1 : devFeePage.value + 1;
  loadingDevFee.value = true;
  try {
    const [summary, logs, payments] = await Promise.all([
      reset ? api(`/WaDesk/DevFee/summary?_=${Date.now()}`, { cache: "no-store" }) : Promise.resolve(null),
      api(`/WaDesk/DevFee/logs?page=${page}&limit=${DEV_FEE_LIMIT}&_=${Date.now()}`, { cache: "no-store" }),
      reset ? api(`/WaDesk/DevFee/payments?_=${Date.now()}`, { cache: "no-store" }) : Promise.resolve(null),
    ]);
    if (summary) {
      devFeeReady.value = summary.data?.table_ready !== false;
      Object.assign(devFeeSummary, {
        quota_total: summary.data?.quota_total ?? null,
        quota_used: Number(summary.data?.quota_used ?? 0),
        quota_remaining: summary.data?.quota_remaining ?? null,
      });
      devFeePendingPayment.value = summary.data?.pending_payment ?? null;
    }
    if (payments) devFeePayments.value = payments.data?.payments ?? [];
    if (logs.data?.table_ready === false) {
      devFeeReady.value = false;
      devFeeLogs.value = [];
      devFeeLogsTotal.value = 0;
      devFeeHasMore.value = false;
      return;
    }
    const rows = logs.data?.logs ?? [];
    devFeeLogs.value = reset ? rows : [...devFeeLogs.value, ...rows];
    devFeeLogsTotal.value = Number(logs.data?.total ?? 0);
    devFeePage.value = Number(logs.data?.page ?? page);
    devFeeHasMore.value = devFeeLogs.value.length < devFeeLogsTotal.value && rows.length > 0;
    await nextTick();
    setupDevFeeObserver();
  } catch (e) {
    if (reset) {
      devFeeLogs.value = [];
      devFeeLogsTotal.value = 0;
      devFeeHasMore.value = false;
    }
    flash(false, e.message || "Gagal memuat Dev Fee");
  } finally {
    loadingDevFee.value = false;
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat("id-ID").format(Number(value) || 0);
}

async function createDevFeeTopup() {
  const quotaAmount = Number(devFeeTopupQuota.value);
  if (!Number.isInteger(quotaAmount) || quotaAmount < 1000 || quotaAmount > 150000) {
    flash(false, "Jumlah quota harus antara 1.000 dan 150.000");
    return;
  }
  creatingDevFeeTopup.value = true;
  try {
    const res = await api("/WaDesk/DevFee/createTopup", { method: "POST", body: { quota_amount: quotaAmount } });
    devFeePendingPayment.value = res.data;
    await loadDevFee(true);
    flash(true, res.message || "Pembayaran BCA dibuat");
  } catch (e) {
    flash(false, e.message || "Gagal membuat pembayaran BCA");
  } finally {
    creatingDevFeeTopup.value = false;
  }
}

async function cancelDevFeeTopup() {
  const paymentRef = devFeePendingPayment.value?.payment_ref;
  if (!paymentRef) return;
  cancellingDevFeeTopup.value = true;
  try {
    await api("/WaDesk/DevFee/cancelTopup", { method: "POST", body: { payment_ref: paymentRef } });
    devFeePaymentModal.value = null;
    devFeePendingPayment.value = null;
    flash(true, "Pembayaran BCA dibatalkan");
    await loadDevFee(true);
  } catch (e) {
    flash(false, e.message || "Gagal membatalkan pembayaran");
  } finally {
    cancellingDevFeeTopup.value = false;
  }
}

function setupFailLogsObserver() {
  failLogsObserver?.disconnect();
  if (tab.value !== "log" || !failLogsSentinel.value || !failLogsHasMore.value) return;
  failLogsObserver = new IntersectionObserver(
    (entries) => {
      if (entries.some((entry) => entry.isIntersecting)) loadFailLogs(false);
    },
    { rootMargin: "160px" }
  );
  failLogsObserver.observe(failLogsSentinel.value);
}

async function loadFailLogs(reset = false) {
  if (loadingFailLogs.value || (!reset && !failLogsHasMore.value)) return;
  const page = reset ? 1 : failLogsPage.value + 1;
  loadingFailLogs.value = true;
  if (reset) expandedFailLog.value = null;
  try {
    const res = await api(
      `/WaDesk/TemplateLogs/list?page=${encodeURIComponent(page)}&limit=${failLogsLimit.value}&_=${Date.now()}`,
      { cache: "no-store" }
    );
    failLogsReady.value = res.data?.table_ready !== false;
    const rows = res.data?.logs ?? [];
    failLogs.value = reset ? rows : [...failLogs.value, ...rows];
    failLogsTotal.value = Number(res.data?.total ?? 0);
    failLogsPage.value = Number(res.data?.page ?? page);
    failLogsHasMore.value = failLogs.value.length < failLogsTotal.value && rows.length > 0;
    await nextTick();
    setupFailLogsObserver();
  } catch (e) {
    if (reset) {
      failLogs.value = [];
      failLogsTotal.value = 0;
      failLogsHasMore.value = false;
    }
    flash(false, e.message || "Gagal memuat log");
  } finally {
    loadingFailLogs.value = false;
  }
}

watch(tab, (id) => {
  if (id === "dev-fee") {
    loadDevFee(true);
  }
  if (id === "log") {
    loadFailLogs(true);
  }
  if (id === "teams") {
    loadTeamBrowse(true);
  }
  if (id === "assign") {
    loadAssignTab();
  }
  if (id === "users") {
    loadUsersTab();
  }
  if (id === "wabas") {
    loadWabas();
  }
  if (id === "numbers") {
    loadWabas().then(() => loadNumbers());
  }
  if (id === "quota") {
    loadQuotaBrowse(true);
  }
  if (id === "templates") {
    loadWabas().then(() => loadTemplateBrowse(true));
  }
  if (id === "report") {
    loadReportTab();
  }
  if (id === "config") {
    loadTeamOptions().then(syncAdminTeamPick);
  }
});

watch(userBrowseQuery, () => {
  clearTimeout(userSearchTimer);
  userSearchTimer = setTimeout(() => {
    if (tab.value === "users") loadUserBrowse(true);
  }, 300);
});

watch(quotaBrowseQuery, () => {
  clearTimeout(quotaSearchTimer);
  quotaSearchTimer = setTimeout(() => {
    if (tab.value === "quota") loadQuotaBrowse(true);
  }, 300);
});

watch(templateBrowseQuery, () => {
  clearTimeout(templateSearchTimer);
  templateSearchTimer = setTimeout(() => {
    if (tab.value === "templates") loadTemplateBrowse(true);
  }, 300);
});

watch(templateWabaFilter, () => {
  if (tab.value === "templates") loadTemplateBrowse(true);
});

watch(numberWabaFilter, () => {
  if (tab.value === "numbers") loadNumbers();
});

watch(userBrowseSentinel, () => {
  nextTick(setupUserBrowseObserver);
});

watch(quotaBrowseSentinel, () => {
  nextTick(setupQuotaBrowseObserver);
});

watch(templateBrowseSentinel, () => {
  nextTick(setupTemplateBrowseObserver);
});

watch(failLogsSentinel, () => {
  nextTick(setupFailLogsObserver);
});

watch(devFeeSentinel, () => {
  nextTick(setupDevFeeObserver);
});

watch(channelBrowseQuery, () => {
  clearTimeout(channelSearchTimer);
  channelSearchTimer = setTimeout(() => {
    if (tab.value === "assign") loadChannelBrowse(true);
  }, 300);
});

watch(assignTeamQuery, () => {
  clearTimeout(assignTeamSearchTimer);
  assignTeamSearchTimer = setTimeout(() => {
    if (tab.value === "assign") loadAssignTeams(true);
  }, 300);
});

watch(channelBrowseSentinel, () => {
  nextTick(setupChannelBrowseObserver);
});

watch(assignTeamSentinel, () => {
  nextTick(setupAssignTeamObserver);
});

watch(teamBrowseQuery, () => {
  clearTimeout(teamSearchTimer);
  teamSearchTimer = setTimeout(() => {
    if (tab.value === "teams") loadTeamBrowse(true);
  }, 300);
});

watch(teamBrowseSentinel, () => {
  nextTick(setupTeamBrowseObserver);
});

async function createTeam() {
  try {
    await api("/WaDesk/Teams/create", { method: "POST", body: { name: teamForm.name, template_category: teamForm.template_category, daily_template_limit: teamForm.daily_template_limit, template_access_expires_at: teamForm.template_access_expires_at || null } });
    flash(true, "Team dibuat");
    teamForm.name = "";
    teamForm.template_category = "UTILITY";
    teamForm.daily_template_limit = 250;
    teamForm.template_access_expires_at = "";
    addTeamModal.value = false;
    await refresh();
  } catch (e) {
    flash(false, e.message);
  }
}

function startEditTeam(t) {
  editingTeamId.value = t.id;
  editingTeamName.value = t.name || "";
  editingTeamCategory.value = t.template_category || "UTILITY";
  editingTeamDailyLimit.value = Number(t.daily_template_limit || 250);
  editingTeamExpiry.value = t.template_access_expires_at || "";
}

function cancelEditTeam() {
  editingTeamId.value = null;
  editingTeamName.value = "";
  editingTeamCategory.value = "UTILITY";
  editingTeamDailyLimit.value = 250;
  editingTeamExpiry.value = "";
  savingTeam.value = false;
}

async function saveTeamName(t) {
  const name = String(editingTeamName.value || "").trim();
  if (!name) {
    flash(false, "Nama team wajib diisi");
    return;
  }
  const category = String(editingTeamCategory.value || "UTILITY").toUpperCase();
  const dailyLimit = Number(editingTeamDailyLimit.value || 0);
  if (name === t.name && category === String(t.template_category || "UTILITY").toUpperCase() && dailyLimit === Number(t.daily_template_limit || 250) && String(editingTeamExpiry.value || "") === String(t.template_access_expires_at || "")) {
    cancelEditTeam();
    return;
  }
  savingTeam.value = true;
  try {
    await api("/WaDesk/Teams/update", {
      method: "POST",
      body: { id: t.id, name, template_category: category, daily_template_limit: dailyLimit, template_access_expires_at: editingTeamExpiry.value || null },
    });
    flash(true, "Team diubah");
    cancelEditTeam();
    await reloadTeamsTab();
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingTeam.value = false;
  }
}

function teamExpired(team) {
  return Boolean(team?.template_access_expires_at) && String(team.template_access_expires_at) < teamExpiryMin;
}

async function setTeamPhoneMasking(team, enabled) {
  savingTeamMaskId.value = team.id;
  try {
    const res = await api("/WaDesk/Teams/setPhoneMasking", {
      method: "POST",
      body: { team_id: team.id, enabled },
    });
    team.mask_phone_numbers = res.data?.mask_phone_numbers ? 1 : 0;
    flash(true, enabled ? "Phone masking enabled" : "Phone masking disabled");
  } catch (e) {
    flash(false, e.message || "Failed to save phone masking setting");
  } finally {
    savingTeamMaskId.value = null;
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

function syncDefaultTeamDraft() {
  const current = teams.value.find((t) => Number(t.is_default) === 1);
  defaultTeamDraft.value = current ? String(current.id) : "";
}

async function saveDefaultTeam() {
  const teamId = Number(defaultTeamDraft.value);
  if (!teamId) {
    flash(false, "Pilih default team");
    return;
  }
  savingDefaultTeam.value = true;
  try {
    await api("/WaDesk/Teams/setDefault", {
      method: "POST",
      body: { team_id: teamId },
    });
    flash(true, "Default team disimpan");
    await reloadTeamsTab();
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingDefaultTeam.value = false;
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
    addUserModal.value = false;
    flash(true, "User dibuat");
    await loadUsersTab();
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
    await loadUsersTab();
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingUser.value = false;
  }
}

async function promoteAgent(u) {
  const tlName = u.team_leader_name || "TL saat ini";
  const hasTl = !!u.team_leader_name;

  askConfirm({
    title: "Jadikan Team Leader",
    message: hasTl
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
          hasTl
            ? `${u.name} sekarang TL; ${tlName} turun jadi agent`
            : `${u.name} sekarang Team Leader`
        );
        await loadUsersTab();
      } catch (e) {
        flash(false, e.message);
      }
    },
  });
}

async function removeUser(u) {
  const isTl = u.role === "team_leader";
  const agentCount = isTl ? Number(u.agent_count || 0) : 0;

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
        await loadUsersTab();
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
    await refresh();
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingDailyLimit.value = false;
  }
}

async function saveOpenAiKey() {
  const apiKey = String(openaiForm.api_key || "").trim();
  if (!apiKey) {
    flash(false, "OpenAI API key wajib diisi");
    return;
  }
  savingOpenAiKey.value = true;
  try {
    const res = await api("/WaDesk/Settings/openai", {
      method: "POST",
      body: { api_key: apiKey },
    });
    openaiForm.api_key = "";
    openaiForm.configured = true;
    openaiForm.api_key_masked = res.data?.api_key_masked || "";
    flash(true, "OpenAI API key disimpan");
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingOpenAiKey.value = false;
  }
}

function askDeleteOpenAiKey() {
  askConfirm({
    title: "Hapus OpenAI API key",
    message: "API key OpenAI tenant akan dihapus. Fitur AI tidak bisa dipakai sampai disimpan lagi.",
    confirmLabel: "Hapus",
    danger: true,
    action: () => deleteOpenAiKey(),
  });
}

async function deleteOpenAiKey() {
  deletingOpenAiKey.value = true;
  try {
    await api("/WaDesk/Settings/deleteOpenai", { method: "POST", body: {} });
    openaiForm.api_key = "";
    openaiForm.configured = false;
    openaiForm.api_key_masked = "";
    flash(true, "OpenAI API key dihapus");
  } catch (e) {
    flash(false, e.message);
  } finally {
    deletingOpenAiKey.value = false;
  }
}

async function syncDevicesFromKirimin() {
  syncingDevices.value = true;
  try {
    await api("/WaDesk/Wabas/sync", { method: "POST", body: {} });
    await loadChannelBrowse(true);
    availableDevices.value = channelBrowseRows.value
      .filter((channel) => String(channel.provider || "").toLowerCase() === "meta")
      .map((channel) => ({
        device_id: channel.meta_phone_number_id || channel.device_id,
        label: channel.label,
        phone_number: channel.phone_number,
        assigned: {
          label: channel.label,
          team_ids: channel.team_ids || [],
          team_names: channel.team_names || "",
        },
      }));
    flash(true, `Nomor Meta: ${availableDevices.value.length} ditemukan`);
  } catch (e) {
    flash(false, e.message);
  } finally {
    syncingDevices.value = false;
  }
}

function selectAssignDevice(dev) {
  channelForm.device_id = dev.device_id;
  channelForm.label = dev.assigned?.label || dev.label || "";
  const existing = Array.isArray(dev.assigned?.team_ids)
    ? dev.assigned.team_ids.map((id) => Number(id)).filter((id) => id > 0)
    : [];
  channelForm.team_ids = [...existing];
}

async function assignChannel() {
  try {
    if (!channelForm.device_id) {
      flash(false, "Pilih device / nomor dulu");
      return;
    }
    const teamIds = (channelForm.team_ids || []).map((v) => Number(v)).filter((id) => id > 0);
    if (!teamIds.length) {
      flash(false, "Select at least one team");
      return;
    }
    const res = await api("/WaDesk/Channels/assign", {
      method: "POST",
      body: {
        device_id: channelForm.device_id,
        label: channelForm.label,
        team_ids: teamIds,
      },
    });
    Object.assign(channelForm, { device_id: "", label: "", team_ids: [] });
    deviceQuery.value = "";
    flash(true, res.message || (res.data?.merged ? "Team ditambahkan ke nomor" : "Channel di-assign"));
    await loadChannelBrowse(true);
    await syncDevicesFromKirimin();
  } catch (e) {
    flash(false, e.message);
  }
}

function startEditKey(k) {
  editingKeyId.value = k.id;
  loadAssignTeams(true);
  const primaryId = Number(k.team_id) || 0;
  const extraIds = Array.isArray(k.team_ids) ? k.team_ids.map((v) => Number(v)).filter((id) => id > 0) : [];
  const teamIds = [...new Set([primaryId, ...extraIds].filter((id) => id > 0))];
  Object.assign(editKeyForm, {
    label: k.label || "",
    team_ids: teamIds,
  });
}

function cancelEditKey() {
  editingKeyId.value = null;
  savingKey.value = false;
}

async function saveKey(k) {
  savingKey.value = true;
  try {
    const teamIds = (editKeyForm.team_ids || []).map((v) => Number(v)).filter((id) => id > 0);
    if (!teamIds.length) {
      flash(false, "Pilih minimal satu team");
      return;
    }
    const body = {
      id: k.id,
      label: editKeyForm.label,
      team_ids: teamIds,
    };
    await api("/WaDesk/Channels/update", { method: "POST", body });
    flash(true, "Channel updated");
    cancelEditKey();
    await loadChannelBrowse(true);
    await syncDevicesFromKirimin();
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingKey.value = false;
  }
}

async function setChannelTemplateSending(channel, enabled) {
  savingTemplateSendChannelId.value = channel.id;
  try {
    await api("/WaDesk/Channels/update", {
      method: "POST",
      body: { id: channel.id, template_sending_enabled: enabled },
    });
    channel.template_sending_enabled = enabled ? 1 : 0;
    flash(true, enabled ? "Template sending enabled" : "Template sending disabled");
  } catch (e) {
    flash(false, e.message || "Failed to save template sending setting");
  } finally {
    savingTemplateSendChannelId.value = null;
  }
}

async function removeKey(id) {
  askConfirm({
    title: "Delete channel",
    message: "The deleted channel mapping cannot be restored. Continue?",
    action: async () => {
      try {
        await api("/WaDesk/Channels/delete", { method: "POST", body: { id } });
        flash(true, "Channel deleted");
        await loadChannelBrowse(true);
        await syncDevicesFromKirimin();
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
    await loadTemplateBrowse(true);
    expandedTemplate.value = t.id;
  } catch (e) {
    flash(false, e.message);
  } finally {
    savingMaxlengthId.value = null;
  }
}

async function loadWabas() {
  try {
    const res = await api("/WaDesk/Wabas/list", { cache: "no-store" });
    wabas.value = res.data?.wabas || [];
    const selectedStillExists = wabas.value.some((waba) => waba.meta_waba_id === templateWabaFilter.value);
    if (templateWabaFilter.value && !selectedStillExists) templateWabaFilter.value = "";
    const numberSelectedStillExists = wabas.value.some((waba) => waba.meta_waba_id === numberWabaFilter.value);
    if (numberWabaFilter.value && !numberSelectedStillExists) numberWabaFilter.value = "";
  } catch (e) {
    wabas.value = [];
    flash(false, e.message || "Gagal memuat WABA");
  }
}

async function loadNumbers() {
  const wabaId = numberWabaFilter.value.trim();
  if (!wabaId) {
    numbers.value = [];
    return;
  }
  loadingNumbers.value = true;
  try {
    const res = await api("/WaDesk/Channels/list?scope=all", { cache: "no-store" });
    numbers.value = (res.data?.channels || res.data?.keys || []).filter((channel) =>
      String(channel.provider || "").toLowerCase() === "meta" && String(channel.waba_id || "") === wabaId
    );
  } catch (e) {
    numbers.value = [];
    flash(false, e.message || "Gagal memuat nomor");
  } finally {
    loadingNumbers.value = false;
  }
}

function openAddNumber() {
  const wabaId = numberWabaFilter.value || wabas.value[0]?.meta_waba_id || "";
  Object.assign(numberForm, { waba_id: wabaId, country_code: "62", phone_number: "", verified_name: "", method: "SMS", otp: "" });
  resetNumberOtp();
  Object.assign(numberFlow, { step: "add", phone_number_id: "", loading: false, error: "" });
  addingNumber.value = true;
}

function normalizeAddPhone() {
  let value = String(numberForm.phone_number || "").replace(/\D/g, "");
  if (value.startsWith("628")) value = value.slice(2);
  else if (value.startsWith("08")) value = value.slice(1);
  numberForm.phone_number = value;
}

function continueNumberRegistration(number) {
  Object.assign(numberForm, { waba_id: number.waba_id || "", country_code: "62", phone_number: number.phone_number || "", verified_name: "", method: "SMS", otp: "" });
  const verified = String(number.meta_verification_status || "").toUpperCase().startsWith("VERIFIED");
  resetNumberOtp();
  Object.assign(numberFlow, { step: verified ? "register" : "request", phone_number_id: number.meta_phone_number_id || number.device_id, loading: false, error: "" });
  addingNumber.value = true;
}

async function addNumber() {
  numberFlow.loading = true;
  try {
    const res = await api("/WaDesk/Wabas/addNumber", { method: "POST", body: numberForm });
    const phoneId = String(res.data?.phone_number_id || "");
    if (!phoneId) throw new Error("Meta tidak mengembalikan Phone Number ID");
    numberFlow.phone_number_id = phoneId;
    numberFlow.step = "request";
    flash(true, "Nomor ditambahkan. Request OTP untuk melanjutkan.");
  } catch (e) {
    if (e.data?.code === "number_limit_reached") {
      numberLimitModal.current = Number(e.data.current_count || numberStats.value.total || 0);
      const hit = wabas.value.find((w) => String(w.meta_waba_id) === String(numberForm.waba_id));
      numberLimitModal.wabaName = hit?.name || numberForm.waba_id;
      numberLimitModal.open = true;
      addingNumber.value = false;
    } else {
      flash(false, e.message || "Gagal menambah nomor");
    }
  } finally { numberFlow.loading = false; }
}

async function requestOtp() {
  if (numberFlow.otpCooldown > 0) return;
  if (numberFlow.otpRequested && !window.confirm("OTP sebelumnya akan hangus. Lanjutkan meminta OTP baru?")) return;
  numberFlow.loading = true;
  numberFlow.error = "";
  try {
    const res = await api("/WaDesk/Wabas/requestOtp", { method: "POST", body: { phone_number_id: numberFlow.phone_number_id, method: numberForm.method } });
    numberFlow.otpCooldown = Number(res.data?.retry_after || 60);
    numberFlow.otpRequested = true;
    startNumberOtpTimer();
    numberFlow.step = "verify";
    flash(true, "OTP dikirim.");
  } catch (e) { applyNumberOtpRetry(e); numberFlow.error = otpErrorMessage(e); } finally { numberFlow.loading = false; }
}

async function verifyOtp() {
  if (numberFlow.otpLocked > 0) return;
  numberFlow.loading = true;
  numberFlow.error = "";
  try {
    await api("/WaDesk/Wabas/verifyOtp", { method: "POST", body: { phone_number_id: numberFlow.phone_number_id, code: numberForm.otp } });
    numberFlow.step = "register";
    numberFlow.otpVerifyFails = 0;
    flash(true, "OTP terverifikasi.");
  } catch (e) {
    applyNumberOtpRetry(e);
    numberFlow.otpVerifyFails = Math.min(3, numberFlow.otpVerifyFails + 1);
    if (numberFlow.otpVerifyFails >= 3 && !numberFlow.otpLocked) {
      numberFlow.otpLocked = 600;
      startNumberOtpTimer();
    }
    numberFlow.error = otpErrorMessage(e);
  } finally { numberFlow.loading = false; }
}

function resetNumberOtp() {
  Object.assign(numberFlow, { otpCooldown: 0, otpLocked: 0, otpVerifyFails: 0, otpRequested: false });
}

function startNumberOtpTimer() {
  if (numberOtpTimer) return;
  numberOtpTimer = setInterval(() => {
    if (numberFlow.otpCooldown > 0) numberFlow.otpCooldown--;
    if (numberFlow.otpLocked > 0) numberFlow.otpLocked--;
    if (!numberFlow.otpCooldown && !numberFlow.otpLocked) {
      clearInterval(numberOtpTimer);
      numberOtpTimer = null;
    }
  }, 1000);
}

function applyNumberOtpRetry(e) {
  const seconds = Number(e?.data?.retry_after || 0);
  if (!seconds) return;
  if (String(e?.data?.code || "").includes("request")) numberFlow.otpCooldown = Math.max(numberFlow.otpCooldown, seconds);
  else numberFlow.otpLocked = Math.max(numberFlow.otpLocked, seconds);
  startNumberOtpTimer();
}

function otpErrorMessage(e) {
  const raw = String(e?.message || "").toLowerCase();
  if (raw.includes("136025") || raw.includes("too many times") || raw.includes("terlalu banyak percobaan")) {
    return "Terlalu banyak percobaan verify — tunggu beberapa menit sebelum mencoba lagi.";
  }
  return e?.message || "OTP tidak valid";
}

function otpTimeLabel(seconds) {
  const safeSeconds = Math.max(0, Number(seconds) || 0);
  const minutes = Math.floor(safeSeconds / 60);
  const remainder = safeSeconds % 60;
  return minutes > 0 ? `${minutes} menit ${remainder} detik` : `${remainder} detik`;
}

async function registerNumber() {
  numberFlow.loading = true;
  try {
    await api("/WaDesk/Wabas/registerNumber", { method: "POST", body: { phone_number_id: numberFlow.phone_number_id } });
    numberFlow.step = "done";
    flash(true, "Registrasi berhasil dikirim ke Meta.");
  } catch (e) { flash(false, e.message || "Gagal register nomor"); } finally { numberFlow.loading = false; }
}

async function syncAfterRegistration() {
  await syncNumbers();
  addingNumber.value = false;
}

function openWabaTeamEditor(waba) {
  editingWabaTeamId.value = waba.id;
  wabaTeamDraft.value = (waba.team_ids || []).map(Number);
}

async function saveWabaTeams(waba) {
  savingWabaTeamId.value = waba.id;
  try {
    await api("/WaDesk/Wabas/assignTeams", {
      method: "POST",
      body: { waba_id: waba.id, team_ids: wabaTeamDraft.value.map(Number) },
    });
    editingWabaTeamId.value = null;
    flash(true, "Akses team untuk WABA disimpan");
    await Promise.all([loadWabas(), loadTemplateBrowse(true)]);
  } catch (e) {
    flash(false, e.message || "Gagal menyimpan team WABA");
  } finally {
    savingWabaTeamId.value = null;
  }
}

async function syncWabas() {
  if (syncingWabas.value) return;
  syncingWabas.value = true;
  try {
    const res = await api("/WaDesk/Wabas/sync", { method: "POST", body: {} });
    const d = res.data || {};
    const suffix = Array.isArray(d.errors) && d.errors.length ? ` · ${d.errors.join("; ")}` : "";
    const subscriptions = Number(d.subscriptions || 0);
    const subscriptionsSkipped = Number(d.subscriptions_skipped || 0);
    const subscriptionText = subscriptions
      ? `, ${subscriptions} subscription aplikasi baru aktif`
      : (subscriptionsSkipped ? `, ${subscriptionsSkipped} subscription sudah aktif` : "");
    const removed = Number(d.templates_removed || 0);
    const removedText = removed ? `, ${removed} template lama dihapus` : "";
    const channelsRemoved = Number(d.channels_removed || 0);
    const channelsRemovedText = channelsRemoved ? `, ${channelsRemoved} channel lama dihapus` : "";
    flash(true, `Sync WABA: ${d.wabas || 0} WABA${subscriptionText}${removedText}${channelsRemovedText}${suffix}`);
    await Promise.all([loadWabas(), loadChannelBrowse(true), loadTemplateBrowse(true), loadNumbers()]);
  } catch (e) {
    flash(false, e.message || "Gagal sinkron WABA");
  } finally {
    syncingWabas.value = false;
  }
}

/** Force re-subscribe the Meta app to the WABA even if already marked subscribed. */
async function resetSubscription() {
  if (resubscribing.value || syncingWabas.value) return;
  resubscribing.value = true;
  try {
    const res = await api("/WaDesk/Wabas/sync", { method: "POST", body: { force_resubscribe: true } });
    const d = res.data || {};
    const suffix = Array.isArray(d.errors) && d.errors.length ? ` · ${d.errors.join("; ")}` : "";
    flash(true, `Reset sub WABA: ${d.subscriptions || 0} subscription diaktifkan ulang${suffix}`);
    await Promise.all([loadWabas(), loadChannelBrowse(true), loadTemplateBrowse(true), loadNumbers()]);
  } catch (e) {
    flash(false, e.message || "Gagal reset subscription WABA");
  } finally {
    resubscribing.value = false;
  }
}

async function syncNumbers() {
  const wabaId = numberWabaFilter.value.trim();
  if (!wabaId || syncingNumbers.value) return;
  syncingNumbers.value = true;
  try {
    const res = await api('/WaDesk/Wabas/syncNumbers', { method: 'POST', body: { waba_id: wabaId } });
    const d = res.data || {};
    const suffix = Array.isArray(d.errors) && d.errors.length ? ` · ${d.errors.join('; ')}` : '';
    const removed = Number(d.phones_removed || 0);
    flash(true, `Sync Number: ${d.phones || 0} nomor${removed ? ` · ${removed} nomor lokal lama dihapus` : ''}${suffix}`);
    await loadNumbers();
  } catch (e) { flash(false, e.message || 'Gagal sync nomor'); } finally { syncingNumbers.value = false; }
}

async function syncTemplates() {
  const wabaId = templateWabaFilter.value.trim();
  if (!wabaId || syncingTemplates.value) return;
  syncingTemplates.value = true;
  try {
    const res = await api('/WaDesk/Wabas/syncTemplates', { method: 'POST', body: { waba_id: wabaId } });
    const d = res.data || {};
    const suffix = Array.isArray(d.errors) && d.errors.length ? ` · ${d.errors.join('; ')}` : '';
    flash(true, `Sync Template: ${d.templates || 0} template${suffix}`);
    await loadTemplateBrowse(true);
  } catch (e) { flash(false, e.message || 'Gagal sync template'); } finally { syncingTemplates.value = false; }
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
    await loadTemplateBrowse(true);
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
    await loadQuotaBrowse(true);
  } catch (e) {
    flash(false, e.message);
  }
}

async function onLogout() {
  await auth.logout();
  router.push({ name: "login" });
}

onMounted(refresh);
onUnmounted(() => {
  teamBrowseObserver?.disconnect();
  channelBrowseObserver?.disconnect();
  assignTeamObserver?.disconnect();
  userBrowseObserver?.disconnect();
  quotaBrowseObserver?.disconnect();
  templateBrowseObserver?.disconnect();
  failLogsObserver?.disconnect();
  devFeeObserver?.disconnect();
  clearTimeout(teamSearchTimer);
  clearTimeout(channelSearchTimer);
  clearTimeout(assignTeamSearchTimer);
  clearTimeout(userSearchTimer);
  clearTimeout(quotaSearchTimer);
  clearTimeout(templateSearchTimer);
  clearInterval(numberOtpTimer);
});
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
.btn-danger {
  @apply px-4 py-2.5 rounded-xl bg-rose-600 font-medium text-sm text-white hover:bg-rose-500 transition disabled:opacity-50;
}
.btn-sm {
  @apply px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-200 text-sm transition disabled:opacity-50;
}
</style>
