<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-emerald-100 via-white to-teal-100 px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-600 shadow-lg">
          <svg class="h-8 w-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 19V5" stroke-linecap="round"/>
            <path d="M4 19H20" stroke-linecap="round"/>
            <path d="M8 17V11" stroke-linecap="round"/>
            <path d="M12 17V7" stroke-linecap="round"/>
            <path d="M16 17V13" stroke-linecap="round"/>
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-900">MDL Investasi</h1>
        <p class="mt-1 text-sm text-slate-600">Catat pemasukan & portfolio harian</p>
      </div>

      <div class="card p-6">
        <h2 class="mb-5 text-lg font-semibold text-slate-900">Masuk</h2>
        <form class="space-y-4" @submit.prevent="onSubmit">
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Email</label>
            <input v-model="email" class="input" type="email" placeholder="admin@mdl.local" required />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kata Sandi</label>
            <input v-model="password" class="input" type="password" placeholder="••••••••" required />
          </div>
          <button class="btn-primary w-full" type="submit" :disabled="loading">
            {{ loading ? "Memproses..." : "Masuk" }}
          </button>
        </form>

        <p v-if="message" class="mt-4 rounded-xl px-4 py-3 text-sm" :class="isError ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'">
          {{ message }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();
const email = ref("");
const password = ref("");
const loading = ref(false);
const message = ref("");
const isError = ref(false);

async function onSubmit() {
  loading.value = true;
  message.value = "";
  isError.value = false;

  try {
    const res = await fetch("/api/Investasi/Auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: email.value, password: password.value }),
    });
    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.success) {
      message.value = data.message || "Login gagal";
      isError.value = true;
      return;
    }

    localStorage.setItem(
      "investasi_user",
      JSON.stringify({
        user: data.user,
        expiry: Date.now() + 24 * 60 * 60 * 1000,
      })
    );

    router.push("/dashboard");
  } catch {
    message.value = "Tidak dapat terhubung ke server";
    isError.value = true;
  } finally {
    loading.value = false;
  }
}
</script>
