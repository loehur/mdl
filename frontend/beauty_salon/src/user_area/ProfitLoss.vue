<template>
  <div class="max-w-5xl">
    <!-- Header Card -->
    <div class="bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 rounded-2xl shadow-xl p-6 mb-6 text-white">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold">📊 Laporan Laba Rugi</h1>
          <p class="text-emerald-100 mt-1">Profit & Loss Statement</p>
        </div>
        
        <!-- Date Filter -->
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-2 bg-white/10 backdrop-blur rounded-xl px-4 py-2">
            <input 
              v-model="dateFrom" 
              type="date" 
              class="bg-transparent border-none text-white placeholder-white/60 outline-none text-sm w-36"
            />
            <span class="text-white/60">–</span>
            <input 
              v-model="dateTo" 
              type="date" 
              class="bg-transparent border-none text-white placeholder-white/60 outline-none text-sm w-36"
            />
          </div>
          
          <button 
            @click="fetchReport"
            :disabled="loading || !isValidDateRange"
            class="px-4 py-2 bg-white text-emerald-700 rounded-xl font-bold hover:bg-emerald-50 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <span>{{ loading ? 'Loading...' : 'Tampilkan' }}</span>
          </button>
        </div>
      </div>
      
      <!-- Date Range Warning -->
      <div v-if="!isValidDateRange" class="mt-4 bg-red-500/20 border border-red-300/30 rounded-lg px-4 py-2 text-sm">
        ⚠️ Rentang tanggal maksimal 31 hari. Rentang saat ini: {{ dateRangeDays }} hari
      </div>
      
      <!-- Period Info -->
      <div v-if="reportData" class="mt-4 flex flex-wrap gap-4">
        <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2">
          <span class="text-emerald-100 text-sm">Periode:</span>
          <span class="font-bold ml-2">{{ formatDate(reportData.period.from) }} - {{ formatDate(reportData.period.to) }}</span>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2">
          <span class="text-emerald-100 text-sm">Durasi:</span>
          <span class="font-bold ml-2">{{ reportData.period.days }} hari</span>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="space-y-4">
      <div v-for="i in 4" :key="i" class="bg-white rounded-xl p-6 animate-pulse">
        <div class="h-6 bg-gray-200 rounded w-1/4 mb-4"></div>
        <div class="h-20 bg-gray-200 rounded"></div>
      </div>
    </div>

    <!-- Report Content -->
    <div v-else-if="reportData" class="space-y-6">
      
      <!-- Stats Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-4 border border-gray-100">
          <p class="text-gray-500 text-sm">Order Selesai</p>
          <p class="text-2xl font-bold text-gray-800">{{ reportData.statistics.completed_orders }}</p>
        </div>
        <div class="bg-white rounded-xl p-4 border border-gray-100">
          <p class="text-gray-500 text-sm">Total Order</p>
          <p class="text-2xl font-bold text-gray-800">{{ reportData.statistics.total_orders }}</p>
        </div>
        <div class="bg-white rounded-xl p-4 border border-gray-100">
          <p class="text-gray-500 text-sm">Barang Terjual</p>
          <p class="text-2xl font-bold text-purple-600">{{ reportData.statistics.items_sold }} pcs</p>
        </div>
        <div class="bg-white rounded-xl p-4 border border-gray-100">
          <p class="text-gray-500 text-sm">Margin Profit</p>
          <p class="text-2xl font-bold" :class="reportData.profit_margin >= 0 ? 'text-emerald-600' : 'text-red-600'">
            {{ reportData.profit_margin }}%
          </p>
        </div>
      </div>

      <!-- Profit/Loss Statement -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
          <h2 class="font-bold text-gray-800 text-lg">Laporan Laba Rugi</h2>
        </div>
        
        <div class="p-6">
          <!-- PENDAPATAN -->
          <div class="mb-6">
            <h3 class="font-bold text-gray-700 text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
              <span class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">📈</span>
              PENDAPATAN
            </h3>
            
            <div class="space-y-2 ml-10">
              <div class="flex justify-between items-center py-2 border-b border-gray-100">
                <span class="text-gray-600">Pendapatan Layanan</span>
                <span class="font-semibold text-gray-800">Rp {{ formatNumber(reportData.revenue.services) }}</span>
              </div>
              <div class="flex justify-between items-center py-2 border-b border-gray-100">
                <div class="flex items-center gap-2">
                  <span class="text-gray-600">Penjualan Barang</span>
                  <span v-if="reportData.statistics.items_sold > 0" class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">
                    {{ reportData.statistics.items_sold }} pcs
                  </span>
                </div>
                <span class="font-semibold text-gray-800">Rp {{ formatNumber(reportData.revenue.inventory) }}</span>
              </div>
              <div class="flex justify-between items-center py-2 border-b border-gray-100">
                <span class="text-gray-600">Pendapatan Lain-lain</span>
                <span class="font-semibold text-gray-800">Rp {{ formatNumber(reportData.revenue.other_income) }}</span>
              </div>
              <div class="flex justify-between items-center py-3 bg-emerald-50 rounded-lg px-3 -mx-3">
                <span class="font-bold text-emerald-700">Total Pendapatan</span>
                <span class="font-bold text-emerald-700 text-lg">Rp {{ formatNumber(reportData.revenue.total) }}</span>
              </div>
            </div>
          </div>

          <!-- HPP -->
          <div class="mb-6">
            <h3 class="font-bold text-gray-700 text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
              <span class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">📦</span>
              HARGA POKOK PENJUALAN (HPP)
            </h3>
            
            <div class="space-y-2 ml-10">
              <div class="flex justify-between items-center py-2 border-b border-gray-100">
                <span class="text-gray-600">HPP Barang Persediaan</span>
                <span class="font-semibold text-gray-800">Rp {{ formatNumber(reportData.cogs.inventory) }}</span>
              </div>
              <div class="flex justify-between items-center py-3 bg-orange-50 rounded-lg px-3 -mx-3">
                <span class="font-bold text-orange-700">Total HPP</span>
                <span class="font-bold text-orange-700 text-lg">(Rp {{ formatNumber(reportData.cogs.total) }})</span>
              </div>
              <!-- Laba Kotor Barang -->
              <div v-if="reportData.revenue.inventory > 0" class="flex justify-between items-center py-2 mt-2 bg-purple-50 rounded-lg px-3 -mx-3">
                <span class="text-purple-700 font-medium">Laba Kotor Barang</span>
                <span class="font-bold" :class="reportData.inventory_profit >= 0 ? 'text-purple-700' : 'text-red-600'">
                  Rp {{ formatNumber(reportData.inventory_profit) }}
                </span>
              </div>
            </div>
          </div>

          <!-- LABA KOTOR -->
          <div class="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
            <div class="flex justify-between items-center">
              <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-xl">💰</span>
                <span class="font-bold text-blue-700 text-lg">LABA KOTOR</span>
              </div>
              <span class="font-bold text-2xl" :class="reportData.gross_profit >= 0 ? 'text-blue-700' : 'text-red-600'">
                Rp {{ formatNumber(reportData.gross_profit) }}
              </span>
            </div>
          </div>

          <!-- BIAYA OPERASIONAL -->
          <div class="mb-6">
            <h3 class="font-bold text-gray-700 text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
              <span class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">💸</span>
              BIAYA OPERASIONAL
            </h3>
            
            <div class="space-y-2 ml-10">
              <template v-if="reportData.expenses.by_category.length">
                <div 
                  v-for="expense in reportData.expenses.by_category" 
                  :key="expense.category_name"
                  class="flex justify-between items-center py-2 border-b border-gray-100"
                >
                  <span class="text-gray-600">{{ expense.category_name || 'Lain-lain' }}</span>
                  <span class="font-semibold text-gray-800">Rp {{ formatNumber(expense.total) }}</span>
                </div>
              </template>
              <div v-else class="text-gray-400 text-sm italic py-2">
                Tidak ada biaya operasional
              </div>
              <div class="flex justify-between items-center py-3 bg-red-50 rounded-lg px-3 -mx-3">
                <span class="font-bold text-red-700">Total Biaya Operasional</span>
                <span class="font-bold text-red-700 text-lg">(Rp {{ formatNumber(reportData.expenses.total) }})</span>
              </div>
            </div>
          </div>

          <!-- LABA BERSIH -->
          <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-xl p-6 text-white">
            <div class="flex justify-between items-center">
              <div class="flex items-center gap-3">
                <span class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl">🎯</span>
                <div>
                  <span class="font-bold text-xl">LABA BERSIH</span>
                  <p class="text-emerald-100 text-sm">Net Profit</p>
                </div>
              </div>
              <div class="text-right">
                <span class="font-bold text-3xl" :class="reportData.net_profit < 0 ? 'text-red-200' : ''">
                  {{ reportData.net_profit < 0 ? '-' : '' }}Rp {{ formatNumber(Math.abs(reportData.net_profit)) }}
                </span>
                <p v-if="reportData.net_profit < 0" class="text-red-200 text-sm mt-1">📉 Rugi</p>
                <p v-else class="text-emerald-100 text-sm mt-1">📈 Untung</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading && !error" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
      <div class="w-20 h-20 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
        </svg>
      </div>
      <p class="text-gray-500">Pilih rentang tanggal dan klik "Tampilkan" untuk melihat laporan</p>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
      <p class="text-red-600">{{ error }}</p>
      <button @click="fetchReport" class="mt-3 px-4 py-2 bg-red-100 text-red-700 rounded-lg font-medium hover:bg-red-200 transition">
        Coba Lagi
      </button>
    </div>

    <!-- Toast -->
    <Teleport to="body">
      <div v-if="toast.show" class="fixed top-4 right-4 z-[100002] animate-fade-in-down">
        <div class="bg-white rounded-lg shadow-2xl border-l-4 p-4 min-w-[300px]" :class="toast.type === 'success' ? 'border-green-500' : 'border-red-500'">
          <p class="font-medium text-gray-800">{{ toast.message }}</p>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';

