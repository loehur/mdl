<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useAuth } from "../stores/auth";
import { api } from "../api";

const auth = useAuth();

const players = ref([]);
const mutasi = ref([]);
const loadingPlayers = ref(false);
const loadingMutasi = ref(false);

const transferOpen = ref(false);
const transferTarget = ref("");
const transferAmount = ref("");
const transferLoading = ref(false);
const transferError = ref("");
const toastMsg = ref("");
let toastTimer = null;

const saldo = computed(() => Number(auth.state.saldo) || 0);
const isLow = computed(() => saldo.value <= 300);

const NAME_COLORS = ["#6366f1", "#eab308", "#8b5cf6", "#ec4899", "#06b6d4", "#f97316", "#14b8a6", "#3b82f8"];

function initials(name) {
  const parts = String(name || "").trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return String(name || "?").slice(0, 2).toUpperCase();
}

function colorFor(name) {
  let h = 0;
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
  return NAME_COLORS[h % NAME_COLORS.length];
}

function showToast(msg) {
  toastMsg.value = msg;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (toastMsg.value = ""), 2500);
}

async function loadPlayers() {
  loadingPlayers.value = true;
  try {
    const res = await api("/Chip/Room/players");
    players.value = res.data?.players || [];
  } catch (e) {
    showToast(e.message || "Gagal memuat pemain");
  } finally {
    loadingPlayers.value = false;
  }
}

async function loadMutasi() {
  loadingMutasi.value = true;
  try {
    const res = await api("/Chip/Room/mutasi");
    mutasi.value = res.data?.items || [];
  } catch (e) {
    /* silent */
  } finally {
    loadingMutasi.value = false;
  }
}

async function refreshAll() {
  try {
    await auth.refreshMe();
  } catch (_) {
    /* ignore */
  }
  await Promise.all([loadPlayers(), loadMutasi()]);
}

function openTransfer(player) {
  transferTarget.value = player.user;
  transferAmount.value = "";
  transferError.value = "";
  transferOpen.value = true;
}

function closeTransfer() {
  transferOpen.value = false;
  transferTarget.value = "";
  transferAmount.value = "";
}

async function doTransfer() {
  const chip = parseInt(transferAmount.value, 10);
  if (!chip || chip <= 0) {
    transferError.value = "Masukkan jumlah chip";
    return;
  }
  transferLoading.value = true;
  transferError.value = "";
  try {
    await api("/Chip/Room/transfer", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ t: transferTarget.value, c: chip }),
    });
    closeTransfer();
    showToast("Transfer berhasil!");
    window.dispatchEvent(new CustomEvent("chip:ws"));
    await refreshAll();
  } catch (e) {
    transferError.value = e.message || "Transfer gagal";
  } finally {
    transferLoading.value = false;
  }
}

function onWs() {
  refreshAll();
}

onMounted(() => {
  refreshAll();
  window.addEventListener("chip:ws", onWs);
});

onUnmounted(() => {
  window.removeEventListener("chip:ws", onWs);
  clearTimeout(toastTimer);
});
</script>

<template>
  <div>
    <!-- Hero saldo -->
    <div class="chip-hero">
      <div class="chip-hero-label">Saldo Anda</div>
      <div class="chip-hero-user">
        <div class="chip-avatar" :style="{ background: colorFor(auth.state.user) }">{{ initials(auth.state.user) }}</div>
        <span class="chip-hero-name" :style="{ color: colorFor(auth.state.user) }">{{ auth.state.user }}</span>
      </div>
      <div class="chip-hero-value" :class="{ low: isLow }">{{ saldo.toLocaleString("id-ID") }}</div>
      <div v-if="isLow" class="chip-hero-badge">⚠ Saldo Kritis</div>
    </div>

    <!-- Pemain lain -->
    <div class="section-label">Pemain Lain — ketuk untuk transfer</div>
    <p v-if="loadingPlayers" style="color: var(--chip-muted); font-size: 0.8125rem">Memuat…</p>
    <div v-else class="chip-friends">
      <button
        v-for="p in players"
        :key="p.user"
        type="button"
        class="chip-box friend"
        @click="openTransfer(p)"
      >
        <div class="chip-box-top">
          <div class="avatar-sm" :style="{ background: colorFor(p.user) }">{{ initials(p.user) }}</div>
          <div class="name" :style="{ color: colorFor(p.user) }">{{ p.user }}</div>
        </div>
        <div class="value" :class="{ low: Number(p.saldo) <= 300 }">{{ Number(p.saldo).toLocaleString("id-ID") }}</div>
        <div class="chip-tap-hint">Tap untuk kirim</div>
      </button>
      <p v-if="!players.length" style="color: var(--chip-muted); font-size: 0.8125rem">Belum ada pemain lain.</p>
    </div>

    <!-- Riwayat -->
    <div class="section-label" style="margin-top: 1.5rem">Transaksi Terakhir</div>
    <div class="feed-list">
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

    <!-- Modal transfer -->
    <Teleport to="body">
      <div v-if="transferOpen" class="offcanvas-backdrop" @click="closeTransfer"></div>
      <div v-if="transferOpen" class="offcanvas-panel">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title">
            Transfer ke
            <b>{{ transferTarget.toUpperCase() }}</b>
          </h5>
          <button type="button" class="btn-close" @click="closeTransfer">&times;</button>
        </div>
        <div class="offcanvas-body">
          <input
            v-model="transferAmount"
            class="transfer-input"
            type="number"
            placeholder="0"
            inputmode="numeric"
            min="1"
          />
          <p v-if="transferError" style="color: var(--chip-danger); font-size: 0.8125rem; margin-top: 0.5rem">{{ transferError }}</p>
          <button type="button" class="btn-transfer" :disabled="transferLoading" @click="doTransfer">
            {{ transferLoading ? "Mengirim..." : "Kirim Chip" }}
          </button>
        </div>
      </div>
    </Teleport>

    <div class="chip-toast" :class="{ show: toastMsg }">{{ toastMsg }}</div>
  </div>
