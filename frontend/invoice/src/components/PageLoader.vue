<template>
  <div class="flex items-center justify-center py-10" role="status" aria-label="Memuat">
    <span class="page-loader" />
  </div>
</template>

<style scoped>
.page-loader {
  width: 2rem;
  height: 2rem;
  border: 3px solid rgba(124, 92, 191, 0.2);
  border-top-color: rgba(124, 92, 191, 0.9);
  border-radius: 50%;
  animation: spin 0.65s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
