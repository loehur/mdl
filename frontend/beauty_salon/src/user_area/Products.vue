<template>
  <div class="max-w-6xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Produk Layanan</h2>
        <button 
          @click="openCreateModal"
          class="px-4 py-2 bg-gradient-to-r from-pink-500 to-fuchsia-600 hover:from-pink-600 hover:to-fuchsia-700 text-white rounded-lg font-medium shadow-lg hover:shadow-xl transition-all flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
          </svg>
          <span>Tambah Produk</span>
        </button>
      </div>

      <!-- Products Grid -->
      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="i in 6" :key="i" class="bg-gray-50 rounded-lg p-4 animate-pulse">
          <div class="h-5 bg-gray-200 rounded w-3/4 mb-3"></div>
          <div class="h-4 bg-gray-200 rounded w-1/2 mb-3"></div>
          <div class="h-3 bg-gray-200 rounded w-full mb-2"></div>
          <div class="h-3 bg-gray-200 rounded w-2/3"></div>
        </div>
      </div>

      <div v-else-if="products.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="product in products" :key="product.id" class="bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 p-5 hover:shadow-lg transition-all group">
          <div class="flex items-start justify-between mb-3">
            <h3 class="font-bold text-gray-800 text-lg">{{ product.name }}</h3>
            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <button @click="editProduct(product)" class="text-blue-600 hover:text-blue-700 p-1" title="Edit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
              <button @click="confirmDelete(product)" class="text-red-600 hover:text-red-700 p-1" title="Hapus">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
              </button>
            </div>
          </div>

          <div class="text-2xl font-bold text-pink-600 mb-3">
            Rp {{ formatNumber(product.price) }}
          </div>

          <div v-if="product.work_steps && product.work_steps.length" class="space-y-1">
            <div class="text-xs font-medium text-gray-500 uppercase mb-2">Langkah Kerja:</div>
            <div v-for="stepId in product.work_steps" :key="stepId" class="flex items-center gap-2 text-sm text-gray-600">
              <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span>{{ getStepName(stepId) }}</span>
            </div>
          </div>
          <div v-else class="text-sm text-gray-400 italic">Belum ada langkah kerja</div>
        </div>
      </div>

      <div v-else class="text-center py-16">
        <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
        </svg>
        <p class="text-gray-500">Belum ada produk layanan</p>
      </div>
    </div>

    <!-- Modal Create/Edit -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-[100000] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-8">
          <div class="bg-gradient-to-r from-pink-500 to-fuchsia-600 px-6 py-4">
            <h3 class="font-bold text-white text-lg">{{ editMode ? 'Edit Produk' : 'Tambah Produk' }}</h3>
          </div>
          
          <form @submit.prevent="saveProduct" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Nama Produk <span class="text-red-500">*</span>
              </label>
              <input 
                v-model="form.name" 
                type="text" 
                required
                class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition"
                placeholder="Contoh: Paket Potong + Cuci"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Harga (Rp) <span class="text-red-500">*</span>
              </label>
              <input 
                v-model="form.price" 
                type="number" 
                step="0.01"
                required
                min="0"
                class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition"
                placeholder="0"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-3">
                Langkah Kerja <span class="text-gray-400 text-xs">(Pilih yang sesuai)</span>
              </label>
              
              <div v-if="workSteps.length" class="space-y-2 max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3">
                <label 
                  v-for="step in workSteps" 
                  :key="step.id" 
                  class="flex items-center gap-3 p-2 hover:bg-pink-50 rounded-lg cursor-pointer transition"
                >
                  <input 
                    type="checkbox" 
                    :value="step.id" 
                    v-model="form.work_steps"
                    class="w-4 h-4 text-pink-600 border-gray-300 rounded focus:ring-pink-500"
                  />
                  <span class="flex-1 text-gray-700">{{ step.name }}</span>
                </label>
              </div>
              <div v-else class="text-sm text-gray-400 italic border border-gray-200 rounded-lg p-4 text-center">
                Belum ada langkah kerja. Silakan buat terlebih dahulu.
              </div>
            </div>

            <div class="flex gap-3 pt-4 sticky bottom-0 bg-white">
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

      <!-- Delete Confirm -->
      <div v-if="deleteConfirm.show" class="fixed inset-0 z-[100001] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="bg-gradient-to-r from-red-500 to-pink-500 px-6 py-4 flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
            </div>
            <div>
              <h3 class="font-bold text-white text-lg">Konfirmasi Hapus</h3>
              <p class="text-white/80 text-sm">Tindakan tidak dapat dibatalkan</p>
            </div>
          </div>
          
          <div class="p-6">
            <p class="text-gray-600 text-center mb-2">Apakah Anda yakin ingin menghapus produk ini?</p>
            <p class="text-gray-800 font-semibold text-center">{{ deleteConfirm.productName }}</p>
          </div>
          
          <div class="px-6 pb-6 flex gap-3">
            <button @click="deleteConfirm.show = false" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium transition">
              Batal
            </button>
            <button @click="deleteProduct" class="flex-1 px-4 py-2 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-lg hover:from-red-600 hover:to-pink-600 font-medium transition shadow-lg">
              Ya, Hapus
            </button>
          </div>
        </div>
      </div>

      <!-- Toast -->
      <div v-if="toast.show" class="fixed top-4 right-4 z-[100002] animate-fade-in-down">
        <div class="bg-white rounded-lg shadow-2xl border-l-4 p-4 min-w-[300px]" :class="toast.type === 'success' ? 'border-green-500' : 'border-red-500'">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="toast.type === 'success' ? 'bg-green-100' : 'bg-red-100'">
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

