<template>
  <div class="max-w-6xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Pelanggan</h2>
        <button @click="openCreateModal" class="px-4 py-2 bg-gradient-to-r from-pink-500 to-fuchsia-600 hover:from-pink-600 hover:to-fuchsia-700 text-white rounded-lg font-medium shadow-lg transition flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          <span>Tambah Pelanggan</span>
        </button>
      </div>

      <!-- Search Bar -->
      <div class="mb-4">
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Cari nama atau no HP..." 
            class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition"
          />
          <button 
            v-if="searchQuery" 
            @click="searchQuery = ''" 
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gradient-to-r from-fuchsia-50 to-pink-50 text-gray-700 font-semibold border-b">
            <tr>
              <th class="px-4 py-3">#</th>
              <th class="px-4 py-3">Nama</th>
              <th class="px-4 py-3">No HP</th>
              <th class="px-4 py-3 text-center">Progress Loyalty</th>
              <th class="px-4 py-3 text-center">Voucher</th>
              <th class="px-4 py-3 text-right w-40">Aksi</th>
            </tr>
          </thead>
          <tbody v-if="loading">
            <tr v-for="i in 3" :key="i" class="animate-pulse border-b">
              <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-8"></div></td>
              <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
              <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
              <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-20 mx-auto"></div></td>
              <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-16 mx-auto"></div></td>
              <td class="px-4 py-3"></td>
            </tr>
          </tbody>
          <tbody v-else-if="filteredCustomers.length" class="divide-y">
            <tr v-for="(customer, index) in filteredCustomers" :key="customer.id" class="hover:bg-fuchsia-50/30 group">
              <td class="px-4 py-3 text-gray-500">{{ index + 1 }}</td>
              <td class="px-4 py-3 font-medium text-gray-800">{{ customer.nama }}</td>
              <td class="px-4 py-3 text-gray-600">{{ customer.no_hp }}</td>
              <!-- Loyalty Progress -->
              <td class="px-4 py-3">
                <div class="flex flex-col items-center">
                  <div class="w-24 bg-gray-200 rounded-full h-2 mb-1">
                    <div class="bg-gradient-to-r from-amber-400 to-yellow-500 h-2 rounded-full transition-all" 
                         :style="{ width: (customer.progress || 0) * 10 + '%' }"></div>
                  </div>
                  <span class="text-xs text-gray-500">{{ customer.progress || 0 }}/10</span>
                </div>
              </td>
              <!-- Vouchers -->
              <td class="px-4 py-3 text-center">
                <span v-if="customer.available_vouchers > 0" class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">
                  🎁 {{ customer.available_vouchers }}
                </span>
                <span v-else class="text-gray-400 text-xs">-</span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-1">
                  <!-- Loyalty Button -->
                  <button @click="openLoyaltyModal(customer)" class="text-amber-600 hover:text-amber-700 p-1.5 hover:bg-amber-50 rounded-lg transition" title="Atur Loyalty">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                    </svg>
                  </button>
                  <button @click="editCustomer(customer)" class="text-blue-600 hover:text-blue-700 p-1.5 hover:bg-blue-50 rounded-lg transition" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                  </button>
                  <button @click="confirmDelete(customer)" class="text-red-600 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-lg transition" title="Hapus">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else-if="customers.length && !filteredCustomers.length">
            <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">Tidak ada pelanggan yang cocok dengan pencarian "{{ searchQuery }}"</td></tr>
          </tbody>
          <tbody v-else>
            <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">Belum ada pelanggan</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Create/Edit Customer -->
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

      <!-- Loyalty Modal -->
      <div v-if="showLoyaltyModal" class="fixed inset-0 z-[100000] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="bg-gradient-to-r from-amber-500 to-yellow-500 px-6 py-4">
            <h3 class="font-bold text-white text-lg flex items-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
              </svg>
              Atur Loyalty - {{ loyaltyCustomer?.nama }}
            </h3>
          </div>
          
          <div class="p-6 space-y-6">
            <!-- Current Status -->
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
              <h4 class="text-sm font-bold text-gray-600 mb-3">Status Saat Ini</h4>
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span class="text-gray-500">Order di Sistem:</span>
                  <span class="font-bold text-gray-800 ml-2">{{ loyaltyInfo?.actual_orders || 0 }}</span>
                </div>
                <div>
                  <span class="text-gray-500">Adjustment:</span>
                  <span class="font-bold ml-2" :class="(loyaltyInfo?.adjustment || 0) > 0 ? 'text-green-600' : 'text-gray-800'">
                    {{ (loyaltyInfo?.adjustment || 0) > 0 ? '+' : '' }}{{ loyaltyInfo?.adjustment || 0 }}
                  </span>
                </div>
                <div>
                  <span class="text-gray-500">Total Efektif:</span>
                  <span class="font-bold text-amber-600 ml-2">{{ loyaltyInfo?.completed_orders || 0 }}</span>
                </div>
                <div>
                  <span class="text-gray-500">Voucher Tersedia:</span>
                  <span class="font-bold text-green-600 ml-2">{{ loyaltyInfo?.available_vouchers_count || 0 }}</span>
                </div>
              </div>
              
              <!-- Progress Bar -->
              <div class="mt-4">
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                  <span>Progress ke voucher berikutnya</span>
                  <span class="font-bold">{{ loyaltyInfo?.progress_to_next || 0 }}/10</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                  <div class="bg-gradient-to-r from-amber-400 to-yellow-500 h-3 rounded-full transition-all" 
                       :style="{ width: (loyaltyInfo?.progress_to_next || 0) * 10 + '%' }"></div>
                </div>
                <p class="text-xs text-amber-600 mt-1">{{ loyaltyInfo?.orders_needed_for_next || 10 }} order lagi untuk voucher berikutnya</p>
              </div>
            </div>

            <!-- Set Progress Form -->
            <div class="bg-amber-50 rounded-xl p-4 border border-amber-200">
              <h4 class="text-sm font-bold text-amber-800 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Atur Total Order (Migrasi Manual)
              </h4>
              <p class="text-xs text-amber-700 mb-3">Masukkan total order selesai dari catatan manual. Voucher akan otomatis diberikan jika memenuhi syarat (setiap 10 order).</p>
              
              <div class="flex gap-2">
                <input type="number" v-model.number="loyaltyForm.totalOrders" min="0" 
                       class="flex-1 px-4 py-2 border border-amber-300 rounded-lg focus:ring-2 focus:ring-amber-200 focus:border-amber-400 outline-none text-center font-bold"
                       placeholder="Total order" />
                <button @click="setLoyaltyProgress" :disabled="savingLoyalty" 
                        class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-medium transition disabled:opacity-50">
                  {{ savingLoyalty ? '...' : 'Simpan' }}
                </button>
              </div>
            </div>

            <!-- Grant Voucher Manually -->
            <div class="bg-green-50 rounded-xl p-4 border border-green-200">
              <h4 class="text-sm font-bold text-green-800 mb-3 flex items-center gap-2">
                🎁 Berikan Voucher Langsung
              </h4>
              <p class="text-xs text-green-700 mb-3">Berikan voucher langsung tanpa mengubah progress order.</p>
              
              <div class="flex gap-2">
                <input type="number" v-model.number="loyaltyForm.voucherQty" min="1" max="10"
                       class="w-20 px-4 py-2 border border-green-300 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-400 outline-none text-center font-bold"
                       placeholder="Qty" />
                <button @click="grantVoucherManually" :disabled="savingVoucher" 
                        class="flex-1 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition disabled:opacity-50">
                  {{ savingVoucher ? '...' : 'Berikan Voucher' }}
                </button>
              </div>
            </div>
          </div>
          
          <div class="px-6 pb-6">
            <button @click="closeLoyaltyModal" class="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium">
              Tutup
            </button>
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
import { ref, reactive, computed, onMounted } from 'vue';

