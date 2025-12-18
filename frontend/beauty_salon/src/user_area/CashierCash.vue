<template>
  <div class="max-w-7xl mx-auto space-y-6">


    <!-- Saldo Total Card -->
    <div class="bg-gradient-to-br from-indigo-500 to-blue-600 rounded-2xl p-4 md:p-6 text-white shadow-xl relative overflow-hidden">
      <div class="absolute top-0 right-0 p-8 opacity-10 transform translate-x-1/2 -translate-y-1/2">
        <svg class="w-64 h-64" fill="currentColor" viewBox="0 0 24 24">
          <path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"></path>
        </svg>
      </div>
      <div class="relative z-10">
        <div class="flex items-center gap-2 mb-2">
          <svg class="w-5 h-5 md:w-6 md:h-6 text-indigo-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
          </svg>
          <p class="text-indigo-100 font-semibold text-sm md:text-base">Saldo Kas Kasir</p>
        </div>
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold tracking-tight mb-3 break-words">{{ formatCurrency(cashBalance) }}</h2>
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 text-xs md:text-sm">
          <div class="flex items-center gap-1">
            <span class="text-green-200">↑ Pemasukan:</span>
            <span class="font-semibold text-white break-words">{{ formatCurrency(totalIncome) }}</span>
          </div>
          <div class="flex items-center gap-1">
            <span class="text-red-200">↓ Pengeluaran:</span>
            <span class="font-semibold text-white break-words">{{ formatCurrency(totalExpense) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Riwayat Pengeluaran -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <!-- Filter Tanggal -->
      <div class="border-b border-gray-100 px-4 py-3 bg-gray-50">
        <div class="space-y-3">
          <!-- Label -->
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-700">Filter Tanggal:</span>
          </div>
          
          <!-- Date Inputs -->
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <input 
              v-model="dateFilter.start" 
              @change="validateDateRange"
              type="date" 
              placeholder="Tanggal Mulai"
              class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-200"
            >
            <span class="text-gray-500 text-center hidden sm:inline">—</span>
            <input 
              v-model="dateFilter.end" 
              @change="validateDateRange"
              type="date" 
              placeholder="Tanggal Akhir"
              class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-200"
            >
            <button 
              v-if="dateFilter.start || dateFilter.end"
              @click="resetDateFilter" 
              class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-lg transition font-medium"
            >
              Reset
            </button>
          </div>
          
          <!-- Error Message -->
          <div v-if="dateRangeError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg border border-red-100">
            ⚠️ {{ dateRangeError }}
          </div>
        </div>
      </div>

      <div class="border-b border-gray-100 px-6 py-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
        <h3 class="font-bold text-gray-800">Riwayat Transaksi Kas</h3>
        <div class="flex items-center gap-2">
          <button 
            @click="showTransferModal = true"
            class="flex-1 sm:flex-none bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-lg flex items-center justify-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
            </svg>
            <span>Transfer Kas</span>
          </button>
          <button 
            @click="showExpenseModal = true"
            class="flex-1 sm:flex-none bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-lg flex items-center justify-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span>Pengeluaran</span>
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
              <thead>
                <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b">
                  <th class="px-6 py-4">Status & Tanggal</th>
                  <th class="px-6 py-4">Keterangan</th>
                  <th class="px-6 py-4 text-right">Jumlah</th>
                  <th class="px-6 py-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
            <tr v-if="filteredExpenses.length === 0 && !loadingExpenses">
              <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                <div class="flex flex-col items-center gap-3">
                  <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  <p class="font-medium">Tidak ada data pengeluaran</p>
                  <p class="text-sm">{{ dateFilter.start || dateFilter.end ? 'Ubah filter tanggal atau reset filter' : 'Belum ada pengeluaran yang tercatat' }}</p>
                </div>
              </td>
            </tr>
                <tr v-if="loadingExpenses" class="animate-pulse">
                  <td colspan="4" class="px-6 py-8 text-center text-gray-400">Memuat data...</td>
                </tr>
                <tr v-else-if="expenses.length === 0">
                  <td colspan="4" class="px-6 py-8 text-center text-gray-400">
                    Belum ada data pengeluaran
                  </td>
                </tr>
                <tr v-for="tx in filteredExpenses" :key="tx.id" class="hover:bg-gray-50 transition border-b border-gray-50 last:border-0">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center gap-3">
                      <div v-if="tx.transaction_type === 'expense'" class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                      </div>
                      <div v-else class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                      </div>
                      <div>
                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-none mb-1">
                          {{ tx.transaction_type === 'expense' ? 'Pengeluaran' : 'Transfer Keluar' }}
                        </div>
                        <div class="text-xs font-mono text-gray-600">{{ formatDate(tx.transaction_date) }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="font-bold text-gray-800 tracking-tight">{{ tx.description }}</div>
                    <div v-if="tx.notes" class="text-xs text-gray-500 mt-1 italic">{{ tx.notes }}</div>
                    <div v-if="tx.transaction_type === 'transfer'" class="text-[10px] text-blue-600 font-bold mt-1 uppercase tracking-wider bg-blue-50 inline-block px-2 py-0.5 rounded">
                      Ke: {{ tx.transfer_to === 'main' ? 'Kas Besar' : tx.transfer_to }}
                    </div>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <span class="font-black text-sm" :class="tx.transaction_type === 'expense' ? 'text-rose-600' : 'text-blue-600'">
                      - {{ formatCurrency(tx.amount) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-center">
                    <button 
                      v-if="isToday(tx.transaction_date)"
                      @click="deleteExpense(tx.id)"
                      class="text-gray-400 hover:text-rose-600 hover:bg-rose-50 p-2 rounded-xl transition-all"
                      title="Hapus"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                      </svg>
                    </button>
                    <span 
                      v-else
                      class="text-gray-200 p-2 inline-block cursor-not-allowed"
                      title="Hanya bisa menghapus transaksi hari ini"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                      </svg>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
    
    <!-- Modal Input Pengeluaran -->
    <Teleport to="body">
      <div v-if="showExpenseModal" class="fixed inset-0 z-[99] bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="showExpenseModal = false">
        <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl max-h-[90vh] flex flex-col animate-fade-in-down">
          <div class="p-6 border-b flex justify-between items-center bg-gradient-to-r from-red-50 to-pink-50">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-red-600 flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                </svg>
              </div>
              <h3 class="font-bold text-gray-800 text-lg">Input Pengeluaran</h3>
            </div>
            <button @click="showExpenseModal = false" class="text-gray-400 hover:text-gray-600 transition text-2xl">&times;</button>
          </div>
          
          <form @submit.prevent="submitExpense" class="p-6 space-y-4 overflow-y-auto flex-1">
            <!-- Searchable Category Select -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Pengeluaran *</label>
              <div class="relative" ref="categoryDropdownRef">
                <input 
                  type="text" 
                  v-model="categorySearch" 
                  @focus="showCategoryDropdown = true"
                  placeholder="Cari kategori..." 
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-200 focus:border-red-400 outline-none transition bg-white"
                />
                <div v-if="expenseForm.category_id" class="absolute right-3 top-3 text-green-500">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                
                <!-- Dropdown List -->
                <div v-if="showCategoryDropdown" class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-xl max-h-72 overflow-y-auto">
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

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan *</label>
              <input 
                v-model="expenseForm.description" 
                type="text" 
                required
                placeholder="Contoh: Beli sabun cuci, transport, dll"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400"
              >
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp) *</label>
                <input 
                  v-model.number="expenseForm.amount" 
                  type="number" 
                  required
                  min="0"
                  step="1000"
                  placeholder="0"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400"
                >
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal *</label>
                <input 
                  v-model="expenseForm.date" 
                  type="date" 
                  required
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400"
                >
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Catatan (Opsional)</label>
              <textarea 
                v-model="expenseForm.notes" 
                rows="3"
                placeholder="Catatan tambahan..."
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400"
              ></textarea>
            </div>
          </form>

          <div class="p-6 border-t bg-gray-50 flex gap-3">
            <button 
              @click="showExpenseModal = false" 
              type="button"
              class="flex-1 bg-white border border-gray-300 text-gray-700 px-4 py-2.5 rounded-lg font-medium hover:bg-gray-50 transition"
            >
              Batal
            </button>
            <button 
              @click="submitExpense"
              :disabled="loading"
              type="submit"
              class="flex-1 bg-red-600 hover:bg-red-700 disabled:bg-gray-400 text-white px-4 py-2.5 rounded-lg font-medium transition shadow-lg"
            >
              <span v-if="loading">Menyimpan...</span>
              <span v-else>Simpan</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
    
    <!-- Modal Konfirmasi Hapus -->
    <Teleport to="body">
      <div v-if="deleteModal.show" class="fixed inset-0 z-[99] bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="deleteModal.show = false">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl animate-fade-in-down">
          <div class="p-6">
            <div class="flex items-center gap-4 mb-4">
              <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
              </div>
              <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-800">Konfirmasi Hapus</h3>
                <p class="text-sm text-gray-600 mt-1">Yakin ingin menghapus pengeluaran ini?</p>
              </div>
            </div>
            <p class="text-sm text-gray-500 bg-gray-50 p-3 rounded-lg border border-gray-200">
              Data yang sudah dihapus tidak dapat dikembalikan.
            </p>
          </div>
          
          <div class="p-6 border-t bg-gray-50 flex gap-3 rounded-b-2xl">
            <button 
              @click="deleteModal.show = false" 
              type="button"
              class="flex-1 bg-white border border-gray-300 text-gray-700 px-4 py-2.5 rounded-lg font-medium hover:bg-gray-100 transition"
            >
              Batal
            </button>
            <button 
              @click="confirmDelete"
              type="button"
              class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-lg font-medium transition shadow-lg"
            >
              Ya, Hapus
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal Transfer Kas -->
    <Teleport to="body">
      <div v-if="showTransferModal" class="fixed inset-0 z-[99] bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="showTransferModal = false">
        <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl animate-fade-in-down overflow-hidden">
          <div class="p-6 border-b flex justify-between items-center bg-gradient-to-r from-blue-50 to-indigo-50">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-2xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-200">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                </svg>
              </div>
              <div>
                <h3 class="font-black text-gray-800 text-xl tracking-tight leading-none mb-1">Transfer Kas</h3>
                <p class="text-xs font-bold text-blue-600 uppercase tracking-widest leading-none">Ke Kas Besar</p>
              </div>
            </div>
            <button @click="showTransferModal = false" class="w-10 h-10 rounded-full hover:bg-white flex items-center justify-center text-gray-400 hover:text-gray-600 transition-all">&times;</button>
          </div>
          
          <form @submit.prevent="submitTransfer" class="p-8 space-y-6">
            <div class="bg-blue-50 rounded-2xl p-4 flex items-center gap-4 border border-blue-100 mb-2">
              <div class="flex-1">
                <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-1">Dari Kasir</p>
                <p class="font-black text-blue-900 leading-none">{{ formatCurrency(cashBalance) }}</p>
              </div>
              <div class="text-blue-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
              </div>
              <div class="flex-1 text-right">
                <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-1">Ke Kas Besar</p>
                <p class="font-black text-blue-900 leading-none">Main Cash</p>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-6">
              <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Keterangan Transfer *</label>
                <input 
                  v-model="transferForm.description" 
                  type="text" 
                  required
                  placeholder="Contoh: Deposit Harian, Setoran Kasir, dll"
                  class="w-full px-4 py-4 border border-gray-100 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all bg-gray-50/50 font-bold"
                >
              </div>

              <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Jumlah Transfer (Rp) *</label>
                <input 
                  v-model.number="transferForm.amount" 
                  type="number" 
                  required
                  :max="cashBalance"
                  min="0"
                  step="1000"
                  placeholder="0"
                  class="w-full px-4 py-4 border border-gray-100 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all bg-gray-50/50 font-black text-xl"
                >
                <p v-if="transferForm.amount > cashBalance" class="mt-2 text-[10px] font-black text-rose-500 uppercase tracking-wider ml-1 animate-pulse">
                  ⚠️ Melebihi saldo kasir saat ini!
                </p>
              </div>
            </div>

            <div>
              <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Catatan Tambahan (Opsional)</label>
              <textarea 
                v-model="transferForm.notes" 
                rows="2"
                placeholder="Misal: No. Ref Setoran atau Nama Petugas..."
                class="w-full px-4 py-3 border border-gray-100 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all bg-gray-50/50 font-medium text-sm"
              ></textarea>
            </div>
          </form>

          <div class="p-8 border-t bg-gray-50/50 flex gap-4">
            <button 
              @click="showTransferModal = false" 
              type="button"
              class="flex-1 bg-white border border-gray-200 text-gray-500 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-gray-50 transition-all"
            >
              Batal
            </button>
            <button 
              @click="submitTransfer"
              :disabled="loading || transferForm.amount <= 0 || transferForm.amount > cashBalance"
              type="submit"
              class="flex-[1.5] bg-blue-600 hover:bg-blue-700 disabled:bg-gray-200 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all shadow-xl shadow-blue-200"
            >
              <span v-if="loading">Mengirim...</span>
              <span v-else>Konfirmasi Transfer</span>
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
import { ref, reactive, onMounted, computed, onUnmounted } from 'vue';

const loading = ref(false);
const loadingExpenses = ref(true);
const expenses = ref([]);
const categories = ref([]);
const totalIncome = ref(0); // Total pemasukan dari orders
const showExpenseModal = ref(false); // Modal state
const showTransferModal = ref(false); // Transfer modal state

// Date filter
const dateFilter = reactive({
  start: '',
  end: ''
});
const dateRangeError = ref('');

const deleteModal = reactive({ 
  show: false, 
  id: null 
}); // Delete confirmation

// Toast notification
const toast = reactive({ 
  show: false, 
  message: '', 
  type: 'success' 
});

function showToast(message, type = 'success') {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  setTimeout(() => toast.show = false, 3000);
}

// Category Search
const categorySearch = ref('');
const showCategoryDropdown = ref(false);
const categoryDropdownRef = ref(null);

const expenseForm = reactive({
  category_id: '',
  description: '',
  amount: 0,
  date: new Date().toISOString().split('T')[0],
  notes: ''
});

const transferForm = reactive({
  amount: 0,
  description: 'Transfer ke Kas Besar',
  notes: ''
});

// Computed: Filter categories by search and is_expense
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

// Computed: Filter categories by is_expense (for backward compatibility)
const expenseCategories = computed(() => {
  return categories.value.filter(cat => cat.is_expense === 1);
});

const nonExpenseCategories = computed(() => {
  return categories.value.filter(cat => cat.is_expense === 0);
});

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

// Computed: Filtered expenses by date range
const filteredExpenses = computed(() => {
  if (!dateFilter.start && !dateFilter.end) {
    return expenses.value;
  }
  
  return expenses.value.filter(exp => {
    const expDate = new Date(exp.transaction_date);
    const startDate = dateFilter.start ? new Date(dateFilter.start) : null;
    const endDate = dateFilter.end ? new Date(dateFilter.end) : null;
    
    if (startDate && endDate) {
      return expDate >= startDate && expDate <= endDate;
    } else if (startDate) {
      return expDate >= startDate;
    } else if (endDate) {
      return expDate <= endDate;
    }
    
    return true;
  });
});

// Computed: Total pengeluaran & transfer out (dari filtered)
const totalExpense = computed(() => {
  return filteredExpenses.value.reduce((sum, tx) => sum + Number(tx.amount), 0);
});

// Computed: Saldo kas (income - expense)
const cashBalance = computed(() => {
  return totalIncome.value - totalExpense.value;
});

// Fetch income from completed orders
async function fetchIncome() {
  try {
    const res = await fetch('/api/Beauty_Salon/Orders');
    const d = await res.json();
    
    if (d.success) {
      let sum = 0;
      d.data.forEach(order => {
        if (order.status === 'completed') {
          const cashAmount = Number(order.pay_cash) || 0;
          sum += cashAmount;
        }
      });
      totalIncome.value = sum;
    }
  } catch(e) {
    console.error('Error fetching income:', e);
  }
}

// Fetch categories
async function fetchCategories() {
  try {
    const res = await fetch('/api/Beauty_Salon/CashManagement/categories');
    const d = await res.json();
    
    if (d.success) {
      categories.value = d.data;
    }
  } catch(e) {
    console.error('Error fetching categories:', e);
  }
}

// Fetch expenses & transfers
async function fetchExpenses() {
  loadingExpenses.value = true;
  try {
    // We don't specify type=expense to get both expense and transfer
    const url = '/api/Beauty_Salon/CashManagement/transactions?cash=cashier';
    console.log('Fetching expenses from:', url);
    
    const res = await fetch(url);
    const d = await res.json();
    console.log('Fetch expenses response:', d);
    
    if (d.success) {
      expenses.value = d.data.sort((a, b) => new Date(b.transaction_date) - new Date(a.transaction_date));
      console.log('Expenses loaded:', expenses.value.length, 'items');
      console.log('First item:', expenses.value[0]);
    }
  } catch(e) {
    console.error('Error fetching expenses:', e);
  } finally {
    loadingExpenses.value = false;
  }
}

// Submit transfer
async function submitTransfer() {
  if (transferForm.amount <= 0 || transferForm.amount > cashBalance.value) {
    showToast('Jumlah transfer tidak valid', 'error');
    return;
  }

  loading.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/CashManagement/transfer', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        from: 'cashier',
        to: 'main',
        amount: transferForm.amount,
        description: transferForm.description,
        notes: transferForm.notes
      })
    });

    const d = await res.json();
    
    if (d.success) {
      showToast('✅ Transfer kas berhasil dilakukan!');
      // Reset
      transferForm.amount = 0;
      transferForm.description = 'Transfer ke Kas Besar';
      transferForm.notes = '';
      showTransferModal.value = false;
      // Refresh
      await fetchExpenses();
    } else {
      showToast('Gagal: ' + (d.message || 'Error server'), 'error');
    }
  } catch(e) {
    console.error('Transfer error:', e);
    showToast('Terjadi kesalahan transfer', 'error');
  } finally {
    loading.value = false;
  }
}

