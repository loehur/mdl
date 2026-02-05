<template>
  <div class="max-w-7xl mx-auto space-y-6">

    <!-- Saldo Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Kas Kasir -->
      <div class="bg-gradient-to-br from-indigo-500 to-blue-600 rounded-2xl p-4 md:p-6 text-white shadow-xl relative overflow-hidden">
        <div class="absolute top-0 right-0 p-8 opacity-10 transform translate-x-1/2 -translate-y-1/2">
          <svg class="w-48 h-48" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
          </svg>
        </div>
        <div class="relative z-10">
          <div class="flex items-center gap-2 mb-2">
            <svg class="w-4 h-4 md:w-5 md:h-5 text-indigo-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <p class="text-indigo-100 font-semibold text-xs md:text-sm">Kas Kasir</p>
          </div>
          <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold tracking-tight mb-3 break-words">{{ formatCurrency(cashierBalance) }}</h2>
          <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 text-[10px] md:text-xs">
            <span class="bg-white/20 px-2 py-1 rounded break-words">Pemasukan: {{ formatCurrency(cashierIncome) }}</span>
            <span class="bg-white/20 px-2 py-1 rounded break-words">Pengeluaran: {{ formatCurrency(cashierExpense) }}</span>
          </div>
        </div>
      </div>

      <!-- Kas Besar -->
      <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-4 md:p-6 text-white shadow-xl relative overflow-hidden">
        <div class="absolute top-0 right-0 p-8 opacity-10 transform translate-x-1/2 -translate-y-1/2">
          <svg class="w-48 h-48" fill="currentColor" viewBox="0 0 24 24">
            <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
          </svg>
        </div>
        <div class="relative z-10">
          <div class="flex items-center gap-2 mb-2">
            <svg class="w-4 h-4 md:w-5 md:h-5 text-amber-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
            </svg>
            <p class="text-amber-100 font-semibold text-xs md:text-sm">Kas Besar (Main)</p>
          </div>
          <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold tracking-tight mb-3 break-words">{{ formatCurrency(mainCashBalance) }}</h2>
          <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 text-[10px] md:text-xs">
            <span class="bg-white/20 px-2 py-1 rounded break-words">Pemasukan: {{ formatCurrency(mainCashIncome) }}</span>
            <span class="bg-white/20 px-2 py-1 rounded break-words">Pengeluaran: {{ formatCurrency(mainCashExpense) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-3 mb-6">
      <button 
        @click="showTransferModal = true"
        class="flex-1 sm:flex-none bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium transition shadow-lg flex items-center justify-center gap-2"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
        </svg>
        Transfer Kas
      </button>
      
      <button 
        @click="showCapitalModal = true"
        class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-xl font-medium transition shadow-lg flex items-center justify-center gap-2"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Tambah Modal
      </button>
      
      <button 
        @click="showExpenseModal = true"
        class="flex-1 sm:flex-none bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl font-medium transition shadow-lg flex items-center justify-center gap-2"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
        </svg>
        Pengeluaran
      </button>
    </div>
    <!-- Transaction History -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
        <h3 class="font-bold text-gray-800">Riwayat Transaksi</h3>
        <div class="flex items-center gap-2">
          <select v-model="filterType" @change="fetchTransactions" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-gray-200">
            <option value="">Semua Tipe</option>
            <option value="income">Pemasukan</option>
            <option value="expense">Pengeluaran</option>
            <option value="transfer">Transfer</option>
          </select>
          <select v-model="filterCash" @change="fetchTransactions" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-gray-200">
            <option value="">Semua Kas</option>
            <option value="cashier">Kas Kasir</option>
            <option value="main">Kas Besar</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b">
            <tr>
              <th class="px-6 py-3">Tanggal</th>
              <th class="px-6 py-3">Tipe</th>
              <th class="px-6 py-3">Kas</th>
              <th class="px-6 py-3">Keterangan</th>
              <th class="px-6 py-3">Kategori</th>
              <th class="px-6 py-3 text-right">Jumlah</th>
              <th class="px-6 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="loadingTransactions" class="animate-pulse">
              <td colspan="7" class="px-6 py-8 text-center text-gray-400">Memuat data...</td>
            </tr>
            <tr v-else-if="transactions.length === 0">
              <td colspan="7" class="px-6 py-8 text-center text-gray-400">Belum ada transaksi</td>
            </tr>
            <tr v-for="trx in transactions" :key="trx.id" class="hover:bg-gray-50 transition">
              <td class="px-6 py-3 whitespace-nowrap text-gray-600 font-mono text-xs">
                {{ formatDate(trx.transaction_date) }}
              </td>
              <td class="px-6 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="getTypeBadge(trx.transaction_type)">
                  {{ getTypeLabel(trx.transaction_type) }}
                </span>
              </td>
              <td class="px-6 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="getCashBadge(trx.cash_source)">
                  {{ getCashLabel(trx.cash_source) }}
                </span>
              </td>
              <td class="px-6 py-3">
                <div class="font-medium text-gray-800">{{ trx.description }}</div>
                <div v-if="trx.transaction_type === 'transfer'" class="text-xs text-gray-500 mt-0.5">
                  {{ getCashLabel(trx.transfer_from) }} → {{ getCashLabel(trx.transfer_to) }}
                </div>
              </td>
              <td class="px-6 py-3 text-gray-600 text-xs">
                {{ trx.category_name || '-' }}
              </td>
              <td class="px-6 py-3 text-right font-bold" :class="getAmountClassForTrx(trx)">
                {{ formatAmountForTrx(trx) }}
              </td>
              <td class="px-6 py-3 text-center">
                <!-- Action Buttons -->
                <div class="flex items-center justify-center gap-2">
                    <!-- Settle Inventory Button -->
                    <button 
                        v-if="isInventoryTransaction(trx) && (!trx.inventory_count || trx.inventory_count == 0)"
                        @click="openInventoryModal(trx)"
                        class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-red-200 shadow-sm animate-pulse border border-red-200"
                        title="Input rincian barang"
                    >
                        ⚠ Input Barang
                    </button>
                    <span 
                        v-else-if="isInventoryTransaction(trx) && trx.inventory_count > 0"
                        class="text-green-600 text-xs font-bold flex items-center gap-1 bg-green-50 px-2 py-1 rounded-full border border-green-100"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Lengkap
                    </span>

                    <button v-if="trx.reference_type !== 'order'" @click="deleteTransaction(trx.id)" class="text-red-600 hover:text-red-800 hover:bg-red-50 p-2 rounded-lg transition" title="Hapus">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <Teleport to="body">
      <div v-if="deleteModal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 transform transition-all">
          <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
              <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="text-lg font-bold text-gray-900 mb-2">Konfirmasi Hapus</h3>
              <p class="text-gray-600 text-sm mb-4">
                Apakah Anda yakin ingin menghapus transaksi ini? 
                <span class="font-semibold text-red-600">Data yang dihapus tidak dapat dikembalikan.</span>
              </p>
              <div class="flex gap-3 justify-end">
                <button 
                  @click="deleteModal.show = false"
                  class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition"
                >
                  Batal
                </button>
                <button 
                  @click="confirmDelete"
                  class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition flex items-center gap-2"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                  </svg>
                  Ya, Hapus
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal Transfer -->
    <Teleport to="body">
      <div v-if="showTransferModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full my-8">
          <!-- Header -->
          <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                </svg>
              </div>
              <h3 class="font-bold text-gray-900">Transfer Antar Kas</h3>
            </div>
            <button @click="showTransferModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <!-- Form -->
          <form @submit.prevent="submitTransfer" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dari *</label>
                <select v-model="transferForm.from" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-200">
                  <option value="cashier">Kas Kasir</option>
                  <option value="main">Kas Besar</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ke *</label>
                <select v-model="transferForm.to" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-200">
                  <option value="cashier">Kas Kasir</option>
                  <option value="main">Kas Besar</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp) *</label>
              <input v-model.number="transferForm.amount" type="number" required min="0" step="1000" placeholder="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan *</label>
              <input v-model="transferForm.description" type="text" required placeholder="Contoh: Transfer untuk modal" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showTransferModal = false" class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition">
                Batal
              </button>
              <button type="submit" :disabled="loadingTransfer" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg font-medium transition">
                <span v-if="loadingTransfer">Transfer...</span>
                <span v-else>Transfer</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Modal Tambah Modal -->
    <Teleport to="body">
      <div v-if="showCapitalModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full my-8">
          <!-- Header -->
          <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
              </div>
              <h3 class="font-bold text-gray-900">Tambah Modal Kas Besar</h3>
            </div>
            <button @click="showCapitalModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <!-- Form -->
          <form @submit.prevent="submitCapital" class="p-6 space-y-4">
            <div class="bg-emerald-50 text-emerald-800 text-sm p-3 rounded-lg flex gap-2">
              <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <span>Dana akan ditambahkan ke <b>Kas Besar</b> sebagai pemasukan (Modal).</span>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Modal (Rp) *</label>
              <input v-model.number="capitalForm.amount" type="number" required min="0" step="1000" placeholder="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-200">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan *</label>
              <input v-model="capitalForm.description" type="text" required placeholder="Contoh: Tambahan modal dari Owner" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-200">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
              <textarea v-model="capitalForm.notes" rows="2" placeholder="Catatan tambahan (opsional)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-200"></textarea>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showCapitalModal = false" class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition">
                Batal
              </button>
              <button type="submit" :disabled="loadingCapital" class="flex-1 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-400 text-white rounded-lg font-medium transition">
                <span v-if="loadingCapital">Menyimpan...</span>
                <span v-else>Simpan Modal</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Modal Pengeluaran -->
    <Teleport to="body">
      <div v-if="showExpenseModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full my-8">
          <!-- Header -->
          <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                </svg>
              </div>
              <h3 class="font-bold text-gray-900">Input Pengeluaran</h3>
            </div>
            <button @click="showExpenseModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <!-- Form -->
          <form @submit.prevent="submitExpense" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sumber Kas *</label>
              <select v-model="expenseForm.cash_source" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-200">
                <option value="cashier">Kas Kasir</option>
                <option value="main">Kas Besar</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Kategori *</label>
              <div class="relative" ref="categoryDropdownRef">
                <input 
                  v-model="categorySearch" 
                  @focus="showCategoryDropdown = true"
                  type="text" 
                  required
                  placeholder="🔍 Cari atau pilih kategori..."
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400"
                >
                
                <!-- Dropdown Categories -->
                <div 
                  v-if="showCategoryDropdown" 
                  class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-2xl max-h-60 overflow-y-auto"
                >
                  <!-- Expense Categories Group -->
                  <div v-if="filteredExpenseCategories.length > 0">
                    <div class="px-3 py-2 bg-gradient-to-r from-red-50 to-pink-50 border-b border-red-100 sticky top-0">
                      <div class="text-xs font-bold text-red-700 uppercase tracking-wider flex items-center gap-2">
                        <span>💸</span>
                        <span>Pengeluaran Operasional</span>
                      </div>
                    </div>
                    <div 
                      v-for="cat in filteredExpenseCategories" 
                      :key="cat.id" 
                      @click="selectCategory(cat)"
                      class="px-4 py-3 hover:bg-red-50 cursor-pointer border-b border-gray-50 last:border-0 transition"
                    >
                      <div class="font-semibold text-gray-800">{{ cat.name }}</div>
                      <div class="text-xs text-gray-500 mt-0.5">{{ cat.description }}</div>
                    </div>
                  </div>
                  
                  <!-- Non-Expense Categories Group -->
                  <div v-if="filteredNonExpenseCategories.length > 0">
                    <div class="px-3 py-2 bg-gradient-to-r from-amber-50 to-yellow-50 border-b border-amber-100 sticky top-0">
                      <div class="text-xs font-bold text-amber-700 uppercase tracking-wider flex items-center gap-2">
                        <span>💰</span>
                        <span>Prive & Aset</span>
                      </div>
                    </div>
                    <div 
                      v-for="cat in filteredNonExpenseCategories" 
                      :key="cat.id" 
                      @click="selectCategory(cat)"
                      class="px-4 py-3 hover:bg-amber-50 cursor-pointer border-b border-gray-50 last:border-0 transition"
                    >
                      <div class="font-semibold text-gray-800">{{ cat.name }}</div>
                      <div class="text-xs text-gray-500 mt-0.5">{{ cat.description }}</div>
                    </div>
                  </div>
                  
                  <div v-if="filteredExpenseCategories.length === 0 && filteredNonExpenseCategories.length === 0" class="px-4 py-6 text-gray-400 text-center text-sm">
                    Kategori tidak ditemukan
                  </div>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp) *</label>
                <input v-model.number="expenseForm.amount" type="number" required min="0" step="1000" placeholder="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-200">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal *</label>
                <input v-model="expenseForm.date" type="date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-200">
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan *</label>
              <input v-model="expenseForm.description" type="text" required placeholder="Contoh: Bayar listrik, beli bahan, dll" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-200">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
              <textarea v-model="expenseForm.notes" rows="2" placeholder="Catatan tambahan (opsional)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-200"></textarea>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showExpenseModal = false" class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition">
                Batal
              </button>
              <button type="submit" :disabled="loadingExpense" class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-gray-400 text-white rounded-lg font-medium transition">
                <span v-if="loadingExpense">Menyimpan...</span>
                <span v-else>Simpan</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Modal Pertanggungjawaban Inventory -->
    <Teleport to="body">
      <div v-if="showInventoryModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full my-8 flex flex-col max-h-[90vh]">
          <!-- Header -->
          <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex-shrink-0 flex items-center justify-between">
            <h3 class="font-bold text-white text-lg">Input Barang Persediaan</h3>
            <button @click="closeInventoryModal" class="text-white/80 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          
          <div class="p-6 overflow-y-auto flex-1">
             <!-- Summary -->
             <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4 flex justify-between items-center">
                <div>
                    <div class="text-xs text-gray-500 uppercase font-semibold">Total Nota Belanja</div>
                    <div class="text-xl font-bold text-gray-800">{{ formatCurrency(inventoryForm.targetAmount) }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-500 uppercase font-semibold">Total Input Barang</div>
                    <div class="text-xl font-bold" :class="inventoryDifference === 0 ? 'text-green-600' : 'text-red-600'">
                        {{ formatCurrency(inventoryForm.currentTotal) }}
                    </div>
                </div>
             </div>
             
             <!-- Warning if mismatch -->
             <div v-if="inventoryDifference !== 0" class="bg-red-50 text-red-700 px-4 py-2 rounded-lg text-sm mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span>Selisih: <b>{{ formatCurrency(Math.abs(inventoryDifference)) }}</b>. Total harus sama persis.</span>
             </div>

             <!-- Item Input List -->
             <div class="space-y-3 pb-24">
                 <div v-for="(item, index) in inventoryItems" :key="index" class="flex gap-2 items-start bg-white border border-gray-100 p-2 rounded shadow-sm relative z-0" :class="{'z-10 ring-2 ring-purple-100': activeInventoryRow === index}">
                    <div class="flex-1 relative">
                         <label class="block text-[10px] text-gray-500 mb-0.5">Nama Barang</label>
                         <input 
                            v-model="item.name" 
                            type="text" 
                            placeholder="Ketik untuk cari..." 
                            class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-purple-500 outline-none" 
                            required
                            @focus="activeInventoryRow = index"
                            @blur="handleInputBlur"
                            autocomplete="off"
                         >
                         
                         <!-- Autocomplete Dropdown -->
                         <div v-if="activeInventoryRow === index && getFilteredProducts(item.name).length > 0" class="absolute left-0 top-full mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-48 overflow-y-auto z-50">
                             <div 
                                v-for="prod in getFilteredProducts(item.name)" 
                                :key="prod.id"
                                @mousedown.prevent="selectInventoryProduct(index, prod)"
                                class="px-3 py-2 hover:bg-purple-50 cursor-pointer text-sm border-b border-gray-50 last:border-0"
                             >
                                 <div class="font-medium text-gray-800">{{ prod.item_name }}</div>
                                 <div class="text-xs text-gray-500 flex justify-between">
                                     <span class="text-gray-400">History</span>
                                     <span>Rp {{ formatNumber(prod.buy_price) }}</span>
                                 </div>
                             </div>
                         </div>
                    </div>
                    <div class="w-20">
                         <label class="block text-[10px] text-gray-500 mb-0.5">Qty</label>
                         <input v-model.number="item.qty" type="number" min="1" placeholder="1" @input="updateInventoryTotal" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-center focus:ring-1 focus:ring-purple-500 outline-none" required>
                    </div>
                    <div class="w-32">
                         <label class="block text-[10px] text-gray-500 mb-0.5">Harga Beli</label>
                         <input v-model.number="item.buy_price" type="number" min="0" placeholder="0" @input="updateInventoryTotal" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-purple-500 outline-none" required>
                    </div>
                    <div class="pt-6">
                        <button type="button" @click="removeInventoryItem(index)" class="text-red-400 hover:text-red-600 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                 </div>
             </div>
             
             <button @click="addInventoryItem" class="mt-4 w-full py-2 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 font-medium hover:border-purple-500 hover:text-purple-600 transition flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Baris Barang
             </button>

          </div>

          <div class="p-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0">
             <button @click="closeInventoryModal" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">Batal</button>
             <button 
                @click="submitInventorySettle" 
                :disabled="loadingInventorySettle || inventoryDifference !== 0"
                class="px-6 py-2 bg-purple-600 text-white rounded-lg font-bold hover:bg-purple-700 transition disabled:bg-gray-400 disabled:cursor-not-allowed shadow-lg shadow-purple-200"
             >
                <span v-if="loadingInventorySettle">Menyimpan...</span>
                <span v-else>Simpan & Update Stok</span>
             </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Toast Notification -->
    <div v-if="toast.show" class="fixed top-4 right-4 z-50 animate-fade-in-down">
      <div class="bg-white rounded-lg shadow-2xl border-l-4 p-4" :class="toast.type === 'success' ? 'border-green-500' : 'border-red-500'">
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0">
            <svg v-if="toast.type === 'success'" class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <svg v-else class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <div class="flex-1">
            <p class="font-medium text-gray-800">{{ toast.message }}</p>
          </div>
          <button @click="toast.show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';

const loadingTransfer = ref(false);
const loadingExpense = ref(false);
const loadingTransactions = ref(true);

const cashierBalance = ref(0);
const cashierIncome = ref(0);
const cashierExpense = ref(0);

const mainCashBalance = ref(0);
const mainCashIncome = ref(0);
const mainCashExpense = ref(0);

const categories = ref([]);
const products = ref([]); // For autocomplete
const transactions = ref([]);

const filterType = ref('');
const filterCash = ref('');

// Category Search
const categorySearch = ref('');
const showCategoryDropdown = ref(false);
const categoryDropdownRef = ref(null);

// Toast notification
const toast = reactive({
  show: false,
  message: '',
  type: 'success'
});

// Delete modal
const deleteModal = reactive({
  show: false,
  id: null
});

// Modal states
const showTransferModal = ref(false);
const showCapitalModal = ref(false); // Add Capital Modal
const loadingCapital = ref(false);
const showExpenseModal = ref(false);
const showInventoryModal = ref(false);
const loadingInventorySettle = ref(false);

const inventoryForm = reactive({
    transactionId: null,
    targetAmount: 0,
    currentTotal: 0
});

function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => toast.show = false, 3000);
}

