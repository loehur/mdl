<template>
  <button
    type="button"
    class="relative inline-flex items-center justify-center rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 hover:text-slate-100 transition"
    :class="compact ? 'h-8 w-8' : 'h-9 w-9'"
    :title="title"
    :aria-label="title"
    @click="theme.toggle()"
  >
    <!-- Sun when effective theme is dark (next click → light / shows day icon to switch) -->
    <!-- Show icon of what you CAN switch TO, or current? Previous: show opposite.
         Better: show CURRENT effective look + auto badge -->
    <svg
      v-if="theme.isLight"
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.8"
      class="h-4 w-4"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="4" />
      <path
        stroke-linecap="round"
        d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32l1.41-1.41"
      />
    </svg>
    <svg
      v-else
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.8"
      class="h-4 w-4"
      aria-hidden="true"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M21 14.3A8.5 8.5 0 119.7 3a7 7 0 0011.3 11.3z"
      />
    </svg>
    <span
      v-if="theme.isAuto"
      class="absolute top-0.5 right-0.5 h-1.5 w-1.5 rounded-full bg-accent"
      title="Otomatis"
      aria-hidden="true"
    />
  </button>
</template>

<script setup>
import { computed } from "vue";
import { useThemeStore } from "../stores/theme";

defineProps({
  compact: { type: Boolean, default: false },
});

const theme = useThemeStore();

const title = computed(() => {
  if (theme.isAuto) {
    return theme.isLight
      ? "Otomatis · siang (terang). Klik → paksa terang"
      : "Otomatis · malam (gelap). Klik → paksa terang";
  }
  if (theme.mode === "light") return "Mode terang (manual). Klik → gelap";
  return "Mode gelap (manual). Klik → otomatis";
});
</script>
