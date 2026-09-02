<script setup lang="ts">
import { ref, watch } from 'vue';
import { LoaderCircle, Search } from 'lucide-vue-next';
import AppShell from '@/components/AppShell.vue';
import { memoryService, type Memory } from '@/services/memory-service';

const items = ref<Memory[]>([]); const query = ref(''); const loading = ref(false); const searched = ref(false); const total = ref<number | null>(null); let debounce: ReturnType<typeof setTimeout> | undefined;
memoryService.count().then((value) => { total.value = value; }).catch(() => { total.value = null; });
watch(query, (value) => { clearTimeout(debounce); const term = value.trim(); if (term.length < 2) { items.value = []; searched.value = false; loading.value = false; return; } loading.value = true; debounce = setTimeout(async () => { try { items.value = await memoryService.list(term, 20); searched.value = true; } finally { loading.value = false; } }, 280); });
</script>

<template><AppShell><div class="page"><header><p class="text-sm font-medium text-nemu-600 dark:text-[#83d8b2]">Your memory</p><h1 class="mt-2 text-3xl font-semibold tracking-[-.035em]">Memories</h1></header>
  <div class="sticky top-0 z-20 -mx-5 mt-7 border-b border-line bg-[#fbfdfb]/95 px-5 pb-4 pt-[max(1rem,env(safe-area-inset-top))] backdrop-blur sm:-mx-7 sm:px-7 dark:bg-[#0a0e0c]/95"><div class="mb-2 flex items-center justify-between px-1"><span class="text-xs font-medium text-muted">Pencarian kata kunci</span><span class="rounded-full bg-nemu-50 px-2.5 py-1 text-xs font-semibold text-nemu-600 dark:bg-nemu-500/20 dark:text-[#a9edcb]">{{ total === null ? '…' : total }} total memories</span></div><label class="surface flex items-center gap-3 rounded-2xl border border-line px-4 py-3"><Search :size="19" class="text-muted"/><input v-model="query" class="w-full bg-transparent text-[16px] outline-none placeholder:text-muted" placeholder="Cari dengan kata kunci"/><LoaderCircle v-if="loading" :size="18" class="animate-spin text-nemu-600"/></label></div>
  <section v-if="!searched && !loading" class="mt-16 text-center"><p class="font-semibold">Cari memory kamu</p><p class="mt-2 text-sm text-muted">Gunakan kata kunci, misalnya: jeruk, istri, atau vios.</p></section>
  <div v-else-if="loading" class="mt-7 flex items-center gap-3 text-sm text-muted"><span class="grid size-8 place-items-center rounded-full bg-nemu-50 dark:bg-nemu-500/20"><LoaderCircle :size="16" class="animate-spin text-nemu-600 dark:text-[#9ae8c3]"/></span><span>Mencari memory…</span></div>
  <div v-else-if="items.length" class="mt-3 divide-y divide-line"><RouterLink v-for="memory in items" :key="memory.id" :to="`/memories/${memory.id}`" class="flex min-h-22 items-center justify-between gap-4 py-4"><div class="min-w-0"><p class="truncate font-semibold">{{ memory.title }}</p><p class="mt-1 truncate text-sm text-muted">{{ memory.content }}</p><p class="mt-1.5 text-xs text-muted">Saved {{ memory.createdAt }}</p></div></RouterLink></div>
  <section v-else class="mt-16 text-center"><p class="font-semibold">Tidak menemukan memory yang cocok.</p><p class="mt-2 text-sm text-muted">Coba gunakan kata yang berbeda.</p></section>
</div></AppShell></template>
