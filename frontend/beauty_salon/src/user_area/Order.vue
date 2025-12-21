<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header & Actions -->
    <!-- Header & Actions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-3 flex items-center justify-between gap-4">
      <h2 class="text-lg font-bold text-gray-800">Daftar Order</h2>
      <button 
        @click="openCreateModal"
        class="px-4 py-2 bg-gradient-to-r from-pink-500 to-fuchsia-600 hover:from-pink-600 hover:to-fuchsia-700 text-white rounded-lg text-sm font-medium shadow-md hover:shadow-lg transition-all flex items-center gap-2"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        <span>Buat Order</span>
      </button>
    </div>

    <!-- Active Orders Grid -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
       <div v-for="i in 3" :key="i" class="bg-gray-50 h-64 rounded-xl animate-pulse"></div>
    </div>
    
    <div v-else-if="orders.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
      <div v-for="order in orders" :key="order.id" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow group">
        <!-- Card Header -->
        <div class="p-5 border-b border-gray-50 bg-gradient-to-r from-gray-50 to-white flex justify-between items-start">
          <div>
            <div class="font-bold text-gray-800 text-lg">{{ order.customer_name }}</div>
            <div class="text-sm text-gray-500 mt-1 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
              {{ order.customer_phone }}
            </div>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="getStatusClass(order.status)">
            {{ getStatusLabel(order.status) }}
          </span>
        </div>

        <!-- Order Items & Progress -->
        <div class="p-5 space-y-4">
           <div v-for="(item, itemIndex) in order.order_items" :key="itemIndex" class="space-y-2">
              <div class="font-medium text-gray-800 flex justify-between items-center">
                <span>{{ item.product_name }}</span>
                
                <!-- Price Edit -->
                <div v-if="editingItem.orderId === order.id && editingItem.itemIndex === itemIndex" class="flex items-center gap-2">
                    <input 
                        type="number" 
                        v-model.number="editingItem.price" 
                        class="w-24 px-2 py-1 text-sm border border-pink-300 rounded focus:border-pink-500 outline-none text-right"
                        @keyup.enter="savePrice"
                        @keyup.esc="cancelEditPrice"
                        autofocus
                        @click.stop
                    />
                    <button @click.stop="savePrice" class="text-green-600 hover:text-green-700 p-1 hover:bg-green-50 rounded">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </button>
                    <button @click.stop="cancelEditPrice" class="text-red-500 hover:text-red-600 p-1 hover:bg-red-50 rounded">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div v-else class="flex items-center gap-2 group/price cursor-pointer relative" @click="startEditPrice(order, itemIndex, item.price)" title="Klik untuk ubah harga">
                    <span class="text-pink-600 font-bold">Rp {{ formatNumber(item.price) }}</span>
                     <div v-if="!['completed', 'cancelled'].includes(order.status)" class="opacity-0 group-hover/price:opacity-100 absolute -left-5 text-gray-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </div>
                </div>
              </div>
              
              <!-- Work Steps Progress -->
              <div class="space-y-2 pl-3 border-l-2 border-gray-100">
                <div v-for="(step, stepIndex) in item.work_steps" :key="stepIndex" class="flex items-center justify-between text-sm">
                   <div class="flex items-center gap-2">
                     <button 
                       @click="toggleStepStatus(order, itemIndex, stepIndex, step)"
                       :disabled="['completed', 'cancelled'].includes(order.status)"
                       class="w-5 h-5 rounded border flex items-center justify-center transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                       :class="step.status === 'completed' ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-pink-500 text-transparent'"
                     >
                       <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                     </button>
                     <span :class="step.status === 'completed' ? 'text-gray-400 line-through' : 'text-gray-600'">
                        {{ displayStepName(step) }}
                     </span>
                   </div>
                   
                   <!-- Worker Selection (Small) -->
                   <div class="relative">
                      <select 
                        v-model="step.worker_id" 
                        :disabled="['completed', 'cancelled'].includes(order.status)"
                        @change="updateStepWorker(order.id, itemIndex, stepIndex, step.worker_id, step.status)"
                        class="text-xs border-0 bg-gray-50 rounded-md py-1 pl-2 pr-6 focus:ring-1 focus:ring-pink-500 cursor-pointer hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        <option :value="null">Pilih Terapis</option>
                        <option v-for="worker in workers" :key="worker.id" :value="worker.id">{{ worker.nama }}</option>
                      </select>
                   </div>
                </div>
              </div>
           </div>
        </div>

        <!-- Footer -->
        <div class="p-4 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
           <div class="text-xs text-gray-500">
             {{ formatDate(order.created_at) }}
           </div>
           <div class="font-bold text-gray-800">
             Total: Rp {{ formatNumber(order.total_price) }}
           </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="p-3 bg-white border-t border-gray-100 grid grid-cols-2 gap-3" v-if="order.status !== 'completed' && order.status !== 'cancelled'">
           <button @click="confirmDelete(order)" class="text-red-500 text-sm font-medium hover:bg-red-50 py-2 rounded-lg transition">Batalkan</button>
           <button @click="finishOrder(order)" class="bg-green-50 text-green-600 text-sm font-medium hover:bg-green-100 py-2 rounded-lg transition">Selesai</button>
        </div>
        <div class="p-3 bg-white border-t border-gray-100" v-if="order.status === 'completed'">
            <button @click="printOrder(order)" class="w-full text-gray-600 bg-gray-50 hover:bg-gray-100 text-sm font-medium py-2 rounded-lg transition border border-gray-200 flex justify-center items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak Nota
            </button>
        </div>
      </div>
    </div>
    
    <div v-else class="text-center py-20 bg-white rounded-xl border border-gray-100 border-dashed">
       <div class="text-gray-300 mb-4">
         <svg class="w-20 h-20 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
       </div>
       <h3 class="text-lg font-medium text-gray-800">Belum ada order aktif</h3>
       <p class="text-gray-500 mb-6">Mulai terima pelanggan hari ini!</p>
       <button @click="openCreateModal" class="text-pink-600 font-medium hover:text-pink-700">Buat Order Baru &rarr;</button>
    </div>

    <!-- Create Order Modal -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 z-[100000] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm overflow-y-auto w-full">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-4 flex flex-col max-h-[90vh]">
          <div class="bg-gradient-to-r from-pink-500 to-fuchsia-600 px-6 py-4 flex-shrink-0">
            <h3 class="font-bold text-white text-lg">Buat Order Baru</h3>
          </div>
          
          <div class="p-6 space-y-6 overflow-y-auto flex-grow">
            <!-- Step 1: Customer -->
            <div>
              <label class="block text-sm font-bold text-gray-800 mb-3">Pilih Pelanggan</label>
               <div class="relative" ref="custDropdownRef">
                 <input 
                   type="text" 
                   v-model="custSearch" 
                   @focus="showCustDropdown = true"
                   placeholder="Cari nama atau no HP..." 
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition bg-white"
                 />
                 <div v-if="form.customer_id" class="absolute right-3 top-3 text-green-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 </div>
                 
                 <!-- Dropdown List -->
                 <div v-if="showCustDropdown" class="absolute z-20 w-full mt-1 bg-white border border-gray-100 rounded-xl shadow-xl max-h-60 overflow-y-auto">
                    <div 
                        v-for="c in filteredCustomers" 
                        :key="c.id" 
                        @click="selectCustomer(c)"
                        class="px-4 py-3 hover:bg-pink-50 cursor-pointer border-b border-gray-50 last:border-0"
                    >
                        <div class="font-bold text-gray-800">{{ c.nama }}</div>
                        <div class="text-xs text-gray-500">{{ c.no_hp }}</div>
                    </div>
                    <div v-if="filteredCustomers.length === 0" class="px-4 py-3 text-gray-400 text-center text-sm">
                        Tidak ditemukan.
                    </div>
                 </div>
               </div>
              <p class="text-xs text-pink-600 mt-2 cursor-pointer hover:underline" @click="router.push('/customers')">+ Tambah Pelanggan Baru</p>
            </div>
            
            <!-- Step 2: Products -->
            <div>
               <label class="block text-sm font-bold text-gray-800 mb-3">Pilih Layanan</label>
               <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-60 overflow-y-auto p-1">
                 <div 
                   v-for="prod in products" 
                   :key="prod.id"
                   class="border rounded-xl p-3 cursor-pointer transition relative overflow-hidden group"
                   :class="isSelected(prod.id) ? 'border-pink-500 bg-pink-50' : 'border-gray-200 hover:border-pink-300'"
                   @click="toggleProductSelect(prod)"
                 >
                    <div class="font-medium text-gray-800">{{ prod.name }}</div>
                    <div class="text-sm font-bold text-pink-600">Rp {{ formatNumber(prod.price) }}</div>
                    
                    <div v-if="isSelected(prod.id)" class="absolute top-2 right-2 text-pink-500">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    </div>
                 </div>
               </div>
            </div>
            
            <!-- Step 3: Summary -->
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
               <div class="flex justify-between items-center mb-2">
                 <span class="text-gray-600">Total Item</span>
                 <span class="font-medium">{{ form.selectedItems.length }}</span>
               </div>
               <div class="flex justify-between items-center text-lg font-bold text-gray-800 border-t border-gray-200 pt-2">
                 <span>Total Harga</span>
                 <span class="text-pink-600">Rp {{ formatNumber(calculateTotal()) }}</span>
               </div>
            </div>
          </div>
          
          <div class="p-6 border-t border-gray-100 bg-gray-50 flex gap-3 flex-shrink-0">
             <button @click="showCreateModal = false" class="flex-1 px-4 py-3 border border-gray-200 text-gray-700 bg-white rounded-xl hover:bg-gray-50 font-medium transition">Batal</button>
             <button @click="submitOrder" :disabled="isSubmitting || !form.customer_id || !form.selectedItems.length" class="flex-1 px-4 py-3 bg-gradient-to-r from-pink-500 to-fuchsia-600 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed">
               {{ isSubmitting ? 'Memproses...' : 'Buat Order' }}
             </button>
          </div>
        </div>
      </div>
      
      <!-- Toast -->
      <div v-if="toast.show" class="fixed top-4 right-4 z-[1000050] animate-fade-in-down">
        <div class="bg-white rounded-lg shadow-2xl border-l-4 p-4" :class="toast.type === 'success' ? 'border-green-500' : 'border-red-500'">
          <p class="font-medium text-gray-800">{{ toast.message }}</p>
        </div>
      </div>

     <!-- Cancel Confirmation Modal -->
    <div v-if="showCancelModal" class="fixed inset-0 z-[100000] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 transform transition-all scale-100">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Batalkan Order?</h3>
            <p class="text-gray-500 text-sm mb-6">Tindakan ini tidak dapat dibatalkan. Apakah Anda yakin ingin membatalkan order ini?</p>
            
            <div class="flex gap-3">
                <button @click="showCancelModal = false" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition">
                    Tidak
                </button>
                <button @click="processCancellation" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-xl font-medium hover:bg-red-700 transition shadow-lg shadow-red-200">
                    Ya, Batalkan
                </button>
            </div>
        </div>
      </div>
    </div>

    <!-- Finish Confirmation Modal -->
    <div v-if="showFinishModal" class="fixed inset-0 z-[100000] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 transform transition-all scale-100">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Selesaikan Order?</h3>
            <p class="text-gray-500 text-sm mb-6">Pastikan semua layanan dan pembayaran telah selesai.</p>
            
            <div class="bg-gray-50 p-4 rounded-xl mb-6 text-left border border-gray-100">
                <h4 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider text-xs">Informasi Pembayaran</h4>
                <div class="space-y-3">
                    <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Metode Bayar</label>
                    <div class="relative">
                        <select v-model="finishForm.payment_method" @change="handlePaymentMethodChange" class="w-full pl-3 pr-8 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-green-500 appearance-none">
                            <option value="tunai">💵 Tunai</option>
                            <option value="non_tunai">💳 Non Tunai (Transfer/QRIS)</option>
                            <option value="split">⚖️ Split (Tunai + Non Tunai)</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                    </div>
                    
                    <!-- Nominal Fields if Split -->
                    <div v-if="finishForm.payment_method === 'split'" class="grid grid-cols-2 gap-2 bg-white p-2 rounded border border-gray-200">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase">Bayar Tunai</label>
                            <input type="number" v-model.number="finishForm.pay_cash" class="w-full border-b border-gray-200 focus:border-pink-500 outline-none py-1 text-sm font-bold text-gray-700" placeholder="0"  />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase">Bayar Non-Tunai</label>
                            <input type="number" v-model.number="finishForm.pay_non_cash" class="w-full border-b border-gray-200 focus:border-pink-500 outline-none py-1 text-sm font-bold text-gray-700" placeholder="0" />
                        </div>
                        <div class="col-span-2 text-xs text-right pt-1" :class="splitDiff === 0 ? 'text-green-600' : 'text-red-500'">
                            Total: {{ formatNumber(finishForm.pay_cash + finishForm.pay_non_cash) }} / {{ formatNumber(orderToFinish?.total_price || 0) }}
                             <span v-if="splitDiff !== 0">(Selisih: {{ formatNumber(Math.abs(splitDiff)) }})</span>
                        </div>
                    </div>
                    <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Catatan</label>
                    <textarea v-model="finishForm.payment_notes" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-green-500" rows="2" placeholder="Cth: BCA, QRIS"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button @click="showFinishModal = false" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition">
                    Batal
                </button>
                <button @click="processFinish" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-xl font-medium hover:bg-green-700 transition shadow-lg shadow-green-200">
                    Ya, Selesaikan
                </button>
            </div>
        </div>
      </div>
    </div>

    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const loading = ref(true);
