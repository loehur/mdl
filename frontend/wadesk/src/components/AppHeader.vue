<template>
  <header class="shrink-0 border-b border-white/10 bg-ink-900/80 sticky top-0 z-30">
    <div class="h-14 px-3 sm:px-4 flex items-center justify-between gap-2 min-w-0">
      <!-- Brand / page title -->
      <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        <template v-if="pageTitle">
          <router-link
            to="/"
            class="shrink-0 text-slate-400 hover:text-slate-100 text-sm inline-flex items-center gap-1"
          >
            <span aria-hidden="true">←</span>
            <span class="hidden sm:inline">Inbox</span>
          </router-link>
          <span class="text-slate-600 hidden sm:inline">|</span>
          <span class="font-display font-semibold text-slate-100 truncate">{{ pageTitle }}</span>
        </template>
        <template v-else>
          <router-link to="/" class="font-display text-lg sm:text-xl font-semibold text-slate-100 shrink-0">
            WaDesk
          </router-link>
          <span class="hidden sm:inline text-xs px-2 py-0.5 rounded-full bg-white/5 text-slate-400 shrink-0">
            {{ auth.user?.role }}
          </span>
          <span
            v-if="auth.user?.team_name"
            class="hidden md:inline text-xs px-2 py-0.5 rounded-full bg-accent/10 text-accent-soft truncate max-w-[8rem]"
          >
            {{ auth.user.team_name }}
          </span>
        </template>
      </div>

      <!-- Desktop nav -->
      <div class="hidden md:flex items-center gap-2 min-w-0">
        <slot name="extra" />
        <nav class="flex items-center gap-1" aria-label="Navigasi utama">
          <RouterLink
            v-for="item in mainNavItems"
            :key="item.to"
            :to="item.to"
            class="nav-link"
            :class="{ 'nav-link-active': isActive(item.to) }"
          >
            {{ item.label }}
          </RouterLink>
          <RouterLink
            v-if="auth.isAdmin"
            to="/admin"
            class="nav-link"
            :class="{ 'nav-link-active': isActive('/admin') }"
          >
            Admin
          </RouterLink>
        </nav>
        <ThemeToggle compact />
        <button type="button" class="wadesk-logout-btn" @click="emitLogout">
          <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H9m4 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
          </svg>
          <span>Keluar</span>
        </button>
      </div>

      <!-- Mobile: theme + menu -->
      <div class="flex md:hidden items-center gap-1 shrink-0">
        <ThemeToggle compact />
        <button
          type="button"
          class="menu-btn"
          :aria-expanded="menuOpen"
          aria-controls="wadesk-mobile-nav"
          aria-label="Menu navigasi"
          @click="menuOpen = !menuOpen"
        >
          <svg v-if="!menuOpen" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
          </svg>
          <svg v-else class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile drawer -->
    <Transition name="menu-slide">
      <div
        v-if="menuOpen"
        id="wadesk-mobile-nav"
        class="md:hidden border-t border-white/10 bg-ink-900/95 backdrop-blur-sm px-3 py-3 space-y-3"
      >
        <div v-if="$slots.extra" class="pb-2 border-b border-white/5">
          <slot name="extra" />
        </div>

        <div v-if="!pageTitle" class="flex flex-wrap gap-2 text-xs">
          <span class="px-2 py-0.5 rounded-full bg-white/5 text-slate-400">{{ auth.user?.role }}</span>
          <span v-if="auth.user?.team_name" class="px-2 py-0.5 rounded-full bg-accent/10 text-accent-soft">
            {{ auth.user.team_name }}
          </span>
          <span v-if="auth.user?.name" class="text-slate-500">{{ auth.user.name }}</span>
        </div>

        <nav class="grid grid-cols-2 gap-2" aria-label="Navigasi mobile">
          <RouterLink
            v-for="item in mainNavItems"
            :key="item.to"
            :to="item.to"
            class="mobile-nav-link"
            :class="{ 'mobile-nav-link-active': isActive(item.to) }"
            @click="menuOpen = false"
          >
            {{ item.label }}
          </RouterLink>
          <RouterLink
            v-if="auth.isAdmin"
            to="/admin"
            class="mobile-nav-link"
            :class="{ 'mobile-nav-link-active': isActive('/admin') }"
            @click="menuOpen = false"
          >
            Admin
          </RouterLink>
        </nav>

        <button type="button" class="wadesk-logout-btn w-full justify-center" @click="onMobileLogout">
          <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H9m4 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
          </svg>
          <span>Keluar</span>
        </button>
      </div>
    </Transition>
  </header>
</template>

<script setup>
import { ref, watch } from "vue";
import { useRoute, RouterLink } from "vue-router";
import { useAuthStore } from "../stores/auth";
import ThemeToggle from "./ThemeToggle.vue";

const props = defineProps({
  pageTitle: { type: String, default: "" },
  active: { type: String, default: "" },
});

const emit = defineEmits(["logout"]);

const auth = useAuthStore();
const route = useRoute();
const menuOpen = ref(false);

const mainNavItems = [
  { to: "/", label: "Inbox" },
  { to: "/report", label: "Report" },
  { to: "/blast", label: "Blast" },
];

watch(
  () => route.path,
  () => {
    menuOpen.value = false;
  }
);

function isActive(path) {
  if (props.active) {
    if (path === "/") return props.active === "inbox";
    return props.active === path.replace(/^\//, "");
  }
  if (path === "/") return route.path === "/" || route.name === "inbox";
  return route.path === path || route.path.startsWith(path + "/");
}

function emitLogout() {
  emit("logout");
}

function onMobileLogout() {
  menuOpen.value = false;
  emitLogout();
}
</script>

<style scoped>
.nav-link {
  @apply px-2.5 py-1.5 rounded-lg text-sm text-slate-300 hover:bg-white/10 hover:text-slate-100 transition whitespace-nowrap;
}
.nav-link-active {
  @apply bg-accent/15 text-accent-soft font-medium;
}
.mobile-nav-link {
  @apply px-3 py-2.5 rounded-xl text-sm text-center text-slate-200 bg-white/5 hover:bg-white/10 transition;
}
.mobile-nav-link-active {
  @apply bg-accent/20 text-accent-soft font-medium ring-1 ring-accent/30;
}
.menu-btn {
  @apply p-2 rounded-lg text-slate-300 hover:bg-white/10 hover:text-slate-100 transition;
}
.menu-slide-enter-active,
.menu-slide-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.menu-slide-enter-from,
.menu-slide-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
