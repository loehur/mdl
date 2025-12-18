<template>
  <div class="max-w-3xl mx-auto space-y-6 pb-12 px-4 md:px-0">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
      <router-link to="/order" class="hover:text-pink-600 transition">Beranda</router-link>
      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      <span class="text-gray-900 font-medium tracking-wide">Profil Akun</span>
    </div>

    <!-- Profile Hero Card -->
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-10">
      <!-- Decorative Background Elements -->
      <div class="absolute -top-24 -right-24 w-64 h-64 bg-pink-50 rounded-full blur-3xl opacity-60"></div>
      <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-purple-50 rounded-full blur-3xl opacity-60"></div>
      
      <div class="relative flex flex-col md:flex-row items-center gap-8">
        <div class="relative group">
          <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-gradient-to-tr from-pink-500 via-fuchsia-500 to-purple-600 p-1 shadow-2xl shadow-pink-100 ring-4 ring-white transition-transform duration-500 group-hover:scale-105">
            <div class="w-full h-full rounded-full bg-white flex items-center justify-center text-pink-600 font-black text-3xl md:text-5xl uppercase">
              {{ userInitial }}
            </div>
          </div>
          <div class="absolute bottom-2 right-2 w-7 h-7 bg-green-500 border-4 border-white rounded-full shadow-lg" title="Status: Aktif"></div>
        </div>
        
        <div class="text-center md:text-left flex-1">
          <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 mb-2">
            <h1 class="text-2xl md:text-4xl font-black text-gray-900 tracking-tight leading-none">{{ userInfo.name }}</h1>
            <span class="inline-flex items-center px-3 py-1 bg-pink-100 text-pink-700 rounded-lg text-[10px] font-black uppercase tracking-widest self-center md:self-auto shadow-sm">
               {{ userInfo.role === 'admin' ? '⚜️ ' + userInfo.role : '💼 ' + userInfo.role }}
            </span>
          </div>
          <div class="flex flex-col md:flex-row gap-2 md:gap-6 text-gray-500 text-sm font-medium">
            <div class="flex items-center justify-center md:justify-start gap-2">
              <svg class="w-4 h-4 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
              {{ userInfo.phone || 'N/A' }}
            </div>
            <div class="flex items-center justify-center md:justify-start gap-2">
              <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
              {{ salonName }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Account Info Sidebar -->
      <div class="lg:col-span-1 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden group">
           <div class="absolute top-0 right-0 w-24 h-24 bg-gray-50 rounded-bl-full opacity-50 transition-all group-hover:bg-pink-50"></div>
           <h3 class="relative text-xs font-bold text-gray-400 uppercase tracking-[0.2em] mb-6">Informasi Akun</h3>
           <div class="relative space-y-5">
              <div class="flex flex-col gap-1">
                 <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">ID Member</span>
                 <span class="font-mono text-sm text-gray-700 bg-gray-50 px-2 py-1 rounded-lg border border-gray-100 flex items-center justify-between">
                    <span>USR-{{ userId }}</span>
                    <button class="text-pink-500 hover:text-pink-700">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    </button>
                 </span>
              </div>
              <div class="pt-4 border-t border-gray-50">
                 <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                       <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.64.304 1.25.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    </div>
                    <div>
                       <p class="text-gray-900 font-bold text-sm">Status Akun</p>
                       <p class="text-xs text-gray-500 font-medium tracking-tight">Terverifikasi & Aman</p>
                    </div>
                 </div>
              </div>
           </div>
        </div>
      </div>

      <!-- Password Update Section -->
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-10 relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-gray-50/50 rounded-full -translate-y-1/2 translate-x-1/2 -z-10 group-hover:bg-pink-50/30 transition-colors duration-500"></div>
          
          <div class="flex items-center justify-between mb-10">
            <div>
              <h2 class="text-2xl font-black text-gray-900 tracking-tight mb-1">Update Keamanan</h2>
              <p class="text-sm font-medium text-gray-500">Ubah kata sandi secara berkala</p>
            </div>
            <div class="w-14 h-14 bg-pink-50 text-pink-600 rounded-2xl flex items-center justify-center transform transition group-hover:rotate-12">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
          </div>

          <form @submit.prevent="changePassword" class="space-y-8">
            <div class="space-y-6">
              <!-- Old Password -->
              <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-3">Kata Sandi Saat Ini</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-pink-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                  </div>
                  <input 
                    v-model="passwordForm.oldPassword" 
                    :type="showOldPassword ? 'text' : 'password'"
                    required
                    placeholder="Wajib diisi"
                    class="w-full bg-gray-50/50 border border-gray-200 rounded-xl pl-12 pr-12 py-4 outline-none focus:bg-white focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 transition-all font-semibold placeholder:text-gray-300"
                  >
                  <button 
                    type="button" 
                    @click="showOldPassword = !showOldPassword"
                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-300 hover:text-pink-600 transition"
                  >
                    <svg v-if="!showOldPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                  </button>
                </div>
              </div>

              <!-- New Password Grid -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-3">Sandi Baru</label>
                  <input 
                    v-model="passwordForm.newPassword" 
                    :type="showNewPassword ? 'text' : 'password'"
                    required
                    minlength="6"
                    placeholder="Min. 6 Karakter"
                    class="w-full bg-gray-50/50 border border-gray-200 rounded-xl px-4 py-4 outline-none focus:bg-white focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 transition-all font-semibold placeholder:text-gray-300"
                  >
                </div>
                <div>
                  <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-3">Konfirmasi</label>
                  <input 
                    v-model="passwordForm.confirmPassword" 
                    :type="showNewPassword ? 'text' : 'password'"
                    required
                    placeholder="Ulangi sandi baru"
                    class="w-full bg-gray-50/50 border border-gray-200 rounded-xl px-4 py-4 outline-none focus:bg-white focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 transition-all font-semibold placeholder:text-gray-300"
                  >
                </div>
              </div>
            </div>

            <!-- Error Banner -->
            <transition name="slide-fade">
              <div v-if="passwordError" class="p-4 bg-rose-50 border border-rose-100 rounded-2xl flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-white border border-rose-100 flex items-center justify-center flex-shrink-0 shadow-sm">
                   <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div class="pt-1">
                  <p class="text-xs font-black text-rose-700 uppercase tracking-wider mb-0.5">Validasi Gagal</p>
                  <p class="text-[11px] text-rose-500 font-bold">{{ passwordError }}</p>
                </div>
              </div>
            </transition>

            <button 
              type="submit" 
              :disabled="loading"
              class="relative w-full group overflow-hidden bg-slate-950 py-5 rounded-2xl transition-all duration-300 hover:shadow-2xl hover:shadow-pink-500/20 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <div class="absolute inset-0 bg-gradient-to-r from-pink-600 via-purple-600 to-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
              <div class="relative flex items-center justify-center gap-3">
                <svg v-if="!loading" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                <svg v-else class="w-5 h-5 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span class="text-white font-black text-xs uppercase tracking-[0.2em] pt-0.5">{{ loading ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
              </div>
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Premium Toast Notifications -->
    <Teleport to="body">
      <transition 
        enter-active-class="transform transition ease-out duration-500"
        enter-from-class="translate-y-full opacity-0 scale-90"
        enter-to-class="translate-y-0 opacity-100 scale-100"
        leave-active-class="transform transition ease-in duration-300"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-4"
      >
        <div v-if="toast.show" class="fixed bottom-10 right-0 left-0 md:left-auto md:right-10 z-[10000] px-4 md:px-0">
          <div class="bg-slate-900/95 backdrop-blur-xl rounded-[2rem] shadow-2xl p-2 pr-6 border border-white/10 max-w-sm mx-auto flex items-center gap-4">
             <div class="w-12 h-12 rounded-full flex items-center justify-center shadow-inner" :class="toast.type === 'success' ? 'bg-emerald-500 text-white shadow-emerald-400/20' : 'bg-rose-500 text-white shadow-rose-400/20'">
                <svg v-if="toast.type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
             </div>
             <div class="flex-1">
                <p class="text-[11px] font-black text-white/50 uppercase tracking-widest leading-none mb-1">Pemberitahuan</p>
                <p class="text-sm font-bold text-white tracking-tight">{{ toast.message }}</p>
             </div>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';

const userInfo = reactive({ name: '', phone: '', role: '' });
const userId = ref('...');
const salonName = ref('...');

const passwordForm = reactive({ oldPassword: '', newPassword: '', confirmPassword: '' });
const showOldPassword = ref(false);
const showNewPassword = ref(false);
const loading = ref(false);
const passwordError = ref('');

const toast = reactive({ show: false, message: '', type: 'success' });

const userInitial = computed(() => {
  if (!userInfo.name) return '?';
  const names = userInfo.name.trim().split(' ');
  return names[0].charAt(0).toUpperCase() + (names.length > 1 ? names[names.length - 1].charAt(0).toUpperCase() : '');
});

function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => { toast.show = false; }, 4000);
}