const orders = ref([]);
const customers = ref([]);
const products = ref([]);
const workers = ref([]); // List of users with role cashier/admin to assign as worker
const showCreateModal = ref(false);

/* Customer Search */
const custSearch = ref('');
const showCustDropdown = ref(false);
const custDropdownRef = ref(null);

const filteredCustomers = computed(() => {
    if (!custSearch.value) return customers.value;
    const q = custSearch.value.toLowerCase();
    return customers.value.filter(c => 
        (c.nama && c.nama.toLowerCase().includes(q)) || 
        (c.no_hp && c.no_hp.includes(q))
    );
});

function selectCustomer(cust) {
    form.customer_id = cust.id;
    custSearch.value = cust.nama;
    showCustDropdown.value = false;
}

// Click Outside Handler
import { onUnmounted } from 'vue'; // Ensure import

const handleClickOutside = (event) => {
    if (custDropdownRef.value && !custDropdownRef.value.contains(event.target)) {
        showCustDropdown.value = false;
        // Reset text if valid
        if (form.customer_id) {
            const selected = customers.value.find(c => c.id === form.customer_id);
            if (selected && custSearch.value !== selected.nama) {
                custSearch.value = selected.nama;
            }
        }
    }
};
const isSubmitting = ref(false);
const showDeleteModal = ref(false);

