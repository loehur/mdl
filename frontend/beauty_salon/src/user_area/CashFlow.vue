<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header & Filter -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 space-y-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Riwayat Pembayaran</h1>
        <p class="text-sm text-gray-500">Transaksi pembayaran tunai (Maksimal rentang 1 minggu)</p>
      </div>
      
      <!-- Filter Section - Responsive -->
      <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex items-center gap-2 flex-1">
          <input type="date" v-model="filters.startDate" class="flex-1 border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-pink-200">
          <span class="text-gray-400 flex-shrink-0">-</span>
          <input type="date" v-model="filters.endDate" class="flex-1 border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-pink-200">
        </div>
        <button @click="fetchData" class="bg-pink-600 hover:bg-pink-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition shadow-lg shadow-pink-200 whitespace-nowrap sm:flex-shrink-0">
          Filter
        </button>
      </div>

      <!-- Search Bar -->
      <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari order ID, nama pelanggan, atau metode bayar..."
          class="w-full pl-10 pr-10 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-200 focus:border-pink-400 outline-none transition"
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
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
            <h3 class="font-bold text-gray-800">Riwayat Pembayaran</h3>
            <span class="text-xs font-medium bg-gray-100 text-gray-600 px-2 py-1 rounded-lg">{{ filteredTransactions.length }} Transaksi</span>
        </div>
         <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b">
                    <tr>
                        <th class="px-6 py-4">Tanggal & Waktu</th>
                        <th class="px-6 py-4">Order ID</th>
                        <th class="px-6 py-4">Pelanggan</th>
                        <th class="px-6 py-4">Metode Bayar</th>
                         <th class="px-6 py-4 text-right">Total Pembayaran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="loading" class="animate-pulse">
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400">Memuat data...</td>
                    </tr>
                    <tr v-else-if="filteredTransactions.length === 0">
                         <td colspan="5" class="px-6 py-8 text-center text-gray-400">
                           {{ transactions.length && searchQuery ? 'Tidak ada transaksi yang cocok dengan pencarian' : 'Tidak ada transaksi tunai pada periode ini' }}
                         </td>
                    </tr>
                    <tr v-for="trx in filteredTransactions" :key="trx.id" class="hover:bg-gray-50 transition duration-150">
                        <td class="px-6 py-3 whitespace-nowrap text-gray-600 font-mono text-xs">
                            {{ formatDateTime(trx.order_date || trx.created_at) }}
                        </td>
                         <td class="px-6 py-3 font-medium text-gray-800">
                             #{{ trx.id }}
                        </td>
                        <td class="px-6 py-3 text-gray-700">
                             {{ trx.customer_name }}
                        </td>
                        <td class="px-6 py-3">
                             <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                :class="trx.payment_method === 'tunai' ? 'bg-emerald-100 text-emerald-800' : 'bg-orange-100 text-orange-800'">
                                 {{ (trx.payment_method || 'Tunai').toUpperCase() }}
                             </span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <div class="font-bold text-emerald-600">{{ formatCurrency(getTotalPayment(trx)) }}</div>
                            <div v-if="trx.payment_method !== 'tunai'" class="text-xs text-gray-500 mt-0.5">
                              Tunai: {{ formatCurrency(trx.pay_cash || 0) }} | Non-tunai: {{ formatCurrency(getTotalPayment(trx) - (trx.pay_cash || 0)) }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
         </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';

const loading = ref(true);
const transactions = ref([]);
const searchQuery = ref('');
const totalCashPeriod = ref(0); // Saldo untuk periode tertentu

// Default: Last 7 days
const today = new Date();
const sevenDaysAgo = new Date(today);
sevenDaysAgo.setDate(today.getDate() - 6); // 6 days ago + today = 7 days
const formatDateInput = (d) => d.toISOString().split('T')[0];

const filters = reactive({
    startDate: formatDateInput(sevenDaysAgo),
    endDate: formatDateInput(today)
});

const filteredTransactions = computed(() => {
    if (!searchQuery.value.trim()) {
        return transactions.value;
    }
    const query = searchQuery.value.toLowerCase().trim();
    return transactions.value.filter(trx => {
        if (String(trx.id).includes(query)) return true;
        if ((trx.customer_name || '').toLowerCase().includes(query)) return true;
        if ((trx.payment_method || '').toLowerCase().includes(query)) return true;
        if ((trx.customer_phone || '').includes(query)) return true;
        return false;
    });
});

async function fetchData() {
    // Validate date range: max 7 days
    const start = new Date(filters.startDate);
    const end = new Date(filters.endDate);
    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays > 7) {
        alert('❌ Rentang tanggal maksimal 7 hari!\nSilakan pilih periode yang lebih pendek.');
        return;
    }
    
    loading.value = true;
    try {
        const res = await fetch('/api/Beauty_Salon/Orders');
        const d = await res.json();
        
        if (d.success) {
            processData(d.data);
        }
    } catch(e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function processData(orders) {
    const start = new Date(filters.startDate);
    const end = new Date(filters.endDate);
    end.setHours(23, 59, 59);

    let sumPeriod = 0;
    const items = [];

    orders.forEach(order => {
        // Must be completed
        if (order.status !== 'completed') return;

        // Get total payment (cash + non-cash)
        const totalPayment = getTotalPayment(order);
        if (totalPayment <= 0) return; // Skip if no payment

        // Date Check for Period
        const oDateStr = order.order_date || order.created_at;
        if (!oDateStr) return;
        const oDate = new Date(oDateStr); 
        
        // Only add to period if within date range
        if (oDate >= start && oDate <= end) {
            sumPeriod += totalPayment;
            items.push(order);
        }
    });

    // Sort Descending (Newest first)
    items.sort((a,b) => new Date(b.order_date || b.created_at) - new Date(a.order_date || a.created_at));

    totalCashPeriod.value = sumPeriod;
    transactions.value = items;
}

// Calculate total payment (cash + non-cash)
function getTotalPayment(order) {
    const cash = Number(order.pay_cash) || 0;
    const nonCash = Number(order.pay_non_cash) || 0; // Fixed: pay_non_cash with underscore!
    return cash + nonCash;
}

function formatCurrency(val) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
}

function formatDate(dStr) {
    const d = new Date(dStr);
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateTime(dStr) {
    if(!dStr) return '-';
    const d = new Date(dStr);
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) + ' ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute:'2-digit' });
}

onMounted(() => {
    fetchData();
});
</script>
