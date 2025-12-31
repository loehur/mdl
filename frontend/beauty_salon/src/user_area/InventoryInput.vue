<template>
  <div class="max-w-6xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <!-- Header -->
      <div class="flex flex-col gap-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h2 class="text-2xl font-bold text-gray-800">Input Barang Persediaan</h2>
            <p class="text-sm text-gray-500 mt-1">Detail barang dari pengeluaran kategori Persediaan</p>
          </div>
          
          <!-- Tab Toggle -->
          <div class="flex p-1 bg-gray-100 rounded-xl">
            <button 
              @click="activeTab = 'pending'" 
              class="px-4 py-2 text-sm font-bold rounded-lg transition-all"
              :class="activeTab === 'pending' ? 'bg-white text-orange-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            >
              <span class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                  <span v-if="filteredPendingTransactions.length" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2" :class="filteredPendingTransactions.length ? 'bg-orange-500' : 'bg-gray-300'"></span>
                </span>
                Belum Diinput ({{ filteredPendingTransactions.length }})
              </span>
            </button>
            <button 
              @click="activeTab = 'settled'" 
              class="px-4 py-2 text-sm font-bold rounded-lg transition-all"
              :class="activeTab === 'settled' ? 'bg-white text-green-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            >
              Sudah Diinput ({{ filteredSettledTransactions.length }})
            </button>
          </div>
        </div>
        
        <!-- Date Filters -->
        <div class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-600">Filter Tanggal:</span>
          </div>
          
          <div class="flex items-center gap-2">
            <input 
              v-model="dateFrom" 
              type="date" 
              class="px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-200 focus:border-orange-400 outline-none text-sm"
            />
            <span class="text-gray-400">–</span>
            <input 
              v-model="dateTo" 
              type="date" 
              class="px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-200 focus:border-orange-400 outline-none text-sm"
            />
          </div>
          
          <div class="flex items-center gap-2">
            <button 
              @click="setDatePreset('today')"
              class="px-3 py-1.5 text-xs font-medium rounded-lg transition"
              :class="datePreset === 'today' ? 'bg-orange-100 text-orange-700' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
            >
              Hari Ini
            </button>
            <button 
              @click="setDatePreset('week')"
              class="px-3 py-1.5 text-xs font-medium rounded-lg transition"
              :class="datePreset === 'week' ? 'bg-orange-100 text-orange-700' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
            >
              7 Hari
            </button>
            <button 
              @click="setDatePreset('month')"
              class="px-3 py-1.5 text-xs font-medium rounded-lg transition"
              :class="datePreset === 'month' ? 'bg-orange-100 text-orange-700' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
            >
              30 Hari
            </button>
            <button 
              @click="setDatePreset('all')"
              class="px-3 py-1.5 text-xs font-medium rounded-lg transition"
              :class="datePreset === 'all' ? 'bg-orange-100 text-orange-700' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
            >
              Semua
            </button>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="bg-gray-50 rounded-lg p-4 animate-pulse">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="h-5 bg-gray-200 rounded w-1/3 mb-2"></div>
              <div class="h-4 bg-gray-200 rounded w-1/4"></div>
            </div>
            <div class="h-10 bg-gray-200 rounded w-24"></div>
          </div>
        </div>
      </div>

      <!-- Pending Transactions Tab -->
      <div v-else-if="activeTab === 'pending'">
        <div v-if="filteredPendingTransactions.length" class="space-y-4">
          <div 
            v-for="trx in filteredPendingTransactions" 
            :key="trx.id" 
            class="bg-gradient-to-br from-orange-50 to-amber-50 rounded-xl border-2 border-orange-200 p-5 hover:shadow-lg transition-all"
          >
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
              <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                  <span class="px-3 py-1 text-xs font-bold bg-orange-100 text-orange-700 rounded-full">
                    Belum Diinput
                  </span>
                  <span class="text-sm text-gray-500">{{ formatDate(trx.transaction_date) }}</span>
                </div>
                <h3 class="font-bold text-gray-800 text-lg mb-1">{{ trx.description || 'Pembelian Persediaan' }}</h3>
                <p class="text-2xl font-bold text-orange-600">Rp {{ formatNumber(trx.amount) }}</p>
              </div>
              
              <button 
                @click="openInputModal(trx)"
                class="px-6 py-3 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all flex items-center gap-2"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Input Barang
              </button>
            </div>
          </div>
        </div>
        
        <div v-else class="text-center py-16">
          <div class="w-20 h-20 mx-auto bg-green-100 rounded-full flex items-center justify-center mb-4">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
          </div>
          <h3 class="text-lg font-bold text-gray-800 mb-1">Semua Sudah Diinput!</h3>
          <p class="text-gray-500">Tidak ada transaksi persediaan yang perlu diinput detail barangnya</p>
        </div>
      </div>

      <!-- Settled Transactions Tab -->
      <div v-else-if="activeTab === 'settled'">
        <div v-if="filteredSettledTransactions.length" class="space-y-3">
          <div 
            v-for="trx in filteredSettledTransactions" 
            :key="trx.id" 
            class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl border border-green-200 p-4 hover:shadow-md transition-all"
          >
            <div class="flex items-center justify-between">
              <div>
                <div class="flex items-center gap-3 mb-1">
                  <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700 rounded-full">
                    ✓ Sudah Diinput
                  </span>
                  <span class="text-sm text-gray-500">{{ formatDate(trx.transaction_date) }}</span>
                </div>
                <h3 class="font-semibold text-gray-800">{{ trx.description || 'Pembelian Persediaan' }}</h3>
                <p class="text-sm text-gray-500">{{ trx.item_count }} jenis barang • {{ trx.total_qty }} pcs</p>
              </div>
              <div class="text-right">
                <p class="font-bold text-green-600 text-lg">Rp {{ formatNumber(trx.amount) }}</p>
              </div>
            </div>
          </div>
        </div>
        
        <div v-else class="text-center py-16">
          <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
          </svg>
          <p class="text-gray-500">Belum ada data transaksi persediaan yang sudah diinput</p>
        </div>
      </div>
    </div>

    <!-- Input Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-[100000] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
          <!-- Modal Header -->
          <div class="bg-gradient-to-r from-orange-500 to-amber-500 px-6 py-4 rounded-t-2xl flex-shrink-0">
            <h3 class="font-bold text-white text-lg">Input Detail Barang</h3>
            <p class="text-orange-100 text-sm">Total Nota: Rp {{ formatNumber(selectedTransaction?.amount || 0) }}</p>
          </div>
          
          <!-- Modal Body -->
          <div class="p-6 overflow-y-auto flex-grow">
            <!-- Items List -->
            <div class="space-y-3 mb-4">
              <div 
                v-for="(item, index) in inventoryItems" 
                :key="index"
                class="bg-gray-50 rounded-xl p-4 border border-gray-200"
              >
                <div class="flex items-start gap-3">
                  <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <!-- Item Name with Autocomplete -->
                    <div class="md:col-span-1 relative" :ref="el => itemRefs[index] = el">
                      <label class="block text-xs font-bold text-gray-500 mb-1">Nama Barang</label>
                      <input 
                        v-model="item.name" 
                        @focus="showSuggestions(index)"
                        @input="filterSuggestions(index)"
                        type="text" 
                        placeholder="Ketik nama barang..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-200 focus:border-orange-400 outline-none"
                      />
                      <!-- Suggestions Dropdown -->
                      <div 
                        v-if="activeSuggestion === index && filteredHistory.length"
                        class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-48 overflow-y-auto"
                      >
                        <div 
                          v-for="suggestion in filteredHistory" 
                          :key="suggestion.item_id"
                          @click="selectSuggestion(index, suggestion)"
                          class="px-3 py-2 hover:bg-orange-50 cursor-pointer border-b border-gray-100 last:border-0"
                        >
                          <div class="font-medium text-gray-800">{{ suggestion.item_name }}</div>
                          <div class="text-xs text-gray-500">Rp {{ formatNumber(suggestion.buy_price) }}</div>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Qty -->
                    <div>
                      <label class="block text-xs font-bold text-gray-500 mb-1">Jumlah</label>
                      <input 
                        v-model.number="item.qty" 
                        type="number" 
                        min="1"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-200 focus:border-orange-400 outline-none text-center"
                      />
                    </div>
                    
                    <!-- Price -->
                    <div>
                      <label class="block text-xs font-bold text-gray-500 mb-1">Harga Beli</label>
                      <div class="relative">
                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm">Rp</span>
                        <input 
                          v-model.number="item.buy_price" 
                          type="number" 
                          min="0"
                          class="w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-200 focus:border-orange-400 outline-none text-right"
                        />
                      </div>
                    </div>
                  </div>
                  
                  <!-- Delete Button -->
                  <button 
                    @click="removeItem(index)" 
                    v-if="inventoryItems.length > 1"
                    class="mt-6 p-2 text-red-500 hover:bg-red-50 rounded-lg transition"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                  </button>
                </div>
                
                <!-- Line Total -->
                <div class="text-right mt-2 text-sm">
                  <span class="text-gray-500">Subtotal:</span>
                  <span class="font-bold text-gray-800 ml-2">Rp {{ formatNumber(item.qty * item.buy_price) }}</span>
                </div>
              </div>
            </div>
            
            <!-- Add Item Button -->
            <button 
              @click="addItem" 
              class="w-full py-3 border-2 border-dashed border-gray-300 rounded-xl text-gray-500 hover:border-orange-400 hover:text-orange-600 hover:bg-orange-50 transition font-medium flex items-center justify-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
              </svg>
              Tambah Baris
            </button>
            
            <!-- Summary -->
            <div class="mt-6 bg-gray-50 rounded-xl p-4 border border-gray-200">
              <div class="flex justify-between items-center mb-2">
                <span class="text-gray-600">Total Nota</span>
                <span class="font-bold text-gray-800">Rp {{ formatNumber(selectedTransaction?.amount || 0) }}</span>
              </div>
              <div class="flex justify-between items-center mb-2">
                <span class="text-gray-600">Total Input</span>
                <span class="font-bold" :class="totalInput === (selectedTransaction?.amount || 0) ? 'text-green-600' : 'text-orange-600'">
                  Rp {{ formatNumber(totalInput) }}
                </span>
              </div>
              <div v-if="difference !== 0" class="flex justify-between items-center pt-2 border-t border-gray-200">
                <span class="text-gray-600">Selisih</span>
                <span class="font-bold text-red-500">Rp {{ formatNumber(Math.abs(difference)) }}</span>
              </div>
            </div>
          </div>
          
          <!-- Modal Footer -->
          <div class="p-6 border-t border-gray-100 bg-gray-50 flex gap-3 rounded-b-2xl flex-shrink-0">
            <button 
              @click="closeModal" 
              class="flex-1 px-4 py-3 border border-gray-200 text-gray-700 bg-white rounded-xl hover:bg-gray-50 font-medium transition"
            >
              Batal
            </button>
            <button 
              @click="submitItems"
              :disabled="saving || difference !== 0 || !hasValidItems"
              class="flex-1 px-4 py-3 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              <svg v-if="saving" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
              </svg>
              {{ saving ? 'Menyimpan...' : 'Simpan Barang' }}
            </button>
          </div>
        </div>
      </div>
      
      <!-- Toast Notification -->
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
import { ref, reactive, computed, onMounted } from 'vue';

