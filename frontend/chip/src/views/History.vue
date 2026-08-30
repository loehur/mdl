<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { api } from "../api";

const mutasi = ref([]);
const loading = ref(false);
const toastMsg = ref("");
let toastTimer = null;

function showToast(msg) {
  toastMsg.value = msg;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (toastMsg.value = ""), 2500);
}

async function loadMutasi() {
  loading.value = true;
  try {
    const res = await api("/Chip/Room/mutasi");
    mutasi.value = res.data?.items || [];
  } catch (e) {
    showToast(e.message || "Gagal memuat riwayat");
  } finally {
    loading.value = false;
  }
}

function onWs() {
  loadMutasi();
}

onMounted(() => {
  loadMutasi();
  window.addEventListener("chip:ws", onWs);
});

onUnmounted(() => {
  window.removeEventListener("chip:ws", onWs);
  clearTimeout(toastTimer);
});
</script>

<template>
  <div>
    <div class="section-label">Transaksi Terakhir</div>
    <p v-if="loading" style="color: var(--chip-muted); font-size: 0.875rem; padding: 1rem 0">Memuat…</p>
    <div v-else class="feed-list">
      <div v-for="m in mutasi" :key="m.id" class="feed-item">
        <div class="feed-arrow">→</div>
        <div class="feed-body">
          <div class="feed-route">
            <span class="from">{{ m.f }}</span>
            <span class="sep">→</span>
            <span class="to">{{ m.t }}</span>
          </div>
          <div class="feed-time">{{ m.insertTime }}</div>
        </div>
        <div class="feed-amount">{{ Number(m.chip).toLocaleString("id-ID") }}</div>
      </div>
      <p v-if="!mutasi.length" class="feed-empty">Belum ada aktivitas transfer</p>
    </div>

    <div class="chip-toast" :class="{ show: toastMsg }">{{ toastMsg }}</div>
  </div>
</template>

<style scoped>
.feed-list {
  display: flex;
  flex-direction: column;
  gap: 0.4375rem;
  max-height: 70vh;
  overflow-y: auto;
}

.feed-item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.75rem 0.875rem;
  background: linear-gradient(145deg, #151518 0%, #111113 100%);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 11px;
}

.feed-item:first-child {
  border-color: rgba(99, 102, 241, 0.25);
}

.feed-arrow {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: rgba(99, 102, 241, 0.08);
  border: 1px solid rgba(99, 102, 241, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #4f46e5;
  font-size: 1rem;
}

.feed-body {
  flex: 1;
  min-width: 0;
}

.feed-route {
  font-size: 0.8125rem;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.feed-route .from {
  color: #f87171;
}

.feed-route .to {
  color: #4ade80;
}

.feed-route .sep {
  color: #71717a;
  margin: 0 0.25rem;
}

.feed-time {
  font-size: 0.625rem;
  color: #71717a;
  margin-top: 0.125rem;
}

.feed-amount {
  font-family: var(--chip-mono);
  font-size: 0.875rem;
  font-weight: 700;
  color: #fafafa;
  flex-shrink: 0;
  background: #1c1c1f;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.feed-empty {
  text-align: center;
  padding: 2.5rem 1rem;
  color: #71717a;
  font-size: 0.875rem;
}
</style>
