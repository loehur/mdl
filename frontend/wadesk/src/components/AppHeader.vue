<template>
  <div class="flex h-full min-h-0 w-full bg-ink-950 text-slate-100">
    <!-- Backdrop mobile -->
    <Transition name="fade">
      <button
        v-if="sidebarOpen"
        type="button"
        class="fixed inset-0 z-40 bg-black/60 md:hidden cursor-default"
        aria-label="Close menu"
        @click="sidebarOpen = false"
      />
    </Transition>

    <!-- Sidebar -->
    <aside
      id="wadesk-sidebar"
      class="fixed md:static inset-y-0 left-0 z-50 w-60 shrink-0 flex flex-col border-r border-white/10 bg-ink-900/95 md:bg-ink-900 backdrop-blur-md md:backdrop-blur-none transition-transform duration-200 ease-out md:translate-x-0"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
      aria-label="Navigation menu"
    >
      <div class="h-14 px-4 flex items-center justify-between border-b border-white/10 shrink-0">
        <router-link
          to="/"
          class="font-display text-lg font-semibold text-slate-100 hover:text-white transition"
          @click="sidebarOpen = false"
        >
          WaDesk
        </router-link>
        <button
          type="button"
          class="menu-btn md:hidden"
          aria-label="Close menu"
          @click="sidebarOpen = false"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
      </div>

      <nav class="flex-1 overflow-y-auto p-3 space-y-1" aria-label="Main navigation">
        <RouterLink
          v-for="item in visibleMainNavItems"
          :key="item.to"
          :to="item.to"
          class="sidebar-link"
          :class="{ 'sidebar-link-active': isActive(item.to) }"
          @click="sidebarOpen = false"
        >
          {{ item.label }}
        </RouterLink>
        <RouterLink
          v-if="auth.isAdmin"
          to="/admin"
          class="sidebar-link"
          :class="{ 'sidebar-link-active': isActive('/admin') }"
          @click="sidebarOpen = false"
        >
          Admin
        </RouterLink>
      </nav>

      <div class="p-3 border-t border-white/10 space-y-3 shrink-0">
        <div class="space-y-1.5 min-w-0">
          <p v-if="auth.user?.name" class="text-sm font-medium text-slate-100 truncate">
            {{ auth.user.name }}
          </p>
          <div class="flex flex-wrap gap-1.5 text-xs">
            <span class="px-2 py-0.5 rounded-full bg-white/5 text-slate-400">{{ auth.user?.role }}</span>
            <span
              v-if="auth.user?.team_name"
              class="px-2 py-0.5 rounded-full bg-accent/10 text-accent-soft truncate max-w-full"
            >
              {{ auth.user.team_name }}
            </span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <ThemeToggle compact />
          <button type="button" class="wadesk-logout-btn shrink-0 p-2" aria-label="Sign out" @click="emitLogout">
            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H9m4 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
            </svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0 min-h-0">
      <header class="h-14 shrink-0 border-b border-white/10 bg-ink-900/80 sticky top-0 z-30 px-3 sm:px-4 flex items-center gap-2 min-w-0">
        <button
          type="button"
          class="menu-btn md:hidden shrink-0"
          :aria-expanded="sidebarOpen"
          aria-controls="wadesk-sidebar"
          aria-label="Open menu"
          @click="sidebarOpen = true"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
          </svg>
        </button>

        <div class="min-w-0 flex-1 flex items-center gap-2">
          <h1 v-if="pageTitle" class="font-display font-semibold text-slate-100 truncate">
            {{ pageTitle }}
          </h1>
          <span v-else class="font-display font-semibold text-slate-100 md:hidden truncate">Chat</span>
        </div>

        <div v-if="$slots.extra" class="hidden sm:flex items-center gap-2 shrink-0">
          <slot name="extra" />
        </div>
      </header>

      <div v-if="$slots.extra" class="sm:hidden shrink-0 px-3 py-2 border-b border-white/5 flex flex-wrap gap-2">
        <slot name="extra" />
      </div>

      <main class="flex-1 min-h-0 flex flex-col overflow-hidden">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
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
const sidebarOpen = ref(false);

const mainNavItems = [
  { to: "/", label: "Chat" },
  { to: "/templates", label: "Templates" },
  { to: "/numbers", label: "Numbers", noAgent: true },
  { to: "/blast", label: "Blast" },
  { to: "/report", label: "Report" },
  { to: "/account", label: "Account" },
];

const visibleMainNavItems = computed(() =>
  mainNavItems.filter((item) => (!item.manageOnly || auth.canManageTeam) && (!item.noAgent || auth.user?.role !== "agent"))
);

watch(
  () => route.path,
  () => {
    sidebarOpen.value = false;
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
  sidebarOpen.value = false;
  emit("logout");
}
</script>

<style scoped>
.sidebar-link {
  @apply flex items-center px-3 py-2.5 rounded-xl text-sm text-slate-300 hover:bg-white/10 hover:text-slate-100 transition;
}
.sidebar-link-active {
  @apply bg-accent/15 text-accent-soft font-medium ring-1 ring-accent/20;
}
.menu-btn {
  @apply p-2 rounded-lg text-slate-300 hover:bg-white/10 hover:text-slate-100 transition;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
