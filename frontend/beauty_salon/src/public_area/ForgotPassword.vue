<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-rose-100 via-pink-50 to-fuchsia-100 relative overflow-hidden">
    <!-- Decorative background elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-80 h-80 bg-pink-300/30 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-fuchsia-300/30 rounded-full blur-3xl"></div>
    </div>

    <div class="w-full max-w-md px-6 py-8 relative z-10 font-sans">
      <!-- Logo/Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-br from-pink-500 to-fuchsia-600 shadow-xl mb-4">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
          </svg>
        </div>
        <h1 class="text-3xl font-black bg-gradient-to-r from-pink-600 to-fuchsia-600 bg-clip-text text-transparent mb-1">Reset Password</h1>
        <p class="text-gray-500 text-sm font-medium">Pulihkan akses akun Anda</p>
      </div>

      <!-- Forgot Password Card -->
      <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl p-8 border border-white/50">
        
        <!-- Step 1: Input Phone -->
        <div v-if="step === 1">
          <h2 class="text-xl font-black text-gray-800 mb-6 tracking-tight">Cari Akun</h2>
          <form class="space-y-5" @submit.prevent="requestOtp">
            <div>
              <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Email Address</label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-pink-500">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <input v-model="email" class="w-full pl-12 pr-4 py-4 border border-gray-100 rounded-2xl focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all bg-white/50 font-semibold" type="email" placeholder="contoh@email.com" required />
              </div>
            </div>

            <button class="w-full py-4 rounded-2xl bg-slate-900 group hover:bg-slate-800 text-white font-black text-xs uppercase tracking-[0.2em] shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-3 relative overflow-hidden" type="submit" :disabled="isLoading">
              <div class="absolute inset-0 bg-gradient-to-r from-pink-600 to-purple-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
              <svg v-if="isLoading" class="h-5 w-5 animate-spin relative z-10" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
              </svg>
              <span class="relative z-10">{{ isLoading ? 'Mengirim...' : 'Kirim Kode OTP' }}</span>
            </button>
            <router-link to="/login" class="block text-center text-xs font-bold text-gray-400 hover:text-gray-600 transition pt-2">← Kembali ke Login</router-link>
          </form>
        </div>

        <!-- Step 2: Input OTP & New Password -->
        <div v-if="step === 2">
          <div class="text-center mb-6">
            <h2 class="text-xl font-black text-gray-800 mb-2 tracking-tight">Verifikasi & Reset</h2>
            <p class="text-xs text-gray-500 font-medium leading-relaxed">Masukkan kode OTP yang dikirim ke<br><span class="font-bold text-pink-600 tracking-wider">{{ email }}</span></p>
          </div>

          <form class="space-y-4" @submit.prevent="resetPassword">
            <div>
              <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Kode OTP (6 Digit)</label>
              <input v-model="otp" autocomplete="one-time-code" class="w-full px-4 py-4 border border-gray-100 rounded-2x text-center text-2xl tracking-[0.3em] font-mono focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all bg-white/50 font-black placeholder:text-gray-200" type="text" maxlength="6" placeholder="      " required />
            </div>

            <div>
              <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Password Baru (Min. 6 Karakter)</label>
              <input v-model="newPassword" class="w-full px-4 py-4 border border-gray-100 rounded-2xl focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all bg-white/50 font-semibold" type="password" placeholder="••••••••" required minlength="6" />
            </div>

            <button class="w-full py-4 rounded-2xl bg-slate-900 group hover:bg-slate-800 text-white font-black text-xs uppercase tracking-[0.2em] shadow-xl transition-all disabled:opacity-50 flex items-center justify-center gap-3 relative overflow-hidden" type="submit" :disabled="isLoading">
              <div class="absolute inset-0 bg-gradient-to-r from-pink-600 to-purple-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
              <svg v-if="isLoading" class="h-5 w-5 animate-spin relative z-10" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
              </svg>
              <span class="relative z-10 font-bold uppercase tracking-widest text-xs pt-0.5">{{ isLoading ? 'Memproses...' : 'Simpan & Masuk' }}</span>
            </button>

            <button type="button" @click="step = 1" class="w-full text-xs font-bold text-gray-400 hover:text-pink-600 transition mt-4">Ganti Email</button>
          </form>
        </div>

        <!-- Alert Message -->
        <transition name="fade">
          <div v-if="message" class="mt-8 p-4 rounded-2xl text-xs font-bold flex items-center gap-3" :class="isError ? 'bg-red-50 text-red-600 border border-red-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-100'">
            <svg v-if="isError" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <svg v-else class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ message }}</span>
          </div>
        </transition>
      </div>

      <!-- Footer -->
      <p class="text-center text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-12">© 2024 Beauty Salon. Cloud Secured.</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();
const email = ref("");
const otp = ref("");
const newPassword = ref("");
const step = ref(1);
const message = ref("");
const isLoading = ref(false);
const isError = ref(false);

async function requestOtp() {
  message.value = "";
  isLoading.value = true;
  isError.value = false;
  
  try {
    const res = await fetch("/api/Beauty_Salon/Auth/forgot_password", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ phone_number: email.value }),
    });
    
    const data = await res.json().catch(() => ({ success: false }));
    
    if (!res.ok || !data.success) {
      message.value = data.message || "Gagal mengirim OTP";
      isError.value = true;
      isLoading.value = false;
      return;
    }
    
    otp.value = "";
    step.value = 2;
    message.value = data.message;
    isLoading.value = false;
  } catch (e) {
    message.value = "Kesalahan jaringan";
    isError.value = true;
    isLoading.value = false;
  }
}

async function resetPassword() {
  message.value = "";
  isLoading.value = true;
  isError.value = false;
  
  try {
    const res = await fetch("/api/Beauty_Salon/Auth/reset_password", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ 
        phone_number: email.value, 
        otp: otp.value,
        new_password: newPassword.value 
      }),
    });
    
    const data = await res.json().catch(() => ({ success: false }));
    
    if (!res.ok || !data.success) {
      message.value = data.message || "Gagal mereset password";
      isError.value = true;
      isLoading.value = false;
      return;
    }
    
    message.value = "Password berhasil diubah secara aman. Mengalihkan...";
    
    setTimeout(() => {
      router.push("/login");
    }, 2000);
  } catch (e) {
    message.value = "Kesalahan jaringan";
    isError.value = true;
    isLoading.value = false;
  }
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