// Submit expense
async function submitExpense() {
  if (!expenseForm.category_id) {
    showToast('Mohon pilih kategori', 'error');
    return;
  }
  
  if (!expenseForm.description || !expenseForm.amount) {
    showToast('Mohon lengkapi keterangan dan jumlah', 'error');
    return;
  }

  loading.value = true;
  try {
    const res = await fetch('/api/Beauty_Salon/CashManagement/expense', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        cash_source: 'cashier',
        category_id: expenseForm.category_id,
        description: expenseForm.description,
        amount: expenseForm.amount,
        date: expenseForm.date,
        notes: expenseForm.notes
      })
    });

    const d = await res.json();
    console.log('Submit expense response:', d);
    
    if (d.success) {
      showToast('✅ Pengeluaran berhasil disimpan!');
      // Reset form
      expenseForm.category_id = '';
      categorySearch.value = '';  // Reset search
      expenseForm.description = '';
      expenseForm.amount = 0;
      expenseForm.date = new Date().toISOString().split('T')[0];
      expenseForm.notes = '';
      // Close modal
      showExpenseModal.value = false;
      // Refresh data
      await fetchExpenses();
    } else {
      showToast('Error: ' + (d.message || 'Gagal menyimpan'), 'error');
    }
  } catch(e) {
    console.error('Error saving expense:', e);
    showToast('Error menyimpan data', 'error');
  } finally {
    loading.value = false;
  }
}