const form = reactive({
  customer_id: null,
  selectedItems: [], // Array of product objects
  notes: ''
});

const toast = reactive({ show: false, message: '', type: 'success' });

function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => toast.show = false, 3000);
}

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num);
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleString('id-ID', { 
    day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' 
  });
}

function getStatusLabel(status) {
    const map = { pending: 'Proses', completed: 'Selesai', cancelled: 'Batal' };
    return map[status] || status;
}

function getStatusClass(status) {
    const map = { 
        pending: 'bg-yellow-100 text-yellow-700', 
        completed: 'bg-green-100 text-green-700', 
        cancelled: 'bg-red-100 text-red-700' 
    };
    return map[status] || 'bg-gray-100 text-gray-700';
}

function displayStepName(step) {
    if (step.step_name && step.step_name !== 'Step ' + step.step_id && step.step_name !== 'undefined') {
        return step.step_name;
    }
    // Fallback to lookup
    const found = allWorkSteps.value.find(s => s.id == step.step_id);
    return found ? found.name : 'Step #' + step.step_id;
}

// -- Fetch Data --
async function fetchData() {
    loading.value = true;
    try {
        const [resOrders, resCust, resProd] = await Promise.all([
            fetch('/api/Beauty_Salon/Orders'),
            fetch('/api/Beauty_Salon/Customers'),
            fetch('/api/Beauty_Salon/Products')
        ]);
        
        const dOrders = await resOrders.json();
        const dCust = await resCust.json();
        const dProd = await resProd.json();

        if (dOrders.success) {
            // Filter: Show pending/active orders OR Completed orders from TODAY
            const today = new Date().toISOString().split('T')[0];
            orders.value = dOrders.data.filter(order => {
                if (order.status !== 'completed') return true;
                const orderDate = (order.order_date || '').split(' ')[0];
                return orderDate === today;
            });
        }
        if (dCust.success) customers.value = dCust.data;
        if (dProd.success) products.value = dProd.data;
    } catch (e) {
        console.error(e);
        showToast('Gagal memuat data', 'error');
    } finally {
        loading.value = false;
    }
}


