<script setup lang="ts">import { useAppStore } from '@/stores/app'; const app = useAppStore();</script>
<template><Transition name="fade"><div v-if="app.toast" role="status" class="fixed bottom-24 left-1/2 z-50 -translate-x-1/2 rounded-2xl bg-ink px-4 py-3 text-sm font-medium text-white shadow-lg md:bottom-7">{{ app.toast }}</div></Transition></template>
