<script setup>
defineProps({
  variant: {
    type: String,
    default: 'primary', // primary, secondary, outline, white
    validator: (value) => ['primary', 'secondary', 'outline', 'white'].includes(value)
  },
  href: {
    type: String,
    default: null
  }
})
</script>

<template>
  <component 
    :is="href ? 'a' : 'button'" 
    :href="href"
    class="btn"
    :class="`btn-${variant}`"
  >
    <slot />
  </component>
</template>

<style scoped>
.btn {
  display: inline-block;
  padding: 12px 24px;
  border-radius: 50px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.3s ease;
  border: 2px solid transparent;
  text-align: center;
}

.btn-primary {
  background-color: var(--color-primary);
  color: var(--color-white);
  box-shadow: 0 4px 15px rgba(30, 144, 255, 0.3);
}

.btn-primary:hover {
  background-color: #187bcd;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(30, 144, 255, 0.4);
}

.btn-secondary {
  background-color: var(--color-accent);
  color: var(--color-white);
  box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
}

.btn-secondary:hover {
  background-color: #43a047;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
}

.btn-outline {
  background-color: transparent;
  border-color: var(--color-primary);
  color: var(--color-primary);
}

.btn-outline:hover {
  background-color: var(--color-primary);
  color: var(--color-white);
}

.btn-white {
    background-color: var(--color-white);
    color: var(--color-primary);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.btn-white:hover {
    background-color: #f0f0f0;
    transform: translateY(-2px);
}
</style>