// Need a list of full work steps details to map IDs to Names
const allWorkSteps = ref([]);
async function fetchAllWorkSteps() {
   try {
       const res = await fetch('/api/Beauty_Salon/WorkStep');
       const d = await res.json();
       if(d.success) allWorkSteps.value = d.data;
   } catch{}
}
function openCreateModal() {
    form.customer_id = null;
    form.selectedItems = [];
    form.notes = '';
    custSearch.value = '';
    showCreateModal.value = true;
}

function isSelected(prodId) {
    return form.selectedItems.some(p => p.id === prodId);
}

function toggleProductSelect(prod) {
    const idx = form.selectedItems.findIndex(p => p.id === prod.id);
    if (idx >= 0) {
        form.selectedItems.splice(idx, 1);
    } else {
        // Prepare work steps with strict structure
        const rawSteps = Array.isArray(prod.work_steps) ? prod.work_steps : [];
        const hydratedSteps = rawSteps.map(stepRaw => {
            // Handle if stepRaw is ID (number/string) or Object
            const stepId = (typeof stepRaw === 'object' && stepRaw !== null) ? (stepRaw.id || stepRaw.step_id) : stepRaw;
            
            // Lookup details
            const details = allWorkSteps.value.find(s => s.id == stepId);
            
            return {
                step_id: stepId,
                step_name: details ? details.name : ('Step ' + stepId),
                fee: details ? Number(details.fee) : 0,
                worker_id: null,
                status: 'pending'
            };
        });

        // Push clean object
        form.selectedItems.push({
            id: prod.id,
            name: prod.name,
            price: Number(prod.price),
            work_steps: hydratedSteps
        });
    }
}

