<template>
  <div class="app-shell">
    <MeshBackground />

    <header class="sticky top-0 z-40 px-5 pt-6 pb-4">
      <div class="mx-auto flex max-w-md items-end justify-between">
        <div class="page-enter">
          <p class="label-caps mb-1">Personal Finance</p>
          <h1 class="font-display text-3xl italic text-pearl">{{ pageTitle }}</h1>
        </div>
        <button class="btn-icon" title="Keluar" @click="logout">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke-linecap="round" />
            <path d="M16 17l5-5-5-5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M21 12H9" stroke-linecap="round" />
          </svg>
        </button>
      </div>
    </header>

    <main class="mx-auto max-w-md px-5 page-enter" style="animation-delay: 0.05s">
      <router-view v-slot="{ Component }">
        <transition name="page" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <nav class="dock" aria-label="Navigasi utama">
      <div class="dock-inner">
        <router-link
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          class="dock-link"
          :class="$route.path === item.path ? 'dock-link-active' : ''"
        >
          <component :is="item.icon" class="h-5 w-5" />
          <span>{{ item.label }}</span>
          <span
            v-if="$route.path === item.path"
            class="absolute -bottom-0.5 h-1 w-1 rounded-full bg-ledger-dim"
          />
        </router-link>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { computed, h } from "vue";
import { useRoute, useRouter } from "vue-router";
import MeshBackground from "../components/MeshBackground.vue";

const route = useRoute();
const router = useRouter();

const iconProps = { viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", "stroke-width": "1.75" };

const IconHome = () =>
  h("svg", iconProps, [
    h("path", { d: "M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z", strokeLinejoin: "round" }),
  ]);

const IconIncome = () =>
  h("svg", iconProps, [
    h("path", { d: "M12 3v18", strokeLinecap: "round" }),
    h("path", { d: "M7 8l5-5 5 5", strokeLinecap: "round", strokeLinejoin: "round" }),
    h("path", { d: "M5 21h14", strokeLinecap: "round" }),
  ]);

const IconFlow = () =>
  h("svg", iconProps, [
    h("path", { d: "M4 7h16M4 12h10M4 17h6", strokeLinecap: "round" }),
    h("circle", { cx: "18", cy: "17", r: "3" }),
    h("path", { d: "M18 14V7", strokeLinecap: "round" }),
  ]);

const IconChart = () =>
  h("svg", iconProps, [
    h("path", { d: "M4 19V5", strokeLinecap: "round" }),
    h("path", { d: "M4 19h16", strokeLinecap: "round" }),
    h("path", { d: "M8 16V11", strokeLinecap: "round" }),
    h("path", { d: "M12 16V8", strokeLinecap: "round" }),
    h("path", { d: "M16 16v-4", strokeLinecap: "round" }),
  ]);

const navItems = [
  { path: "/dashboard", label: "Home", icon: IconHome },
  { path: "/pemasukan", label: "Masuk", icon: IconIncome },
  { path: "/investasi", label: "Aliran", icon: IconFlow },
  { path: "/portfolio", label: "Aset", icon: IconChart },
];

const titles = {
  "/dashboard": "Overview",
  "/pemasukan": "Pemasukan",
  "/investasi": "Aliran Dana",
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

<style scoped>
.page-enter-active,
.page-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.page-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.page-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
