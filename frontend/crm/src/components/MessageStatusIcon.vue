<script setup>
defineProps({
  status: {
    type: String,
    default: "",
  },
  light: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <span class="inline-flex items-center shrink-0" :class="light ? 'text-white' : ''">
    <span v-if="status === 'read'">
      <svg class="w-4 h-3 inline" viewBox="0 0 16 11" fill="none" aria-hidden="true">
        <path d="M11.07 0.73L4.51 7.29L1.79 4.57L0.38 5.98L4.51 10.12L12.48 2.14L11.07 0.73Z" fill="#53bdeb" />
        <path d="M14.07 0.73L7.51 7.29L6.79 6.57L5.38 7.98L7.51 10.12L15.48 2.14L14.07 0.73Z" fill="#53bdeb" />
      </svg>
    </span>
    <span v-else-if="status === 'delivered'">
      <svg class="w-4 h-3 inline" :class="light ? 'text-white/90' : 'text-[var(--wa-text-tertiary)]'" viewBox="0 0 16 11" fill="none" aria-hidden="true">
        <path d="M11.07 0.73L4.51 7.29L1.79 4.57L0.38 5.98L4.51 10.12L12.48 2.14L11.07 0.73Z" fill="currentColor" />
        <path d="M14.07 0.73L7.51 7.29L6.79 6.57L5.38 7.98L7.51 10.12L15.48 2.14L14.07 0.73Z" fill="currentColor" />
      </svg>
    </span>
    <span v-else-if="status === 'sent'">
      <svg class="w-3 h-3 inline" :class="light ? 'text-white/90' : 'text-[var(--wa-text-tertiary)]'" viewBox="0 0 12 11" fill="none" aria-hidden="true">
        <path d="M10.07 0.73L3.51 7.29L0.79 4.57L0 5.36L3.51 8.87L10.86 1.52L10.07 0.73Z" fill="currentColor" />
      </svg>
    </span>
    <span v-else>
      <svg class="w-3 h-3 inline" :class="light ? 'text-white/90' : 'text-[var(--wa-text-tertiary)]'" viewBox="0 0 12 12" fill="none" aria-hidden="true">
        <circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5" fill="none" />
        <path d="M6 3v3.5l2 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
      </svg>
    </span>
  </span>
</template>
