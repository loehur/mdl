<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../stores/auth";
import { api } from "../api";

const auth = useAuth();
const router = useRouter();

const users = ref([]);
const loading = ref(false);
const toastMsg = ref("");
let toastTimer = null;

const createUser = ref("");
const createChip = ref(0);
const createLoading = ref(false);

const deleteUser = ref("");
const deleteLoading = ref(false);

const resetOpen = ref(false);
const resetCoinOpen = ref(false);
const resetLoading = ref(false);

function showToast(msg) {
  toastMsg.value = msg;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (toastMsg.value = ""), 2500);
}

async function loadUsers() {
  loading.value = true;
  try {
    const res = await api("/Chip/Admin/list");
    users.value = res.data?.users || [];
  } catch (e) {
    showToast(e.message || "Gagal memuat user");
  } finally {
    loading.value = false;
  }
}

async function doCreate() {
  if (!createUser.value.trim()) {
    showToast("Isi username");
    return;
  }
  createLoading.value = true;
  try {
    await api("/Chip/Admin/create", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user: createUser.value, chip: Number(createChip.value) || 0 }),
    });
    createUser.value = "";
    createChip.value = 0;
    showToast("User dibuat");
    await loadUsers();
  } catch (e) {
    showToast(e.message || "Gagal membuat user");
  } finally {
    createLoading.value = false;
  }
}

async function doDelete() {
  if (!deleteUser.value) return;
  deleteLoading.value = true;
  try {
    await api("/Chip/Admin/delete", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user: deleteUser.value }),
    });
    deleteUser.value = "";
    showToast("User dihapus");
    await loadUsers();
  } catch (e) {
    showToast(e.message || "Gagal menghapus");
  } finally {
    deleteLoading.value = false;
  }
}

async function doReset() {
  resetLoading.value = true;
  try {
    await api("/Chip/Admin/reset", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ confirm: "yes" }),
    });
    resetOpen.value = false;
    showToast("Semua user & mutasi direset");
    await loadUsers();
  } catch (e) {
    showToast(e.message || "Gagal reset");
  } finally {
    resetLoading.value = false;
  }
}

async function doResetCoin() {
  resetLoading.value = true;
  try {
    await api("/Chip/Admin/resetCoin", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ confirm: "yes" }),
    });
    resetCoinOpen.value = false;
    showToast("Semua mutasi direset");
    await loadUsers();
  } catch (e) {
    showToast(e.message || "Gagal reset coin");
  } finally {
    resetLoading.value = false;
  }
}

async function doAdminLogout() {
  await auth.adminLogout();
  router.push("/admin");
}

onMounted(loadUsers);
</script>

