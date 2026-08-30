<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "./stores/auth";

const auth = useAuth();
const router = useRouter();

const live = ref(false);
let sock = null;
let reconnectTimer = null;
let offlinePoll = null;

const WS_URL =
  "wss://free.blr2.piesocket.com/v3/1?api_key=P5u7Bm0oLAfw4QQMPGH45yzAt3L0Bs4LXmgXi74n&notify_self=0";

const showNav = computed(() => auth.isLoggedIn.value && !["/login", "/admin"].includes(router.currentRoute.value.path));

function connectWs() {
  try {
    sock = new WebSocket(WS_URL);
  } catch (_) {
    scheduleReconnect();
    return;
  }

  sock.onopen = () => {
    live.value = true;
    stopOfflinePoll();
  };
  sock.onmessage = () => {
    window.dispatchEvent(new CustomEvent("chip:ws"));
  };
  sock.onclose = () => {
    live.value = false;
    startOfflinePoll();
    scheduleReconnect();
  };
  sock.onerror = () => {
    sock && sock.close();
  };
}

function scheduleReconnect() {
  clearTimeout(reconnectTimer);
  reconnectTimer = setTimeout(connectWs, 4000);
}

function startOfflinePoll() {
  if (offlinePoll) return;
  offlinePoll = setInterval(() => {
    window.dispatchEvent(new CustomEvent("chip:ws"));
  }, 20000);
}

function stopOfflinePoll() {
  clearInterval(offlinePoll);
  offlinePoll = null;
}

async function doLogout() {
  await auth.logout();
  router.push("/login");
}

onMounted(connectWs);
onUnmounted(() => {
  if (sock) sock.close();
  clearTimeout(reconnectTimer);
  stopOfflinePoll();
});
</script>

<template>
  <div class="chip-app">
    <header class="chip-header">
      <div class="chip-brand">
        <div class="chip-logo">C</div>
        <div>
          <h1>Chip</h1>
          <span>Live Viewer</span>
        </div>
      </div>
      <div class="chip-header-actions">
        <span class="live-badge" :class="{ connected: live }">
          <span class="live-dot"></span>
          {{ live ? "Live" : "Offline" }}
        </span>
        <button v-if="showNav" type="button" class="btn-logout" @click="doLogout">Logout</button>
      </div>
    </header>

    <nav v-if="showNav" class="chip-tabs">
      <RouterLink to="/room" class="chip-tab">Chip</RouterLink>
      <RouterLink to="/history" class="chip-tab">Riwayat</RouterLink>
      <RouterLink to="/watch" class="chip-tab">Leaderboard</RouterLink>
    </nav>

    <main class="chip-main">
      <RouterView />
    </main>
  </div>
</template>
