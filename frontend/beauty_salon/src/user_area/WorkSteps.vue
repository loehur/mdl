<template>
  <div class="max-w-5xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Langkah Kerja</h2>
        <button 
          @click="showModal = true"
          class="px-4 py-2 bg-gradient-to-r from-pink-500 to-fuchsia-600 hover:from-pink-600 hover:to-fuchsia-700 text-white rounded-lg font-medium shadow-lg hover:shadow-xl transition-all flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
          </svg>
          <span>Tambah Langkah</span>
        </button>
      </div>

      <!-- Table Desktop -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gradient-to-r from-fuchsia-50 to-pink-50 text-gray-700 font-semibold border-b border-gray-200">
            <tr>
              <th class="px-4 py-3 w-16">#</th>
              <th class="px-4 py-3">Nama Langkah</th>
              <th v-if="isAdmin" class="px-4 py-3 text-right">Fee</th>
              <th class="px-4 py-3 text-right w-32">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="loading" v-for="i in 3" :key="i" class="animate-pulse">
              <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-8"></div></td>
              <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-48"></div></td>
              <td v-if="isAdmin" class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-24 ml-auto"></div></td>
              <td class="px-4 py-3"></td>
            </tr>
            <template v-else-if="steps.length">
              <tr v-for="(step, index) in steps" :key="step.id" class="hover:bg-fuchsia-50/30 transition group">
                <td class="px-4 py-3 text-gray-500">{{ index + 1 }}</td>
                <td class="px-4 py-3 font-medium text-gray-800">{{ step.name }}</td>
                <td v-if="isAdmin" class="px-4 py-3 text-right font-mono text-gray-600">
                  Rp {{ formatNumber(step.fee) }}
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button 
                      @click="editStep(step)"
                      class="text-blue-600 hover:text-blue-700 p-1"
                      title="Edit"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                      </svg>
                    </button>
                    <button 
                      @click="confirmDelete(step)"
                      class="text-red-600 hover:text-red-700 p-1"
                      title="Hapus"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-else>
              <td :colspan="isAdmin ? 4 : 3" class="px-4 py-12 text-center">
                <div class="flex flex-col items-center gap-3 text-gray-400">
                  <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                  </svg>
                  <p class="text-sm font-medium">Belum ada langkah kerja</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Cards Mobile -->
      <div class="md:hidden space-y-3">
        <div v-if="loading" v-for="i in 3" :key="i" class="bg-gray-50 rounded-lg p-4 animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
          <div class="h-3 bg-gray-200 rounded w-1/2"></div>
        </div>
        <template v-else-if="steps.length">
          <div v-for="(step, index) in steps" :key="step.id" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <div class="flex items-start justify-between mb-2">
              <div class="flex-1">
                <div class="text-xs text-gray-500 mb-1">Langkah #{{ index + 1 }}</div>
                <div class="font-medium text-gray-800">{{ step.name }}</div>
              </div>
            </div>
            <div v-if="isAdmin" class="text-sm text-gray-600 font-mono mb-3">
              Rp {{ formatNumber(step.fee) }}
            </div>
            <div class="flex gap-2">
              <button 
                @click="editStep(step)"
                class="flex-1 py-2 border border-blue-200 text-blue-600 rounded-lg text-sm font-medium hover:bg-blue-50 transition"
              >
                Edit
              </button>
              <button 
                @click="confirmDelete(step)"
                class="flex-1 py-2 border border-red-200 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition"
              >
                Hapus
              </button>
            </div>
          </div>
        </template>
        <div v-else class="text-center py-12 text-gray-400">
          <svg class="w-16 h-16 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
          </svg>
          <p class="text-sm">Belum ada langkah kerja</p>
        </div>
      </div>
    </div>

    <!-- Modal Create/Edit -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-[100000] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
          <div class="bg-gradient-to-r from-pink-500 to-fuchsia-600 px-6 py-4">
            <h3 class="font-bold text-white text-lg">{{ editMode ? 'Edit Langkah' : 'Tambah Langkah' }}</h3>
          </div>
          
          <form @submit.prevent="saveStep" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Nama Langkah <span class="text-red-500">*</span>
              </label>
              <input 
                v-model="form.name" 
                type="text" 
                required
                class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition"
                placeholder="Contoh: Cuci Rambut"
              />
            </div>

            <div v-if="isAdmin">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Fee (Rp) <span class="text-red-500">*</span>
              </label>
              <input 
                v-model="form.fee" 
                type="number" 
                step="0.01"
                required
                min="0"
                class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition"
                placeholder="0"
              />
            </div>

            <div class="flex gap-3 pt-4">
              <button 
                type="button"
                @click="closeModal"
                class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium transition"
              >
                Batal
              </button>
              <button 
                type="submit"
                :disabled="saving"
                class="flex-1 px-4 py-2 bg-gradient-to-r from-pink-500 to-fuchsia-600 hover:from-pink-600 hover:to-fuchsia-700 text-white rounded-lg font-medium transition disabled:opacity-50"
              >
                {{ saving ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Confirm Modal -->
      <div v-if="deleteConfirm.show" class="fixed inset-0 z-[100001] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
          <div class="bg-gradient-to-r from-red-500 to-pink-500 px-6 py-4 flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
            </div>
            <div>
              <h3 class="font-bold text-white text-lg">Konfirmasi Hapus</h3>
              <p class="text-white/80 text-sm">Tindakan ini tidak dapat dibatalkan</p>
            </div>
          </div>
          
          <div class="p-6">
            <p class="text-gray-600 text-center mb-2">Apakah Anda yakin ingin menghapus langkah ini?</p>
            <p class="text-gray-800 font-semibold text-center">{{ deleteConfirm.stepName }}</p>
          </div>
          
          <div class="px-6 pb-6 flex gap-3">
            <button 
              @click="deleteConfirm.show = false" 
              class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium transition"
            >
              Batal
            </button>
            <button 
              @click="deleteStep" 
              class="flex-1 px-4 py-2 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-lg hover:from-red-600 hover:to-pink-600 font-medium transition shadow-lg"
            >
              Ya, Hapus
            </button>
          </div>
        </div>
      </div>

      <!-- Toast -->
      <div v-if="toast.show" class="fixed top-4 right-4 z-[100002] animate-fade-in-down">
        <div 
          class="bg-white rounded-lg shadow-2xl border-l-4 p-4 min-w-[300px]"
          :class="toast.type === 'success' ? 'border-green-500' : 'border-red-500'"
        >
          <div class="flex items-center gap-3">
            <div 
              class="w-10 h-10 rounded-full flex items-center justify-center"
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
import { ref, reactive, onMounted, computed } from 'vue';

const steps = ref([]);
const loading = ref(true);
const showModal = ref(false);
const editMode = ref(false);
const saving = ref(false);

const form = reactive({
  id: null,
  name: '',
  fee: 0
});

const deleteConfirm = reactive({
  show: false,
  stepId: null,
  stepName: ''
});

const toast = reactive({
  show: false,
  message: '',
  type: 'success'
});

// Get user role from localStorage
const userRole = computed(() => {
  try {
    const user = JSON.parse(localStorage.getItem('salon_user'));
    return user?.role || 'cashier';
  } catch {
    return 'cashier';
  }
});

const isAdmin = computed(() => userRole.value === 'admin');

function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => {
    toast.show = false;
  }, 3000);
}

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num);
}