// Filter categories by is_expense
const expenseCategories = computed(() => {
  return categories.value.filter(cat => cat.is_expense === 1);
});

const nonExpenseCategories = computed(() => {
  return categories.value.filter(cat => cat.is_expense === 0);
});

// Filtered categories based on search
const filteredExpenseCategories = computed(() => {
  const filtered = categories.value.filter(cat => cat.is_expense === 1);
  if (!categorySearch.value) return filtered;
  
  const q = categorySearch.value.toLowerCase();
  return filtered.filter(cat => 
    cat.name.toLowerCase().includes(q) || 
    (cat.description && cat.description.toLowerCase().includes(q))
  );
});

const filteredNonExpenseCategories = computed(() => {
  const filtered = categories.value.filter(cat => cat.is_expense === 0);
  if (!categorySearch.value) return filtered;
  
  const q = categorySearch.value.toLowerCase();
  return filtered.filter(cat => 
    cat.name.toLowerCase().includes(q) || 
    (cat.description && cat.description.toLowerCase().includes(q))
  );
});

const capitalForm = reactive({
  amount: 0,
  description: '',
  notes: ''
});

const transferForm = reactive({
  from: 'cashier',
  to: 'main',
  amount: 0,
  description: ''
});

const expenseForm = reactive({
  cash_source: 'main',
  category_id: '',
  amount: 0,
  description: '',
  date: new Date().toISOString().split('T')[0],
  notes: ''
});

