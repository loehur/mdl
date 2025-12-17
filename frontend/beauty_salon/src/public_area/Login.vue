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
        <div v-if="step === 1">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Masuk</h2>
          
          <form class="space-y-4" @submit.prevent="onSubmit">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nomor HP</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                  </svg>
                </div>
                <input v-model="id_user" class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition bg-white/50" type="text" inputmode="numeric" placeholder="08123456789" required />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Kata Sandi</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                  </svg>
                </div>
                <input v-model="password" class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition bg-white/50" type="password" placeholder="••••••" required />
              </div>
            </div>

            <button class="w-full py-3 rounded-xl bg-gradient-to-r from-pink-500 to-fuchsia-600 hover:from-pink-600 hover:to-fuchsia-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2" type="submit" :disabled="isLoading">
              <svg v-if="isLoading" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
              </svg>
              <span>{{ isLoading ? 'Memproses...' : 'Masuk' }}</span>
            </button>
          </form>

          <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
              Belum punya akun? 
              <router-link to="/register" class="text-pink-600 font-semibold hover:text-pink-700">Daftar Sekarang</router-link>
            </p>
          </div>
        </div>

        <!-- OTP Step -->
        <div v-else>
          <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-br from-pink-100 to-fuchsia-100 mb-4">
              <svg class="w-8 h-8 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Verifikasi OTP</h3>
            <p class="text-sm text-gray-600">Kode verifikasi dikirim ke<br><span class="font-semibold">{{ id_user }}</span></p>
          </div>

          <form class="space-y-4" @submit.prevent="verifyOtp">
            <div>
              <input v-model="otp" class="w-full px-4 py-4 border border-gray-200 rounded-xl text-center text-2xl tracking-widest font-mono focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition bg-white/50" type="text" maxlength="6" placeholder="000000" required />
            </div>

            <button class="w-full py-3 rounded-xl bg-gradient-to-r from-pink-500 to-fuchsia-600 hover:from-pink-600 hover:to-fuchsia-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all disabled:opacity-50 flex items-center justify-center gap-2" type="submit" :disabled="isLoading">
              <svg v-if="isLoading" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
              </svg>
              <span>{{ isLoading ? 'Memverifikasi...' : 'Verifikasi' }}</span>
            </button>

            <button type="button" @click="step = 1" class="w-full text-sm text-gray-600 hover:text-pink-600 transition">← Kembali</button>
          </form>
        </div>

        <!-- Message -->
        <div v-if="message" class="mt-4 p-3 rounded-lg text-sm font-medium" :class="isError ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'">
          {{ message }}
        </div>
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
const otp = ref("");
const step = ref(1);
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
    
    // Step 2 Login (OTP)
    if (data.otp_required) {
        step.value = 2;
        message.value = data.message;
        isLoading.value = false;
        return;
    }

    // Direct Login (Fallback logic if OTP not required, usually wont trigger now)
    try {
      if (data.user) {
        localStorage.setItem("salon_user", JSON.stringify(data.user));
      }
    } catch {}
    message.value = data.message || "Login berhasil";
    await router.push(data.redirect || "/order");
    isLoading.value = false;
  } catch (e) {
    message.value = "Network error";
    isError.value = true;
    isLoading.value = false;
  }
}

async function verifyOtp() {
    message.value = "";
    isLoading.value = true;
    isError.value = false;

    try {
        const res = await fetch("/api/Beauty_Salon/Auth/verify-login", {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({ 
                phone_number: id_user.value, 
                otp: otp.value 
            }),
        });
        const data = await res.json().catch(() => ({ success: false }));
        
        if (!res.ok || !data.success) {
            message.value = data.message || "Verifikasi gagal";
            isError.value = true;
            isLoading.value = false;
            return;
        }

        // Success Verified
        message.value = "Login Berhasil! Mengalihkan...";
        try {
            if (data.user) {
                localStorage.setItem("salon_user", JSON.stringify(data.user));
            }
        } catch {}
        
        setTimeout(() => {
            router.push("/order");
        }, 1500);

    } catch(e) {
        message.value = "Network error";
        isError.value = true;
        isLoading.value = false;
    }
}
</script>
