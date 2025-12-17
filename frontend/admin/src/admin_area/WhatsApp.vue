<template>
  <div class="grid gap-6">
    <!-- Form Buat Session Baru -->
    <section class="p-5 bg-white rounded-xl shadow">
      <h3 class="text-lg font-semibold flex items-center gap-2">
        <svg
          class="w-5 h-5 text-green-600"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            d="M16.5 3h-9A2.5 2.5 0 005 5.5v13l3.5-2h8A2.5 2.5 0 0019 14.5v-9A2.5 2.5 0 0016.5 3z"
          />
          <path d="M8 9c1.5 2 3.5 3.5 6 4" stroke-linecap="round" />
        </svg>
        WhatsApp
      </h3>
      <form class="mt-4 grid gap-3" @submit.prevent="onCreate">
        <input
          v-model="deviceName"
          class="border rounded-lg px-3 py-3"
          type="text"
          placeholder="Nama perangkat (Contoh: Admin Toko)"
          required
        />
        <button
          class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 flex items-center gap-2"
          type="submit"
          :disabled="isCreating"
        >
          <svg
            v-if="isCreating"
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
              d="M4 12a8 8 0 018-8v4a4 4 0 010 8H4z"
            />
          </svg>
          <span>{{ isCreating ? "Membuat..." : "Buat Session Baru" }}</span>
        </button>
        <p
          v-if="message"
          :class="['text-sm', isError ? 'text-red-700' : 'text-green-700']"
        >
          {{ message }}
        </p>
      </form>
    </section>

    <!-- Modal Scan QR (Overlay) -->
    <div 
      v-if="activeSessionId" 
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden relative animate-fade-in-up">
        
        <!-- Header Modal -->
        <div class="px-6 py-4 border-b flex items-center justify-between bg-gray-50">
          <h3 class="text-lg font-bold text-gray-800">Scan QR Code</h3>
          <button 
            @click="closeQr" 
            class="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-200 transition-colors"
          >
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        <!-- Content Modal -->
        <div class="p-6 flex flex-col items-center text-center">
          <div class="mb-4">
            <p class="text-sm text-gray-600">
              Buka WhatsApp di HP Anda > Menu > Perangkat Tertaut > <strong>Tautkan Perangkat</strong>
            </p>
            <p class="text-xs text-gray-400 mt-1">Device: {{ activeDeviceName }}</p>
          </div>
          
          <div class="relative">
            <div v-if="qrString" class="bg-white p-2 border-2 border-gray-100 rounded-xl shadow-inner">
              <img :src="qrString" alt="QR Code" class="w-64 h-64 object-contain" />
            </div>
            
            <div v-else-if="qrLoading" class="w-64 h-64 flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl bg-gray-50 text-gray-400">
               <svg class="h-10 w-10 animate-spin mb-3 text-green-500" viewBox="0 0 24 24" fill="none">
                 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 010 8H4z"/>
               </svg>
               <span class="text-sm font-medium">Menghubungkan...</span>
               <span class="text-xs mt-1">Sedang menyiapkan QR</span>
            </div>
            
            <div v-else class="w-64 h-64 flex flex-col items-center justify-center border-2 border-green-100 rounded-xl bg-green-50 text-green-600">
              <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <span class="font-bold text-lg">Terhubung!</span>
            </div>
          </div>

          <div class="mt-6 text-xs text-center text-gray-400 max-w-xs leading-relaxed">
            QR Code akan update otomatis setiap beberapa detik. <br>
            Jangan tutup jendela ini sampai status berubah menjadi Terhubung.
          </div>
        </div>
      </div>
    </div>

    <!-- Daftar Session -->
    <section class="p-5 bg-white rounded-xl shadow">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Daftar Perangkat</h3>
        <button
          class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 transition-colors"
          @click="fetchSaved"
          title="Refresh List"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
        </button>
      </div>
      
      <div class="space-y-3">
        <div v-if="!savedSessions.length" class="text-sm text-gray-500 text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-200">
          Belum ada perangkat tersimpan
        </div>
        
        <div
          v-for="(s, i) in savedSessions"
          :key="i"
          class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm hover:shadow-md transition-shadow flex flex-col sm:flex-row sm:items-center justify-between gap-4"
        >
          <!-- Info Perangkat -->
          <div class="flex items-start gap-3">
            <div class="p-2bg-green-50 rounded-full flex-shrink-0 text-green-600 mt-1">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
            </div>
            <div>
              <div class="font-semibold text-gray-900 flex items-center gap-2">
                {{ s.device_name }}
                <span v-if="s.main_notif == 1" class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-green-700 bg-green-100 rounded-full border border-green-200">Main</span>
                <button 
                  v-else 
                  @click="setAsMain(s)"
                  class="text-xs text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1"
                  title="Jadikan pengirim notifikasi utama"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                  Set Main
                </button>
              </div>
              <div class="text-xs text-gray-500 font-mono mt-0.5 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                ID: {{ s.auth }}
              </div>
            </div>
          </div>
          
          <!-- Tombol Aksi -->
            <div class="flex items-center gap-2 w-full sm:w-auto">
            <!-- Tombol Cek Status / Scan (Dynamic State) -->
            <button
              v-if="!s.connected"
              class="flex-1 sm:flex-none justify-center px-4 py-2.5 text-sm font-medium rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 active:bg-blue-200 transition-colors flex items-center gap-2 shadow-sm"
              @click="checkStatus(s)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
              <span>Connect</span>
            </button>
            <button
              v-else
              disabled
              class="flex-1 sm:flex-none justify-center px-4 py-2.5 text-sm font-medium rounded-lg border border-green-200 bg-green-50 text-green-700 cursor-not-allowed flex items-center gap-2 shadow-sm opacity-80"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
              <span>Connected</span>
            </button>

            <!-- Tombol Hapus -->
            <button
              class="flex-none px-3 py-2.5 text-sm font-medium rounded-lg border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 active:bg-red-200 transition-colors shadow-sm"
              @click="confirmDelete(s)"
              title="Hapus Session"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Modal Konfirmasi Hapus -->
    <div
      v-if="showConfirm"
      class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-xl shadow p-6 w-full max-w-sm">
        <h4 class="text-lg font-semibold text-gray-900">Hapus Perangkat?</h4>
        <p class="mt-2 text-sm text-gray-600">
          Anda yakin ingin menghapus session <strong>{{ confirmTarget?.device_name }}</strong>? Koneksi WhatsApp akan diputus.
        </p>
        <div class="mt-5 flex items-center justify-end gap-3">
          <button
            class="px-4 py-2 text-sm font-medium rounded text-gray-700 hover:bg-gray-100"
            @click="onCancelDelete"
          >
            Batal
          </button>
          <button
            class="px-4 py-2 text-sm font-medium rounded bg-red-600 text-white hover:bg-red-700"
            :disabled="deletingAuth === (confirmTarget ? confirmTarget.auth : '')"
            @click="onConfirmDelete"
          >
            {{ deletingAuth ? 'Menghapus...' : 'Ya, Hapus' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from "vue";
import { apiUrl } from "../api.js";

const deviceName = ref("");
const message = ref("");
const isError = ref(false);
const isCreating = ref(false);

// State untuk QR yang sedang aktif ditampilkan
const activeSessionId = ref("");
const activeDeviceName = ref("");
const qrString = ref("");
const qrLoading = ref(false);
let qrPoller = null;

const savedSessions = ref([]);
const deletingAuth = ref("");
const showConfirm = ref(false);
const confirmTarget = ref(null);

function getUser() {
  try {
    const raw = localStorage.getItem("mdl_user");
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

// 1. Load daftar session saat mounted
onMounted(() => {
  fetchSaved();
});

onUnmounted(() => {
  stopQrPoll();
});

async function fetchSaved() {
  const user = getUser();
  if (!user) return;
  try {
    const res = await fetch(
      apiUrl(`/Admin/WhatsApp/list-saved?user_id=${encodeURIComponent(user.id)}`)
    );
    const data = await res.json().catch(() => ({ success: false, sessions: [] }));
    if (res.ok && data.success) {
      // Map wa_status active -> connected = true
      savedSessions.value = (Array.isArray(data.sessions) ? data.sessions : []).map(s => ({
        ...s,
        connected: s.wa_status === 'active'
      }));
      // Verify real-time status
      verifyAllStatuses();
    }
  } catch(e) {
    console.error("Fetch saved error", e);
  }
}

async function verifyAllStatuses() {
  const user = getUser();
  if (!user || !savedSessions.value.length) return;

  const promises = savedSessions.value.map(async (s, index) => {
    try {
      const res = await fetch(apiUrl("/Admin/WhatsApp/cek-status"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ session_id: s.auth, user_id: user.id }),
      });
      const data = await res.json();
      if (data.success) {
        // Update connected state based on real-time check
        savedSessions.value[index].connected = !!data.logged_in;
      }
    } catch (e) {
      console.error(`Status check error for ${s.auth}`, e);
    }
  });

  await Promise.all(promises);
}

// 2. Buat Session Baru
async function onCreate() {
  message.value = "";
  isError.value = false;
  
  const user = getUser();
  if (!user) {
    message.value = "Harus login";
    isError.value = true;
    return;
  }

  isCreating.value = true;
  try {
    const res = await fetch(apiUrl("/Admin/WhatsApp/create-session"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ device_name: deviceName.value, user_id: user.id }),
    });
    const data = await res.json().catch(() => ({ success: false }));
    
    if (!res.ok || !data.success) {
      throw new Error(data.message || "Gagal membuat session");
    }

    // Berhasil create -> Refresh list dan langsung tampilkan QR
    await fetchSaved();
    deviceName.value = ""; // Reset input
    
    // Auto buka scanner untuk session baru ini
    startQrMonitor(data.session_id, data.device_name || "New Device");

  } catch (e) {
    message.value = e.message;
    isError.value = true;
  } finally {
    isCreating.value = false;
  }
}