// Inventory Logic
const inventoryItems = ref([]); // reused for both creation (if needed) and settling now
const activeInventoryRow = ref(null); // Track which row is focused for autocomplete
const inventoryDifference = computed(() => {
    return inventoryForm.currentTotal - inventoryForm.targetAmount;
});

// Fetch Products for Autocomplete
async function fetchInventoryHistory() {
    try {
        const res = await fetch('/api/Beauty_Salon/CashManagement/inventoryHistory');
        const d = await res.json();
        if (d.success) products.value = d.data;
    } catch(e) {
        console.error('Error fetching inventory history', e);
    }
}

// Helper filter products
function getFilteredProducts(query) {
    if (!query) return [];
    const q = query.toLowerCase();
    // Return max 5 matches
    return products.value.filter(p => p.item_name.toLowerCase().includes(q)).slice(0, 5);
}

// Select product from autocomplete
function selectInventoryProduct(index, prod) {
    if (inventoryItems.value[index]) {
        inventoryItems.value[index].name = prod.item_name;
        // Auto fill price from history
        inventoryItems.value[index].buy_price = Number(prod.buy_price) || 0; 
        updateInventoryTotal();
    }
    activeInventoryRow.value = null;
}

function handleInputBlur() {
    // Delay hiding to allow click event to register
    setTimeout(() => {
        activeInventoryRow.value = null;
    }, 200);
}

