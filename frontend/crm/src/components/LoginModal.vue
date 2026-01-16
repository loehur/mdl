<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  loading: Boolean,
  error: String,
  warning: String,
  initialUsername: String
});

const emit = defineEmits(['login']);

const username = ref(props.initialUsername || '');

// Listen to ENTER key to submit
const handleKeyup = (e) => {
  if (e.key === 'Enter' && username.value && !props.loading) {
    handleLogin();
  }
};

const handleLogin = () => {
  if (username.value && !props.loading) {
    emit('login', username.value);
  }
};

// Keep local state in sync if prop changes (optional)
watch(() => props.initialUsername, (newVal) => {
  if (newVal !== undefined) {
    username.value = newVal;
  }
});
</script>

<template>
  <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 overflow-hidden">
    <!-- Animated Gradient Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900">
      <!-- Floating Orbs -->
      <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl animate-pulse"></div>
      <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-purple-500/15 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s"></div>
      <div class="absolute top-1/2 right-1/3 w-64 h-64 bg-cyan-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s"></div>
    </div>

    <!-- Login Card (Glassmorphism) -->
    <div class="relative w-full max-w-md transform transition-all animate-scale-in">
      <!-- Glowing Border Effect -->
      <div class="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-cyan-500 rounded-3xl blur opacity-30 animate-gradient-shift"></div>

      <div class="relative bg-slate-900/90 backdrop-blur-xl border border-white/10 rounded-3xl shadow-2xl overflow-hidden">
        <!-- Header Section -->
        <div class="px-8 pt-10 pb-6 text-center relative">
          <!-- Decorative Grid -->
          <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_rgba(99,102,241,0.1)_0%,_transparent_70%)]"></div>

          <!-- Logo Icon -->
          <div class="relative inline-flex mb-6">
            <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/30 transform hover:scale-105 transition-transform">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
            </div>
            <!-- Floating Badge -->
            <div class="absolute -top-1 -right-1 w-6 h-6 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center shadow-lg">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-white" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </div>
          </div>

          <!-- Title -->
          <h1 class="text-3xl font-bold bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent mb-2">
            MDL Chat
          </h1>
          <p class="text-slate-400 text-sm">
            Masukkan ID untuk terhubung ke konsol
          </p>
        </div>

        <!-- Form Section -->
        <div class="px-8 pb-10">
          <div class="space-y-5">
            <!-- ID Input -->
            <div class="relative group">
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2 transition-colors group-focus-within:text-indigo-400">
                Username
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500 group-focus-within:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
                <input
                  v-model="username"
                  @keyup="handleKeyup"
                  type="text"
                  placeholder="Username"
                  class="w-full bg-slate-800/50 border border-slate-700/50 rounded-xl pl-12 pr-4 py-4 text-white text-lg font-medium placeholder:text-slate-500 focus:outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20 focus:bg-slate-800 transition-all"
                  :disabled="loading"
                  autofocus
                />
              </div>
            </div>

            <!-- Warning Message (Duplicate Connection) -->
            <div v-if="warning" class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl flex items-start gap-3 text-amber-400 text-sm backdrop-blur-sm">
              <div class="w-8 h-8 rounded-full bg-amber-500/20 flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div>
                <span class="font-semibold block mb-1">Koneksi Duplikat</span>
                <span class="text-amber-300/80">{{ warning }}</span>
              </div>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="p-4 bg-red-500/10 border border-red-500/20 rounded-xl flex items-center gap-3 text-red-400 text-sm backdrop-blur-sm">
              <div class="w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <span>{{ error }}</span>
            </div>

            <!-- Connect Button -->
            <button
              @click="handleLogin"
              class="w-full relative group overflow-hidden bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 shadow-lg shadow-indigo-500/25"
              :disabled="loading || !username"
            >
              <!-- Shine Effect -->
              <div class="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-700 bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>

              <span class="relative flex items-center justify-center gap-3">
                <span v-if="loading" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                {{ loading ? "Menghubungkan..." : "Hubungkan" }}
              </span>
            </button>
          </div>

          <!-- Footer Info -->
          <div class="mt-8 pt-6 border-t border-slate-800 text-center">
            <p class="text-slate-500 text-xs">
              💡 ID Anda adalah nomor karyawan atau username yang diberikan admin
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
