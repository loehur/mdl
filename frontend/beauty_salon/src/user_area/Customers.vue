<template>
  <div class="max-w-5xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Pelanggan</h2>
        <button @click="openCreateModal" class="px-4 py-2 bg-gradient-to-r from-pink-500 to-fuchsia-600 hover:from-pink-600 hover:to-fuchsia-700 text-white rounded-lg font-medium shadow-lg transition flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          <span>Tambah Pelanggan</span>
        </button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gradient-to-r from-fuchsia-50 to-pink-50 text-gray-700 font-semibold border-b">
            <tr>
              <th class="px-4 py-3">#</th>
              <th class="px-4 py-3">Nama</th>
              <th class="px-4 py-3">No HP</th>
              <th class="px-4 py-3 text-right w-32">Aksi</th>
            </tr>
          </thead>
          <tbody v-if="loading">
            <tr v-for="i in 3" :key="i" class="animate-pulse border-b">
              <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-8"></div></td>
              <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
              <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
              <td class="px-4 py-3"></td>
            </tr>
          </tbody>
          <tbody v-else-if="customers.length" class="divide-y">
            <tr v-for="(customer, index) in customers" :key="customer.id" class="hover:bg-fuchsia-50/30 group">
              <td class="px-4 py-3 text-gray-500">{{ index + 1 }}</td>
              <td class="px-4 py-3 font-medium text-gray-800">{{ customer.nama }}</td>
              <td class="px-4 py-3 text-gray-600">{{ customer.no_hp }}</td>
              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition">
                  <button @click="editCustomer(customer)" class="text-blue-600 hover:text-blue-700 p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                  </button>
                  <button @click="confirmDelete(customer)" class="text-red-600 hover:text-red-700 p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr><td colspan="4" class="px-4 py-12 text-center text-gray-400">Belum ada pelanggan</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-[100000] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="bg-gradient-to-r from-pink-500 to-fuchsia-600 px-6 py-4">
            <h3 class="font-bold text-white text-lg">{{ editMode ? 'Edit Pelanggan' : 'Tambah Pelanggan' }}</h3>
          </div>
          <form @submit.prevent="saveCustomer" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nama <span class="text-red-500">*</span></label>
              <input v-model="form.nama" type="text" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none" placeholder="Nama pelanggan" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">No HP <span class="text-red-500">*</span></label>
              <input v-model="form.no_hp" type="text" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none" placeholder="08xxxxxxxxxx" />
            </div>
            <div class="flex gap-3 pt-4">
              <button type="button" @click="closeModal" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium">Batal</button>
              <button type="submit" :disabled="saving" class="flex-1 px-4 py-2 bg-gradient-to-r from-pink-500 to-fuchsia-600 text-white rounded-lg font-medium disabled:opacity-50">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Confirm -->
      <div v-if="deleteConfirm.show" class="fixed inset-0 z-[100001] flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="bg-gradient-to-r from-red-500 to-pink-500 px-6 py-4">
            <h3 class="font-bold text-white text-lg">Konfirmasi Hapus</h3>
          </div>
          <div class="p-6"><p class="text-center text-gray-800">Hapus pelanggan <strong>{{ deleteConfirm.customerName }}</strong>?</p></div>
          <div class="px-6 pb-6 flex gap-3">
            <button @click="deleteConfirm.show = false" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">Batal</button>
            <button @click="deleteCustomer" class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg">Hapus</button>
          </div>
        </div>
      </div>

      <!-- Toast -->
      <div v-if="toast.show" class="fixed top-4 right-4 z-[100002] bg-white rounded-lg shadow-2xl border-l-4 p-4" :class="toast.type === 'success' ? 'border-green-500' : 'border-red-500'">
        <p class="font-medium">{{ toast.message }}</p>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';

const customers = ref([]);
const loading = ref(true);
const showModal = ref(false);
const editMode = ref(false);
const saving = ref(false);
const form = reactive({ id: null, nama: '', no_hp: '' });
const deleteConfirm = reactive({ show: false, customerId: null, customerName: '' });
const toast = reactive({ show: false, message: '', type: 'success' });

function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => toast.show = false, 3000);
}

async function fetchCustomers() {
  loading.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/Customers');
    const data = await res.json();
    if (data.success) customers.value = data.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function openCreateModal() {
  editMode.value = false;
  form.id = null;
  form.nama = '';
  form.no_hp = '';
  showModal.value = true;
}

function editCustomer(customer) {
  editMode.value = true;
  form.id = customer.id;
  form.nama = customer.nama;
  form.no_hp = customer.no_hp;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

async function saveCustomer() {
  saving.value = true;
  try {
    const url = editMode.value ? `/api/Beauty_Salon/Customers/update/${form.id}` : '/api/Beauty_Salon/Customers/create';
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nama: form.nama, no_hp: form.no_hp })
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message);
      closeModal();
      fetchCustomers();
    } else {
      showToast(data.message || 'Gagal menyimpan', 'error');
    }
  } catch (e) {
    showToast('Kesalahan jaringan', 'error');
  } finally {
    saving.value = false;
  }
}

function confirmDelete(customer) {
  deleteConfirm.customerId = customer.id;
  deleteConfirm.customerName = customer.nama;
  deleteConfirm.show = true;
}

async function deleteCustomer() {
  const id = deleteConfirm.customerId;
  deleteConfirm.show = false;
  try {
    const res = await fetch(`/api/Beauty_Salon/Customers/delete/${id}`, { method: 'POST' });
    const data = await res.json();
    if (data.success) {
      showToast(data.message);
      fetchCustomers();
    } else {
      showToast(data.message || 'Gagal menghapus', 'error');
    }
  } catch (e) {
    showToast('Kesalahan jaringan', 'error');
  }
}

onMounted(() => fetchCustomers());
</script>