const products = ref([]);
const workSteps = ref([]);
const loading = ref(true);
const showModal = ref(false);
const editMode = ref(false);
const saving = ref(false);

const form = reactive({
  id: null,
  name: '',
  price: 0,
  work_steps: []
});

const deleteConfirm = reactive({
  show: false,
  productId: null,
  productName: ''
});

const toast = reactive({
  show: false,
  message: '',
  type: 'success'
});

function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => toast.show = false, 3000);
}

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num);
}

function getStepName(stepId) {
  const step = workSteps.value.find(s => s.id == stepId);
  return step ? step.name : 'Unknown';
}

async function fetchWorkSteps() {
  try {
    const res = await fetch('/api/Beauty_Salon/WorkStep');
    const data = await res.json();
    if (data.success) {
      workSteps.value = data.data;
    }
  } catch (e) {
    console.error('Fetch work steps error:', e);
  }
}

async function fetchProducts() {
  loading.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/Products');
    const data = await res.json();
    if (data.success) {
      products.value = data.data;
    }
  } catch (e) {
    console.error('Fetch products error:', e);
  } finally {
    loading.value = false;
  }
}

function openCreateModal() {
  editMode.value = false;
  form.id = null;
  form.name = '';
  form.price = 0;
  form.work_steps = [];
  showModal.value = true;
}

function editProduct(product) {
  editMode.value = true;
  form.id = product.id;
  form.name = product.name;
  form.price = product.price;
  form.work_steps = Array.isArray(product.work_steps) ? [...product.work_steps] : [];
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

async function saveProduct() {
  saving.value = true;
  
  try {
    const url = editMode.value 
      ? `/api/Beauty_Salon/Products/update/${form.id}`
      : '/api/Beauty_Salon/Products/create';
    
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: form.name,
        price: form.price,
        work_steps: form.work_steps
      })
    });

    const data = await res.json();

    if (data.success) {
      showToast(data.message, 'success');
      closeModal();
      fetchProducts();
    } else {
      showToast(data.message || 'Gagal menyimpan', 'error');
    }
  } catch (e) {
    showToast('Terjadi kesalahan jaringan', 'error');
  } finally {
    saving.value = false;
  }
}

function confirmDelete(product) {
  deleteConfirm.productId = product.id;
  deleteConfirm.productName = product.name;
  deleteConfirm.show = true;
}

async function deleteProduct() {
  const id = deleteConfirm.productId;
  deleteConfirm.show = false;
  
  try {
    const res = await fetch(`/api/Beauty_Salon/Products/delete/${id}`, {
      method: 'POST'
    });

    const data = await res.json();

    if (data.success) {
      showToast(data.message, 'success');
      fetchProducts();
    } else {
      showToast(data.message || 'Gagal menghapus', 'error');
    }
  } catch (e) {
    showToast('Terjadi kesalahan jaringan', 'error');
  }
}

onMounted(async () => {
  await fetchWorkSteps();
  await fetchProducts();
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
