<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { ArrowLeft, RotateCcw, Trash2 } from 'lucide-vue-next';
import AppShell from '@/components/AppShell.vue';
import ConfirmSheet from '@/components/ConfirmSheet.vue';
import { memoryService, type Memory } from '@/services/memory-service';
import { useAppStore } from '@/stores/app';
const memories = ref<Memory[]>([]), confirmEmpty = ref(false), app = useAppStore();
async function reload() { memories.value = await memoryService.trashList(); }
onMounted(reload);
async function restore(id: string) { await memoryService.restore(id); app.showToast('Memory dipulihkan'); reload(); }
async function emptyTrash() { const result = await memoryService.emptyTrash(); confirmEmpty.value = false; memories.value = []; app.showToast(`${result.deleted} memory dihapus permanen`); }
</script>
<template><AppShell><div class="page"><button class="icon-button -ml-3" @click="$router.back()"><ArrowLeft /></button><div class="mt-7 flex items-start justify-between gap-4"><div><h1 class="text-3xl font-semibold tracking-[-.035em]">Trash</h1><p class="mt-2 text-sm text-muted">Menyimpan maksimal 5 memory terbaru.</p></div><button v-if="memories.length" class="flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/25" @click="confirmEmpty=true"><Trash2 :size="16"/>Kosongkan</button></div><div v-if="memories.length" class="mt-7 divide-y divide-line"><div v-for="memory in memories" :key="memory.id" class="flex items-center justify-between gap-4 py-4"><div class="min-w-0"><p class="truncate font-semibold">{{memory.title}}</p><p class="mt-1 text-sm text-muted">Dihapus baru saja</p></div><button class="soft-button min-h-10 shrink-0 rounded-xl px-3" @click="restore(memory.id)"><RotateCcw :size="16"/>Restore</button></div></div><section v-else class="mt-20 text-center"><p class="font-semibold">Trash kosong.</p><p class="mt-2 text-sm text-muted">Memory yang dihapus akan muncul di sini.</p></section><ConfirmSheet :open="confirmEmpty" title="Kosongkan Trash?" description="Semua memory di Trash akan dihapus permanen dan tidak dapat dipulihkan." action="Kosongkan Trash" @close="confirmEmpty=false" @confirm="emptyTrash" /></div></AppShell></template>