function addInventoryItem() {
    inventoryItems.value.push({
        name: '',
        qty: 1,
        buy_price: 0
    });
}

function removeInventoryItem(index) {
    inventoryItems.value.splice(index, 1);
    updateInventoryTotal();
}

function updateInventoryTotal() {
    let total = 0;
    inventoryItems.value.forEach(item => {
        total += (Number(item.qty) || 0) * (Number(item.buy_price) || 0);
    });
    inventoryForm.currentTotal = total;
}

function formatNumber(val) {
   return new Intl.NumberFormat('id-ID').format(val || 0);
}

// Check if transaction is 'Persediaan Barang'
function isInventoryTransaction(trx) {
    if (!trx.category_name) return false;
    const name = trx.category_name.toLowerCase();
    return name.includes('persediaan') || name.includes('stok');
}

// Open Inventory Modal
function openInventoryModal(trx) {
    inventoryForm.transactionId = trx.id;
    inventoryForm.targetAmount = Number(trx.amount);
    inventoryForm.currentTotal = 0;
    inventoryItems.value = [{ name: '', qty: 1, buy_price: 0 }]; // Start with 1 row
    showInventoryModal.value = true;
    updateInventoryTotal();
    // Ensure history is loaded
    if (products.value.length === 0) fetchInventoryHistory();
}