// Delete expense
function deleteExpense(id) {
  // Find the expense to check date
  const expense = expenses.value.find(e => e.id === id);
  if (!expense || !isToday(expense.transaction_date)) {
    showToast('❌ Hanya bisa menghapus pengeluaran hari ini', 'error');
    return;
  }
  
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
      showToast('🗑️ Pengeluaran berhasil dihapus');
      deleteModal.show = false;
      deleteModal.id = null;
      await fetchExpenses();
    } else {
      showToast('Error: ' + (d.message || 'Gagal menghapus'), 'error');
    }
  } catch(e) {
    console.error('Error deleting expense:', e);
    showToast('Error menghapus data', 'error');
  }
}

function formatCurrency(val) {
  return new Intl.NumberFormat('id-ID', { 
    style: 'currency', 
    currency: 'IDR', 
    minimumFractionDigits: 0 
  }).format(val || 0);
}

function formatDate(dStr) {
  if (!dStr) return '-';
  const d = new Date(dStr);
  return d.toLocaleDateString('id-ID', { 
    day: 'numeric', 
    month: 'short', 
    year: 'numeric' 
  });
}

// Validate date range (max 7 days)
function validateDateRange() {
  dateRangeError.value = '';
  
  if (!dateFilter.start || !dateFilter.end) {
    return;
  }
  
  const start = new Date(dateFilter.start);
  const end = new Date(dateFilter.end);
  
  // Check if start > end
  if (start > end) {
    dateRangeError.value = 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir';
    return;
  }
  
  // Calculate difference in days
  const diffTime = Math.abs(end - start);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  if (diffDays > 7) {
    dateRangeError.value = 'Rentang tanggal maksimal 7 hari';
    // Reset end date to 7 days from start
    const maxEnd = new Date(start);
    maxEnd.setDate(maxEnd.getDate() + 7);
    dateFilter.end = maxEnd.toISOString().split('T')[0];
  }
}

// Reset date filter
function resetDateFilter() {
  dateFilter.start = '';
  dateFilter.end = '';
  dateRangeError.value = '';
}

// Check if date is today
function isToday(dateString) {
  if (!dateString) return false;
  const date = new Date(dateString);
  const today = new Date();
  return date.getFullYear() === today.getFullYear() &&
         date.getMonth() === today.getMonth() &&
         date.getDate() === today.getDate();
}

onMounted(() => {
  fetchIncome();
  fetchCategories();
  fetchExpenses();
  
  // Add click outside listener
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  // Cleanup click outside listener
  document.removeEventListener('click', handleClickOutside);
});
</script>
