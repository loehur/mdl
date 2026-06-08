<template>
  <div class="app-shell">
    <MeshBackground />

    <header class="app-header" :class="{ 'app-header--hidden': !headerVisible }">
      <div class="mx-auto flex max-w-md items-end justify-between px-5 pt-6 pb-4">
        <div class="page-enter">
          <p class="label-caps mb-1">Personal Finance</p>
          <h1 class="page-title">{{ pageTitle }}</h1>
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

    <div class="header-spacer" aria-hidden="true" />

    <main class="mx-auto max-w-md px-5 pt-3 page-enter" style="animation-delay: 0.05s">
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
import { computed, h, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import MeshBackground from "../components/MeshBackground.vue";
import { clearSession } from "../utils/session";

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

const IconExpense = () =>
  h("svg", iconProps, [
    h("path", { d: "M12 21V3", strokeLinecap: "round" }),
    h("path", { d: "M7 16l5 5 5-5", strokeLinecap: "round", strokeLinejoin: "round" }),
    h("path", { d: "M5 3h14", strokeLinecap: "round" }),
  ]);

const IconRecap = () =>
  h("svg", iconProps, [
    h("path", { d: "M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01", strokeLinecap: "round" }),
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
  { path: "/rekap", label: "Rekap", icon: IconRecap },
  { path: "/pemasukan", label: "Masuk", icon: IconIncome },
  { path: "/pengeluaran", label: "Keluar", icon: IconExpense },
  { path: "/investasi", label: "Aliran", icon: IconFlow },
  { path: "/portfolio", label: "Aset", icon: IconChart },
];

const titles = {
  "/dashboard": "Overview",
  "/rekap": "Rekap",
  "/pemasukan": "Pemasukan",
  "/pengeluaran": "Pengeluaran",
  "/investasi": "Aliran Dana",
  "/portfolio": "Portfolio",
};

const pageTitle = computed(() => titles[route.path] || "Investasi");
const headerVisible = ref(true);

let scrollTicking = false;

function updateHeaderVisibility() {
  headerVisible.value = window.scrollY <= 8;
  scrollTicking = false;
}

function onScroll() {
  if (scrollTicking) return;
  scrollTicking = true;
  requestAnimationFrame(updateHeaderVisibility);
}

onMounted(() => {
  window.addEventListener("scroll", onScroll, { passive: true });
  updateHeaderVisibility();
});

onUnmounted(() => {
  window.removeEventListener("scroll", onScroll);
});

watch(
  () => route.path,
  () => {
    window.scrollTo({ top: 0 });
    headerVisible.value = true;
  }
);

async function logout() {
  try {
    await fetch("/api/Investasi/Auth/logout", { method: "POST" });
  } catch {
    /* ignore */
  }
  clearSession();
  router.push("/login");
}
</script>

<style scoped>
.app-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 40;
  transition: transform 0.28s ease, opacity 0.28s ease;
}

.app-header--hidden {
  transform: translateY(-100%);
  opacity: 0;
  pointer-events: none;
}

.header-spacer {
  height: 6.75rem;
}

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
