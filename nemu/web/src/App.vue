<script setup lang="ts">
import { initializeTheme } from './services/theme-service';
import { RouterView } from 'vue-router';
initializeTheme();
</script>

<template><RouterView /></template>