async function fetchSteps() {
  loading.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/WorkStep');
    const data = await res.json();
    
    if (data.success) {
      steps.value = data.data;
    }
  } catch (e) {
    console.error('Fetch steps error:', e);
  } finally {
    loading.value = false;
  }
}

function editStep(step) {
  editMode.value = true;
  form.id = step.id;
  form.name = step.name;
  form.fee = step.fee;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editMode.value = false;
  form.id = null;
  form.name = '';
  form.fee = 0;
}

async function saveStep() {
  saving.value = true;
  
  try {
    const url = editMode.value 
      ? `/api/Beauty_Salon/WorkStep/update/${form.id}`
      : '/api/Beauty_Salon/WorkStep/create';
    
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: form.name,
        fee: form.fee
      })
    });

    const data = await res.json();

    if (data.success) {
      showToast(data.message, 'success');
      closeModal();
      fetchSteps();
    } else {
      showToast(data.message || 'Gagal menyimpan', 'error');
    }
  } catch (e) {
    showToast('Terjadi kesalahan jaringan', 'error');
  } finally {
    saving.value = false;
  }
}

function confirmDelete(step) {
  deleteConfirm.stepId = step.id;
  deleteConfirm.stepName = step.name;
  deleteConfirm.show = true;
}

async function deleteStep() {
  const id = deleteConfirm.stepId;
  deleteConfirm.show = false;
  
  try {
    const res = await fetch(`/api/Beauty_Salon/WorkStep/delete/${id}`, {
      method: 'POST'
    });

    const data = await res.json();

    if (data.success) {
      showToast(data.message, 'success');
      fetchSteps();
    } else {
      showToast(data.message || 'Gagal menghapus', 'error');
    }
  } catch (e) {
    showToast('Terjadi kesalahan jaringan', 'error');
  }
}

onMounted(() => {
  fetchSteps();
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