const loading = ref(true);
const saving = ref(false);
const activeTab = ref('pending');
const pendingTransactions = ref([]);
const settledTransactions = ref([]);
const showModal = ref(false);
const selectedTransaction = ref(null);
const inventoryItems = ref([]);
const historyItems = ref([]); // For autocomplete
const activeSuggestion = ref(null);
const filteredHistory = ref([]);
const itemRefs = ref({});

// Date filter
const dateFrom = ref('');
const dateTo = ref('');
const datePreset = ref('all');

const toast = reactive({ show: false, message: '', type: 'success' });

// Helper function to check if date is in range
function isInDateRange(dateStr) {
  if (!dateFrom.value && !dateTo.value) return true;
  if (!dateStr) return false;
  
  const date = new Date(dateStr);
  date.setHours(0, 0, 0, 0);
  
  if (dateFrom.value) {
    const from = new Date(dateFrom.value);
    from.setHours(0, 0, 0, 0);
    if (date < from) return false;
  }
  
  if (dateTo.value) {
    const to = new Date(dateTo.value);
    to.setHours(23, 59, 59, 999);
    if (date > to) return false;
  }
  
  return true;
}

// Date preset function
function setDatePreset(preset) {
  datePreset.value = preset;
  const today = new Date();
  
  switch (preset) {
    case 'today':
      dateFrom.value = today.toISOString().split('T')[0];
      dateTo.value = today.toISOString().split('T')[0];
      break;
    case 'week':
      const weekAgo = new Date(today);
      weekAgo.setDate(weekAgo.getDate() - 7);
      dateFrom.value = weekAgo.toISOString().split('T')[0];
      dateTo.value = today.toISOString().split('T')[0];
      break;
    case 'month':
      const monthAgo = new Date(today);
      monthAgo.setDate(monthAgo.getDate() - 30);
      dateFrom.value = monthAgo.toISOString().split('T')[0];
      dateTo.value = today.toISOString().split('T')[0];
      break;
    case 'all':
    default:
      dateFrom.value = '';
      dateTo.value = '';
      break;
  }
}

