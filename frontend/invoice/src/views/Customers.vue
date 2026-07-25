<template>
  <div class="space-y-5 pb-6">
    <div class="flex gap-2">
      <input
        v-model="search"
        class="field-input flex-1"
        type="search"
        placeholder="Cari nama / HP / email"
      />
      <router-link to="/pelanggan/buat" class="btn-primary shrink-0 px-4 py-2 text-sm">
        + Tambah
      </router-link>
    </div>

    <PageLoader v-if="loading" />

    <EmptyState
      v-else-if="!customers.length"
      title="Belum ada pelanggan"
      subtitle="Tambahkan pelanggan sebelum membuat invoice."
    />

    <EmptyState
      v-else-if="!filtered.length"
      title="Tidak ditemukan"
      subtitle="Coba kata kunci lain."
    />

    <div v-else class="space-y-3">
      <div
        v-for="c in filtered"
        :key="c.id"
        class="glass flex items-start justify-between gap-3 p-4"
      >
        <router-link :to="`/pelanggan/edit/${c.id}`" class="min-w-0 flex-1">
          <p class="font-bold text-pearl">{{ c.name }}</p>
          <p class="mt-0.5 text-sm text-mist">{{ c.phone }}</p>
          <p v-if="c.email" class="mt-0.5 truncate text-xs text-mist">{{ c.email }}</p>
        </router-link>
        <button
          type="button"
          class="shrink-0 text-sm text-debit-dim"
          @click="openDelete(c)"
        >
          Hapus
        </button>
      </div>
    </div>

    <AlertBanner class="mt-2" :message="message" :type="isError ? 'error' : 'success'" />

    <ConfirmModal
      :open="confirmOpen"
      title="Hapus pelanggan?"
      message="Data pelanggan akan dihapus permanen."
      :detail="pendingDelete?.name || ''"
      confirm-label="Hapus"
      variant="danger"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="confirmOpen = false"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import AlertBanner from "../components/AlertBanner.vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import EmptyState from "../components/EmptyState.vue";
import PageLoader from "../components/PageLoader.vue";

const loading = ref(true);
const customers = ref([]);
const search = ref("");
const message = ref("");
const isError = ref(false);
const confirmOpen = ref(false);
const pendingDelete = ref(null);
const deleting = ref(false);

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return customers.value;
  return customers.value.filter((c) => {
    const hay = `${c.name} ${c.phone} ${c.email || ""}`.toLowerCase();
    return hay.includes(q);
  });
});

async function loadList() {
  loading.value = true;
  message.value = "";
  isError.value = false;
  try {
    const res = await fetch("/api/Invoice/Customers/list");
    const data = await res.json();
    if (res.ok && data.status) {
      customers.value = data.data.customers || [];
    } else {
      message.value = data.message || "Gagal memuat pelanggan";
      isError.value = true;
    }
  } catch {
    message.value = "Tidak dapat terhubung ke server";
    isError.value = true;
  } finally {
    loading.value = false;
  }
}

function openDelete(customer) {
  pendingDelete.value = customer;
  confirmOpen.value = true;
}

async function confirmDelete() {
  if (!pendingDelete.value) return;
  deleting.value = true;
  message.value = "";
  isError.value = false;
  try {
    const res = await fetch("/api/Invoice/Customers/delete", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: pendingDelete.value.id }),
    });
    const data = await res.json();
    if (!res.ok || !data.status) {
      message.value = data.message || "Gagal menghapus pelanggan";
      isError.value = true;
      return;
    }
    message.value = data.message || "Pelanggan dihapus";
    confirmOpen.value = false;
    pendingDelete.value = null;
    await loadList();
  } catch {
    message.value = "Tidak dapat terhubung ke server";
    isError.value = true;
  } finally {
    deleting.value = false;
  }
}

onMounted(loadList);
</script>