function calculateTotal() {
    return form.selectedItems.reduce((sum, item) => sum + item.price, 0);
}

async function submitOrder() {
    isSubmitting.value = true;
    try {
        // Construct payload explicitly to avoid reference issues or extra fields
        const orderItemsPayload = form.selectedItems.map(p => ({
            product_id: p.id,
            product_name: p.name,
            price: p.price,
            work_steps: p.work_steps.map(ws => ({
                step_id: ws.step_id,
                step_name: ws.step_name,
                fee: ws.fee,
                worker_id: null, // Force null for new orders
                status: 'pending'
            }))
        }));

        console.log('Submitting Order Payload:', orderItemsPayload); // Debug

        const res = await fetch('/api/Beauty_Salon/Orders/create', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                customer_id: form.customer_id,
                order_items: orderItemsPayload,
                notes: form.notes
            })
        });
        
        const data = await res.json();
        if (data.success) {
            showToast('Order berhasil dibuat');
            showCreateModal.value = false;
            fetchData(); 
        } else {
             showToast(data.message || 'Gagal buat order', 'error');
        }
    } catch(e) {
        console.error(e);
        showToast('Error koneksi', 'error');
    } finally {
        isSubmitting.value = false;
    }
}

// -- Order Actions --

async function updateStepWorker(orderId, itemIndex, stepIndex, workerId, currentStatus) {
    try {
        await fetch(`/api/Beauty_Salon/Orders/updateWorkStep/${orderId}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                item_index: itemIndex,
                step_index: stepIndex,
                worker_id: workerId,
                status: currentStatus
            })
        });
        // Silent update or toast?
    } catch(e) { console.error(e); }
}

async function toggleStepStatus(order, itemIdx, stepIdx, step) {
    const newStatus = step.status === 'completed' ? 'pending' : 'completed';
    // Optimistic update
    step.status = newStatus;
    
    try {
        await fetch(`/api/Beauty_Salon/Orders/updateWorkStep/${order.id}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                item_index: itemIdx,
                step_index: stepIdx,
                worker_id: step.worker_id,
                status: newStatus
            })
        });
        
        // Check if all steps completed -> maybe allow finish order visual cue?
    } catch(e) {
        step.status = step.status === 'completed' ? 'pending' : 'completed'; // revert
        showToast('Gagal update status', 'error');
    }
}

