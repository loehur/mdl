<template>
  <AppHeader page-title="Numbers" active="numbers" @logout="logout">
    <div class="flex-1 overflow-y-auto"><div class="max-w-5xl mx-auto p-4 space-y-4">
      <section v-if="auth.user?.role === 'agent'" class="rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">Menu ini tidak tersedia untuk Agent.</section>
      <template v-else>
        <section class="rounded-2xl border border-white/10 bg-ink-900/50 p-4 space-y-3">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="font-display font-semibold text-lg">Nomor WhatsApp</h2>
              <p class="mt-1 text-sm text-slate-500">{{ auth.isAdmin ? 'Pilih WABA yang ingin dikelola.' : `WABA: ${waba.name || 'Memuat...'}. WABA ini terkunci untuk team Anda.` }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="btn" :disabled="syncing || !waba.id" @click="sync">{{ syncing ? 'Sync...' : 'Sync nomor' }}</button>
              <button type="button" class="btn" :disabled="!waba.id" @click="openAdd">Add Number</button>
            </div>
          </div>
          <select v-if="auth.isAdmin" v-model="waba.id" class="field" @change="selectAdminWaba"><option disabled value="">Pilih WABA</option><option v-for="item in wabas" :key="item.id" :value="item.meta_waba_id">{{ item.name }}</option></select>
          <p v-if="error" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm text-rose-400">{{ error }}</p>
        </section>

        <div class="flex flex-col gap-4">
          <Teleport to="body">
            <div v-if="limitModal.open" class="fixed inset-0 z-[90] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" role="dialog" aria-modal="true" @click.self="limitModal.open=false">
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
                    WABA <strong class="text-slate-100">{{ waba.name || waba.id }}</strong> sudah memiliki
                    <strong class="text-amber-300">{{ limitModal.current }} nomor</strong>.
                    Tambahkan nomor baru setelah menghapus nomor yang tidak terpakai, atau gunakan WABA lain.
                  </p>
                  <div class="mt-5 flex justify-center">
                    <div class="w-full max-w-xs">
                      <div class="flex justify-between text-[11px] text-slate-500"><span>Terpakai</span><span>{{ limitModal.current }} / 20</span></div>
                      <div class="mt-1 h-2.5 overflow-hidden rounded-full bg-white/5">
                        <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-rose-500" :style="{ width: Math.min(100, (limitModal.current / 20) * 100) + '%' }" />
                      </div>
                    </div>
                  </div>
                  <button type="button" class="btn mt-6 w-full" @click="limitModal.open=false">Mengerti</button>
                </div>
              </div>
            </div>
          </Teleport>
          <Teleport to="body">
          <div v-if="showAdd" class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center bg-black/60 p-0 sm:p-4 backdrop-blur-sm" @click.self="showAdd=false">
              <div class="w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl border border-white/10 bg-ink-900 p-4 space-y-3 shadow-2xl">
                <div class="flex items-center justify-between gap-3"><p class="font-medium">Add Number</p><button type="button" class="text-xs text-slate-400" @click="showAdd=false">Tutup</button></div>
                <template v-if="flow.step==='add'">
                  <p class="text-xs text-slate-500">Verified name mengikuti nama WABA: {{ waba.name }}</p>
                  <div class="flex gap-2"><input value="62" readonly tabindex="-1" class="field bg-ink-950/50 text-slate-400 cursor-not-allowed" style="width:4rem;flex:0 0 4rem" aria-label="Country code Indonesia" /><input v-model="form.phone" class="field flex-1" style="min-width:0" placeholder="8xxxxxxxxxx" @input="normalize" /></div>
                  <button type="button" class="btn" :disabled="flow.loading" @click="add">{{ flow.loading ? 'Memproses...' : 'Tambah nomor' }}</button>
                </template>
                <template v-else>
                  <p class="text-xs text-slate-400">Phone Number ID: <span class="font-mono text-accent">{{ flow.phoneId }}</span></p>
                  <p v-if="flow.error" class="rounded-xl border-2 border-rose-500 bg-rose-100 px-3 py-2 text-sm font-medium !text-rose-950">{{ flow.error }}<span v-if="otp.locked > 0"> Sisa waktu tunggu: {{ timeLabel(otp.locked) }}.</span></p>
                  <template v-if="flow.step==='request'">
                    <select v-model="form.method" class="field"><option value="SMS">SMS</option><option value="VOICE">Voice call</option></select>
                    <div class="flex gap-2">
                      <button type="button" class="btn" :disabled="flow.loading || otp.cooldown > 0 || otp.locked > 0" @click="requestOtp">{{ otp.locked > 0 ? `Tunggu ${timeLabel(otp.locked)}` : (flow.loading ? 'Meminta OTP...' : requestLabel) }}</button>
                      <button type="button" class="btn ml-auto" :disabled="flow.loading || otp.locked > 0" @click="form.otp = ''; flow.error = ''; flow.step = 'verify'">Sudah terima OTP</button>
                    </div>
                    <p class="text-xs text-slate-500">Pilih ini jika OTP sudah dikirim sebelum halaman direfresh.</p>
                  </template>
                  <template v-else-if="flow.step==='verify'">
                    <p v-if="otp.locked > 0" class="text-sm text-amber-300">Terlalu banyak percobaan verify. Tunggu {{ timeLabel(otp.locked) }} sebelum mencoba lagi.</p>
                    <template v-else>
                      <input v-model="form.otp" class="field" inputmode="numeric" placeholder="Masukkan kode OTP" />
                      <button type="button" class="btn" :disabled="flow.loading || !form.otp" @click="verify">Verify OTP</button>
                      <p class="text-xs text-slate-500">Sisa percobaan sesi ini: {{ Math.max(0, 3 - otp.verifyFails) }}.</p>
                    </template>
                    <div class="flex justify-end"><button type="button" class="btn" :disabled="flow.loading" @click="form.otp = ''; flow.error = ''; flow.step = 'request'">Kembali</button></div>
                  </template>
                  <template v-else-if="flow.step==='register'"><p class="text-xs text-emerald-400">OTP terverifikasi. Nomor siap diregistrasikan.</p><button type="button" class="btn" :disabled="flow.loading" @click="register">Register Number</button></template>
                  <template v-else-if="flow.step==='done'"><p class="text-sm text-emerald-400">Registrasi berhasil dikirim ke Meta.</p><button type="button" class="btn" :disabled="flow.loading" @click="flow.step='register'">OK</button></template>
                </template>
              </div>
            </div>
          </Teleport>
          <div v-if="flow.error" class="fixed inset-x-4 bottom-6 z-[80] mx-auto max-w-lg rounded-xl border-2 border-rose-500 bg-rose-100 px-4 py-3 text-sm font-medium !text-rose-950 shadow-2xl">{{ flow.error }}<span v-if="otp.locked > 0"> Sisa waktu tunggu: {{ timeLabel(otp.locked) }}.</span></div>
          <div class="grid grid-cols-3 gap-2">
            <div class="rounded-xl border border-white/10 bg-ink-950/40 px-3 py-2"><p class="text-[11px] text-slate-500">Total</p><p class="text-lg font-semibold">{{ numberStats.total }}</p></div>
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-3 py-2"><p class="text-[11px] text-emerald-300">Active</p><p class="text-lg font-semibold text-emerald-200">{{ numberStats.active }}</p></div>
            <div class="rounded-xl border border-slate-500/20 bg-slate-500/5 px-3 py-2"><p class="text-[11px] text-slate-400">Inactive</p><p class="text-lg font-semibold text-slate-200">{{ numberStats.inactive }}</p></div>
          </div>
          <section class="h-[34rem] max-h-[60vh] overflow-y-auto rounded-xl border border-white/10 divide-y divide-white/5">
            <p v-if="loading" class="py-10 text-center text-sm text-slate-500">Memuat nomor...</p>
            <p v-else-if="!numbers.length" class="py-10 text-center text-sm text-slate-500">Belum ada nomor pada WABA team ini.</p>
            <div v-for="n in numbers" :key="n.id" class="px-4 py-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-medium">{{ n.label || n.phone_number }}</p>
                  <p class="font-mono text-xs text-accent mt-1">+{{ n.phone_number }}</p>
                  <p class="text-[11px] text-slate-500 mt-1">Phone Number ID: {{ n.meta_phone_number_id }}</p>
                </div>
                <div class="flex flex-wrap gap-1.5 justify-end text-xs">
                  <span :title="'Status: ' + (n.status || 'unknown')" class="px-2 py-1 rounded" :class="String(n.status || '').toLowerCase() === 'active' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-white/5 text-slate-300'">{{ n.meta_provider_status || n.status || 'unknown' }}</span>
                  <span v-if="n.meta_verification_status" class="px-2 py-1 rounded bg-sky-500/10 text-sky-300">{{ n.meta_verification_status }}</span>
                  <span v-if="n.meta_display_name_status" class="px-2 py-1 rounded bg-violet-500/10 text-violet-300">Display: {{ n.meta_display_name_status }}</span>
                  <span v-if="n.meta_quality_rating" class="px-2 py-1 rounded" :class="qualityClass(n.meta_quality_rating)">Quality: {{ n.meta_quality_rating }}</span>
                  <button v-if="String(n.status).toLowerCase() !== 'active'" type="button" class="px-2 py-1 rounded bg-sky-500/10 text-sky-300 hover:bg-sky-500/20" @click="continueFlow(n)">{{ String(n.meta_verification_status || '').startsWith('VERIFIED') ? 'Register' : 'Request OTP' }}</button>
                </div>
              </div>
            </div>
          </section>
        </div>
      </template>
    </div></div>
  </AppHeader>
</template>
<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { api } from '../api';
import AppHeader from '../components/AppHeader.vue';
const auth=useAuthStore(), router=useRouter(), numbers=ref([]), wabas=ref([]), waba=reactive({id:'',name:''}), loading=ref(false), syncing=ref(false), error=ref(''), showAdd=ref(false);
const limitModal=reactive({open:false,current:0});
const form=reactive({phone:'',method:'SMS',otp:''}), flow=reactive({step:'add',phoneId:'',loading:false,error:''});
const otp=reactive({cooldown:0,locked:0,verifyFails:0,requested:false}); let otpTimer=null;
const numberStats=computed(()=>{const total=numbers.value.length,active=numbers.value.filter(n=>String(n.status||'').toLowerCase()==='active').length;return {total,active,inactive:total-active}});
const logout=async()=>{await auth.logout();router.push({name:'login'})}; const qualityClass=q=>({GREEN:'bg-emerald-500/15 text-emerald-300',YELLOW:'bg-amber-500/15 text-amber-300',RED:'bg-rose-500/15 text-rose-300'}[String(q).toUpperCase()]||'bg-white/5 text-slate-300');
const requestLabel=computed(()=>otp.cooldown>0?`Minta ulang (${otp.cooldown}s)`:(otp.requested?'Minta ulang OTP':'Request OTP'));
const timeLabel=s=>{const seconds=Math.max(0,Number(s)||0),minutes=Math.floor(seconds/60),remainder=seconds%60;return minutes>0?`${minutes} menit ${remainder} detik`:`${remainder} detik`};
function resetOtp(){Object.assign(otp,{cooldown:0,locked:0,verifyFails:0,requested:false})} function startTimer(){if(otpTimer)return;otpTimer=setInterval(()=>{if(otp.cooldown>0)otp.cooldown--;if(otp.locked>0)otp.locked--;if(!otp.cooldown&&!otp.locked){clearInterval(otpTimer);otpTimer=null}},1000)} function applyRetry(e){const seconds=Number(e?.data?.retry_after||0);if(seconds){if(String(e?.data?.code||'').includes('request'))otp.cooldown=Math.max(otp.cooldown,seconds);else otp.locked=Math.max(otp.locked,seconds);startTimer()}}
function rateMessage(e){const raw=String(e?.message||'').toLowerCase();return raw.includes('136025')||raw.includes('too many times')||raw.includes('terlalu banyak percobaan')?'Terlalu banyak percobaan verify — tunggu beberapa menit sebelum mencoba lagi.':(e?.message||'OTP tidak valid');}
async function load(){if(auth.user?.role==='agent')return;loading.value=true;error.value='';try{if(auth.isAdmin){const r=await api('/WaDesk/Wabas/list',{cache:'no-store'});wabas.value=r.data?.wabas||[];if(!waba.id&&wabas.value[0]){waba.id=wabas.value[0].meta_waba_id;waba.name=wabas.value[0].name}const c=await api('/WaDesk/Channels/list?scope=all',{cache:'no-store'});numbers.value=(c.data?.channels||[]).filter(n=>String(n.provider).toLowerCase()==='meta'&&String(n.waba_id)===String(waba.id))}else{const r=await api('/WaDesk/Wabas/teamNumbers',{cache:'no-store'});numbers.value=r.data?.numbers||[];Object.assign(waba,r.data?.waba||{})}}catch(e){error.value=e.message}finally{loading.value=false}}
function selectAdminWaba(){const hit=wabas.value.find(x=>String(x.meta_waba_id)===String(waba.id));waba.name=hit?.name||'';load()} async function sync(){if(!waba.id)return;syncing.value=true;error.value='';try{if(auth.isAdmin)await api('/WaDesk/Wabas/syncNumbers',{method:'POST',body:{waba_id:waba.id}});else await api('/WaDesk/Wabas/syncNumbersForTeam',{method:'POST',body:{}});await load()}catch(e){error.value=e.message}finally{syncing.value=false}}
function openAdd(){Object.assign(form,{phone:'',method:'SMS',otp:''});Object.assign(flow,{step:'add',phoneId:'',loading:false,error:''});resetOtp();showAdd.value=true} function normalize(){let v=String(form.phone).replace(/\D/g,'');if(v.startsWith('628'))v=v.slice(2);else if(v.startsWith('08'))v=v.slice(1);form.phone=v} function continueFlow(n){flow.phoneId=n.meta_phone_number_id;flow.step=String(n.meta_verification_status||'').toUpperCase().startsWith('VERIFIED')?'register':'request';form.otp='';flow.error='';resetOtp();showAdd.value=true}
async function add(){flow.loading=true;try{const r=await api('/WaDesk/Wabas/addNumber',{method:'POST',body:{phone_number:form.phone,waba_id:waba.id}});flow.phoneId=String(r.data?.phone_number_id||'');if(!flow.phoneId)throw new Error('Meta tidak mengembalikan Phone Number ID');flow.step='request'}catch(e){if(e.data?.code==='number_limit_reached'){limitModal.current=Number(e.data.current_count||numbers.value.length||0);limitModal.open=true;showAdd.value=false}else error.value=e.message}finally{flow.loading=false}}
async function requestOtp(){if(otp.cooldown>0||otp.locked>0)return;if(otp.requested&&!window.confirm('OTP sebelumnya akan hangus. Lanjutkan meminta OTP baru?'))return;flow.loading=true;flow.error='';try{const r=await api('/WaDesk/Wabas/requestOtp',{method:'POST',body:{phone_number_id:flow.phoneId,method:form.method}});otp.cooldown=Number(r.data?.retry_after||60);otp.requested=true;startTimer();flow.step='verify'}catch(e){applyRetry(e);flow.error=rateMessage(e)}finally{flow.loading=false}}
async function verify(){if(otp.locked>0)return;flow.loading=true;flow.error='';try{await api('/WaDesk/Wabas/verifyOtp',{method:'POST',body:{phone_number_id:flow.phoneId,code:form.otp}});flow.step='register';otp.verifyFails=0}catch(e){applyRetry(e);otp.verifyFails=Math.min(3,otp.verifyFails+1);if(otp.verifyFails>=3&&!otp.locked){otp.locked=600;startTimer()}flow.error=rateMessage(e)}finally{flow.loading=false}}
async function register(){flow.loading=true;try{await api('/WaDesk/Wabas/registerNumber',{method:'POST',body:{phone_number_id:flow.phoneId}});flow.step='done'}catch(e){error.value=e.message}finally{flow.loading=false}} onMounted(load);onUnmounted(()=>clearInterval(otpTimer));
</script>
<style scoped>
.field {
  @apply w-full rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-accent/40;
}
.btn {
  @apply px-4 py-2.5 rounded-xl bg-accent font-medium text-sm hover:bg-accent-soft transition disabled:opacity-50;
}
</style>