function closeInventoryModal() {
    showInventoryModal.value = false;
    inventoryItems.value = [];
}

// Submit Inventory Settle
async function submitInventorySettle() {
    if (inventoryDifference.value !== 0) {
        showToast('Jumlah total barang harus sama dengan nilai nota!', 'error');
        return;
    }
    
    loadingInventorySettle.value = true;
    try {
        const res = await fetch('/api/Beauty_Salon/CashManagement/settleInventory', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                transaction_id: inventoryForm.transactionId,
                items: inventoryItems.value
            })
        });
        
        const d = await res.json();
        if (d.success) {
            showToast('✅ Barang berhasil disimpan & stok diupdate!');
            closeInventoryModal();
            fetchTransactions(); // Refresh list to update status
        } else {
            showToast(d.message || 'Gagal menyimpan', 'error');
        }
    } catch(e) {
        console.error(e);
        showToast('Terjadi kesalahan sistem', 'error');
    } finally {
        loadingInventorySettle.value = false;
    }
}

// Select Category
function selectCategory(cat) {
  expenseForm.category_id = cat.id;
  categorySearch.value = cat.name;
  showCategoryDropdown.value = false;
}

// Click Outside Handler
const handleClickOutside = (event) => {
  if (categoryDropdownRef.value && !categoryDropdownRef.value.contains(event.target)) {
    showCategoryDropdown.value = false;
    // Reset text if valid
    if (expenseForm.category_id) {
      const selected = categories.value.find(c => c.id === expenseForm.category_id);
      if (selected && categorySearch.value !== selected.name) {
        categorySearch.value = selected.name;
      }
    }
  }
};

