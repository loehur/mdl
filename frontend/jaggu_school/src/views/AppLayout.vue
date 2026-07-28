<template>
  <div class="min-h-screen pb-24">
    <header class="sticky top-0 z-20 border-b border-red-100/80 bg-white/85 backdrop-blur">
      <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <div>
          <p class="font-display text-lg font-bold text-jaggu-crimson leading-tight">Jaggu School</p>
          <p class="text-xs text-slate-500">{{ user?.name }} · {{ roleLabel }}</p>
        </div>
        <button
          type="button"
          class="text-xs font-semibold text-jaggu-red border border-red-200 rounded-full px-3 py-1.5 hover:bg-red-50"
          @click="logout"
        >
          Keluar
        </button>
      </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-5">
      <router-view />
    </main>

    <nav class="fixed bottom-0 inset-x-0 border-t border-red-100 bg-white/95 backdrop-blur safe-bottom">
      <div class="max-w-3xl mx-auto grid" :class="isParent ? 'grid-cols-2' : 'grid-cols-1'">
        <template v-if="isParent">
          <router-link
            to="/monitor"
            class="py-3 text-center text-sm font-semibold"
            :class="navClass('/monitor')"
          >Pantau</router-link>
          <router-link
            to="/jadwal"
            class="py-3 text-center text-sm font-semibold"
            :class="navClass('/jadwal')"
          >Jadwal</router-link>
        </template>
        <router-link
          v-else
          to="/today"
          class="py-3 text-center text-sm font-semibold"
          :class="navClass('/today')"
        >Hari ini</router-link>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { clearSession, getUser, isParent as checkParent } from "../utils/session";

const route = useRoute();
const router = useRouter();
const user = computed(() => getUser());
const isParent = computed(() => checkParent());
const roleLabel = computed(() => (isParent.value ? "Orang tua" : "Anak"));

function navClass(path) {
  return route.path === path ? "text-jaggu-red" : "text-slate-500";
}

async function logout() {
  try {
    await fetch("/api/Jaggu_School/Auth/logout", { method: "POST" });
  } catch {
    // ignore
  }
  clearSession();
  router.replace("/login");
}
</script>