const showCancelModal = ref(false);
const orderToCancel = ref(null);
const showFinishModal = ref(false);
const orderToFinish = ref(null);
const finishForm = reactive({ 
    payment_method: 'tunai', 
    payment_notes: '',
    pay_cash: 0,
    pay_non_cash: 0
});

const splitDiff = computed(() => {
    if (!orderToFinish.value) return 0;
    const cash = Number(finishForm.pay_cash) || 0;
    const nonCash = Number(finishForm.pay_non_cash) || 0;
    return (cash + nonCash) - Number(orderToFinish.value.total_price);
});

function handlePaymentMethodChange() {
    if (!orderToFinish.value) return;
    const total = orderToFinish.value.total_price;
    
    if (finishForm.payment_method === 'tunai') {
        finishForm.pay_cash = total;
        finishForm.pay_non_cash = 0;
    } else if (finishForm.payment_method === 'non_tunai') {
        finishForm.pay_cash = 0;
        finishForm.pay_non_cash = total;
    } else {
        finishForm.pay_cash = 0;
        finishForm.pay_non_cash = 0;
    }
}

function finishOrder(order) {
    // Validation: Check pending steps and missing workers
    let pendingCount = 0;
    let missingWorkerCount = 0;

    if (order.order_items) {
        order.order_items.forEach(item => {
            if (item.work_steps) {
                item.work_steps.forEach(step => {
                    if (step.status !== 'completed') {
                        pendingCount++;
                    }
                    if (!step.worker_id) {
                        missingWorkerCount++;
                    }
                });
            }
        });
    }

    if (pendingCount > 0) {
        showToast('Semua langkah kerja harus berstatus Selesai (checklist hijau).', 'error');
        return;
    }

    if (missingWorkerCount > 0) {
        showToast('Semua langkah kerja harus memiliki Terapis yang bertugas.', 'error');
        return;
    }

    // Open Modal if Valid
    orderToFinish.value = order;
    // Reset form
    finishForm.payment_method = 'tunai';
    finishForm.payment_notes = '';
    finishForm.pay_cash = order.total_price;
    finishForm.pay_non_cash = 0;
    showFinishModal.value = true;
}

