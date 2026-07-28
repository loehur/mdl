<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <div class="mx-auto mb-4 h-16 w-16 rounded-2xl bg-jaggu-red shadow-lg shadow-red-200 flex items-center justify-center">
          <span class="text-white text-2xl font-display font-bold">JS</span>
        </div>
        <h1 class="font-display text-3xl font-bold text-jaggu-crimson tracking-tight">Jaggu School</h1>
        <p class="mt-2 text-slate-600 text-sm">Jadwal mapel & ceklist harian</p>
      </div>

      <form class="rounded-3xl bg-white/90 backdrop-blur border border-red-100 shadow-xl shadow-red-100/40 p-6 space-y-4" @submit.prevent="login">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Email</label>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="username"
            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-jaggu-red/30 focus:border-jaggu-red"
            placeholder="nama@gmail.com"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Kata sandi</label>
          <input
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-jaggu-red/30 focus:border-jaggu-red"
            placeholder="••••••"
          />
        </div>

        <p v-if="error" class="text-sm text-red-600 bg-red-50 rounded-xl px-3 py-2">{{ error }}</p>

        <button
          type="submit"
          :disabled="loading"
          class="w-full rounded-xl bg-jaggu-red hover:bg-jaggu-crimson disabled:opacity-60 text-white font-semibold py-2.5 transition"
        >
          {{ loading ? "Masuk..." : "Masuk" }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { saveSession } from "../utils/session";

const router = useRouter();
const email = ref("");
const password = ref("");
const loading = ref(false);
const error = ref("");

async function login() {
  loading.value = true;
  error.value = "";
  try {
    const res = await fetch("/api/Jaggu_School/Auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ email: email.value, password: password.value }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
      throw new Error(data.message || "Login gagal");
    }
    const user = data.user || data.data?.user;
    saveSession(user, data.token || null);
    router.replace(data.redirect || (user?.role === "parent" ? "/monitor" : "/today"));
  } catch (e) {
    error.value = e.message || "Login gagal";
  } finally {
    loading.value = false;
  }
}
</script>