<template>
  <div>
    <div class="section-label" style="justify-content: space-between">
      <span>Kelola User</span>
      <button type="button" style="background: none; border: none; color: var(--chip-danger); font-size: 0.75rem; font-weight: 600; cursor: pointer" @click="doAdminLogout">Logout Admin</button>
    </div>

    <!-- Create -->
    <div class="chip-card" style="margin-bottom: 1rem; text-align: left">
      <p style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.5rem">Tambah User (pisahkan koma untuk banyak)</p>
      <input v-model="createUser" class="chip-input" type="text" placeholder="budi, ani, citra" style="margin-bottom: 0.5rem" />
      <input v-model.number="createChip" class="chip-input" type="number" placeholder="Chip awal (0)" min="0" style="margin-bottom: 0.5rem" />
      <button type="button" class="chip-btn" :disabled="createLoading" @click="doCreate">
        {{ createLoading ? "Menyimpan..." : "Buat" }}
      </button>
    </div>

    <!-- List -->
    <div class="section-label">Daftar User</div>
    <p v-if="loading" style="color: var(--chip-muted); font-size: 0.8125rem">Memuat…</p>
    <div v-else class="admin-list">
      <div v-for="u in users" :key="u.user" class="admin-item">
        <div class="admin-info">
          <div class="admin-name">{{ u.user }}</div>
          <div class="admin-sub">Awal: {{ Number(u.chip_awal).toLocaleString("id-ID") }} · Saldo: {{ Number(u.saldo).toLocaleString("id-ID") }}</div>
        </div>
        <button type="button" class="admin-del" @click="deleteUser = u.user">Hapus</button>
      </div>
      <p v-if="!users.length" class="feed-empty">Belum ada user</p>
    </div>

    <!-- Danger zone -->
    <div class="section-label" style="margin-top: 1.5rem">Zona Berbahaya</div>
    <div style="display: flex; gap: 0.5rem">
      <button type="button" class="chip-btn chip-btn--danger" @click="resetCoinOpen = true">Reset Coin</button>
      <button type="button" class="chip-btn chip-btn--danger" @click="resetOpen = true">Reset Semua</button>
    </div>

    <!-- Modal delete -->
    <Teleport to="body">
      <div v-if="deleteUser" class="modal-backdrop" @click="deleteUser = ''"></div>
      <div v-if="deleteUser" class="modal-panel">
        <h3 style="font-size: 1rem; margin-bottom: 0.75rem">Hapus user <b>{{ deleteUser }}</b>?</h3>
        <div style="display: flex; gap: 0.5rem">
          <button type="button" class="chip-btn" style="flex: 1" @click="deleteUser = ''">Batal</button>
          <button type="button" class="chip-btn chip-btn--danger" style="flex: 1" :disabled="deleteLoading" @click="doDelete">
            {{ deleteLoading ? "Menghapus..." : "Ya, Hapus" }}
          </button>
        </div>
      </div>
    </Teleport>

    <!-- Modal reset semua -->
    <Teleport to="body">
      <div v-if="resetOpen" class="modal-backdrop" @click="!resetLoading && (resetOpen = false)"></div>
      <div v-if="resetOpen" class="modal-panel">
        <h3 style="font-size: 1rem; margin-bottom: 0.5rem">Reset Semua</h3>
        <p style="font-size: 0.8125rem; color: var(--chip-muted); margin-bottom: 1rem">Hapus SEMUA user dan seluruh mutasi. Tindakan tidak bisa dibatalkan!</p>
        <div style="display: flex; gap: 0.5rem">
          <button type="button" class="chip-btn" style="flex: 1" :disabled="resetLoading" @click="resetOpen = false">Batal</button>
          <button type="button" class="chip-btn chip-btn--danger" style="flex: 1" :disabled="resetLoading" @click="doReset">
            {{ resetLoading ? "Mereset..." : "Ya, Reset" }}
          </button>
        </div>
      </div>
    </Teleport>

    <!-- Modal reset coin -->
    <Teleport to="body">
      <div v-if="resetCoinOpen" class="modal-backdrop" @click="!resetLoading && (resetCoinOpen = false)"></div>
      <div v-if="resetCoinOpen" class="modal-panel">
        <h3 style="font-size: 1rem; margin-bottom: 0.5rem">Reset Coin</h3>
        <p style="font-size: 0.8125rem; color: var(--chip-muted); margin-bottom: 1rem">Hapus semua mutasi. Saldo kembali ke chip awal tiap user.</p>
        <div style="display: flex; gap: 0.5rem">
          <button type="button" class="chip-btn" style="flex: 1" :disabled="resetLoading" @click="resetCoinOpen = false">Batal</button>
          <button type="button" class="chip-btn chip-btn--danger" style="flex: 1" :disabled="resetLoading" @click="doResetCoin">
            {{ resetLoading ? "Mereset..." : "Ya, Reset" }}
          </button>
        </div>
      </div>
    </Teleport>

    <div class="chip-toast" :class="{ show: toastMsg }">{{ toastMsg }}</div>
  </div>
</template>

<style scoped>
.admin-list {
  display: flex;
  flex-direction: column;
  gap: 0.4375rem;
}

.admin-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.75rem 0.875rem;
  background: linear-gradient(145deg, #151518 0%, #111113 100%);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 11px;
}

.admin-name {
  font-size: 0.9375rem;
  font-weight: 600;
  text-transform: capitalize;
}

.admin-sub {
  font-size: 0.6875rem;
  color: var(--chip-muted);
  margin-top: 0.125rem;
}

.admin-del {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--chip-danger);
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.admin-del:hover {
  background: rgba(239, 68, 68, 0.2);
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1000;
}

.modal-panel {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: min(92vw, 380px);
  background: linear-gradient(180deg, #111113 0%, #1a1a1e 100%);
  border: 1px solid var(--chip-border);
  border-radius: var(--chip-radius-lg);
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.5);
  z-index: 1001;
  padding: 1.25rem;
}

.feed-empty {
  text-align: center;
  padding: 2.5rem 1rem;
  color: #71717a;
  font-size: 0.875rem;
}
</style>
