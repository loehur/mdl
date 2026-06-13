<template>
  <div class="app-shell">
    <MeshBackground />

    <header class="app-header" :class="{ 'app-header--hidden': !headerVisible }">
      <div class="mx-auto flex max-w-md items-center justify-between px-5 pt-4 pb-3">
        <div class="page-enter">
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

    <main class="mx-auto max-w-md px-5 pt-3">
      <router-view v-slot="{ Component, route }">
        <transition name="page">
          <component :is="Component" :key="route.path" />
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
          :class="$route.path === item.path || ($route.path.startsWith('/detail') && item.path === '/riwayat') ? 'dock-link-active' : ''"
        >
          <component :is="item.icon" />
          <span>{{ item.label }}</span>
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

const IconCreate = () =>
  h("svg", iconProps, [
    h("path", { d: "M12 5v14M5 12h14", strokeLinecap: "round" }),
  ]);

const IconHistory = () =>
  h("svg", iconProps, [
    h("path", { d: "M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01", strokeLinecap: "round" }),
  ]);

const navItems = [
  { path: "/dashboard", label: "Home", icon: IconHome },
  { path: "/buat", label: "Buat", icon: IconCreate },
  { path: "/riwayat", label: "Riwayat", icon: IconHistory },
];

const titles = {
  "/dashboard": "Overview",
  "/buat": "Buat Invoice",
  "/riwayat": "Riwayat",
};

const pageTitle = computed(() => {
  if (route.path.startsWith("/detail")) return "Detail Invoice";
  return titles[route.path] || "Invoice";
});

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
    await fetch("/api/Invoice/Auth/logout", { method: "POST" });
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
  height: 5rem;
}

.page-enter-active,
.page-leave-active {
  transition: opacity 0.1s ease;
}

.page-enter-from,
.page-leave-to {
  opacity: 0;
}
</style>
