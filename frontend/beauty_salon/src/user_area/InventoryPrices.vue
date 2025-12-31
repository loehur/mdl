<template>
  <div class="max-w-6xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-2xl font-bold text-gray-800">Harga Jual Persediaan</h2>
          <p class="text-sm text-gray-500 mt-1">Atur harga jual barang dari pembelian persediaan</p>
        </div>
        <div class="flex items-center gap-3">
          <div class="relative">
            <svg class="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Cari barang..."
              class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-200 focus:border-purple-400 outline-none transition w-64"
            />
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-br from-purple-50 to-fuchsia-50 rounded-xl p-4 border border-purple-100">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
              <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
              </svg>
            </div>
            <div>
              <p class="text-sm text-gray-500">Total Item</p>
              <p class="text-xl font-bold text-gray-800">{{ inventoryItems.length }}</p>
            </div>
          </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-4 border border-green-100">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <div>
              <p class="text-sm text-gray-500">Sudah Ada Harga Jual</p>
              <p class="text-xl font-bold text-green-600">{{ itemsWithPrice }}</p>
            </div>
          </div>
        </div>
        
        <div class="bg-gradient-to-br from-amber-50 to-yellow-50 rounded-xl p-4 border border-amber-100">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
              <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
            </div>
            <div>
              <p class="text-sm text-gray-500">Belum Ada Harga Jual</p>
              <p class="text-xl font-bold text-amber-600">{{ itemsWithoutPrice }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 5" :key="i" class="bg-gray-50 rounded-lg p-4 animate-pulse">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="h-5 bg-gray-200 rounded w-1/3 mb-2"></div>
              <div class="h-4 bg-gray-200 rounded w-1/4"></div>
            </div>
            <div class="h-10 bg-gray-200 rounded w-32"></div>
          </div>
        </div>
      </div>

      <!-- Items List -->
      <div v-else-if="filteredItems.length" class="space-y-3">
        <div 
          v-for="item in filteredItems" 
          :key="item.item_id" 
          class="bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 p-4 hover:shadow-md transition-all group"
          :class="{
            'border-green-200 bg-gradient-to-br from-green-50 to-emerald-50': item.sell_price && item.sell_price > 0,
            'border-amber-200 bg-gradient-to-br from-amber-50 to-yellow-50': !item.sell_price || item.sell_price == 0
          }"
        >
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <!-- Item Info -->
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2">
                <h3 class="font-bold text-gray-800 text-lg">{{ item.item_name }}</h3>
                <span 
                  v-if="item.sell_price && item.sell_price > 0"
                  class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700 rounded-full"
                >
                  ✓ Sudah Set
                </span>
                <span 
                  v-else
                  class="px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700 rounded-full animate-pulse"
                >
                  ⚠ Belum Set
                </span>
              </div>
              
              <div class="flex flex-wrap items-center gap-4 text-sm">
                <div class="flex items-center gap-1.5 text-gray-600">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                  </svg>
                  <span class="text-gray-500">Harga Beli:</span>
                  <span class="font-semibold text-gray-800">Rp {{ formatNumber(item.buy_price) }}</span>
                </div>
                
                <div class="flex items-center gap-1.5 text-gray-600">
                  <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                  </svg>
                  <span class="text-gray-500">Stok:</span>
                  <span class="font-semibold" :class="item.total_qty > 0 ? 'text-purple-600' : 'text-red-600'">{{ item.total_qty }}</span>
                  <span v-if="item.total_sold > 0" class="text-xs text-gray-400">
                    (Beli: {{ item.total_purchased }}, Jual: {{ item.total_sold }})
                  </span>
                </div>
                
                <div class="flex items-center gap-1.5 text-gray-600">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                  <span class="text-gray-500">Transaksi:</span>
                  <span class="font-semibold text-gray-800">{{ item.transaction_count }}x</span>
                </div>
              </div>

              <!-- Profit Indicator -->
              <div v-if="item.sell_price && item.sell_price > 0" class="mt-2">
                <div 
                  class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-medium"
                  :class="getProfitClass(item)"
                >
                  <span v-if="getProfit(item) > 0">📈</span>
                  <span v-else-if="getProfit(item) < 0">📉</span>
                  <span v-else>➖</span>
                  <span>Margin: {{ formatNumber(getProfit(item)) }} ({{ getProfitPercent(item) }}%)</span>
                </div>
              </div>
            </div>
            
            <!-- Price Input -->
            <div class="flex items-center gap-3">
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm font-medium">Rp</span>
                <input 
                  v-model.number="item.sell_price_input" 
                  type="number" 
                  min="0"
                  step="1000"
                  placeholder="Harga Jual"
                  class="w-36 pl-10 pr-3 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-200 focus:border-purple-500 outline-none transition text-right font-semibold text-gray-800"
                  :class="{
                    'border-green-300 bg-green-50': hasChanged(item) && item.sell_price_input > 0,
                    'border-amber-300 bg-amber-50': !item.sell_price_input || item.sell_price_input == 0
                  }"
                />
              </div>
              
              <button 
                @click="savePrice(item)"
                :disabled="saving === item.item_id || !hasChanged(item)"
                class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-fuchsia-600 hover:from-purple-600 hover:to-fuchsia-700 text-white rounded-xl font-medium shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              >
                <svg v-if="saving === item.item_id" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>{{ saving === item.item_id ? 'Menyimpan...' : 'Simpan' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-16">
        <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
        </svg>
        <p class="text-gray-500">{{ searchQuery ? 'Tidak ada barang yang cocok' : 'Belum ada data pembelian persediaan' }}</p>
        <p class="text-gray-400 text-sm mt-1">Data akan muncul setelah ada transaksi pembelian persediaan</p>
      </div>
    </div>

    <!-- Toast Notification -->
    <Teleport to="body">
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

const inventoryItems = ref([]);
const loading = ref(true);
const saving = ref(null);
const searchQuery = ref('');

const toast = reactive({
  show: false,
  message: '',
  type: 'success'
});

// Computed properties
const filteredItems = computed(() => {
  if (!searchQuery.value) return inventoryItems.value;
  const q = searchQuery.value.toLowerCase();
  return inventoryItems.value.filter(item => 
    item.item_name.toLowerCase().includes(q)
  );
});

const itemsWithPrice = computed(() => {
  return inventoryItems.value.filter(item => item.sell_price && item.sell_price > 0).length;
});

const itemsWithoutPrice = computed(() => {
  return inventoryItems.value.filter(item => !item.sell_price || item.sell_price == 0).length;
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

function hasChanged(item) {
  return item.sell_price_input !== (item.sell_price || 0);
}

function getProfit(item) {
  return (item.sell_price || 0) - (item.buy_price || 0);
}

function getProfitPercent(item) {
  if (!item.buy_price || item.buy_price === 0) return 0;
  const profit = getProfit(item);
  return Math.round((profit / item.buy_price) * 100);
}

function getProfitClass(item) {
  const profit = getProfit(item);
  if (profit > 0) return 'bg-green-100 text-green-700';
  if (profit < 0) return 'bg-red-100 text-red-700';
  return 'bg-gray-100 text-gray-700';
}

async function fetchInventoryItems() {
  loading.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/CashManagement/inventoryItemsList');
    const data = await res.json();
    if (data.success) {
      inventoryItems.value = data.data.map(item => ({
        ...item,
        sell_price_input: item.sell_price || 0
      }));
    } else {
      showToast(data.message || 'Gagal memuat data', 'error');
    }
  } catch (e) {
    console.error('Fetch inventory items error:', e);
    showToast('Terjadi kesalahan jaringan', 'error');
  } finally {
    loading.value = false;
  }
}

async function savePrice(item) {
  if (!hasChanged(item)) return;
  
  saving.value = item.item_id;
  try {
    const res = await fetch('/api/Beauty_Salon/CashManagement/updateSellPrice', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        item_id: item.item_id,
        sell_price: item.sell_price_input
      })
    });

    const data = await res.json();

    if (data.success) {
      item.sell_price = item.sell_price_input;
      showToast('✅ Harga jual berhasil disimpan!', 'success');
    } else {
      showToast(data.message || 'Gagal menyimpan', 'error');
    }
  } catch (e) {
    console.error('Save price error:', e);
    showToast('Terjadi kesalahan jaringan', 'error');
  } finally {
    saving.value = null;
  }
}

onMounted(() => {
  fetchInventoryItems();
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
