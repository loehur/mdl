<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header & Filter -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Kinerja Terapis</h1>
        <p class="text-sm text-gray-500">Statistik pekerjaan dan layanan</p>
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

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Table Header -->
         <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase text-gray-500 font-semibold tracking-wider">
                        <th class="px-6 py-4">Nama Terapis</th>
                        <th class="px-6 py-4 text-left">Layanan</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="loading" class="animate-pulse">
                        <td colspan="3" class="px-6 py-8 text-center text-gray-400">Memuat data...</td>
                    </tr>
                    <tr v-else-if="stats.length === 0">
                         <td colspan="3" class="px-6 py-8 text-center text-gray-400">Tidak ada data Kinerja pada periode ini</td>
                    </tr>
                    <tr v-for="stat in stats" :key="stat.workerId" class="hover:bg-gray-50 transition duration-150">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-pink-100 text-pink-600 flex items-center justify-center font-bold text-xs ring-2 ring-white shadow-sm">
                                    {{ getInitials(stat.name) }}
                                </div>
                                <span class="font-medium text-gray-700">{{ stat.name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="(count, name) in stat.statsByStep" :key="name" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-50 border border-gray-200 text-gray-600">
                                    {{ name }} <span class="ml-1 font-bold text-gray-800">({{ count }})</span>
                                </span>
                            </div>
                        </td>
                         <td class="px-6 py-4 text-center">
                            <button @click="viewDetails(stat)" class="text-pink-600 hover:text-pink-800 text-sm font-medium hover:underline">Detail</button>
                        </td>
                    </tr>
                </tbody>
            </table>
         </div>
    </div>
    
    <!-- Detail Modal -->
    <Teleport to="body">
       <div v-if="selectedStat" class="fixed inset-0 z-[99] bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="selectedStat = null">
            <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl max-h-[80vh] flex flex-col animate-fade-in-down border border-gray-100">
                <div class="p-4 border-b flex justify-between items-center bg-gray-50 rounded-t-2xl">
                    <h3 class="font-bold text-gray-800 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full bg-pink-600 text-white flex items-center justify-center text-xs shadow-md">{{ getInitials(selectedStat.name) }}</span>
                        Detail Kinerja: <span class="text-pink-600">{{ selectedStat.name }}</span>
                    </h3>
                    <button @click="selectedStat = null" class="text-gray-400 hover:text-gray-600 transition text-xl">&times;</button>
                </div>
                
                <!-- Ringkasan Layanan -->
                <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Ringkasan Layanan</h4>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="(count, name) in selectedStat.statsByStep" :key="name" class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-xs font-medium text-gray-700 shadow-sm flex items-center gap-2">
                           <span>{{ name }}</span>
                           <span class="bg-pink-100 text-pink-700 px-1.5 py-0.5 rounded text-[10px] font-bold">{{ count }}</span>
                        </div>
                        <div v-if="Object.keys(selectedStat.statsByStep).length === 0" class="text-xs text-gray-400 italic">Belum ada data.</div>
                    </div>
                </div>

                <div class="p-0 overflow-y-auto flex-1">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase sticky top-0 backdrop-blur-md bg-opacity-95">
                           <tr>
                                <th class="px-4 py-3 text-left">Tanggal</th>
                                <th class="px-4 py-3 text-left">Layanan</th>
                                <th class="px-4 py-3 text-left">Pelanggan</th>
                           </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                           <tr v-for="(task, idx) in selectedStat.tasks" :key="idx" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap font-mono text-xs">{{ formatDate(task.date) }}</td>
                                <td class="px-4 py-3 font-medium text-gray-700">{{ task.stepName }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ task.customerName }}</td>
                           </tr>
                           <tr v-if="selectedStat.tasks.length === 0">
                               <td colspan="3" class="px-4 py-8 text-center text-gray-400">Belum ada layanan selesai.</td>
                           </tr>
                        </tbody>
                    </table>
                </div>
                 <div class="p-4 border-t bg-gray-50 rounded-b-2xl text-right flex justify-between items-center">
                    <span class="text-sm text-gray-500 font-medium">Total: {{ selectedStat.tasks.length }} Layanan</span>
                    <button @click="selectedStat = null" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50 font-medium text-gray-700 transition">Tutup</button>
                </div>
            </div>
       </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';

const loading = ref(true);
const stats = ref([]);
const selectedStat = ref(null);
const workers = ref([]);

// Default: This Month
const today = new Date();
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
const formatDateInput = (d) => d.toISOString().split('T')[0];

const filters = reactive({
    startDate: formatDateInput(firstDay),
    endDate: formatDateInput(today)
});

async function fetchWorkers() {
    try {
        const res = await fetch('/api/Beauty_Salon/Therapists');
        const d = await res.json();
        if(d.success) workers.value = d.data;
    } catch {}
}

async function fetchData() {
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
                     if (!workerMap[wId]) {
                         workerMap[wId] = { 
                             workerId: wId, 
                             name: 'Terapis #' + wId, 
                             totalTasks: 0, 
                             tasks: [],
                             statsByStep: {} 
                         };
                     }
                     
                     workerMap[wId].totalTasks++;
                     workerMap[wId].tasks.push({
                         date: oDateStr,
                         stepName: step.step_name,
                         customerName: order.customer_name,
                     });
                     
                     // Aggregate by step
                     const sName = step.step_name || 'Layanan';
                     if(!workerMap[wId].statsByStep[sName]) workerMap[wId].statsByStep[sName] = 0;
                     workerMap[wId].statsByStep[sName]++;
                 }
             });
        });
    });

    // Sort by Total Tasks Desc
    stats.value = Object.values(workerMap).sort((a,b) => b.totalTasks - a.totalTasks);
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

function getTopService(statsByStep) {
    const entries = Object.entries(statsByStep);
    if(entries.length === 0) return '-';
    // Sort by count desc
    entries.sort((a,b) => b[1] - a[1]);
    const top = entries[0];
    const others = entries.length - 1;
    
    if (others > 0) return `${top[0]} (${top[1]}) +${others} lainnya`;
    return `${top[0]} (${top[1]})`;
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
