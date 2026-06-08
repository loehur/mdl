<template>
  <div class="relative flex min-h-screen">
    <MeshBackground />

    <!-- Left decorative panel (desktop) -->
    <aside class="relative hidden w-[45%] overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12">
      <div>
        <p class="label-caps text-ledger/80">Investasi PWA</p>
        <h1 class="mt-6 font-display text-6xl leading-[1.05] text-pearl">
          Catat.<br />
          <span class="italic text-gradient-accent">Kelola.</span><br />
          Lacak.
        </h1>
        <p class="mt-6 max-w-sm text-base leading-relaxed text-mist">
          Jurnal keuangan pribadi untuk pemasukan harian, aliran investasi, dan nilai portfolio — dalam satu tempat yang tenang.
        </p>
      </div>

      <div class="space-y-4">
        <div class="hairline" />
        <div class="flex gap-8">
          <div>
            <p class="label-caps">Fitur</p>
            <p class="mt-2 text-sm text-mist">Pemasukan harian</p>
            <p class="text-sm text-mist">Deposit & penarikan</p>
            <p class="text-sm text-mist">Update portfolio</p>
          </div>
          <div>
            <p class="label-caps">Segera</p>
            <p class="mt-2 text-sm text-mist">Rekap & laporan</p>
          </div>
        </div>
      </div>

      <div class="absolute -right-20 top-1/4 h-80 w-80 rounded-full border border-ledger/10" />
      <div class="absolute right-10 bottom-20 h-40 w-40 rounded-full border border-ledger/15 bg-ledger/5 blur-sm" />
    </aside>

    <!-- Login form -->
    <div class="flex flex-1 items-center justify-center px-5 py-12">
      <div class="w-full max-w-sm page-enter">
        <div class="mb-10 lg:hidden">
          <p class="label-caps text-ledger/80">Investasi</p>
          <h1 class="mt-2 font-display text-4xl italic text-pearl">Selamat datang</h1>
        </div>

        <div class="glass-strong p-8">
          <div class="mb-8 flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-ledger/20 bg-ledger/10">
              <svg class="h-5 w-5 text-ledger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v10M8 12h8" stroke-linecap="round" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-pearl">Masuk ke akun</p>
              <p class="text-xs text-mist">Akses data keuangan pribadi Anda</p>
            </div>
          </div>

          <form class="space-y-5" @submit.prevent="onSubmit">
            <div>
              <label class="field-label">Email</label>
              <input
                v-model="email"
                class="field-input"
                type="email"
                autocomplete="email"
                placeholder="nama@email.com"
                required
              />
            </div>
            <div>
              <label class="field-label">Kata sandi</label>
              <input
                v-model="password"
                class="field-input"
                type="password"
                autocomplete="current-password"
                placeholder="••••••••"
                required
              />
            </div>

            <button class="btn-primary w-full" type="submit" :disabled="loading">
              <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-ink/30 border-t-ink" />
              {{ loading ? "Memverifikasi..." : "Masuk" }}
            </button>
          </form>

          <AlertBanner class="mt-5" :message="message" :type="isError ? 'error' : 'success'" />
        </div>

        <p class="mt-8 text-center text-xs text-mist/70">
          Data tersimpan aman · hanya untuk penggunaan pribadi
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import MeshBackground from "../components/MeshBackground.vue";
import AlertBanner from "../components/AlertBanner.vue";

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
      message.value = data.message || data.error || "Login gagal";
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
