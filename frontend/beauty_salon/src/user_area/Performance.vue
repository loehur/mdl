<template>
  <div class="max-w-6xl mx-auto space-y-6">
    <!-- Header & Filter -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 space-y-4">
      <!-- Title -->
      <div>
        <h1 class="text-xl md:text-2xl font-bold text-gray-800">Kinerja Terapis</h1>
        <p class="text-xs md:text-sm text-gray-500">Statistik pekerjaan dan poin terapis (Maksimal rentang 31 hari)</p>
      </div>
      
      <!-- Filter Section -->
      <div class="flex flex-col sm:flex-row sm:items-center gap-2">
        <input 
          type="date" 
          v-model="filters.startDate" 
          class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-pink-200"
        >
        <span class="text-gray-400 hidden sm:inline">—</span>
        <input 
          type="date" 
          v-model="filters.endDate" 
          class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-pink-200"
        >
        <button 
          @click="fetchData" 
          class="bg-pink-600 hover:bg-pink-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition shadow-lg flex items-center justify-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
          </svg>
          <span>Filter</span>
        </button>
        <button 
          v-if="canSyncFee"
          @click="syncFee" 
          :disabled="syncing"
          class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-lg flex items-center justify-center gap-2 disabled:opacity-50"
          title="Perbarui fee berdasarkan pengaturan terbaru"
        >
          <svg class="w-4 h-4" :class="{ 'animate-spin': syncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
          </svg>
          <span>{{ syncing ? 'Memproses...' : 'Sinkronkan Fee' }}</span>
        </button>
      </div>
      
      <!-- Sync Fee Info -->
      <div v-if="!canSyncFee && stats.length > 0" class="text-xs text-gray-400 italic flex items-center gap-1">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        Sinkronisasi fee hanya tersedia untuk bulan ini dan bulan lalu
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm">
        <p class="text-xs text-gray-500 mb-1">Total Terapis Aktif</p>
        <p class="text-2xl font-bold text-gray-800">{{ activeTherapists }}</p>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm">
        <p class="text-xs text-gray-500 mb-1">Total Layanan Selesai</p>
        <p class="text-2xl font-bold text-pink-600">{{ totalTasks }}</p>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm">
        <p class="text-xs text-gray-500 mb-1">Total Poin Terapis</p>
        <p class="text-2xl font-bold text-green-600">{{ formatNumber(totalFee) }} Poin</p>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm">
        <p class="text-xs text-gray-500 mb-1">Rata-rata Poin/Terapis</p>
        <p class="text-2xl font-bold text-amber-600">{{ formatNumber(averageFee) }} Poin</p>
      </div>
    </div>

    <!-- Therapist Cards -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 6" :key="i" class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm animate-pulse">
        <div class="flex items-center gap-4 mb-4">
          <div class="w-14 h-14 bg-gray-200 rounded-full"></div>
          <div class="flex-1">
            <div class="h-4 bg-gray-200 rounded w-24 mb-2"></div>
            <div class="h-3 bg-gray-100 rounded w-16"></div>
          </div>
        </div>
        <div class="h-12 bg-gray-100 rounded"></div>
      </div>
    </div>

    <div v-else-if="stats.length === 0" class="bg-white rounded-xl p-12 border border-gray-100 shadow-sm text-center">
      <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
      </svg>
      <p class="font-medium text-gray-600">Tidak ada data kinerja</p>
      <p class="text-sm text-gray-400">Pada periode yang dipilih</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="stat in stats" :key="stat.workerId" 
           class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
        <!-- Header -->
        <div class="p-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-pink-500 to-fuchsia-600 text-white flex items-center justify-center font-bold text-lg shadow-lg">
              {{ getInitials(stat.name) }}
            </div>
            <div class="flex-1">
              <h3 class="font-bold text-gray-800">{{ stat.name }}</h3>
              <p class="text-xs text-gray-500">{{ stat.totalTasks }} layanan selesai</p>
            </div>
          </div>
        </div>

        <!-- Fee Summary -->
        <div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-green-100">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-green-700">Total Poin</span>
            <span class="text-xl font-bold text-green-600">{{ formatNumber(stat.totalFee) }} Poin</span>
          </div>
        </div>

        <!-- Services Breakdown -->
        <div class="p-4">
          <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Rincian Layanan</p>
          <div class="space-y-2">
            <div v-for="(service, name) in stat.statsByStep" :key="name" 
                 class="flex items-center justify-between text-sm p-2 bg-gray-50 rounded-lg">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-pink-400"></span>
                <span class="text-gray-700">{{ name }}</span>
              </div>
              <div class="flex items-center gap-3">
                <span class="text-xs text-gray-500">{{ service.count }}x</span>
                <span class="font-bold text-green-600">{{ formatNumber(service.totalFee) }} Poin</span>
              </div>
            </div>
            <div v-if="Object.keys(stat.statsByStep).length === 0" class="text-xs text-gray-400 italic text-center py-4">
              Belum ada layanan selesai
            </div>
          </div>
        </div>

        <!-- Detail Button -->
        <div class="p-4 border-t border-gray-100 bg-gray-50">
          <button @click="viewDetails(stat)" 
                  class="w-full py-2 text-sm font-medium text-pink-600 hover:text-pink-700 hover:bg-pink-50 rounded-lg transition flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
            Lihat Detail
          </button>
        </div>
      </div>
    </div>
    
    <!-- Detail Modal -->
    <Teleport to="body">
       <div v-if="selectedStat" class="fixed inset-0 z-[99] bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="selectedStat = null">
            <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl max-h-[85vh] flex flex-col animate-fade-in-down border border-gray-100">
                <!-- Header -->
                <div class="p-4 border-b flex justify-between items-center bg-gradient-to-r from-pink-500 to-fuchsia-600 rounded-t-2xl">
                    <h3 class="font-bold text-white flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-white/20 backdrop-blur text-white flex items-center justify-center text-sm font-bold">
                          {{ getInitials(selectedStat.name) }}
                        </span>
                        <div>
                          <span class="block">{{ selectedStat.name }}</span>
                          <span class="text-xs text-white/80 font-normal">Detail Kinerja Periode {{ formatDateRange() }}</span>
                        </div>
                    </h3>
                    <button @click="selectedStat = null" class="text-white/70 hover:text-white transition text-2xl w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10">&times;</button>
                </div>
                
                <!-- Summary -->
                <div class="p-4 bg-gray-50 border-b border-gray-100 grid grid-cols-3 gap-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-800">{{ selectedStat.totalTasks }}</p>
                        <p class="text-xs text-gray-500">Total Layanan</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-600">{{ formatNumber(selectedStat.totalFee) }} Poin</p>
                        <p class="text-xs text-gray-500">Total Poin</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-amber-600">{{ formatNumber(Math.round(selectedStat.totalFee / Math.max(selectedStat.totalTasks, 1))) }} Poin</p>
                        <p class="text-xs text-gray-500">Rata-rata Poin</p>
                    </div>
                </div>

                <!-- Ringkasan per Layanan -->
                <div class="px-4 py-3 bg-white border-b border-gray-100">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Ringkasan per Layanan</h4>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="(service, name) in selectedStat.statsByStep" :key="name" 
                             class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm flex items-center gap-3">
                           <span class="font-medium text-gray-700">{{ name }}</span>
                           <span class="bg-pink-100 text-pink-700 px-2 py-0.5 rounded text-xs font-bold">{{ service.count }}x</span>
                           <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-bold">{{ formatNumber(service.totalFee) }} Poin</span>
                        </div>
                        <div v-if="Object.keys(selectedStat.statsByStep).length === 0" class="text-xs text-gray-400 italic">Belum ada data.</div>
                    </div>
                </div>

                <!-- Detail Table -->
                <div class="flex-1 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase sticky top-0">
                           <tr>
                                <th class="px-4 py-3 text-left">Tanggal</th>
                                <th class="px-4 py-3 text-left">Layanan</th>
                                <th class="px-4 py-3 text-left">Pelanggan</th>
                                <th class="px-4 py-3 text-right">Poin</th>
                           </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                           <tr v-for="(task, idx) in selectedStat.tasks" :key="idx" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap font-mono text-xs">{{ formatDate(task.date) }}</td>
                                <td class="px-4 py-3 font-medium text-gray-700">{{ task.stepName }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ task.customerName }}</td>
                                <td class="px-4 py-3 text-right font-bold text-green-600">{{ formatNumber(task.fee) }} Poin</td>
                           </tr>
                           <tr v-if="selectedStat.tasks.length === 0">
                               <td colspan="4" class="px-4 py-8 text-center text-gray-400">Belum ada layanan selesai.</td>
                           </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Footer -->
                <div class="p-4 border-t bg-gray-50 rounded-b-2xl flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-500">Total: <strong class="text-gray-700">{{ selectedStat.tasks.length }} Layanan</strong></span>
                        <span class="text-sm text-gray-500">Poin: <strong class="text-green-600">{{ formatNumber(selectedStat.totalFee) }} Poin</strong></span>
                    </div>
                    <button @click="selectedStat = null" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50 font-medium text-gray-700 transition">Tutup</button>
                </div>
            </div>
       </div>

       <!-- Sync Fee Confirmation Modal -->
       <div v-if="showSyncModal" class="fixed inset-0 z-[100] bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="showSyncModal = false">
         <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl animate-fade-in-down border border-gray-100">
           <div class="p-6 text-center">
             <!-- Icon -->
             <div class="mx-auto w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mb-4">
               <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
               </svg>
             </div>
             
             <h3 class="text-lg font-bold text-gray-800 mb-2">Sinkronkan Fee?</h3>
             <p class="text-sm text-gray-600 mb-4">Semua order di periode <strong>{{ formatDateRange() }}</strong> akan diperbarui dengan pengaturan fee terbaru.</p>
             
             <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-left mb-4">
               <p class="text-xs text-amber-700">
                 <strong>Catatan:</strong> Hanya fee yang berbeda dari pengaturan saat ini yang akan diperbarui. Order yang sudah sesuai tidak akan diubah.
               </p>
             </div>
             
             <div class="flex gap-3">
               <button @click="showSyncModal = false" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-medium transition">
                 Batal
               </button>
               <button @click="confirmSyncFee" :disabled="syncing" class="flex-1 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-medium transition disabled:opacity-50 flex items-center justify-center gap-2">
                 <svg v-if="syncing" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                 </svg>
                 {{ syncing ? 'Memproses...' : 'Ya, Sinkronkan' }}
               </button>
             </div>
           </div>
         </div>
       </div>

       <!-- Sync Result Modal -->
       <div v-if="syncResult" class="fixed inset-0 z-[101] bg-black/40 backdrop-blur-sm flex items-center justify-center p-4">
         <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl animate-fade-in-down border border-gray-100">
           <div class="p-6 text-center">
             <!-- Success Icon -->
             <div class="mx-auto w-16 h-16 rounded-full flex items-center justify-center mb-4"
                  :class="syncResult.success ? 'bg-green-100' : 'bg-red-100'">
               <svg v-if="syncResult.success" class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
               </svg>
               <svg v-else class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
               </svg>
             </div>
             
             <h3 class="text-lg font-bold mb-2" :class="syncResult.success ? 'text-green-800' : 'text-red-800'">
               {{ syncResult.success ? 'Berhasil!' : 'Gagal' }}
             </h3>
             <p class="text-sm text-gray-600 mb-4">{{ syncResult.message }}</p>
             
             <!-- Stats if success -->
             <div v-if="syncResult.success && syncResult.data" class="bg-gray-50 rounded-lg p-4 mb-4 grid grid-cols-3 gap-2 text-center">
               <div>
                 <p class="text-xl font-bold text-gray-800">{{ syncResult.data.total_orders_checked }}</p>
                 <p class="text-xs text-gray-500">Order Dicek</p>
               </div>
               <div>
                 <p class="text-xl font-bold text-amber-600">{{ syncResult.data.orders_updated }}</p>
                 <p class="text-xs text-gray-500">Order Diupdate</p>
               </div>
               <div>
                 <p class="text-xl font-bold text-green-600">{{ syncResult.data.steps_updated }}</p>
                 <p class="text-xs text-gray-500">Step Diupdate</p>
               </div>
             </div>
             
             <button @click="closeSyncResult" class="w-full px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-xl font-medium transition">
               Tutup
             </button>
           </div>
         </div>
       </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';

