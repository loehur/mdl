<template>
  <main
    class="mx-auto max-w-md px-4 py-10 bg-gradient-to-br from-slate-50 via-indigo-50 to-cyan-50 min-h-screen"
  >
    <h2 class="text-3xl font-bold mb-2 text-slate-800">Masuk Admin</h2>
    <p class="text-sm text-slate-600 mb-6">
      Masuk dengan nomor telepon dan kata sandi
    </p>
    <form class="grid gap-4" @submit.prevent="onSubmit">
      <input
        v-model="phone_number"
        class="border rounded-lg px-3 py-3"
        type="tel"
        placeholder="Nomor telepon (diawali 08)"
        required
      />
      <input
        v-model="password"
        class="border rounded-lg px-3 py-3"
        type="password"
        placeholder="Kata sandi"
        required
      />
      <button
        class="px-4 py-3 rounded-lg bg-gradient-to-r from-indigo-600 to-cyan-600 hover:from-indigo-700 hover:to-cyan-700 text-white disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        type="submit"
        :disabled="isLoading"
      >
        <svg
          v-if="isLoading"
          class="h-5 w-5 animate-spin text-white"
          viewBox="0 0 24 24"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          />
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
          />
        </svg>
        <span>{{ isLoading ? "Memproses..." : "Masuk" }}</span>
      </button>
    </form>
    <div
      v-if="otpRequired"
      class="mt-6 grid gap-3 p-4 bg-white/60 backdrop-blur rounded-lg ring-1 ring-white/40"
    >
      <label class="text-sm text-gray-700">Masukkan Kode OTP</label>
      <input
        v-model="otp"
        class="border rounded-lg px-3 py-3"
        type="text"
        placeholder="6 digit OTP"
        maxlength="6"
      />
      <button
        class="px-4 py-3 rounded-lg bg-gradient-to-r from-indigo-600 to-cyan-600 hover:from-indigo-700 hover:to-cyan-700 text-white disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        :disabled="isVerifying"
        @click="onVerify"
      >
        <svg
          v-if="isVerifying"
          class="h-5 w-5 animate-spin text-white"
          viewBox="0 0 24 24"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          />
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
          />
        </svg>
        <span>{{ isVerifying ? "Memverifikasi..." : "Verifikasi OTP" }}</span>
      </button>
      <p class="text-xs text-slate-700">
        OTP telah dikirim ke nomor: {{ phone_number }}
      </p>
      <p v-if="devOtp" class="text-xs text-orange-600">
        [DEV] Kode OTP: {{ devOtp }}
      </p>
    </div>
    <p
      v-if="message"
      class="mt-4"
      :class="isError ? 'text-red-700' : 'text-green-700'"
    >
      {{ message }}
    </p>
  </main>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { apiUrl } from "../api.js";
const router = useRouter();
const phone_number = ref("");
const password = ref("");
const message = ref("");
const isLoading = ref(false);
const isError = ref(false);
const otpRequired = ref(false);
const otp = ref("");
const isVerifying = ref(false);
const devOtp = ref("");

async function onSubmit() {
  message.value = "";
  isLoading.value = true;
  try {
    const res = await fetch(apiUrl("/Admin/Auth/login"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        phone_number: phone_number.value,
        password: password.value,
      }),
    });
    const data = await res.json().catch(() => ({ success: false }));
    if (!res.ok || !data.success) {
      message.value = data.message || "Login gagal";
      isError.value = true;
      isLoading.value = false;
      return;
    }
    if (data.otp_required) {
      message.value = data.message || "OTP dikirim";
      isError.value = false;
      otpRequired.value = true;
      devOtp.value = data.dev_otp || "";
      isLoading.value = false;
      return;
    }
    message.value = data.message || "Login berhasil";
    isError.value = false;
    isLoading.value = false;
  } catch (e) {
    message.value = "Network error";
    isError.value = true;
    isLoading.value = false;
  }
}

async function onVerify() {
  message.value = "";
  isVerifying.value = true;
  try {
    const res = await fetch(apiUrl("/Admin/Auth/verify-otp"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        phone_number: phone_number.value,
        otp: otp.value,
      }),
    });
    const data = await res.json().catch(() => ({ success: false }));
    if (!res.ok || !data.success) {
      message.value = data.message || "Verifikasi gagal";
      isError.value = true;
      isVerifying.value = false;
      return;
    }
    try {
      if (data.user) {
        localStorage.setItem("mdl_user", JSON.stringify(data.user));
      }
    } catch {}
    message.value = data.message || "Berhasil";
    isError.value = false;
    await router.push(data.redirect || "/dashboard");
    isVerifying.value = false;
  } catch (e) {
    message.value = "Network error";
    isError.value = true;
    isVerifying.value = false;
  }
}
</script>

<style></style>