</template>

<style scoped>
.chip-hero {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 40%, rgba(59, 130, 246, 0.08) 100%);
  border: 1px solid rgba(99, 102, 241, 0.25);
  border-radius: 20px;
  padding: 1.75rem 1.5rem;
  text-align: center;
  margin-bottom: 1.25rem;
  box-shadow: 0 8px 32px rgba(99, 102, 241, 0.12);
}

.chip-hero::before {
  content: "";
  position: absolute;
  top: -50%;
  right: -20%;
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, rgba(139, 92, 246, 0.2), transparent 70%);
  pointer-events: none;
}

.chip-hero-label {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #a5b4fc;
  margin-bottom: 0.5rem;
}

.chip-hero-user {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.chip-avatar {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  color: #fff;
}

.chip-hero-name {
  font-size: 1rem;
  font-weight: 600;
  text-transform: capitalize;
}

.chip-hero-value {
  font-family: var(--chip-mono);
  font-size: clamp(2rem, 8vw, 2.75rem);
  font-weight: 700;
  letter-spacing: -0.03em;
  color: #fafafa;
  line-height: 1.1;
}

.chip-hero-value.low {
  color: #fbbf24;
  text-shadow: 0 0 20px rgba(251, 191, 36, 0.4);
  animation: blink 0.7s linear infinite;
}

@keyframes blink {
  50% {
    opacity: 0.4;
  }
}

.chip-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.75rem;
  padding: 0.25rem 0.625rem;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-radius: 999px;
}

.chip-friends {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
}

@media (min-width: 768px) {
  .chip-friends {
    grid-template-columns: repeat(3, 1fr);
    gap: 0.875rem;
  }
}

.chip-box {
  position: relative;
  background: linear-gradient(145deg, #151518 0%, #111113 100%);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 14px;
  padding: 1rem 0.875rem;
  text-align: center;
  font-family: inherit;
  color: inherit;
  cursor: pointer;
  transition: all 0.2s ease;
}

.chip-box.friend:hover {
  border-color: rgba(99, 102, 241, 0.35);
  background: linear-gradient(145deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.06) 100%);
  box-shadow: 0 6px 20px rgba(99, 102, 241, 0.15);
  transform: translateY(-2px);
}

.chip-box-top {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  margin-bottom: 0.5rem;
}

.avatar-sm {
  width: 24px;
  height: 24px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.5625rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

.chip-box .name {
  font-size: 0.8125rem;
  font-weight: 600;
  text-transform: capitalize;
  letter-spacing: 0.02em;
}

.chip-box .value {
  font-family: var(--chip-mono);
  font-size: 1.375rem;
  font-weight: 600;
  letter-spacing: -0.02em;
  color: #fafafa;
}

.chip-box .value.low {
  color: #fbbf24;
}

.chip-tap-hint {
  font-size: 0.625rem;
  color: #52525b;
  margin-top: 0.375rem;
}

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

/* Offcanvas */
.offcanvas-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1000;
}

.offcanvas-panel {
  position: fixed;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: var(--viewer-max);
  background: linear-gradient(180deg, #111113 0%, #1a1a1e 100%);
  border: 1px solid var(--chip-border);
  border-top: none;
  border-radius: 0 0 var(--chip-radius-lg) var(--chip-radius-lg);
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.5), 0 0 60px rgba(99, 102, 241, 0.08);
  z-index: 1001;
  overflow: hidden;
}

.offcanvas-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1rem 1.25rem 0.75rem;
}

.offcanvas-title {
  font-size: 0.9375rem;
  font-weight: 500;
  color: var(--chip-muted);
  line-height: 1.5;
}

.offcanvas-title b {
  display: block;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--chip-danger);
  margin-top: 0.125rem;
}

.btn-close {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--chip-surface);
  border: 1px solid var(--chip-border);
  border-radius: 10px;
  color: var(--chip-muted);
  font-size: 1.25rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-close:hover {
  color: var(--chip-text);
  border-color: var(--chip-border-hover);
}

.offcanvas-body {
  padding: 0.75rem 1.25rem 1.25rem;
}

.transfer-input {
  width: 100%;
  height: 96px;
  padding: 0 1rem;
  font-family: var(--chip-mono);
  font-size: 2.5rem;
  font-weight: 600;
  text-align: center;
  background: var(--chip-input);
  border: 1px solid var(--chip-border);
  border-radius: var(--chip-radius);
  color: var(--chip-text);
  transition: all 0.2s ease;
}

.transfer-input:focus {
  outline: none;
  border-color: rgba(99, 102, 241, 0.5);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.btn-transfer {
  width: 100%;
  margin-top: 0.875rem;
  padding: 1rem 1.5rem;
  font-size: 1rem;
  font-weight: 600;
  font-family: inherit;
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  border: none;
  border-radius: var(--chip-radius);
  color: white;
  cursor: pointer;
  box-shadow: 0 4px 20px rgba(239, 68, 68, 0.35);
  transition: all 0.2s ease;
}

.btn-transfer:hover {
  transform: translateY(-1px);
}

.btn-transfer:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}
</style>
