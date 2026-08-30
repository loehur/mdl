<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../stores/auth";

const auth = useAuth();
const router = useRouter();
const password = ref("");
const loading = ref(false);
const error = ref("");

async function submit() {
  if (!password.value) {
    error.value = "Masukkan password";
    return;
  }
  loading.value = true;
  error.value = "";
  try {
    await auth.adminLogin(password.value);
    router.push("/admin/menu");
  } catch (e) {
    error.value = e.message || "Password salah";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="chip-card" style="max-width: 380px; margin: 3rem auto">
    <h2 class="section-label" style="justify-content: center">Admin</h2>
    <form @submit.prevent="submit">
      <input
        v-model="password"
        class="chip-input"
        type="password"
        placeholder="Password admin"
        style="margin-bottom: 0.75rem"
      />
      <p v-if="error" style="color: var(--chip-danger); font-size: 0.8125rem; margin-bottom: 0.75rem">{{ error }}</p>
      <button type="submit" class="chip-btn" :disabled="loading">
        {{ loading ? "Masuk..." : "Masuk" }}
      </button>
    </form>
    <p style="margin-top: 1rem; font-size: 0.75rem; color: var(--chip-muted)">
      <RouterLink to="/login" style="color: var(--chip-primary)">Kembali ke login user</RouterLink>
    </p>
  </div>
</template>