const customers = ref([]);
const loading = ref(true);
const searchQuery = ref('');

// Computed: Filtered Customers
const filteredCustomers = computed(() => {
  if (!searchQuery.value.trim()) {
    return customers.value;
  }
  const query = searchQuery.value.toLowerCase().trim();
  return customers.value.filter(customer => {
    const nama = (customer.nama || '').toLowerCase();
    const noHp = (customer.no_hp || '').toLowerCase();
    return nama.includes(query) || noHp.includes(query);
  });
});
const showModal = ref(false);
const editMode = ref(false);
const saving = ref(false);
const form = reactive({ id: null, nama: '', no_hp: '' });
const deleteConfirm = reactive({ show: false, customerId: null, customerName: '' });
const toast = reactive({ show: false, message: '', type: 'success' });

// Loyalty Modal
const showLoyaltyModal = ref(false);
const loyaltyCustomer = ref(null);
const loyaltyInfo = ref(null);
const loyaltyForm = reactive({ totalOrders: 0, voucherQty: 1 });
const savingLoyalty = ref(false);
const savingVoucher = ref(false);

function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => toast.show = false, 3000);
}

async function fetchCustomers() {
  loading.value = true;
  try {
    // Fetch customers with loyalty data
    const res = await fetch('/api/Beauty_Salon/Vouchers/allCustomersLoyalty');
    const data = await res.json();
    if (data.success) {
      customers.value = data.data;
    } else {
      // Fallback to regular customers endpoint
      const res2 = await fetch('/api/Beauty_Salon/Customers');
      const data2 = await res2.json();
      if (data2.success) customers.value = data2.data;
    }
  } catch (e) {
    console.error(e);
    // Fallback
    try {
      const res = await fetch('/api/Beauty_Salon/Customers');
      const data = await res.json();
      if (data.success) customers.value = data.data;
    } catch (e2) {
      console.error(e2);
    }
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

// Loyalty Functions
async function openLoyaltyModal(customer) {
  loyaltyCustomer.value = customer;
  loyaltyInfo.value = null;
  loyaltyForm.totalOrders = customer.effective_orders || 0;
  loyaltyForm.voucherQty = 1;
  showLoyaltyModal.value = true;
  
  // Fetch detailed loyalty info
  try {
    const res = await fetch(`/api/Beauty_Salon/Vouchers/customerStats/${customer.id}`);
    const data = await res.json();
    if (data.success) {
      loyaltyInfo.value = data.data;
      loyaltyForm.totalOrders = data.data.completed_orders || 0;
    }
  } catch (e) {
    console.error('Failed to fetch loyalty info:', e);
  }
}

function closeLoyaltyModal() {
  showLoyaltyModal.value = false;
  loyaltyCustomer.value = null;
  loyaltyInfo.value = null;
}

async function setLoyaltyProgress() {
  if (!loyaltyCustomer.value) return;
  
  savingLoyalty.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/Vouchers/setProgress', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        customer_id: loyaltyCustomer.value.id,
        completed_orders_count: loyaltyForm.totalOrders
      })
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message);
      
      // Show voucher info if any were granted
      if (data.data.vouchers_auto_granted > 0) {
        showToast(`🎁 ${data.data.vouchers_auto_granted} voucher otomatis diberikan!`);
      }
      
      // Refresh loyalty info
      loyaltyInfo.value = {
        ...loyaltyInfo.value,
        completed_orders: data.data.total_effective_orders,
        actual_orders: data.data.actual_orders_in_system,
        adjustment: data.data.adjustment_applied,
        progress_to_next: data.data.current_progress,
        orders_needed_for_next: data.data.orders_needed_for_next_voucher,
        available_vouchers_count: (loyaltyInfo.value?.available_vouchers_count || 0) + data.data.vouchers_auto_granted
      };
      
      // Refresh main list
      fetchCustomers();
    } else {
      showToast(data.message || 'Gagal menyimpan', 'error');
    }
  } catch (e) {
    showToast('Kesalahan jaringan', 'error');
  } finally {
    savingLoyalty.value = false;
  }
}

async function grantVoucherManually() {
  if (!loyaltyCustomer.value || loyaltyForm.voucherQty < 1) return;
  
  savingVoucher.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/Vouchers/grantManual', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        customer_id: loyaltyCustomer.value.id,
        qty: loyaltyForm.voucherQty
      })
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message);
      
      // Update loyalty info
      if (loyaltyInfo.value) {
        loyaltyInfo.value.available_vouchers_count = (loyaltyInfo.value.available_vouchers_count || 0) + data.data.vouchers_granted;
      }
      
      // Refresh main list
      fetchCustomers();
    } else {
      showToast(data.message || 'Gagal memberikan voucher', 'error');
    }
  } catch (e) {
    showToast('Kesalahan jaringan', 'error');
  } finally {
    savingVoucher.value = false;
  }
}

onMounted(() => fetchCustomers());
</script>