// 3. Cek Status / Scan Ulang
function checkStatus(s) {
  startQrMonitor(s.auth, s.device_name);
}

// Logic monitoring QR untuk session tertentu
function startQrMonitor(sessionId, name) {
  // Set UI state
  activeSessionId.value = sessionId;
  activeDeviceName.value = name;
  qrString.value = "";
  qrLoading.value = true;
  
  // Start polling
  stopQrPoll();
  pollOneStatus(); // Immediate Check
  qrPoller = setInterval(pollOneStatus, 3000); // Check every 3s
}

async function pollOneStatus() {
  if (!activeSessionId.value) return;
  
  try {
    const user = getUser();
    const res = await fetch(apiUrl("/Admin/WhatsApp/cek-status"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        session_id: activeSessionId.value,
        user_id: user?.id
      }),
    });
    const data = await res.json();
    
    if (data.success) {
      if (data.logged_in) {
        // Sudah login
        qrString.value = "";
        qrLoading.value = false;
        
        // Update status di list menjadi connected
        const idx = savedSessions.value.findIndex(s => s.auth === activeSessionId.value);
        if (idx !== -1) {
          savedSessions.value[idx].connected = true;
        }

        // Close scanner jika sudah connected (opsional, tapi user minta "connect jadi connected dan tidak bisa diklik lagi")
        // Biarkan 1 cycle tampil "Session Terhubung" di kotak QR, lalu user bisa tutup atau auto close
        
      } else if (data.qr_ready && data.qr_string) {
        // Ada QR
        qrString.value = data.qr_string;
        qrLoading.value = false;
      } else {
        // Belum ada QR (mungkin sedang preparing)
        qrLoading.value = true;
      }
    }
  } catch (e) {
    console.error("Poll error", e);
  }
}

