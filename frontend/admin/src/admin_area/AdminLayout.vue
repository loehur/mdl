<template>
  <div class="min-h-screen flex bg-gray-50 font-sans relative overflow-x-hidden">
    <!-- Desktop Sidebar -->
    <aside class="w-72 bg-slate-900 text-white hidden md:flex flex-col shadow-xl z-20">
      <!-- Logo Area -->
      <div class="h-16 flex items-center px-6 border-b border-white/5 bg-slate-950/30">
        <div class="font-bold text-xl tracking-wide bg-gradient-to-r from-blue-400 to-teal-400 bg-clip-text text-transparent">
          Admin MDL
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 px-2">Menu Utama</div>
        
        <router-link
          to="/dashboard"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-blue-600 text-white shadow-lg shadow-blue-900/50 font-medium"
          :class="$route.path === '/dashboard' ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="8" height="8" rx="2" stroke-opacity="0.8"/>
            <rect x="13" y="3" width="8" height="8" rx="2" stroke-opacity="0.8"/>
            <rect x="3" y="13" width="8" height="8" rx="2" stroke-opacity="0.8"/>
            <rect x="13" y="13" width="8" height="8" rx="2" stroke-opacity="0.8"/>
          </svg>
          <span>Dashboard</span>
        </router-link>

        <router-link
          to="/whatsapp"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group"
          active-class="bg-green-600 text-white shadow-lg shadow-green-900/50 font-medium"
          :class="$route.path.includes('/whatsapp') ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path d="M16.5 3h-9A2.5 2.5 0 005 5.5v13l3.5-2h8A2.5 2.5 0 0019 14.5v-9A2.5 2.5 0 0016.5 3z" />
             <path d="M8 9c1.5 2 3.5 3.5 6 4" stroke-linecap="round" />
          </svg>
          <span>WhatsApp Gateway</span>
        </router-link>
      </nav>

      <!-- Footer Sidebar -->
      <div class="p-4 border-t border-white/5 bg-slate-950/20">
        <button class="flex items-center gap-3 w-full px-4 py-2 text-sm text-slate-400 hover:text-white transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
          <span>Logout</span>
        </button>
      </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col min-w-0 bg-gray-50/50">
      
      <!-- Top Header -->
      <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-10">
        <!-- Mobile Toggle -->
        <button @click="isOpen = true" class="md:hidden text-gray-500 hover:text-gray-700">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>

        <!-- Current Page Title (Desktop) -->
        <h1 class="text-xl font-bold text-gray-800 hidden md:block">
          {{ $route.path === '/dashboard' ? 'Dashboard Overview' : ($route.path.includes('whatsapp') ? 'WhatsApp Manager' : 'Admin Area') }}
        </h1>
        <h1 class="text-lg font-bold text-gray-800 md:hidden">Admin</h1>

        <!-- User Profile -->
        <div class="flex items-center gap-4">
          <div class="flex flex-col items-end hidden sm:flex">
            <span class="text-sm font-semibold text-gray-800">{{ firstName }}</span>
            <span class="text-xs text-gray-500">Administrator</span>
          </div>
          <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-500 to-purple-500 p-0.5 shadow-md cursor-pointer hover:shadow-lg transition-shadow">
             <div class="w-full h-full rounded-full bg-white flex items-center justify-center text-blue-600 font-bold text-lg">
                {{ firstName.charAt(0) }}
             </div>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 p-6 pb-24 md:pb-6 overflow-y-auto scroll-smooth">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
             <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>

    <!-- Mobile Drawer Overlay & Sidebar (Teleported to body to avoid container issues) -->
    <Teleport to="body">
      <div v-if="isOpen" class="fixed inset-0 bg-black/60 z-[9998] md:hidden transition-opacity" @click="isOpen = false"></div>

      <aside
        class="fixed inset-y-0 left-0 w-72 bg-slate-900 text-white z-[9999] transform transition-transform duration-300 ease-in-out shadow-2xl md:hidden flex flex-col"
        :class="isOpen ? 'translate-x-0' : '-translate-x-full'"
      >
        <div class="h-16 flex items-center justify-between px-6 border-b border-white/5 bg-slate-950/30">
          <span class="font-bold text-xl bg-gradient-to-r from-blue-400 to-teal-400 bg-clip-text text-transparent">Admin MDL</span>
          <button @click="isOpen = false" class="text-slate-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
          <router-link to="/dashboard" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-blue-600 text-white shadow-lg"
            :class="$route.path === '/dashboard' ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="8" height="8" rx="2" /><rect x="13" y="3" width="8" height="8" rx="2" /><rect x="3" y="13" width="8" height="8" rx="2" /><rect x="13" y="13" width="8" height="8" rx="2" /></svg>
             Dashboard
          </router-link>
          <router-link to="/whatsapp" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-green-600 text-white shadow-lg"
            :class="$route.path.includes('/whatsapp') ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16.5 3h-9A2.5 2.5 0 005 5.5v13l3.5-2h8A2.5 2.5 0 0019 14.5v-9A2.5 2.5 0 0016.5 3z" /><path d="M8 9c1.5 2 3.5 3.5 6 4" stroke-linecap="round" /></svg>
             WhatsApp
          </router-link>
        </nav>
      </aside>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from "vue";
import { useRoute } from "vue-router";

const isOpen = ref(false);
const route = useRoute();
const user = ref(null);

// Load user from localStorage
onMounted(() => {
  try {
    const stored = localStorage.getItem("mdl_user");
    if (stored) {
      user.value = JSON.parse(stored);
    }
  } catch (e) {
    console.warn("Failed to load user from localStorage");
  }
});

// Get first name from full name
const firstName = computed(() => {
  if (user.value && user.value.name) {
    return user.value.name.split(" ")[0];
  }
  return "Guest";
});

watch(
  () => route.fullPath,
  () => {
    isOpen.value = false;
  }
);
</script>

<style></style>