const loading = ref(false);
const error = ref(null);
const reportData = ref(null);

// Helper to get local date string YYYY-MM-DD
const getLocalDateString = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

// Default: current month
const today = new Date();
const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const dateFrom = ref(getLocalDateString(firstDayOfMonth));
const dateTo = ref(getLocalDateString(today));

const toast = reactive({ show: false, message: '', type: 'success' });

// Computed
const dateRangeDays = computed(() => {
  if (!dateFrom.value || !dateTo.value) return 0;
  const from = new Date(dateFrom.value);
  const to = new Date(dateTo.value);
  return Math.ceil((to - from) / (1000 * 60 * 60 * 24)) + 1;
});

const isValidDateRange = computed(() => {
  return dateRangeDays.value > 0 && dateRangeDays.value <= 31;
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
    day: 'numeric', month: 'short', year: 'numeric'
  });
}

async function fetchReport() {
  if (!isValidDateRange.value) {
    showToast('Rentang tanggal maksimal 31 hari', 'error');
    return;
  }
  
  loading.value = true;
  error.value = null;
  
  try {
    const res = await fetch(`/api/Beauty_Salon/CashManagement/profitLossReport?date_from=${dateFrom.value}&date_to=${dateTo.value}`);
    const data = await res.json();
    
    if (data.success) {
      reportData.value = data.data;
    } else {
      error.value = data.message || 'Gagal memuat laporan';
      showToast(error.value, 'error');
    }
  } catch (e) {
    console.error('Fetch report error:', e);
    error.value = 'Terjadi kesalahan jaringan';
    showToast(error.value, 'error');
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchReport();
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
