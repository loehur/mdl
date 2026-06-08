<template>
  <div class="min-h-screen pb-24">
    <header class="sticky top-0 z-20 border-b border-emerald-100 bg-white/90 backdrop-blur">
      <div class="mx-auto flex max-w-lg items-center justify-between px-4 py-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">MDL Investasi</p>
          <h1 class="text-lg font-bold text-slate-900">{{ pageTitle }}</h1>
        </div>
        <button class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-100" @click="logout">
          Keluar
        </button>
      </div>
    </header>

    <main class="mx-auto max-w-lg px-4 py-5">
      <router-view />
    </main>

    <nav class="fixed bottom-0 left-0 right-0 z-30 border-t border-emerald-100 bg-white/95 backdrop-blur">
      <div class="mx-auto grid max-w-lg grid-cols-4 gap-1 px-2 py-2">
        <router-link
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          class="flex flex-col items-center rounded-xl px-2 py-2 text-[11px] font-semibold transition"
          active-class="bg-emerald-50 text-emerald-700"
          :class="$route.path === item.path ? '' : 'text-slate-500'"
        >
          <span class="mb-1 text-base">{{ item.icon }}</span>
          {{ item.label }}
        </router-link>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();

const navItems = [
  { path: "/dashboard", label: "Ringkasan", icon: "📊" },
  { path: "/pemasukan", label: "Pemasukan", icon: "💰" },
  { path: "/investasi", label: "Investasi", icon: "📥" },
  { path: "/portfolio", label: "Portfolio", icon: "📈" },
];

const titles = {
  "/dashboard": "Ringkasan",
  "/pemasukan": "Pemasukan Harian",
  "/investasi": "Deposit & Penarikan",
  "/portfolio": "Portfolio",
};

const pageTitle = computed(() => titles[route.path] || "Investasi");

async function logout() {
  try {
    await fetch("/api/Investasi/Auth/logout", { method: "POST" });
  } catch {
    /* ignore */
  }
  localStorage.removeItem("investasi_user");
  router.push("/login");
}
</script>
