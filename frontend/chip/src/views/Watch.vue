<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { api } from "../api";

const board = ref({ players: [], total: 0, low_count: 0, player_count: 0 });
const history = ref([]);
const loading = ref(false);
const activeTab = ref("board");
const toastMsg = ref("");
let toastTimer = null;

const NAME_COLORS = ["#6366f1", "#eab308", "#8b5cf6", "#ec4899", "#06b6d4", "#f97316", "#14b8a6", "#3b82f8"];
const MEDALS = ["🥇", "🥈", "🥉"];

const players = computed(() => board.value.players || []);
const maxChip = computed(() => Math.max(1, ...players.value.map((p) => Number(p.chip) || 0)));

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

async function loadAll() {
  loading.value = true;
  try {
    const [b, h] = await Promise.all([api("/Chip/Watch/board"), api("/Chip/Watch/history")]);
    board.value = b.data || board.value;
    history.value = h.data?.items || [];
  } catch (e) {
    showToast(e.message || "Gagal memuat");
  } finally {
    loading.value = false;
  }
}

function onWs() {
  loadAll();
}

onMounted(() => {
  loadAll();
  window.addEventListener("chip:ws", onWs);
});

onUnmounted(() => {
  window.removeEventListener("chip:ws", onWs);
  clearTimeout(toastTimer);
});
</script>

<template>
  <div>
    <nav class="chip-tabs">
      <button type="button" class="chip-tab" :class="{ active: activeTab === 'board' }" @click="activeTab = 'board'">Leaderboard</button>
      <button type="button" class="chip-tab" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">Riwayat</button>
    </nav>

    <template v-if="activeTab === 'board'">
      <div class="stat-row">
        <div class="chip-card"><p class="stat-label">Total</p><p class="stat-value">{{ board.total.toLocaleString("id-ID") }}</p></div>
        <div class="chip-card"><p class="stat-label">Pemain</p><p class="stat-value">{{ board.player_count }}</p></div>
        <div class="chip-card"><p class="stat-label">Kritis</p><p class="stat-value" style="color: #fbbf24">{{ board.low_count }}</p></div>
      </div>

      <div v-if="loading" style="color: var(--chip-muted); font-size: 0.875rem; padding: 1rem 0">Memuat…</div>
      <div v-else-if="!players.length" class="lb-empty">Belum ada pemain terdaftar</div>
      <div v-else class="lb-list">
        <div v-for="(p, i) in players" :key="p.user" class="lb-item">
          <div class="lb-rank" :class="{ top: i < 3 }">{{ i < 3 ? MEDALS[i] : i + 1 }}</div>
          <div class="lb-avatar" :style="{ background: colorFor(p.user) }">{{ initials(p.user) }}</div>
          <div class="lb-info">
            <div class="lb-name" :style="{ color: colorFor(p.user) }">{{ p.user }}</div>
            <div class="lb-bar-wrap">
              <div class="lb-bar" :style="{ width: Math.round((Number(p.chip) / maxChip) * 100) + '%' }"></div>
            </div>
          </div>
          <div class="lb-chip" :class="{ low: Number(p.chip) <= 300 }">{{ Number(p.chip).toLocaleString("id-ID") }}</div>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="section-label">Aktivitas Transfer</div>
      <div class="feed-list">
        <div v-for="m in history" :key="m.id" class="feed-item">
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
        <p v-if="!history.length" class="feed-empty">Belum ada aktivitas transfer</p>
      </div>
    </template>

    <div class="chip-toast" :class="{ show: toastMsg }">{{ toastMsg }}</div>
  </div>
</template>

<style scoped>
.chip-tabs {
  display: flex;
  gap: 0.375rem;
  padding: 0.25rem;
  background: var(--chip-surface);
  border: 1px solid var(--chip-border);
  border-radius: var(--chip-radius);
  margin-bottom: 1.25rem;
}

.chip-tab {
  flex: 1;
  padding: 0.625rem 0.75rem;
  font-size: 0.8125rem;
  font-weight: 600;
  font-family: inherit;
  color: var(--chip-muted);
  background: transparent;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.25s ease;
}

.chip-tab:hover {
  color: var(--chip-text);
}

.chip-tab.active {
  color: var(--chip-text);
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.12) 100%);
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.15);
}

.stat-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}

.stat-label {
  font-size: 0.6875rem;
  color: var(--chip-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.stat-value {
  font-family: var(--chip-mono);
  font-size: 1.375rem;
  font-weight: 700;
  margin-top: 0.25rem;
}

.lb-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.lb-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 0.875rem;
  background: linear-gradient(145deg, #151518 0%, #111113 100%);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
}

.lb-item:nth-child(1) {
  border-color: rgba(234, 179, 8, 0.35);
  background: linear-gradient(145deg, rgba(120, 90, 20, 0.25) 0%, #151518 100%);
}

.lb-item:nth-child(3) {
  border-color: rgba(180, 83, 9, 0.25);
}

.lb-rank {
  width: 28px;
  text-align: center;
  font-size: 1rem;
  font-weight: 700;
  color: #94a3b8;
  flex-shrink: 0;
}

.lb-rank.top {
  font-size: 1.25rem;
}

.lb-avatar {
  width: 38px;
  height: 38px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

.lb-info {
  flex: 1;
  min-width: 0;
}

.lb-name {
  font-size: 0.9375rem;
  font-weight: 600;
  text-transform: capitalize;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.lb-bar-wrap {
  height: 4px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 2px;
  margin-top: 0.375rem;
  overflow: hidden;
}

.lb-bar {
  height: 100%;
  border-radius: 2px;
  background: linear-gradient(90deg, #6366f1, #a855f7);
}

.lb-chip {
  font-family: var(--chip-mono);
  font-size: 1rem;
  font-weight: 700;
  text-align: right;
  flex-shrink: 0;
  letter-spacing: -0.02em;
  color: #fafafa;
}

.lb-chip.low {
  color: #d97706;
  animation: blink 0.8s linear infinite;
}

@keyframes blink {
  50% {
    opacity: 0.4;
  }
}

.lb-empty {
  text-align: center;
  padding: 2rem;
  color: #94a3b8;
  font-size: 0.875rem;
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
