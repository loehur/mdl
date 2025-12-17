<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header & Filter -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Laporan Kas</h1>
        <p class="text-sm text-gray-500">Saldo tunai dari pesanan selesai</p>
      </div>
      <div class="flex items-center gap-2">
        <input type="date" v-model="filters.startDate" class="border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-pink-200">
        <span class="text-gray-400">-</span>
        <input type="date" v-model="filters.endDate" class="border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-pink-200">
        <button @click="fetchData" class="bg-pink-600 hover:bg-pink-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-lg shadow-pink-200">
          Filter
        </button>
      </div>
    </div>

    <!-- Saldo Card -->
    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 text-white shadow-xl relative overflow-hidden">
        <div class="absolute top-0 right-0 p-8 opacity-10 transform translate-x-1/2 -translate-y-1/2">
             <svg class="w-64 h-64" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.15-1.46-3.27-3.4h1.96c.1 1.05 1.18 1.91 2.53 1.91 1.29 0 2.13-.77 2.13-2.11 0-2.85-6-1.62-6-5.5 0-1.68 1.17-2.91 2.87-3.29V4h2.67v1.82c1.71.43 2.91 1.62 3.03 3.12h-1.95c-.15-.98-.94-1.63-2.03-1.63-1.07 0-1.94.72-1.94 1.83 0 2.54 6.02 1.55 6.02 5.54 0 1.96-1.33 3.32-3.35 3.69z"></path></svg>
        </div>
        <div class="relative z-10">
            <p class="text-emerald-100 font-medium mb-1">Total Saldo Kas (Tunai)</p>
            <h2 class="text-4xl font-bold tracking-tight">{{ formatCurrency(totalCash) }}</h2>
            <p class="text-sm text-emerald-100 mt-2 opacity-90">Periode: {{ formatDate(filters.startDate) }} s/d {{ formatDate(filters.endDate) }}</p>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
            <h3 class="font-bold text-gray-800">Riwayat Transaksi Masuk</h3>
            <span class="text-xs font-medium bg-gray-100 text-gray-600 px-2 py-1 rounded-lg">{{ transactions.length }} Transaksi</span>
        </div>
         <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b">
                    <tr>
                        <th class="px-6 py-4">Tanggal & Waktu</th>
                        <th class="px-6 py-4">Order ID</th>
                        <th class="px-6 py-4">Pelanggan</th>
                        <th class="px-6 py-4">Metode Bayar</th>
                         <th class="px-6 py-4 text-right">Nominal Masuk (Tunai)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="loading" class="animate-pulse">
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400">Memuat data...</td>
                    </tr>
                    <tr v-else-if="transactions.length === 0">
                         <td colspan="5" class="px-6 py-8 text-center text-gray-400">Tidak ada transaksi tunai pada periode ini</td>
                    </tr>
                    <tr v-for="trx in transactions" :key="trx.id" class="hover:bg-gray-50 transition duration-150">
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
                        <td class="px-6 py-3 text-right font-bold text-emerald-600">
                            + {{ formatCurrency(trx.pay_cash) }}
                        </td>
                    </tr>
                </tbody>
            </table>
         </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';

const loading = ref(true);
const transactions = ref([]);
const totalCash = ref(0);

// Default: This Month
const today = new Date();
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
const formatDateInput = (d) => d.toISOString().split('T')[0];

const filters = reactive({
    startDate: formatDateInput(firstDay),
    endDate: formatDateInput(today)
});

async function fetchData() {
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

    let sum = 0;
    const items = [];

    orders.forEach(order => {
        // Must be completed
        if (order.status !== 'completed') return;

        // Date Check
        const oDateStr = order.order_date || order.created_at;
        if (!oDateStr) return;
        const oDate = new Date(oDateStr); 
        if (oDate < start || oDate > end) return;

        // Cash Calculation
        // pay_cash store the cash amount. If method is 'non_tunai', pay_cash should be 0.
        // We ensure we only count positive cash entries.
        const cashAmount = Number(order.pay_cash) || 0;

        if (cashAmount > 0) {
            sum += cashAmount;
            items.push(order);
        }
    });

    // Sort Descending (Newest first)
    items.sort((a,b) => new Date(b.order_date || b.created_at) - new Date(a.order_date || a.created_at));

    totalCash.value = sum;
    transactions.value = items;
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