// Fetch categories
async function fetchCategories() {
  try {
    const res = await fetch('/api/Beauty_Salon/CashManagement/categories'); // Fixed endpoint
    const d = await res.json();
    if (d.success) categories.value = d.data;
  } catch(e) {
    console.error('Error fetching categories:', e);
  }
}

// Fetch transactions (show all relevant to Kas Besar)
async function fetchTransactions() {
  loadingTransactions.value = true;
  try {
    const allTransactions = [];
    
    // 1. Fetch ALL transfers (both directions)
    const resTransfer = await fetch('/api/Beauty_Salon/CashManagement/transactions?type=transfer');
    const dataTransfer = await resTransfer.json();
    if (dataTransfer.success) {
      allTransactions.push(...dataTransfer.data);
    }
    
    // 2. Fetch Main Cash expenses
    const resExpense = await fetch('/api/Beauty_Salon/CashManagement/transactions?cash=main&type=expense');
    const dataExpense = await resExpense.json();
    if (dataExpense.success) {
      allTransactions.push(...dataExpense.data);
    }
    
    // 3. Fetch Main Cash income (manual income)
    const resIncome = await fetch('/api/Beauty_Salon/CashManagement/transactions?cash=main&type=income');
    const dataIncome = await resIncome.json();
    if (dataIncome.success) {
      allTransactions.push(...dataIncome.data);
    }
    
    // Apply filters if any
    let filtered = allTransactions;
    
    if (filterType.value) {
      filtered = filtered.filter(tx => tx.transaction_type === filterType.value);
    }
    
    if (filterCash.value) {
      filtered = filtered.filter(tx => {
        if (filterCash.value === 'main') {
          // Show: expense/income from main, OR transfers involving main
          return tx.cash_source === 'main' || 
                 tx.transfer_from === 'main' || 
                 tx.transfer_to === 'main';
        } else {
          // Show: transfers involving cashier only
          return tx.transfer_from === 'cashier' || tx.transfer_to === 'cashier';
        }
      });
    }
    
    // Sort by date (newest first)
    filtered.sort((a, b) => new Date(b.transaction_date) - new Date(a.transaction_date));
    
    transactions.value = filtered;
  } catch(e) {
    console.error('Error fetching transactions:', e);
  } finally {
    loadingTransactions.value = false;
  }
}

