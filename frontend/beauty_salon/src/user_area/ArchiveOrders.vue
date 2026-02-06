<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-6 py-4 flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-gray-800">Arsip Order</h2>
        <p class="text-sm text-gray-500">Riwayat order selesai dan dibatalkan</p>
      </div>
      <div class="flex gap-2">
         <!-- Date Filter Placeholder -->
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-500">Memuat arsip...</div>
      
      <div v-else-if="orders.length === 0" class="p-12 text-center">
        <div class="inline-block p-4 rounded-full bg-gray-50 mb-4">
             <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900">Belum ada arsip</h3>
        <p class="text-gray-500">Order yang diselesaikan akan muncul di sini.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 border-b border-gray-100 text-gray-500 uppercase tracking-wider text-xs">
            <tr>
              <th class="px-6 py-4 font-semibold">Order ID</th>
              <th class="px-6 py-4 font-semibold">Tanggal Selesai</th>
              <th class="px-6 py-4 font-semibold">Pelanggan</th>
              <th class="px-6 py-4 font-semibold">Layanan</th>
              <th class="px-6 py-4 font-semibold">Total</th>
              <th class="px-6 py-4 font-semibold">Pembayaran</th>
              <th class="px-6 py-4 font-semibold text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="order in orders" :key="order.id" 
                class="transition"
                :class="order.status === 'cancelled' ? 'bg-red-50/50 hover:bg-red-50' : 'hover:bg-gray-50/50'">
              <td class="px-6 py-4 font-mono" :class="order.status === 'cancelled' ? 'text-gray-400' : 'text-gray-500'">
                #{{ order.id }}
                <span v-if="order.status === 'cancelled'" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700">Dibatalkan</span>
              </td>
              <td class="px-6 py-4" :class="order.status === 'cancelled' ? 'text-gray-500' : 'text-gray-700'">
                <div>{{ formatDate(order.completed_at || order.updated_at) }}</div>
                <div class="text-xs text-gray-400">{{ formatTime(order.completed_at || order.updated_at) }}</div>
              </td>
              <td class="px-6 py-4" :class="order.status === 'cancelled' ? 'opacity-70' : ''">
                <div class="font-medium" :class="order.status === 'cancelled' ? 'text-gray-600 line-through' : 'text-gray-900'">{{ order.customer_name }}</div>
                <div class="text-xs text-gray-500">{{ order.customer_phone }}</div>
              </td>
              <td class="px-6 py-4 max-w-xs truncate" :class="order.status === 'cancelled' ? 'text-gray-500 line-through' : 'text-gray-600'">
                 {{ order.order_items.map(i => i.product_name).join(', ') }}
              </td>
              <td class="px-6 py-4 font-bold" :class="order.status === 'cancelled' ? 'text-gray-500' : 'text-gray-800'">
                Rp {{ formatNumber(order.total_price) }}
              </td>
              <td class="px-6 py-4">
                 <div class="flex flex-col gap-1">
                    <span v-if="order.payment_method === 'split'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 w-fit">
                        Split (T: {{ formatNumber(order.pay_cash) }} / N: {{ formatNumber(order.pay_non_cash) }})
                    </span>
                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium w-fit" 
                        :class="order.payment_method === 'non_tunai' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                        {{ order.payment_method === 'non_tunai' ? 'Non Tunai' : 'Tunai' }}
                    </span>
                    <span v-if="order.payment_notes" class="text-xs text-gray-500 italic truncate max-w-[150px]">{{ order.payment_notes }}</span>
                 </div>
              </td>
              <td class="px-6 py-4 text-right space-x-2">
                <button v-if="order.status === 'completed'" @click="printReceipt(order)" class="text-gray-600 hover:text-gray-800 font-medium text-xs inline-flex items-center gap-1">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                  Cetak
                </button>
                <button @click="viewDetail(order)" class="text-pink-600 hover:text-pink-800 font-medium text-xs">Detail</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
       <div v-if="selectedOrder" class="fixed inset-0 z-[1001] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="selectedOrder = null">
          <div class="rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]"
               :class="selectedOrder.status === 'cancelled' ? 'bg-gray-50 border-2 border-dashed border-red-200' : 'bg-white'">
              <!-- Banner Dibatalkan -->
              <div v-if="selectedOrder.status === 'cancelled'" class="bg-red-100 border-b border-red-200 px-6 py-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                <span class="font-bold text-red-700 uppercase tracking-wider">Order Dibatalkan</span>
              </div>
              <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center"
                   :class="selectedOrder.status === 'cancelled' ? 'bg-gray-100' : 'bg-gray-50'">
                  <h3 class="font-bold text-lg" :class="selectedOrder.status === 'cancelled' ? 'text-gray-500' : 'text-gray-800'">Detail Order #{{ selectedOrder.id }}</h3>
                  <button @click="selectedOrder = null" class="text-gray-400 hover:text-gray-600">✕</button>
              </div>
              <div class="p-6 overflow-y-auto space-y-4">
                  <!-- Cust Info -->
                  <div class="flex justify-between items-start">
                     <div>
                        <div class="text-xs text-gray-500 uppercase font-bold">Pelanggan</div>
                        <div class="font-medium text-lg">{{ selectedOrder.customer_name }}</div>
                        <div class="text-sm text-gray-500">{{ selectedOrder.customer_phone }}</div>
                     </div>
                     <div class="text-right">
                        <div class="text-xs text-gray-500 uppercase font-bold">{{ selectedOrder.status === 'cancelled' ? 'Tanggal Dibatalkan' : 'Tanggal Selesai' }}</div>
                        <div class="font-medium">{{ formatDate(selectedOrder.completed_at || selectedOrder.updated_at) }}</div>
                        <div class="text-xs text-gray-500">{{ formatTime(selectedOrder.completed_at || selectedOrder.updated_at) }}</div>
                     </div>
                  </div>

                  <hr class="border-gray-100">

                  <!-- Items -->
                  <div class="space-y-3">
                      <div v-for="(item, idx) in selectedOrder.order_items" :key="idx" class="flex justify-between">
                          <div>
                              <div class="font-medium">{{ item.product_name }}</div>
                              <div class="text-xs text-gray-500 ml-2">
                                  <div v-for="step in item.work_steps" :key="step.step_id || step.id">
                                     • {{ step.name || step.step_name }} <span v-if="step.worker_id" class="text-pink-600 font-medium">({{ getWorkerName(step.worker_id) }})</span>
                                  </div>
                              </div>
                          </div>
                          <div class="font-medium">Rp {{ formatNumber(item.price) }}</div>
                      </div>
                  </div>

                  <hr class="border-gray-100 border-dashed">
                  
                  <!-- Payment -->
                  <div class="bg-gray-50 p-4 rounded-xl space-y-2 text-sm">
                      <div class="flex justify-between font-bold text-gray-800 text-lg">
                          <span>Total</span>
                          <span>Rp {{ formatNumber(selectedOrder.total_price) }}</span>
                      </div>
                      <div class="flex justify-between text-gray-600">
                          <span>Metode Bayar</span>
                          <span class="capitalize">{{ (selectedOrder.payment_method || '-').replace('_', ' ') }}</span>
                      </div>
                      <div v-if="selectedOrder.payment_method === 'split'" class="text-xs text-gray-500 flex justify-between border-t border-gray-200 pt-1 mt-1">
                          <span>Rincian Split</span>
                          <span>Tunai: {{ formatNumber(selectedOrder.pay_cash) }} | Non: {{ formatNumber(selectedOrder.pay_non_cash) }}</span>
                      </div>
                      <div v-if="selectedOrder.payment_notes" class="text-gray-500 italic pt-1">
                         "{{ selectedOrder.payment_notes }}"
                      </div>
                  </div>
                  
                  <!-- Print Button (hanya untuk order selesai) -->
                  <button v-if="selectedOrder.status === 'completed'" @click="printReceipt(selectedOrder)" class="w-full mt-4 py-3 bg-gradient-to-r from-gray-700 to-gray-900 hover:from-gray-800 hover:to-black text-white rounded-xl font-medium transition-all flex items-center justify-center gap-2 shadow-lg">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                      Cetak Nota
                  </button>
                  <div v-else class="w-full mt-4 py-3 bg-red-50 border border-red-200 rounded-xl flex items-center justify-center gap-2">
                      <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                      <span class="text-sm font-medium text-red-600">Order dibatalkan — tidak dapat dicetak</span>
                  </div>
              </div>
          </div>
       </div>
    </Teleport>
     
    <!-- Toast Notification -->
    <Teleport to="body">
      <div v-if="toast.show" class="fixed top-4 right-4 z-[100050] animate-fade-in-down">
        <div class="bg-white rounded-lg shadow-2xl border-l-4 p-4 min-w-[280px]" :class="toast.type === 'success' ? 'border-green-500' : toast.type === 'error' ? 'border-red-500' : 'border-yellow-500'">
          <p class="font-medium text-gray-800">{{ toast.message }}</p>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';

