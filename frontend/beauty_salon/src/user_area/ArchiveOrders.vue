<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-6 py-4 flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-gray-800">Arsip Order Selesai</h2>
        <p class="text-sm text-gray-500">Riwayat transaksi yang telah diselesaikan</p>
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
            <tr v-for="order in orders" :key="order.id" class="hover:bg-gray-50/50 transition">
              <td class="px-6 py-4 font-mono text-gray-500">#{{ order.id }}</td>
              <td class="px-6 py-4 text-gray-700">
                <div>{{ formatDate(order.completed_at || order.updated_at) }}</div>
                <div class="text-xs text-gray-400">{{ formatTime(order.completed_at || order.updated_at) }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="font-medium text-gray-900">{{ order.customer_name }}</div>
                <div class="text-xs text-gray-500">{{ order.customer_phone }}</div>
              </td>
              <td class="px-6 py-4 text-gray-600 max-w-xs truncate">
                 {{ order.order_items.map(i => i.product_name).join(', ') }}
              </td>
              <td class="px-6 py-4 font-bold text-gray-800">
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
              <td class="px-6 py-4 text-right">
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
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
              <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                  <h3 class="font-bold text-lg text-gray-800">Detail Order #{{ selectedOrder.id }}</h3>
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
                        <div class="text-xs text-gray-500 uppercase font-bold">Tanggal</div>
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
                                     • {{ step.name || step.step_name }} <span v-if="step.worker_id" class="text-gray-400">(Worker #{{ step.worker_id }})</span>
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
              </div>
          </div>
       </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const loading = ref(true);
const orders = ref([]);
const selectedOrder = ref(null);

onMounted(async () => {
    try {
        // Fetch COMPLETED orders
        const res = await fetch('/api/Beauty_Salon/Orders?status=completed');
        const d = await res.json();
        if (d.success) {
            orders.value = d.data;
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

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}
</script>
