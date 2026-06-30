<script setup>
defineProps({
  padding: {
    type: String,
    default: 'regular' // small, regular, large
  },
  hover: {
    type: Boolean,
    default: false
  }
})
</script>

<template>
  <div class="card" :class="[`p-${padding}`, { 'hover-effect': hover }]">
    <slot />
  </div>
</template>

<style scoped>
.card {
  background-color: var(--color-white);
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  overflow: hidden;
}

.p-small {
  padding: 16px;
}

.p-regular {
  padding: 24px;
}

.p-large {
  padding: 32px;
}

.hover-effect:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
}
</style>
