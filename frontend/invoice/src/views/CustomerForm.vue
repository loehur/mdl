<template>
  <div class="space-y-5 pb-6">
    <PageLoader v-if="loading" />

    <section v-else class="glass-strong p-5">
      <h2 class="section-title mb-4">{{ isEdit ? "Edit Pelanggan" : "Data Pelanggan" }}</h2>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="field-label">Nama / Perusahaan *</label>
          <input
            v-model="form.name"
            class="field-input"
            required
            placeholder="Nama lengkap atau perusahaan"
            autocomplete="organization"
          />
        </div>
        <div>
          <label class="field-label">Nomor HP *</label>
          <input
            v-model="form.phone"
            class="field-input"
            required
            placeholder="08..."
            inputmode="tel"
            autocomplete="tel"
          />
        </div>
        <div>
          <label class="field-label">Email</label>
          <input
            v-model="form.email"
            class="field-input"
            type="email"
            placeholder="opsional"
            autocomplete="email"
          />
        </div>

        <button class="btn-primary mt-2 w-full" type="submit" :disabled="saving">
          {{ saving ? "Menyimpan..." : isEdit ? "Simpan Perubahan" : "Simpan Pelanggan" }}
        </button>

        <button
          type="button"
          class="btn-ghost w-full"
          @click="router.push('/pelanggan')"
        >
          Batal
        </button>

        <AlertBanner class="mt-2" :message="message" :type="isError ? 'error' : 'success'" />
      </form>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AlertBanner from "../components/AlertBanner.vue";
import PageLoader from "../components/PageLoader.vue";

const route = useRoute();
const router = useRouter();

const editId = computed(() => {
  const id = Number(route.params.id);
  return Number.isFinite(id) && id > 0 ? id : null;
});

const isEdit = computed(() => editId.value !== null);

const form = ref({
  name: "",
  phone: "",
  email: "",
});

const loading = ref(false);
const saving = ref(false);
const message = ref("");
const isError = ref(false);

async function loadCustomer() {
  if (!editId.value) return;

  loading.value = true;
  message.value = "";
  isError.value = false;

  try {
    const res = await fetch(`/api/Invoice/Customers/detail?id=${editId.value}`);
    const data = await res.json();

    if (!res.ok || !data.status) {
      message.value = data.message || "Pelanggan tidak ditemukan";
      isError.value = true;
      return;
    }

    form.value = {
      name: data.data.name || "",
      phone: data.data.phone || "",
      email: data.data.email || "",
    };
  } catch {
    message.value = "Gagal memuat pelanggan";
    isError.value = true;
  } finally {
    loading.value = false;
  }
}

async function onSubmit() {
  if (!form.value.name.trim() || !form.value.phone.trim()) {
    message.value = "Nama dan nomor HP wajib diisi";
    isError.value = true;
    return;
  }

  saving.value = true;
  message.value = "";
  isError.value = false;

  try {
    const payload = {
      name: form.value.name.trim(),
      phone: form.value.phone.trim(),
      email: form.value.email.trim(),
    };

    const url = isEdit.value
      ? "/api/Invoice/Customers/update"
      : "/api/Invoice/Customers/create";

    if (isEdit.value) {
      payload.id = editId.value;
    }

    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!res.ok || !data.status) {
      message.value =
        data.message || (isEdit.value ? "Gagal memperbarui pelanggan" : "Gagal menambah pelanggan");
      isError.value = true;
      return;
    }

    router.push("/pelanggan");
  } catch {
    message.value = "Tidak dapat terhubung ke server";
    isError.value = true;
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  if (isEdit.value) {
    loadCustomer();
  }
});
</script>