// Computed - Filtered transactions
const filteredPendingTransactions = computed(() => {
  return pendingTransactions.value.filter(trx => isInDateRange(trx.transaction_date));
});

const filteredSettledTransactions = computed(() => {
  return settledTransactions.value.filter(trx => isInDateRange(trx.transaction_date));
});

// Computed
const totalInput = computed(() => {
  return inventoryItems.value.reduce((sum, item) => sum + (item.qty * item.buy_price), 0);
});

const difference = computed(() => {
  return totalInput.value - (selectedTransaction.value?.amount || 0);
});

const hasValidItems = computed(() => {
  return inventoryItems.value.some(item => item.name && item.qty > 0 && item.buy_price > 0);
});

// Functions
function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => toast.show = false, 3000);
}

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num || 0);
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
  });
}

async function fetchData() {
  loading.value = true;
  try {
    const [pendingRes, settledRes, historyRes] = await Promise.all([
      fetch('/api/Beauty_Salon/CashManagement/pendingInventoryTransactions'),
      fetch('/api/Beauty_Salon/CashManagement/settledInventoryTransactions'),
      fetch('/api/Beauty_Salon/CashManagement/inventoryHistory')
    ]);
    
    const pendingData = await pendingRes.json();
    const settledData = await settledRes.json();
    const historyData = await historyRes.json();
    
    if (pendingData.success) pendingTransactions.value = pendingData.data;
    if (settledData.success) settledTransactions.value = settledData.data;
    if (historyData.success) historyItems.value = historyData.data;
  } catch (e) {
    console.error('Fetch error:', e);
    showToast('Gagal memuat data', 'error');
  } finally {
    loading.value = false;
  }
}

