<template>
  <div v-if="message" class="alert" :class="type === 'error' ? 'alert-error' : 'alert-success'">
    {{ message }}
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: "" },
  type: { type: String, default: "success" },
});
</script>
