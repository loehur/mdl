<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../stores/auth";

const auth = useAuth();
const router = useRouter();
const username = ref("");
const loading = ref(false);
const error = ref("");

async function submit() {
  const u = username.value.trim();
  if (!u) {
    error.value = "Masukkan username";
    return;
  }
  loading.value = true;
  error.value = "";
  try {
    await auth.login(u);
    router.push("/room");
  } catch (e) {
    error.value = e.message || "Login gagal";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="chip-card" style="max-width: 380px; margin: 3rem auto">
    <h2 class="section-label" style="justify-content: center">Masuk</h2>
    <form @submit.prevent="submit">
      <input
        v-model="username"
        class="chip-input"
        type="text"
        placeholder="Username"
        autocomplete="username"
        autocapitalize="none"
        spellcheck="false"
        style="margin-bottom: 0.75rem"
      />
      <p v-if="error" style="color: var(--chip-danger); font-size: 0.8125rem; margin-bottom: 0.75rem">{{ error }}</p>
      <button type="submit" class="chip-btn" :disabled="loading">
        {{ loading ? "Masuk..." : "Masuk" }}
      </button>
    </form>
    <p style="margin-top: 1rem; font-size: 0.75rem; color: var(--chip-muted)">
      Admin? <RouterLink to="/admin" style="color: var(--chip-primary)">Login admin</RouterLink>
    </p>
  </div>
</template>
