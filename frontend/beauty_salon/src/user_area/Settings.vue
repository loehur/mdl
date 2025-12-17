<template>
  <div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Pengaturan Salon</h2>

      <form @submit.prevent="saveSalon" class="space-y-6">
        <!-- Nama Salon -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Nama Salon <span class="text-red-500">*</span>
          </label>
          <input 
            v-model="form.nama_salon" 
            type="text" 
            required
            class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition"
            placeholder="Contoh: Salon Cantik"
          />
        </div>

        <!-- Alamat Salon -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Alamat Salon <span class="text-red-500">*</span>
          </label>
          <textarea 
            v-model="form.alamat_salon" 
            required
            rows="4"
            class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition resize-none"
            placeholder="Alamat lengkap salon Anda..."
          ></textarea>
        </div>

        <!-- Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div class="flex gap-3">
            <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-blue-800">
              <p class="font-medium mb-1">Informasi</p>
              <p>Data ini akan ditampilkan pada invoice dan dokumen salon Anda.</p>
            </div>
          </div>
        </div>

        <!-- Buttons -->
        <div class="flex gap-3 pt-4">
          <button 
            type="submit"
            :disabled="saving"
            class="px-6 py-3 bg-gradient-to-r from-pink-500 to-fuchsia-600 hover:from-pink-600 hover:to-fuchsia-700 text-white rounded-lg font-medium shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <svg v-if="saving" class="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
            </svg>
            <span>{{ saving ? 'Menyimpan...' : 'Simpan' }}</span>
          </button>
        </div>
      </form>
    </div>

    <!-- Toast Notification -->
    <Teleport to="body">
      <div v-if="toast.show" class="fixed top-4 right-4 z-[100000] animate-fade-in-down">
        <div 
          class="bg-white rounded-lg shadow-2xl border-l-4 p-4 min-w-[300px]"
          :class="toast.type === 'success' ? 'border-green-500' : 'border-red-500'"
        >
          <div class="flex items-center gap-3">
            <div 
              class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
              :class="toast.type === 'success' ? 'bg-green-100' : 'bg-red-100'"
            >
              <svg v-if="toast.type === 'success'" class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <svg v-else class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </div>
            <p class="font-medium text-gray-800">{{ toast.message }}</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';

const form = reactive({
  nama_salon: '',
  alamat_salon: ''
});

const saving = ref(false);
const toast = reactive({
  show: false,
  message: '',
  type: 'success'
});

function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => {
    toast.show = false;
  }, 3000);
}

async function fetchSalon() {
  try {
    const res = await fetch('/api/Beauty_Salon/Salon');
    const data = await res.json();
    
    if (data.success && data.data) {
      form.nama_salon = data.data.nama_salon;
      form.alamat_salon = data.data.alamat_salon;
    }
  } catch (e) {
    console.error('Fetch salon error:', e);
  }
}

async function saveSalon() {
  saving.value = true;
  
  try {
    const res = await fetch('/api/Beauty_Salon/Salon/save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        nama_salon: form.nama_salon,
        alamat_salon: form.alamat_salon
      })
    });

    const data = await res.json();

    if (data.success) {
      showToast(data.message, 'success');
    } else {
      showToast(data.message || 'Gagal menyimpan data', 'error');
    }
  } catch (e) {
    showToast('Terjadi kesalahan jaringan', 'error');
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  fetchSalon();
});
</script>

<style scoped>
@keyframes fade-in-down {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in-down {
  animation: fade-in-down 0.3s ease-out;
}
</style>