async function processFinish() {
    if (!orderToFinish.value) return;

    // Validate Split Payment
    if (finishForm.payment_method === 'split') {
        const cash = Number(finishForm.pay_cash) || 0;
        const nonCash = Number(finishForm.pay_non_cash) || 0;
        const totalInput = cash + nonCash;
        const billTotal = Number(orderToFinish.value.total_price);
        
        // Check difference with small tolerance
        if (Math.abs(totalInput - billTotal) > 0.01) {
            showToast(`Total tidak pas! Input: ${formatNumber(totalInput)} vs Tagihan: ${formatNumber(billTotal)}`, 'error');
            console.log('Split Validation Fail:', { cash, nonCash, totalInput, billTotal });
            return;
        }
    } else {
        // Force Values based on method to be safe
        handlePaymentMethodChange();
    }

    try {
        const res = await fetch(`/api/Beauty_Salon/Orders/updateStatus/${orderToFinish.value.id}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                status: 'completed',
                payment_method: finishForm.payment_method,
                payment_notes: finishForm.payment_notes,
                pay_cash: finishForm.pay_cash,
                pay_non_cash: finishForm.pay_non_cash
            })
        });
        const d = await res.json();
        if(d.success) {
            showToast('Order selesai');
            fetchData();
        } else {
             showToast(d.message || 'Gagal selesaikan order', 'error');
        }
    } catch(e) { 
        showToast('Terjadi kesalahan sistem', 'error'); 
    } finally {
        showFinishModal.value = false;
        orderToFinish.value = null;
    }
}

function confirmDelete(order) {
    // Open Modal instead of confirm()
    orderToCancel.value = order;
    showCancelModal.value = true;
}

async function processCancellation() {
    if (!orderToCancel.value) return;
    
    try {
        const res = await fetch(`/api/Beauty_Salon/Orders/updateStatus/${orderToCancel.value.id}`, {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({ status: 'cancelled' })
        });
        
        const d = await res.json();
        if(d.success) {
             showToast('Order dibatalkan');
             fetchData();
        } else {
             showToast(d.message || 'Gagal batalkan', 'error');
        }
    } catch(e) {
        showToast('Terjadi kesalahan sistem', 'error');
    } finally {
        showCancelModal.value = false;
        orderToCancel.value = null;
    }
}
// -- Update Price --
const editingItem = reactive({
  orderId: null,
  itemIndex: null,
  price: 0
});

function startEditPrice(order, index, price) {
 if (['completed', 'cancelled'].includes(order.status)) return;
 editingItem.orderId = order.id;
 editingItem.itemIndex = index;
 editingItem.price = price;
}

function cancelEditPrice() {
 editingItem.orderId = null;
 editingItem.itemIndex = null;
}

async function savePrice() {
 const { orderId, itemIndex, price } = editingItem;
 if (!orderId) return;
 
 const order = orders.value.find(o => o.id === orderId);
 if (!order) return;

 try {
     const res = await fetch(`/api/Beauty_Salon/Orders/updateItemPrice/${orderId}`, {
         method: 'POST',
         headers: {'Content-Type': 'application/json'},
         body: JSON.stringify({
             item_index: itemIndex,
             price: price
         })
     });
     const d = await res.json();
     if (d.success) {
         // Update local state
         if (order.order_items[itemIndex]) {
             order.order_items[itemIndex].price = price;
         }
         if (d.new_total !== undefined) {
             order.total_price = d.new_total;
         }
         showToast('Harga berhasil diubah');
         cancelEditPrice();
     } else {
         showToast(d.message || 'Gagal ubah harga', 'error');
     }
 } catch(e) {
     console.error(e);
     showToast('Terjadi kesalahan', 'error');
 }
}

// -- Print Helper --
// -- Print Helper (Direct Print) --
function generateReceiptText(order) {
    let html = "";
    
    // Helper formats for Printer Server
    // 1 Column = Center
    const row1 = (str) => `<tr><td>${str}</td></tr>`;
    // 2 Columns = Left - Right
    const row2 = (left, right) => `<tr><td>${left}</td><td>${right}</td></tr>`;
    const divider = () => `<tr><td>--------------------------------</td></tr>`;

    // Header
    const sName = (salonInfo.value.nama_salon || 'MDL BEAUTY SALON').toUpperCase();
    const sAddr = salonInfo.value.alamat_salon || 'Jakarta';
    
    html += row1(`<b>${sName}</b>`);
    html += row1(sAddr);
    html += divider();
    
    // Info
    // Note: If single TD is always center, and we want Left for info, we might need a trick or just accept center.
    // However, usually receipt headers are centered, info is left. 
    // If the user says "1 td = center", then for left alignment we might need to use row2('Text', '') or similar if supported.
    // For now, I will use row1 for general lines, but for Key-Value pairs I use row2.
    // Let's try to format the info section as Key-Value to ensure it looks neat or use row1 if acceptable.
    // Based on standard receipt, "No: #123" is often left. 
    // Let's try using row2 for info to force left alignment if we put empty string in column 2? 
    // Or maybe the user implies this specific markup logic:
    // "<td> jika ada 1 maka otomatis rata tengah" -> This implies strictly center.
    // For strictly left text (like date), maybe we assume the user accepts center or we try `<tr><td style="text-align:left">`?
    // User didn't mention styles. I will stick to the requested logic:
    // Header -> Center (1 td)
    // Items -> Left/Right (2 td)
    
    // Let's put Info in 2 columns for better layout
    html += row2(`No Order`, `#${order.id}`);
    html += row2(`Tanggal`, `${new Date().toLocaleDateString('id-ID')}`);
    html += row2(`Jam`, `${new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'})}`);
    html += row2(`Pelanggan`, order.customer_name);
    html += divider();

    // Items
    (order.order_items || []).forEach(item => {
        // Product Name on its own line (Centered? Or maybe left?)
        // If we want left, maybe we use row2(name, '')
        // Let's use row1 for name (Centered is okay for name) or row2 for name + price.
        // User pattern: "Product Name" ... "1x Price"
        
        // Let's try:
        // Col1: Item Name (br) x Qty
        // Col2: Price
        // Since we don't have qty in data (it implies 1), we just do:
        html += row2(item.product_name, formatNumber(item.price));
    });

    html += divider();
    
    // Totals
    html += row2("<b>TOTAL</b>", `<b>${formatNumber(order.total_price)}</b>`);
    
    // Payment
    const methodStr = (order.payment_method || 'TUNAI').toUpperCase().replace('_', ' ');
    const payCash = Number(order.pay_cash) || 0;
    const payNonCash = Number(order.pay_non_cash) || 0;
    const totalPaid = payCash + payNonCash;
    
    html += row2(methodStr, formatNumber(totalPaid));

    if (order.payment_method === 'split') {
        html += row2("Tunai", formatNumber(payCash));
        html += row2("Non-Tunai", formatNumber(payNonCash));
    }

    html += divider();
    html += row1("Terima Kasih");
    
    return html;
}

async function printOrder(order) {
    const text = generateReceiptText(order);
    
    // 1. Try Printer Server (Localhost)
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 2000); // 2s timeout
        
        // Assumption based on existing patterns: HTTP POST to local print service
        const res = await fetch('http://localhost:3000/print', {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({ 
                 text: text, 
                 printer_name: 'Thermal',
                 cut: true
             }),
             signal: controller.signal
        });
        clearTimeout(timeoutId);
        
        if (res.ok) {
            showToast('Tercetak via Server Local', 'success');
            return;
        }
    } catch (e) {
        console.log('Printer server not reachable, trying Serial...');
    }
    
    // 2. Try Web Serial API
    if ('serial' in navigator) {
        try {
            const ports = await navigator.serial.getPorts();
            let port;
            if (ports.length > 0) {
                 port = ports[0];
            } else {
                 try {
                    port = await navigator.serial.requestPort();
                 } catch (err) {
                    if (err.name === 'NotFoundError') {
                        showToast('Tidak ada port dipilih', 'info');
                        return;
                    }
                    throw err;
                 }
            }
            
            await port.open({ baudRate: 9600 });
            
            const encoder = new TextEncoder();
            const writer = port.writable.getWriter();
            
            // ESC/POS Commands
            const ESC = '\x1B';
            const GS = '\x1D';
            const init = ESC + '@';
            const cut = GS + 'V' + '\x42' + '\x00';
            
            await writer.write(encoder.encode(init + text + cut));
            
            writer.releaseLock();
            await port.close();
            
            showToast('Tercetak via Serial Port', 'success');
            return;
            
        } catch (e) {
            console.error('Serial Error:', e);
            showToast('Gagal cetak Serial: ' + e.message, 'error');
        }
    } else {
        // 3. Bluetooth (Placeholder)
        showToast('Browser tidak mendukung Web Serial. Bluetooth belum tersedia.', 'warning');
    }
}

const salonInfo = ref({ nama_salon: 'MDL BEAUTY SALON', alamat_salon: 'Jakarta' });

// -- Init --
onMounted(async () => {
    await fetchAllWorkSteps();
    await fetchTherapists(); // Fetch therapists as workers
    await fetchSalon();
    await fetchData();
});

async function fetchSalon() {
    try {
        const res = await fetch('/api/Beauty_Salon/Salon');
        const d = await res.json();
        if(d.success && d.data) salonInfo.value = d.data;
    } catch {}
}

async function fetchTherapists() {
    try {
        const res = await fetch('/api/Beauty_Salon/Therapists');
        const d = await res.json();
        if(d.success) workers.value = d.data; // Use therapists data for worker selection
    } catch {}
}

</script>

<style scoped>
/* Custom Scrollbar for overflow areas */
::-webkit-scrollbar {
  width: 6px;
}
::-webkit-scrollbar-track {
  background: #f1f1f1; 
}
::-webkit-scrollbar-thumb {
  background: #ddd; 
  border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
  background: #ccc; 
}
@keyframes fade-in-down {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-down {
  animation: fade-in-down 0.3s ease-out;
}
</style>
