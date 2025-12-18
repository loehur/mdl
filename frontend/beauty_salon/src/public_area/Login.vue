<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-rose-100 via-pink-50 to-fuchsia-100 relative overflow-hidden">
    <!-- Decorative background elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-80 h-80 bg-pink-300/30 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-fuchsia-300/30 rounded-full blur-3xl"></div>
      <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-rose-200/20 rounded-full blur-3xl"></div>
    </div>

    <div class="w-full max-w-md px-6 py-8 relative z-10">
      <!-- Logo/Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-pink-500 to-fuchsia-600 shadow-xl mb-4">
          <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <h1 class="text-3xl font-bold bg-gradient-to-r from-pink-600 to-fuchsia-600 bg-clip-text text-transparent mb-2">Beauty Salon</h1>
        <p class="text-gray-600">Selamat datang kembali</p>
      </div>

      <!-- Login Card -->
      <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl p-8 border border-white/50">
        <div>
          <h2 class="text-2xl font-bold text-gray-800 mb-6 font-display tracking-tight">Masuk ke Akun</h2>
          
          <form class="space-y-5" @submit.prevent="onSubmit">
            <div>
              <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Nomor HP</label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-pink-500">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                  </svg>
                </div>
                <input v-model="id_user" class="w-full pl-12 pr-4 py-4 border border-gray-100 rounded-2xl focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all bg-white/50 font-medium" type="text" inputmode="numeric" placeholder="Contoh: 08123456789" required />
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Kata Sandi</label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-pink-500">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                  </svg>
                </div>
                <input v-model="password" class="w-full pl-12 pr-4 py-4 border border-gray-100 rounded-2xl focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all bg-white/50 font-medium" type="password" placeholder="••••••••" required />
              </div>
            </div>

            <div class="flex items-center justify-end mb-4">
              <router-link to="/forgot-password" class="text-xs font-bold text-pink-600 hover:text-pink-700">Lupa Password?</router-link>
            </div>

            <button class="w-full py-4 rounded-2xl bg-slate-900 group hover:bg-slate-800 text-white font-black text-xs uppercase tracking-[0.2em] shadow-2xl hover:shadow-slate-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-3 relative overflow-hidden" type="submit" :disabled="isLoading">
              <div class="absolute inset-0 bg-gradient-to-r from-pink-600 to-purple-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
              <svg v-if="isLoading" class="h-5 w-5 animate-spin relative z-10" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
              </svg>
              <span class="relative z-10">{{ isLoading ? 'Memproses...' : 'Masuk Sekarang' }}</span>
            </button>
          </form>

          <div class="mt-8 text-center">
            <p class="text-xs text-gray-500 font-medium">
              Belum memiliki akun? 
              <router-link to="/register" class="text-pink-600 font-bold hover:text-pink-700 underline underline-offset-4 decoration-pink-200">Daftar Akun Baru</router-link>
            </p>
          </div>
        </div>

        <!-- Message -->
        <transition name="fade">
          <div v-if="message" class="mt-6 p-4 rounded-2xl text-xs font-bold flex items-center gap-3" :class="isError ? 'bg-red-50 text-red-600 border border-red-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-100'">
            <svg v-if="isError" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <svg v-else class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ message }}</span>
          </div>
        </transition>
      </div>

      <!-- Footer -->
      <p class="text-center text-xs text-gray-500 mt-6">© 2025 Beauty Salon. All rights reserved.</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
const router = useRouter();
const id_user = ref("");
const password = ref("");
const message = ref("");
const isLoading = ref(false);
const isError = ref(false);

async function onSubmit() {
  message.value = "";
  isLoading.value = true;
  isError.value = false;
  try {
    const res = await fetch("/api/Beauty_Salon/Auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ id_user: id_user.value, password: password.value }),
    });
    const data = await res.json().catch(() => ({ success: false }));
    if (!res.ok || !data.success) {
      message.value = data.message || "Login gagal";
      isError.value = true;
      isLoading.value = false;
      return;
    }
    
    // Direct Login
    try {
      if (data.user) {
        localStorage.setItem("salon_user", JSON.stringify(data.user));
      }
    } catch {}
    
    message.value = data.message || "Login berhasil! Mohon tunggu...";
    
    // Redirect based on backend response or default to /order
    setTimeout(() => {
      router.push(data.redirect || "/order");
    }, 1000);

  } catch (e) {
    message.value = "Network error";
    isError.value = true;
    isLoading.value = false;
  }
}
</script>
