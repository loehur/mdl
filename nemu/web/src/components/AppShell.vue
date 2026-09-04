<script setup lang="ts">
import { Brain, House, Library, Settings } from 'lucide-vue-next';
import BottomNavigation from './BottomNavigation.vue';
import AppToast from './AppToast.vue';
</script>
<template>
  <div class="min-h-dvh bg-[#fbfdfb] text-ink dark:bg-[#090a0b]">
    <aside class="fixed inset-y-0 left-0 hidden w-60 border-r border-line bg-white px-4 py-7 dark:bg-[#101114] md:block"><div class="mb-10 flex items-center gap-2 px-3"><span class="grid size-8 place-items-center rounded-xl bg-nemu-600 text-white"><Brain :size="18" /></span><span class="leading-tight"><span class="block text-lg font-bold tracking-tight">NEMU</span><span class="block text-[11px] font-medium text-muted">nemu.nalju.com</span></span></div><nav class="space-y-1"><RouterLink to="/" class="nav-link"><House :size="19" />Home</RouterLink><RouterLink to="/memories" class="nav-link"><Library :size="19" />Memories</RouterLink><RouterLink to="/settings" class="nav-link"><Settings :size="19" />Settings</RouterLink></nav><p class="absolute bottom-7 px-3 text-xs leading-5 text-muted">Save now.<br />Find later.</p></aside>
    <main class="md:ml-60"><slot /></main><BottomNavigation /><AppToast />
  </div>
</template>
