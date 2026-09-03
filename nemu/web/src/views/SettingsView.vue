<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ChevronRight, LockKeyhole, LogOut, Moon, Trash2 } from 'lucide-vue-next';
import AppShell from '@/components/AppShell.vue';
import { loadProfile, profile, signOut, type NemuSession } from '@/services/auth-service';
import { applyTheme, currentTheme } from '@/services/theme-service';
import { planService, type PlanInfo } from '@/services/plan-service';

const router = useRouter();
const dark = ref(currentTheme() === 'dark');
const account = ref<Pick<NemuSession, 'email' | 'displayName' | 'avatarUrl'> | undefined>(profile());
const avatarFailed = ref(false); const avatarRetried = ref(false);
const activePlan = ref<PlanInfo>();
const initial = computed(() => account.value?.displayName?.trim().charAt(0).toUpperCase() || account.value?.email?.charAt(0).toUpperCase() || '?');
onMounted(async () => { try { account.value = await loadProfile(); avatarFailed.value = false; avatarRetried.value = false; activePlan.value = await planService.get(); } catch { /* retain cached profile */ } });
function onAvatarError(event: Event) { const image = event.target as HTMLImageElement; if (!avatarRetried.value && account.value?.avatarUrl) { avatarRetried.value = true; image.src = `${account.value.avatarUrl}${account.value.avatarUrl.includes('?') ? '&' : '?'}retry=${Date.now()}`; return; } avatarFailed.value = true; }
function toggle() { dark.value = !dark.value; applyTheme(dark.value ? 'dark' : 'light'); }
async function logout() { signOut(); await router.replace('/login'); }
</script>

<template>
  <AppShell><div class="page"><header><p class="text-sm font-medium text-nemu-600 dark:text-[#83d8b2]">Preferences</p><h1 class="mt-2 text-3xl font-semibold tracking-[-.035em]">Settings</h1></header>
    <section class="mt-8"><p class="px-1 text-xs font-semibold uppercase tracking-[.12em] text-muted">Account</p><div class="surface mt-3 overflow-hidden rounded-2xl border border-line"><button class="flex w-full items-center gap-3 p-4 text-left"><img v-if="account?.avatarUrl && !avatarFailed" :src="account.avatarUrl" alt="Foto profil Google" class="size-10 rounded-full object-cover" referrerpolicy="no-referrer" @error="onAvatarError"/><span v-else class="grid size-10 place-items-center rounded-full bg-[#f0c8ad] text-sm font-bold text-[#714328]">{{ initial }}</span><span class="flex-1"><span class="block font-semibold">{{ account?.displayName || 'Akun Google' }}</span><span class="block text-sm text-muted">{{ account?.email || 'Masuk ulang untuk melihat akun' }}</span></span><ChevronRight :size="19" class="text-muted"/></button></div><button class="mt-3 flex w-full items-center justify-center gap-2 rounded-2xl py-3 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/25" @click="logout"><LogOut :size="17"/>Keluar dari akun</button></section>
    <section class="mt-8"><p class="px-1 text-xs font-semibold uppercase tracking-[.12em] text-muted">Plan</p><div class="surface mt-3 flex items-center gap-4 rounded-2xl border border-line p-4"><span class="grid size-11 place-items-center rounded-xl bg-nemu-50 text-sm font-bold text-nemu-600 dark:bg-nemu-500/20 dark:text-[#a9edcb]">{{ activePlan?.plan === 'personal' ? 'B' : activePlan?.plan === 'pro' ? 'PRO' : 'F' }}</span><span class="flex-1"><span class="block font-semibold">{{ activePlan?.plan === 'personal' ? 'Basic' : activePlan?.plan === 'pro' ? 'Pro' : 'Free' }}</span><span class="mt-1 block text-sm text-muted">{{ activePlan?.used ?? '…' }} dari {{ activePlan?.limit ?? '…' }} memory digunakan</span></span><span class="rounded-full bg-nemu-50 px-2.5 py-1 text-xs font-semibold text-nemu-600 dark:bg-nemu-500/20 dark:text-[#a9edcb]">Aktif</span><ChevronRight :size="19" class="text-muted"/></div></section>
    <section class="mt-8"><p class="px-1 text-xs font-semibold uppercase tracking-[.12em] text-muted">Security</p><div class="surface mt-3 overflow-hidden rounded-2xl border border-line"><button class="flex w-full items-center gap-3 border-b border-line p-4 text-left"><LockKeyhole :size="19" class="text-muted"/><span class="flex-1 font-medium">Biometric lock</span><span class="text-sm text-muted">Off</span><ChevronRight :size="19" class="text-muted"/></button><button class="flex w-full items-center gap-3 p-4 text-left" @click="toggle"><Moon :size="19" class="text-muted"/><span class="flex-1 font-medium">Dark mode</span><span class="text-sm text-muted">{{ dark ? 'On' : 'Off' }}</span></button></div></section>
    <section class="mt-8"><p class="px-1 text-xs font-semibold uppercase tracking-[.12em] text-muted">Data</p><div class="surface mt-3 overflow-hidden rounded-2xl border border-line"><RouterLink to="/trash" class="flex items-center gap-3 p-4"><Trash2 :size="19" class="text-muted"/><span class="flex-1 font-medium">Trash</span><ChevronRight :size="19" class="text-muted"/></RouterLink></div></section><p class="mt-9 text-center text-xs leading-5 text-muted">Memory kamu bersifat pribadi.<br/>NEMU v0.1</p>
  </div></AppShell>
</template>
