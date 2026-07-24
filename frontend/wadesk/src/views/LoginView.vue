<template>
  <div class="min-h-full flex items-center justify-center p-4 relative overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(13,148,136,0.25),_transparent_55%),radial-gradient(ellipse_at_bottom_right,_rgba(56,189,248,0.12),_transparent_50%)]" />
    <div class="absolute inset-0 opacity-40" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%2394a3b8\' fill-opacity=\'0.06\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')" />

    <div class="relative w-full max-w-md">
      <div class="text-center mb-8">
        <p class="font-display text-4xl font-bold tracking-tight text-white">WaDesk</p>
        <p class="mt-2 text-slate-400 text-sm">Multi-tenant WhatsApp inbox</p>
      </div>

      <div class="rounded-2xl border border-white/10 bg-ink-900/80 backdrop-blur-xl p-6 shadow-2xl">
        <div class="flex gap-2 mb-6 p-1 rounded-xl bg-ink-950/60">
          <button
            type="button"
            class="flex-1 py-2 rounded-lg text-sm font-medium transition"
            :class="mode === 'login' ? 'bg-accent text-white' : 'text-slate-400 hover:text-white'"
            @click="mode = 'login'"
          >
            Masuk
          </button>
          <button
            type="button"
            class="flex-1 py-2 rounded-lg text-sm font-medium transition"
            :class="mode === 'register' ? 'bg-accent text-white' : 'text-slate-400 hover:text-white'"
            @click="mode = 'register'"
          >
            Daftar Admin
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
          <div v-if="mode === 'register'">
            <label class="block text-xs text-slate-400 mb-1">Nama organisasi</label>
            <input v-model="form.org_name" required class="input" placeholder="PT Contoh" />
          </div>
          <div v-if="mode === 'register'">
            <label class="block text-xs text-slate-400 mb-1">Nama Anda</label>
            <input v-model="form.name" required class="input" placeholder="Nama admin" />
          </div>
          <div>
            <label class="block text-xs text-slate-400 mb-1">Email</label>
            <input v-model="form.email" type="email" required class="input" placeholder="admin@email.com" />
          </div>
          <div>
            <label class="block text-xs text-slate-400 mb-1">Password</label>
            <input v-model="form.password" type="password" required minlength="6" class="input" placeholder="••••••••" />
          </div>

          <p v-if="auth.error" class="text-sm text-rose-400">{{ auth.error }}</p>

          <button
            type="submit"
            class="w-full py-3 rounded-xl bg-accent hover:bg-accent-soft font-semibold transition disabled:opacity-50"
            :disabled="auth.loading"
          >
            {{ auth.loading ? "Memproses..." : mode === "login" ? "Masuk" : "Buat akun" }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";

const auth = useAuthStore();
const router = useRouter();
const mode = ref("login");
const form = reactive({
  org_name: "",
  name: "",
  email: "",
  password: "",
});

async function submit() {
  try {
    if (mode.value === "login") {
      await auth.login(form.email, form.password);
    } else {
      await auth.register({
        org_name: form.org_name,
        name: form.name,
        email: form.email,
        password: form.password,
      });
    }
    router.push({ name: "inbox" });
  } catch {
    /* shown via auth.error */
  }
}
</script>

<style scoped>
.input {
  @apply w-full rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-accent/50;
}
</style>
