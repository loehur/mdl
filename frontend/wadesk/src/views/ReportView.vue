<template>
  <div class="min-h-screen bg-ink-950 text-slate-100 font-body">
    <AppHeader page-title="Report" active="report" @logout="onLogout" />

    <div
      v-if="!auth.canSendWa"
      class="px-4 py-2 bg-amber-500/10 border-b border-amber-500/20 text-amber-200 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
    >
      <span>Anda belum masuk team — tidak bisa melihat report.</span>
      <router-link to="/admin" class="text-accent-soft hover:underline shrink-0">Masuk team di Admin →</router-link>
    </div>

    <div v-else class="max-w-5xl mx-auto p-4">
      <DailyReportPanel :enabled="auth.canSendWa" />
    </div>
  </div>
</template>

<script setup>
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import AppHeader from "../components/AppHeader.vue";
import DailyReportPanel from "../components/DailyReportPanel.vue";

const auth = useAuthStore();
const router = useRouter();

async function onLogout() {
  await auth.logout();
  router.push({ name: "login" });
}
</script>