// Submit transfer
async function submitTransfer() {
  if (transferForm.from === transferForm.to) {
    showToast('Tidak bisa transfer ke kas yang sama!', 'error');
    return;
  }

  loadingTransfer.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/CashManagement/transfer', { // Fixed endpoint
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        from: transferForm.from,
        to: transferForm.to,
        amount: transferForm.amount,
        description: transferForm.description
      })
    });

    const d = await res.json();
    if (d.success) {
      showToast('✅ Transfer berhasil!');
      transferForm.amount = 0;
      transferForm.description = '';
      showTransferModal.value = false; // Close modal
      fetchBalances();
      fetchTransactions();
    } else {
      showToast('Error: ' + (d.message || 'Gagal transfer'), 'error');
    }
  } catch(e) {
    console.error('Error transfer:', e);
    showToast('Error transfer kas', 'error');
  } finally {
    loadingTransfer.value = false;
  }
}

// Submit expense
async function submitExpense() {
  if (!expenseForm.category_id) {
    showToast('Pilih kategori terlebih dahulu', 'error');
    return;
  }
  if (loadingExpense.value) return; 

  loadingExpense.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/CashManagement/expense', { // Fixed endpoint
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        cash_source: expenseForm.cash_source,
        category_id: expenseForm.category_id,
        amount: expenseForm.amount,
        description: expenseForm.description,
        date: expenseForm.date,
        notes: expenseForm.notes
      })
    });

    const d = await res.json();
    if (d.success) {
      showToast('✅ Pengeluaran berhasil disimpan!');
      expenseForm.category_id = '';
      categorySearch.value = ''; // Reset search
      expenseForm.amount = 0;
      expenseForm.description = '';
      expenseForm.date = new Date().toISOString().split('T')[0]; // Reset to today
      expenseForm.notes = '';
      showExpenseModal.value = false; // Close modal  
      fetchBalances();
      fetchTransactions();
    } else {
      showToast('Error: ' + (d.message || 'Gagal menyimpan'), 'error');
    }
  } catch(e) {
    console.error('Error expense:', e);
    showToast('Error menyimpan pengeluaran', 'error');
  } finally {
    loadingExpense.value = false;
  }
}

// Submit Capital
async function submitCapital() {
  if (loadingCapital.value) return;
  loadingCapital.value = true;
  
  try {
    const payload = {
      amount: capitalForm.amount,
      cash_source: 'main', // Default to Main Cash
      category_id: null, // Income may not need generic category
      description: capitalForm.description,
      notes: capitalForm.notes
    };
    
    // Call addIncome endpoint
    const res = await fetch('/api/Beauty_Salon/CashManagement/addIncome', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    
    const d = await res.json();
    
    if (d.success) {
      showToast('✅ Modal berhasil ditambahkan');
      // Reset form
      capitalForm.amount = 0;
      capitalForm.description = '';
      capitalForm.notes = '';
      showCapitalModal.value = false;
      fetchBalances();
      fetchTransactions();
    } else {
      showToast('Error: ' + (d.message || 'Gagal menyimpan'), 'error');
    }
  } catch(e) {
    console.error('Error adding capital:', e);
    showToast('Error menyimpan modal', 'error');
  } finally {
    loadingCapital.value = false;
  }
}

// Delete transaction
function deleteTransaction(id) {
  deleteModal.id = id;
  deleteModal.show = true;
}

// Confirm delete
async function confirmDelete() {
  if (!deleteModal.id) return;
  
  try {
    const res = await fetch(`/api/Beauty_Salon/CashManagement/deleteTransaction/${deleteModal.id}`, {
      method: 'POST'
    });
    const d = await res.json();
    
    if (d.success) {
      showToast('🗑️ Transaksi berhasil dihapus');
      deleteModal.show = false;
      deleteModal.id = null;
      fetchBalances();
      fetchTransactions();
    } else {
      showToast('Error: ' + (d.message || 'Gagal menghapus'), 'error');
    }
  } catch(e) {
    console.error('Error deleting:', e);
    showToast('Error menghapus transaksi', 'error');
  }
}

// Helpers
function formatCurrency(val) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
}