function openInputModal(trx) {
  selectedTransaction.value = trx;
  inventoryItems.value = [{ name: '', qty: 1, buy_price: 0 }];
  activeSuggestion.value = null;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  selectedTransaction.value = null;
  activeSuggestion.value = null;
}

function addItem() {
  inventoryItems.value.push({ name: '', qty: 1, buy_price: 0 });
}

function removeItem(index) {
  inventoryItems.value.splice(index, 1);
}

function showSuggestions(index) {
  activeSuggestion.value = index;
  filteredHistory.value = historyItems.value.slice(0, 10);
}

function filterSuggestions(index) {
  const query = inventoryItems.value[index].name.toLowerCase();
  if (!query) {
    filteredHistory.value = historyItems.value.slice(0, 10);
  } else {
    filteredHistory.value = historyItems.value
      .filter(h => h.item_name.toLowerCase().includes(query))
      .slice(0, 10);
  }
}

function selectSuggestion(index, suggestion) {
  inventoryItems.value[index].name = suggestion.item_name;
  inventoryItems.value[index].buy_price = suggestion.buy_price;
  activeSuggestion.value = null;
}

async function submitItems() {
  if (difference.value !== 0) {
    showToast('Total input harus sama dengan total nota!', 'error');
    return;
  }
  
  if (!hasValidItems.value) {
    showToast('Minimal masukkan 1 barang yang valid', 'error');
    return;
  }
  
  saving.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/CashManagement/settleInventory', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        transaction_id: selectedTransaction.value.id,
        items: inventoryItems.value.filter(item => item.name && item.qty > 0)
      })
    });
    
    const data = await res.json();
    
    if (data.success) {
      showToast('✅ Barang berhasil disimpan!', 'success');
      closeModal();
      fetchData(); // Refresh lists
    } else {
      showToast(data.message || 'Gagal menyimpan', 'error');
    }
  } catch (e) {
    console.error('Submit error:', e);
    showToast('Terjadi kesalahan jaringan', 'error');
  } finally {
    saving.value = false;
  }
}

// Click outside handler for suggestions
function handleClickOutside(event) {
  if (activeSuggestion.value !== null) {
    const ref = itemRefs.value[activeSuggestion.value];
    if (ref && !ref.contains(event.target)) {
      activeSuggestion.value = null;
    }
  }
}

onMounted(() => {
  // Set default filter to 30 days
  setDatePreset('month');
  
  fetchData();
  document.addEventListener('click', handleClickOutside);
});

import { onUnmounted } from 'vue';
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
@keyframes fade-in-down {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-down {
  animation: fade-in-down 0.3s ease-out;
}
</style>