function stopQrPoll() {
  if (qrPoller) {
    clearInterval(qrPoller);
    qrPoller = null;
  }
}

function closeQr() {
  stopQrPoll();
  activeSessionId.value = "";
  qrString.value = "";
}

// 4. Hapus Session
function confirmDelete(s) {
  confirmTarget.value = s;
  showConfirm.value = true;
}

function onCancelDelete() {
  showConfirm.value = false;
  confirmTarget.value = null;
}

async function onConfirmDelete() {
  if (!confirmTarget.value) return;
  
  const targetAuth = confirmTarget.value.auth;
  deletingAuth.value = targetAuth;
  
  const user = getUser();
  try {
    await fetch(apiUrl("/Admin/WhatsApp/delete-session"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user_id: user.id, auth: targetAuth }),
    });
    // Selalu refresh list, ignore error response
    await fetchSaved();
    
    // Jika yang dihapus sedang dibuka QR-nya, tutup
    if (activeSessionId.value === targetAuth) {
      closeQr();
    }
  } catch (e) {
    console.error("Delete error", e);
  } finally {
    deletingAuth.value = "";
    showConfirm.value = false;
    confirmTarget.value = null;
  }
}

async function setAsMain(s) {
  const user = getUser();
  if (!user) return;
  
  try {
    const res = await fetch(apiUrl("/Admin/WhatsApp/set-main"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: user.id, auth: s.auth }),
    });
    const data = await res.json();
    if (data.success) {
        await fetchSaved();
    }
  } catch(e) {
      console.error("Set main error", e);
  }
}
</script>