function formatDate(dStr) {
  if (!dStr) return '-';
  const d = new Date(dStr);
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatAmount(type, amount) {
  const prefix = type === 'expense' ? '- ' : type === 'income' ? '+ ' : '';
  return prefix + formatCurrency(amount);
}

// Format amount for transaction with correct sign for transfers (from Kas Besar view)
function formatAmountForTrx(trx) {
  if (trx.transaction_type === 'transfer') {
    const viewCash = filterCash.value || 'main'; // Default to Kas Besar perspective
    const isOutflow = (viewCash === 'main' && trx.transfer_from === 'main') || (viewCash === 'cashier' && trx.transfer_from === 'cashier');
    const prefix = isOutflow ? '- ' : '+ ';
    return prefix + formatCurrency(trx.amount);
  }
  return formatAmount(trx.transaction_type, trx.amount);
}

function getAmountClassForTrx(trx) {
  if (trx.transaction_type === 'transfer') {
    const viewCash = filterCash.value || 'main';
    const isOutflow = (viewCash === 'main' && trx.transfer_from === 'main') || (viewCash === 'cashier' && trx.transfer_from === 'cashier');
    return isOutflow ? 'text-red-600' : 'text-green-600';
  }
  return getAmountClass(trx.transaction_type);
}

function getTypeBadge(type) {
  if (type === 'income') return 'bg-green-100 text-green-800';
  if (type === 'expense') return 'bg-red-100 text-red-800';
  return 'bg-blue-100 text-blue-800';
}

function getTypeLabel(type) {
  if (type === 'income') return 'Pemasukan';
  if (type === 'expense') return 'Pengeluaran';
  return 'Transfer';
}

function getCashBadge(cash) {
  return cash === 'cashier' ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800';
}

function getCashLabel(cash) {
  return cash === 'cashier' ? 'Kas Kasir' : 'Kas Besar';
}

function getAmountClass(type) {
  if (type === 'income') return 'text-green-600';
  if (type === 'expense') return 'text-red-600';
  return 'text-blue-600';
}

// Fetch balances for both cashier and main cash
async function fetchBalances() {
  try {
    // === KAS KASIR ===
    const resCashier = await fetch('/api/Beauty_Salon/CashManagement/balances?cash_source=cashier');
    if (!resCashier.ok) {
      console.error('Cashier balance API error:', resCashier.status, resCashier.statusText);
    }
    const dataCashier = await resCashier.json();
    console.log('Cashier balance API response:', dataCashier);
    
    if (dataCashier.success && dataCashier.data) {
        cashierBalance.value = Number(dataCashier.data.current_balance) || 0;
        cashierIncome.value = Number(dataCashier.data.total_income) || 0;
        cashierExpense.value = Number(dataCashier.data.total_expense) || 0;
        console.log('Cashier balance set to:', cashierBalance.value);
    } else {
        console.error('Failed to fetch cashier balance:', dataCashier);
        // Fallback: set to 0 if API fails
        cashierBalance.value = 0;
        cashierIncome.value = 0;
        cashierExpense.value = 0;
    }

    // === KAS BESAR ===
    const resMain = await fetch('/api/Beauty_Salon/CashManagement/balances?cash_source=main');
    if (!resMain.ok) {
      console.error('Main cash balance API error:', resMain.status, resMain.statusText);
    }
    const dataMain = await resMain.json();
    console.log('Main cash balance API response:', dataMain);
    
    if (dataMain.success && dataMain.data) {
        mainCashBalance.value = Number(dataMain.data.current_balance) || 0;
        mainCashIncome.value = Number(dataMain.data.total_income) || 0;
        mainCashExpense.value = Number(dataMain.data.total_expense) || 0;
    } else {
        console.error('Failed to fetch main cash balance:', dataMain);
        // Fallback: set to 0 if API fails
        mainCashBalance.value = 0;
        mainCashIncome.value = 0;
        mainCashExpense.value = 0;
    }
    
  } catch(e) {
    console.error('Error fetching balances:', e);
    // Set defaults on error
    cashierBalance.value = 0;
    cashierIncome.value = 0;
    cashierExpense.value = 0;
    mainCashBalance.value = 0;
    mainCashIncome.value = 0;
    mainCashExpense.value = 0;
  }
}

onMounted(() => {
  fetchBalances();
  fetchCategories();
  fetchTransactions();
  
  // Add click outside listener
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  // Cleanup click outside listener
  document.removeEventListener('click', handleClickOutside);
});
</script>