const loading = ref(true);
const orders = ref([]);
const selectedOrder = ref(null);
const therapists = ref([]);
const toast = reactive({ show: false, message: '', type: 'success' });
const salonInfo = ref({ nama_salon: 'MDL BEAUTY SALON', alamat_salon: 'Jakarta' });

const workerNameMap = computed(() => {
    const m = {};
    therapists.value.forEach(t => { m[t.id] = t.nama || ('Terapis #' + t.id); });
    return m;
});

function getWorkerName(workerId) {
    return workerNameMap.value[workerId] || (workerId ? 'Terapis #' + workerId : '-');
}

function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => toast.show = false, 3000);
}

onMounted(async () => {
    try {
        // Fetch therapists untuk mapping worker_id -> nama
        const resTherapists = await fetch('/api/Beauty_Salon/Therapists');
        const dTherapists = await resTherapists.json();
        if (dTherapists.success) therapists.value = dTherapists.data;

        // Fetch COMPLETED dan CANCELLED orders
        const res = await fetch('/api/Beauty_Salon/Orders?status=all');
        const d = await res.json();
        if (d.success) {
            // Filter: hanya completed dan cancelled, sort by completed_at/updated_at
            orders.value = d.data
                .filter(o => o.status === 'completed' || o.status === 'cancelled')
                .sort((a, b) => {
                    const dateA = new Date(a.completed_at || a.updated_at || 0);
                    const dateB = new Date(b.completed_at || b.updated_at || 0);
                    return dateB - dateA;
                });
        }
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
});

function viewDetail(order) {
    selectedOrder.value = order;
}

function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num || 0);
}

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
    html += row2(`No Order`, `#${order.id}`);
    html += row2(`Tanggal`, `${formatDate(order.completed_at)}`);
    html += row2(`Jam`, `${formatTime(order.completed_at)}`);
    html += row2(`Pelanggan`, order.customer_name);
    html += divider();

    // Items
    (order.order_items || []).forEach(item => {
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

async function printReceipt(order) {
    const text = generateReceiptText(order);
    
    // 1. Try Printer Server (Localhost)
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 2000); // 2s timeout
        
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

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}
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