const loading = ref(true);
const stats = ref([]);
const selectedStat = ref(null);
const workers = ref([]);
const syncing = ref(false);

// Default: This Month
const today = new Date();
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
const formatDateInput = (d) => d.toISOString().split('T')[0];

const filters = reactive({
    startDate: formatDateInput(firstDay),
    endDate: formatDateInput(today)
});

// Computed summaries
const activeTherapists = computed(() => stats.value.filter(s => s.totalTasks > 0).length);
const totalTasks = computed(() => stats.value.reduce((sum, s) => sum + s.totalTasks, 0));
const totalFee = computed(() => stats.value.reduce((sum, s) => sum + s.totalFee, 0));
const averageFee = computed(() => activeTherapists.value > 0 ? Math.round(totalFee.value / activeTherapists.value) : 0);

// Sync Fee Modal
const showSyncModal = ref(false);
const syncResult = ref(null);

// Check if sync fee is allowed (only for current month and last month)
const canSyncFee = computed(() => {
    const now = new Date();
    const currentMonthStart = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastMonthStart = new Date(currentMonthStart);
    lastMonthStart.setMonth(lastMonthStart.getMonth() - 1);
    
    const filterStart = new Date(filters.startDate);
    
    // Filter start date must be >= last month start
    return filterStart >= lastMonthStart;
});

