<template>
  <div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-ink-200 bg-ink-100">
      <slot name="icon">
        <svg class="h-6 w-6 text-mist" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="12" cy="12" r="9" />
          <path d="M8 12h8" stroke-linecap="round" />
        </svg>
      </slot>
    </div>
    <p class="font-display text-lg text-pearl/80">{{ title }}</p>
    <p v-if="subtitle" class="mt-1 max-w-xs text-sm text-mist">{{ subtitle }}</p>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: "" },
});
</script>