async function loadUserInfo() {
  try {
    const sessionStr = localStorage.getItem('salon_user_session');
    const localUserStr = localStorage.getItem('salon_user');
    
    const session = sessionStr ? JSON.parse(sessionStr) : {};
    const local = localUserStr ? JSON.parse(localUserStr) : {};
    
    const u = session.user || local;
    
    if (u) {
      userInfo.name = u.name || 'Pengguna';
      userInfo.phone = u.phone_number || '';
      userInfo.role = u.role || 'Member';
      userId.value = u.id || '-';
      salonName.value = u.salon_name || 'My Salon';
    }
  } catch (error) {
    console.error('Error loading user info:', error);
  }
}

async function changePassword() {
  passwordError.value = '';
  
  if (passwordForm.newPassword.length < 6) {
    passwordError.value = 'Password baru terlalu pendek (min. 6 karakter)';
    return;
  }
  
  if (passwordForm.newPassword !== passwordForm.confirmPassword) {
    passwordError.value = 'Konfirmasi password baru tidak sesuai';
    return;
  }
  
  loading.value = true;
  
  try {
    const res = await fetch('/api/Beauty_Salon/Users/changePassword', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        old_password: passwordForm.oldPassword,
        new_password: passwordForm.newPassword
      })
    });
    
    const data = await res.json();
    
    if (data.success) {
      showToast('Sandi berhasil diperbarui secara aman.');
      passwordForm.oldPassword = '';
      passwordForm.newPassword = '';
      passwordForm.confirmPassword = '';
    } else {
      passwordError.value = data.message || 'Gagal mengubah password';
    }
  } catch (error) {
    passwordError.value = 'Komunikasi server gagal. Periksa koneksi Anda.';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadUserInfo();
});
</script>

<style scoped>
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.3s cubic-bezier(1, 0.5, 0.8, 1);
}
.slide-fade-enter-from, .slide-fade-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}
</style>