function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num || 0);
}

// Open sync fee modal
function syncFee() {
    if (!canSyncFee.value) return;
    showSyncModal.value = true;
}

// Confirm and execute sync
async function confirmSyncFee() {
    syncing.value = true;
    try {
        const res = await fetch('/api/Beauty_Salon/Orders/syncFee', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                start_date: filters.startDate,
                end_date: filters.endDate
            })
        });
        const data = await res.json();
        
        showSyncModal.value = false;
        syncResult.value = data;
        
        if (data.success) {
            // Refresh data to show updated fees
            await fetchData();
        }
    } catch (e) {
        console.error(e);
        showSyncModal.value = false;
        syncResult.value = {
            success: false,
            message: 'Terjadi kesalahan saat menyinkronkan fee'
        };
    } finally {
        syncing.value = false;
    }
}

// Close result modal
function closeSyncResult() {
    syncResult.value = null;
}

async function fetchWorkers() {
    try {
        const res = await fetch('/api/Beauty_Salon/Therapists');
        const d = await res.json();
        if(d.success) workers.value = d.data;
    } catch {}
}

async function fetchData() {
    // Validate date range: max 31 days
    const start = new Date(filters.startDate);
    const end = new Date(filters.endDate);
    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays > 31) {
        alert('❌ Rentang tanggal maksimal 31 hari!\nSilakan pilih periode yang lebih pendek.');
        return;
    }
    
    loading.value = true;
    try {
        const res = await fetch('/api/Beauty_Salon/Orders');
        const d = await res.json();
        
        if (d.success) {
            processStats(d.data);
        }
    } catch(e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function processStats(orders) {
    const workerMap = {};
    
    // Init workers
    workers.value.forEach(w => {
        workerMap[w.id] = {
            workerId: w.id,
            name: w.nama, 
            totalTasks: 0,
            totalFee: 0,
            tasks: [],
            statsByStep: {} // Store counts per step type
        };
    });

    const start = new Date(filters.startDate);
    const end = new Date(filters.endDate);
    end.setHours(23, 59, 59);

    orders.forEach(order => {
        const oDateStr = order.order_date || order.created_at;
        if (!oDateStr) return;
        const oDate = new Date(oDateStr); 
        
        if (oDate < start || oDate > end) return;
        if (order.status === 'cancelled') return;

        (order.order_items || []).forEach(item => {
             (item.work_steps || []).forEach(step => {
                 // Hitung jika completed
                 if (step.worker_id && step.status === 'completed') {
                     const wId = step.worker_id;
                     const fee = Number(step.fee) || 0;
                     
                     if (!workerMap[wId]) {
                         workerMap[wId] = { 
                             workerId: wId, 
                             name: 'Terapis #' + wId, 
                             totalTasks: 0, 
                             totalFee: 0,
                             tasks: [],
                             statsByStep: {} 
                         };
                     }
                     
                     workerMap[wId].totalTasks++;
                     workerMap[wId].totalFee += fee;
                     workerMap[wId].tasks.push({
                         date: oDateStr,
                         stepName: step.step_name,
                         customerName: order.customer_name,
                         fee: fee
                     });
                     
                     // Aggregate by step
                     const sName = step.step_name || 'Layanan';
                     if(!workerMap[wId].statsByStep[sName]) {
                         workerMap[wId].statsByStep[sName] = { count: 0, totalFee: 0 };
                     }
                     workerMap[wId].statsByStep[sName].count++;
                     workerMap[wId].statsByStep[sName].totalFee += fee;
                 }
             });
        });
    });

    // Sort by Total Fee Desc, then by Total Tasks
    stats.value = Object.values(workerMap)
        .filter(w => w.totalTasks > 0) // Only show therapists with work
        .sort((a,b) => b.totalFee - a.totalFee || b.totalTasks - a.totalTasks);
}

function getInitials(name) {
    return name ? name.substring(0,2).toUpperCase() : '??';
}

function formatDate(dateStr) {
    if(!dateStr) return '-';
    // Format: 17 Des 10:30
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) + ' ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute:'2-digit' });
}

function formatDateRange() {
    const start = new Date(filters.startDate);
    const end = new Date(filters.endDate);
    return start.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) + ' - ' + end.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function viewDetails(stat) {
    // Sort tasks by date desc
    stat.tasks.sort((a,b) => new Date(b.date) - new Date(a.date));
    selectedStat.value = stat;
}

onMounted(async () => {
    await fetchWorkers();
    await fetchData();
});
</script>

<style scoped>
.animate-fade-in-down {
  animation: fadeInDown 0.3s ease-out;
}
@keyframes fadeInDown {
  from { opacity: 0; transform: translateY(-10px); scale: 0.95; }
  to { opacity: 1; transform: translateY(0); scale: 1; }
}
</style>
