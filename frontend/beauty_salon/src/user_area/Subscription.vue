<template>
  <div class="min-h-screen">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center h-64">
      <div class="animate-spin rounded-full h-12 w-12 border-4 border-pink-500 border-t-transparent"></div>
    </div>

    <div v-else class="space-y-6">
      <!-- Subscription Status Card -->
      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <!-- Header with gradient -->
        <div class="relative overflow-hidden" :class="statusGradient">
          <div class="absolute inset-0 bg-black/10"></div>
          <div class="relative px-6 py-8 text-white">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-2xl font-bold mb-2">Status Langganan</h2>
                <div class="flex items-center gap-3">
                  <span class="px-3 py-1 rounded-full text-sm font-semibold" :class="statusBadge">
                    {{ statusLabel }}
                  </span>
                  <span v-if="subscription?.is_trial" class="text-white/80 text-sm">
                    (Masa Percobaan)
                  </span>
                </div>
              </div>
              <div class="text-right">
                <div class="text-4xl font-bold">{{ daysRemaining }}</div>
                <div class="text-white/80 text-sm">hari tersisa</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Details -->
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="text-center p-4 bg-gray-50 rounded-xl">
              <div class="text-gray-500 text-sm mb-1">Mulai Dari</div>
              <div class="text-lg font-semibold text-gray-800">{{ formatDate(subscription?.subscription?.start_date) }}</div>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-xl">
              <div class="text-gray-500 text-sm mb-1">Berakhir Pada</div>
              <div class="text-lg font-semibold text-gray-800">{{ formatDate(subscription?.subscription?.end_date) }}</div>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-xl">
              <div class="text-gray-500 text-sm mb-1">Harga Bulanan</div>
              <div class="text-lg font-semibold text-gray-800">{{ formatPrice(subscription?.monthly_price) }}</div>
            </div>
          </div>

          <!-- Warning Alert -->
          <div v-if="subscription?.should_warn" class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-3">
            <svg class="w-6 h-6 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
              <div class="font-semibold text-amber-800">Langganan akan segera berakhir!</div>
              <p class="text-amber-700 text-sm mt-1">
                Perpanjang langganan Anda sebelum {{ formatDate(subscription?.subscription?.end_date) }} untuk menghindari gangguan layanan.
              </p>
            </div>
          </div>

          <!-- Expired Alert -->
          <div v-if="subscription?.effective_status === 'expired'" class="mt-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3">
            <svg class="w-6 h-6 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
              <div class="font-semibold text-red-800">Langganan Anda telah berakhir!</div>
              <p class="text-red-700 text-sm mt-1">
                Perpanjang sekarang untuk melanjutkan menggunakan aplikasi Beauty Salon.
              </p>
            </div>
          </div>

          <!-- Grace Period Alert -->
          <div v-if="subscription?.effective_status === 'grace_period'" class="mt-6 p-4 bg-orange-50 border border-orange-200 rounded-xl flex items-start gap-3">
            <svg class="w-6 h-6 text-orange-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
              <div class="font-semibold text-orange-800">Masa Tenggang ({{ subscription?.grace_period_days }} hari)</div>
              <p class="text-orange-700 text-sm mt-1">
                Anda memiliki {{ subscription?.grace_period_days + daysRemaining }} hari masa tenggang. Perpanjang sekarang untuk menghindari pemblokiran akses.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Pricing Plans -->
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-6">Pilih Paket Berlangganan</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Monthly -->
          <div 
            @click="selectPlan('monthly', 1)"
            class="relative border-2 rounded-xl p-6 cursor-pointer transition-all hover:shadow-lg"
            :class="selectedPlan === 'monthly' ? 'border-pink-500 bg-pink-50' : 'border-gray-200 hover:border-pink-300'"
          >
            <div class="text-center">
              <h4 class="font-semibold text-gray-800 mb-2">Bulanan</h4>
              <div class="text-3xl font-bold text-pink-600">{{ formatPrice(PRICES.monthly) }}</div>
              <div class="text-gray-500 text-sm mt-1">per bulan</div>
            </div>
            <div v-if="selectedPlan === 'monthly'" class="absolute top-2 right-2">
              <svg class="w-6 h-6 text-pink-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
            </div>
          </div>

          <!-- Quarterly -->
          <div 
            @click="selectPlan('quarterly', 3)"
            class="relative border-2 rounded-xl p-6 cursor-pointer transition-all hover:shadow-lg"
            :class="selectedPlan === 'quarterly' ? 'border-pink-500 bg-pink-50' : 'border-gray-200 hover:border-pink-300'"
          >
            <div class="absolute -top-3 left-1/2 -translate-x-1/2">
              <span class="px-3 py-1 bg-green-500 text-white text-xs font-semibold rounded-full">Hemat 5%</span>
            </div>
            <div class="text-center">
              <h4 class="font-semibold text-gray-800 mb-2">3 Bulan</h4>
              <div class="text-3xl font-bold text-pink-600">{{ formatPrice(PRICES.quarterly) }}</div>
              <div class="text-gray-500 text-sm mt-1">{{ formatPrice(PRICES.quarterly / 3) }}/bulan</div>
            </div>
            <div v-if="selectedPlan === 'quarterly'" class="absolute top-2 right-2">
              <svg class="w-6 h-6 text-pink-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
            </div>
          </div>

          <!-- Yearly -->
          <div 
            @click="selectPlan('yearly', 12)"
            class="relative border-2 rounded-xl p-6 cursor-pointer transition-all hover:shadow-lg"
            :class="selectedPlan === 'yearly' ? 'border-pink-500 bg-pink-50' : 'border-gray-200 hover:border-pink-300'"
          >
            <div class="absolute -top-3 left-1/2 -translate-x-1/2">
              <span class="px-3 py-1 bg-gradient-to-r from-pink-500 to-purple-500 text-white text-xs font-semibold rounded-full">Terbaik! Hemat 15%</span>
            </div>
            <div class="text-center">
              <h4 class="font-semibold text-gray-800 mb-2">Tahunan</h4>
              <div class="text-3xl font-bold text-pink-600">{{ formatPrice(PRICES.yearly) }}</div>
              <div class="text-gray-500 text-sm mt-1">{{ formatPrice(PRICES.yearly / 12) }}/bulan</div>
            </div>
            <div v-if="selectedPlan === 'yearly'" class="absolute top-2 right-2">
              <svg class="w-6 h-6 text-pink-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
            </div>
          </div>
        </div>

        <!-- Payment method -->
        <div class="mt-6 grid grid-cols-2 gap-3">
          <button
            type="button"
            @click="onSelectPaymentMethod('qris')"
            class="rounded-xl border-2 px-4 py-3 text-sm font-semibold transition"
            :class="selectedPaymentMethod === 'qris' ? 'border-pink-500 bg-pink-50 text-pink-700' : 'border-gray-200 text-gray-600 hover:border-pink-300'"
          >
            QRIS
          </button>
          <button
            type="button"
            @click="onSelectPaymentMethod('bca')"
            class="rounded-xl border-2 px-4 py-3 text-sm font-semibold transition"
            :class="selectedPaymentMethod === 'bca' ? 'border-pink-500 bg-pink-50 text-pink-700' : 'border-gray-200 text-gray-600 hover:border-pink-300'"
          >
            Transfer BCA
          </button>
        </div>

        <!-- Pay Button -->
        <div class="mt-4 text-center">
          <button 
            @click="createPayment"
            :disabled="processing"
            class="px-8 py-3 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="!processing">
              Bayar {{ formatPrice(selectedAmount) }}
            </span>
            <span v-else class="flex items-center gap-2">
              <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Memproses...
            </span>
          </button>
        </div>
      </div>

      <!-- QRIS Payment Modal -->
      <Teleport to="body">
        <div v-if="showPaymentModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl">
            <div class="p-4 border-b border-gray-100 bg-gradient-to-r from-pink-500 to-purple-500 rounded-t-2xl">
              <h3 class="text-lg font-bold text-white text-center">
                {{ isBcaPayment ? 'Transfer BCA' : 'Scan QRIS' }}
              </h3>
            </div>
            
            <div class="p-4 space-y-3">
              <template v-if="isBcaPayment">
                <div class="rounded-xl border-2 border-pink-200 bg-pink-50/50 p-4 text-sm space-y-2">
                  <p class="font-semibold text-gray-800">{{ paymentData?.bank_account?.label || 'BCA' }}</p>
                  <p class="font-mono text-xl font-bold tracking-wide text-pink-600">{{ paymentData?.bank_account?.number }}</p>
                  <p class="text-gray-600">a/n {{ paymentData?.bank_account?.name }}</p>
                  <div class="mt-2 rounded-lg bg-white p-3 border border-pink-100">
                    <p class="text-xs text-gray-500">Nominal transfer (exact)</p>
                    <p class="text-2xl font-bold text-pink-600">{{ formatPrice(paymentData?.amount) }}</p>
                    <p v-if="paymentData?.unique_nominal" class="mt-1 text-xs text-amber-600">
                      Nominal unik — transfer persis hingga digit terakhir
                    </p>
                  </div>
                </div>
                <p class="text-[10px] text-gray-500 text-center">Konfirmasi otomatis setelah transfer terdeteksi</p>
              </template>

              <template v-else>
              <!-- QR Code Display -->
              <div class="flex justify-center">
                <div v-if="paymentData?.qr_string && !paymentExpired" class="p-2 bg-white border-2 border-pink-200 rounded-xl flex justify-center">
                  <qrcode-vue :value="paymentData.qr_string" :size="180" level="H" />
                </div>
                <p v-if="paymentData?.qr_string && !paymentExpired" class="mt-3 text-center text-xs text-gray-500">Pembayaran QRIS akan dikonfirmasi otomatis dalam pembaruan berkala, maksimal 10 menit.</p>
                <div v-else-if="paymentExpired" class="w-48 h-48 bg-red-50 rounded-xl flex flex-col items-center justify-center text-center px-3">
                  <svg class="w-8 h-8 text-red-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <p class="text-xs text-red-700 font-semibold">QRIS kadaluarsa</p>
                </div>
                <div v-else class="w-48 h-48 bg-gray-100 rounded-xl flex items-center justify-center">
                  <div class="animate-spin rounded-full h-8 w-8 border-4 border-pink-500 border-t-transparent"></div>
                </div>
              </div>
              </template>

              <!-- Payment Info -->
              <div class="bg-gray-50 rounded-lg p-3 space-y-1 text-xs">
                <div class="flex justify-between">
                  <span class="text-gray-600">No. Referensi</span>
                  <span class="font-mono font-semibold text-gray-800 truncate max-w-[150px]">{{ paymentData?.payment_ref }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Periode</span>
                  <span class="font-semibold text-gray-800">{{ selectedMonths }} Bulan</span>
                </div>
                <div v-if="paymentData?.discount > 0" class="flex justify-between text-green-600">
                  <span>Diskon</span>
                  <span class="font-semibold">-{{ formatPrice(paymentData?.discount) }}</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-gray-200 mt-1">
                  <span class="text-gray-800 font-semibold self-center">Total</span>
                  <span class="text-xl font-bold text-pink-600">{{ formatPrice(paymentData?.amount) }}</span>
                </div>
              </div>

              <p v-if="!paymentSuccess && !paymentExpired" class="text-[10px] text-gray-500 text-center">
                Status dicek otomatis setiap beberapa detik
              </p>

              <!-- Manual Status / Refresh -->
              <div v-if="!paymentSuccess" class="mt-2 space-y-2">
                 <button 
                    v-if="!paymentExpired"
                    @click="manualCheckPayment" 
                    :disabled="checkingPayment"
                    class="w-full px-4 py-2 bg-blue-50 text-blue-700 font-semibold rounded-lg hover:bg-blue-100 transition flex items-center justify-center gap-2 border border-blue-200 text-sm"
                  >
                    <svg v-if="checkingPayment" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ checkingPayment ? 'Memeriksa...' : 'Cek Status Pembayaran' }}
                  </button>
                  <button
                    v-else-if="!isBcaPayment"
                    @click="refreshExpiredPayment"
                    :disabled="processing"
                    class="w-full px-4 py-2 bg-pink-50 text-pink-700 font-semibold rounded-lg hover:bg-pink-100 transition flex items-center justify-center gap-2 border border-pink-200 text-sm"
                  >
                    {{ processing ? 'Memperbarui...' : 'Perbarui QRIS' }}
                  </button>
              </div>

              <!-- Success Message -->
              <div v-if="paymentSuccess" class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
                <svg class="w-8 h-8 mx-auto text-green-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h4 class="font-bold text-green-800 text-sm">Pembayaran Berhasil!</h4>
              </div>
            </div>
            
            <div class="p-4 border-t border-gray-100 space-y-2">
              <button
                v-if="!paymentSuccess && paymentData?.payment_ref"
                @click="cancelActivePayment()"
                :disabled="processing"
                class="w-full px-4 py-2 border border-red-200 text-red-600 font-semibold rounded-lg hover:bg-red-50 transition text-sm disabled:opacity-50"
              >
                {{ processing ? 'Membatalkan...' : 'Batalkan Pembayaran' }}
              </button>
              <button 
                @click="closePaymentModal"
                class="w-full px-4 py-2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition text-sm"
              >
                {{ paymentSuccess ? 'Selesai' : 'Tutup' }}

              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Generic Confirmation Modal -->
      <Teleport to="body">
        <div v-if="showConfirmModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl p-6 transform transition-all scale-100">
            <div class="text-center mb-4">
              <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <h3 class="text-lg font-bold text-gray-900">{{ confirmTitle }}</h3>
              <p class="text-sm text-gray-500 mt-2">
                {{ confirmMessage }}
              </p>
            </div>
            <div class="flex gap-3">
               <button 
                @click="showConfirmModal = false"
                class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                Batal
              </button>
              <button 
                @click="executeConfirmAction"
                class="flex-1 px-4 py-2.5 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                :class="confirmButtonClass"
                :disabled="processing"
              >
                {{ processing ? 'Memproses...' : confirmLabel }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Message Modal (Custom Alert) -->
      <Teleport to="body">
        <div v-if="showMessageModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl p-6 transform transition-all scale-100">
            <div class="text-center mb-4">
              <div v-if="messageType === 'error'" class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div v-else-if="messageType === 'success'" class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div v-else class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              
              <h3 class="text-lg font-bold text-gray-900">{{ messageTitle }}</h3>
              <p class="text-sm text-gray-600 mt-2 leading-relaxed">
                {{ messageBody }}
              </p>
            </div>
            <div class="mt-6">
              <button 
                @click="showMessageModal = false"
                class="w-full px-4 py-2.5 bg-gray-800 text-white font-semibold rounded-xl hover:bg-gray-700 transition"
              >
                Mengerti
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Payment History -->
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-6">Riwayat Pembayaran</h3>
        
        <div v-if="paymentHistory.length === 0" class="text-center py-8 text-gray-500">
          <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
          </svg>
          <p>Belum ada riwayat pembayaran</p>
        </div>

        <div v-else class="space-y-3">
          <div 
            v-for="payment in paymentHistory" 
            :key="payment.id"
            class="flex items-center justify-between p-4 bg-gray-50 rounded-xl"
          >
            <div>
              <div class="font-semibold text-gray-800">{{ formatPrice(payment.amount) }}</div>
              <div class="text-sm text-gray-500">{{ formatDate(payment.created_at) }}</div>
              <div class="text-xs text-gray-400 font-mono">{{ payment.payment_ref }}</div>
            </div>
            <div class="text-right flex flex-col items-end gap-2">
              <div class="text-sm text-gray-600">{{ formatDate(payment.period_start) }} - {{ formatDate(payment.period_end) }}</div>
              <div class="flex items-center gap-2">
                 <button 
                  v-if="payment.payment_status === 'pending'"
                  @click="resumePayment(payment)"
                  class="px-3 py-1 bg-pink-500 text-white text-xs font-semibold rounded-lg hover:bg-pink-600 transition shadow-sm"
                  :disabled="processing"
                 >
                  Bayar
                 </button>
                 <button 
                  v-if="payment.payment_status === 'pending'"
                  @click="cancelPayment(payment)"
                  class="px-3 py-1 border border-red-200 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition shadow-sm"
                  :disabled="processing"
                 >
                  Batal
                 </button>
                <span 
                  class="inline-block px-2 py-1 rounded-full text-xs font-semibold"
                  :class="paymentStatusClass(payment.payment_status)"
                >
                  {{ paymentStatusLabel(payment.payment_status) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { API_BASE_URL } from '../api';
import QrcodeVue from 'qrcode.vue';

const loading = ref(true);
const processing = ref(false);
const subscription = ref(null);
const paymentHistory = ref([]);
const selectedPlan = ref('monthly');
const selectedMonths = ref(1);
const selectedAmount = ref(60000);
const selectedPaymentMethod = ref('qris');
const showPaymentModal = ref(false);
const paymentData = ref(null);
const checkingPayment = ref(false);
const paymentSuccess = ref(false);
const paymentExpired = ref(false);
let statusInterval = null;

const PRICES = computed(() => {
  const base = parseInt(subscription.value?.monthly_price || 60000);
  return {
    monthly: base,
    quarterly: base * 3 * 0.95, // 5% discount
    yearly: base * 12 * 0.85    // 15% discount
  };
});

const statusGradient = computed(() => {
  const status = subscription.value?.effective_status;
  if (status === 'active') return 'bg-gradient-to-r from-green-500 to-emerald-600';
  if (status === 'trial') return 'bg-gradient-to-r from-blue-500 to-indigo-600';
  if (status === 'grace_period') return 'bg-gradient-to-r from-orange-500 to-amber-600';
  if (status === 'expired') return 'bg-gradient-to-r from-red-500 to-rose-600';
  return 'bg-gradient-to-r from-gray-500 to-slate-600';
});

const statusBadge = computed(() => {
  const status = subscription.value?.effective_status;
  if (status === 'active') return 'bg-white/20 text-white';
  if (status === 'trial') return 'bg-white/20 text-white';
  if (status === 'grace_period') return 'bg-white/20 text-white';
  if (status === 'expired') return 'bg-white/20 text-white';
  return 'bg-white/20 text-white';
});

const statusLabel = computed(() => {
  const status = subscription.value?.effective_status;
  if (status === 'active') return 'Aktif';
  if (status === 'trial') return 'Trial';
  if (status === 'grace_period') return 'Masa Tenggang';
  if (status === 'expired') return 'Kadaluarsa';
  return 'Tidak Diketahui';
});

const daysRemaining = computed(() => {
  const days = subscription.value?.days_remaining ?? 0;
  return days < 0 ? 0 : days;
});

const isBcaPayment = computed(() => paymentData.value?.payment_method === 'bca');

const activePendingPayment = computed(() => {
  if (paymentData.value?.payment_ref) return paymentData.value;
  return paymentHistory.value.find((p) => p.payment_status === 'pending') || null;
});

function onSelectPaymentMethod(method) {
  if (method === selectedPaymentMethod.value && !activePendingPayment.value) {
    return;
  }

  const pending = activePendingPayment.value;
  if (pending?.payment_ref && (pending.payment_method || 'qris') !== method) {
    showConfirm(
      'Ganti Metode Bayar?',
      'Pembayaran pending akan dibatalkan agar Anda bisa memilih metode lain. Lanjutkan?',
      'Ya, Batalkan',
      'bg-red-600 hover:bg-red-700 focus:ring-red-500',
      async () => {
        showConfirmModal.value = false;
        const ok = await cancelActivePayment(pending.payment_ref, false);
        if (ok) {
          selectedPaymentMethod.value = method;
          if (showPaymentModal.value) {
            showPaymentModal.value = false;
            stopCheckingPayment();
            paymentData.value = null;
          }
        }
      }
    );
    return;
  }

  selectedPaymentMethod.value = method;
}

function selectPlan(plan, months) {
  selectedPlan.value = plan;
  selectedMonths.value = months;
  selectedAmount.value = PRICES.value[plan];
}

function formatPrice(amount) {
  if (!amount) return 'Rp 0';
  return 'Rp ' + parseInt(amount).toLocaleString('id-ID');
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  const date = new Date(dateStr);
  return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

async function fetchSubscription() {
  try {
    const res = await fetch(`${API_BASE_URL}/Beauty_Salon/Subscription`, {
      credentials: 'include'
    });
    const data = await res.json();
    if (data.success) {
      subscription.value = data.data;
      selectedAmount.value = PRICES.value[selectedPlan.value];
    }
  } catch (err) {
    console.error('Failed to fetch subscription:', err);
  }
}

async function fetchPaymentHistory() {
  try {
    const res = await fetch(`${API_BASE_URL}/Beauty_Salon/Subscription/history`, {
      credentials: 'include'
    });
    const data = await res.json();
    if (data.success) {
      paymentHistory.value = data.data || [];
    }
  } catch (err) {
    console.error('Failed to fetch payment history:', err);
  }
}

async function createPayment() {
  processing.value = true;
  paymentSuccess.value = false;
  paymentExpired.value = false;
  try {
    const res = await fetch(`${API_BASE_URL}/Beauty_Salon/Subscription/pay`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        months: selectedMonths.value,
        payment_method: selectedPaymentMethod.value
      })
    });
    const data = await res.json();
    if (data.success && data.status === 'paid') {
      paymentSuccess.value = true;
      await fetchSubscription();
      await fetchPaymentHistory();
      showAlert(data.message || 'Pembayaran sudah berhasil!', 'Sukses', 'success');
      return;
    }
    if (data.success && (data.data?.qr_string || data.data?.payment_method === 'bca')) {
      openPaymentModal(data.data);
      fetchPaymentHistory();
    } else if (res.status === 409) {
      await fetchPaymentHistory();
      showAlert(data.message || 'Batalkan pembayaran pending dulu sebelum ganti metode.', 'Pending', 'info');
    } else {
      await fetchPaymentHistory();
      showAlert(data.message || 'Gagal membuat invoice', 'Gagal', 'error');
    }
  } catch (err) {
    console.error('Failed to create payment:', err);
    showAlert('Terjadi kesalahan: ' + err.message, 'Error', 'error');
  } finally {
    processing.value = false;
  }
}

function openPaymentModal(data) {
  paymentData.value = data;
  paymentSuccess.value = false;
  paymentExpired.value = false;
  showPaymentModal.value = true;
  startPollingPayment();
}

async function resumePayment(payment) {
  processing.value = true;
  paymentSuccess.value = false;
  paymentExpired.value = false;
  try {
    const res = await fetch(`${API_BASE_URL}/Beauty_Salon/Subscription/resume`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        payment_ref: payment.payment_ref
      })
    });
    const data = await res.json();

    if (data.success && data.status === 'paid') {
      await fetchSubscription();
      await fetchPaymentHistory();
      showAlert(data.message || 'Pembayaran sudah berhasil!', 'Sukses', 'success');
      return;
    }

    if (data.success && (data.data?.qr_string || data.data?.payment_method === 'bca')) {
      let months = 1;
      if (data.data.amount >= PRICES.value.yearly) months = 12;
      else if (data.data.amount >= PRICES.value.quarterly) months = 3;
      selectedMonths.value = months;

      openPaymentModal(data.data);
      fetchPaymentHistory();
      if (data.refreshed && data.data?.qr_string) {
        showAlert('QRIS diperbarui. Silakan scan ulang.', 'QRIS Baru', 'info');
      }
    } else if (data.expired) {
      await fetchPaymentHistory();
      showConfirm(
        'Invoice Kadaluarsa',
        data.message || 'Pembayaran ini telah kadaluarsa. Apakah Anda ingin membuat pembayaran baru dengan paket yang sama?',
        'Buat Baru',
        'bg-pink-600 hover:bg-pink-700',
        async () => {
          showConfirmModal.value = false;

          const amount = parseInt(payment.amount);
          let planMonths = 1;
          if (amount >= PRICES.value.yearly) planMonths = 12;
          else if (amount >= PRICES.value.quarterly) planMonths = 3;

          const planName = planMonths === 12 ? 'yearly' : (planMonths === 3 ? 'quarterly' : 'monthly');
          selectPlan(planName, planMonths);

          await createPayment();
        }
      );
    } else {
      await fetchPaymentHistory();
      showAlert(data.message || 'Gagal melanjutkan pembayaran', 'Gagal', 'error');
    }
  } catch (err) {
    console.error('Failed to resume payment:', err);
    showAlert('Terjadi kesalahan: ' + err.message, 'Error', 'error');
  } finally {
    processing.value = false;
  }
}

async function refreshExpiredPayment() {
  const amount = parseInt(paymentData.value?.amount || selectedAmount.value || 0);
  let planMonths = 1;
  if (amount >= PRICES.value.yearly) planMonths = 12;
  else if (amount >= PRICES.value.quarterly) planMonths = 3;
  const planName = planMonths === 12 ? 'yearly' : (planMonths === 3 ? 'quarterly' : 'monthly');
  selectPlan(planName, planMonths);

  // Jika sudah failed di DB, buat invoice baru. Jika masih pending, coba refresh QR dulu.
  if (paymentExpired.value) {
    await createPayment();
    return;
  }

  if (!paymentData.value?.payment_ref) {
    await createPayment();
    return;
  }

  await resumePayment({
    payment_ref: paymentData.value.payment_ref,
    amount: paymentData.value.amount
  });
}

function startPollingPayment() {
  stopCheckingPayment();
  if (!paymentData.value?.payment_ref) return;

  statusInterval = setInterval(() => {
    pollPaymentStatus(true);
  }, 3000);
}

async function pollPaymentStatus(silent = true) {
  if (!paymentData.value?.payment_ref || paymentSuccess.value) return;

  try {
    const res = await fetch(
      `${API_BASE_URL}/Beauty_Salon/Subscription/pollPayment?payment_ref=${encodeURIComponent(paymentData.value.payment_ref)}`,
      { credentials: 'include' }
    );
    const data = await res.json();
    const status = String(data.status || '').toLowerCase();

    if (status === 'paid' || status === 'success') {
      stopCheckingPayment();
      paymentSuccess.value = true;
      paymentExpired.value = false;
      await fetchSubscription();
      await fetchPaymentHistory();
      if (!silent) {
        showAlert(data.message || 'Pembayaran berhasil dikonfirmasi!', 'Sukses', 'success');
      }
      return;
    }

    if (status === 'expired' || status === 'failed' || status === 'cancelled') {
      stopCheckingPayment();
      paymentExpired.value = true;
      await fetchPaymentHistory();
      if (!silent) {
        showAlert(data.message || 'Pembayaran kadaluarsa. Perbarui QRIS untuk melanjutkan.', 'Kadaluarsa', 'error');
      }
    }
  } catch (err) {
    if (!silent) {
      console.error('Poll payment error:', err);
    }
  }
}

async function manualCheckPayment() {
  if (!paymentData.value?.payment_ref) return;

  checkingPayment.value = true;
  try {
    const res = await fetch(
      `${API_BASE_URL}/Beauty_Salon/Subscription/checkPayment?payment_ref=${encodeURIComponent(paymentData.value.payment_ref)}`,
      { credentials: 'include' }
    );
    const data = await res.json();
    const status = String(data.status || '').toLowerCase();

    if (data.success && (status === 'paid' || status === 'success')) {
      stopCheckingPayment();
      paymentSuccess.value = true;
      paymentExpired.value = false;
      await fetchSubscription();
      await fetchPaymentHistory();
      showAlert(data.message || 'Pembayaran berhasil dikonfirmasi!', 'Sukses', 'success');
    } else if (status === 'expired' || status === 'failed') {
      stopCheckingPayment();
      paymentExpired.value = true;
      await fetchPaymentHistory();
      showAlert(data.message || 'QRIS kadaluarsa. Silakan perbarui QRIS.', 'Kadaluarsa', 'error');
    } else {
      const isError = status === 'error';
      const title = isError ? 'Gagal Cek' : 'Belum Lunas';
      const msg = isError
        ? (data.message || 'Gagal mengecek status pembayaran')
        : ('Status pembayaran saat ini: ' + (data.status || 'Menunggu') + '. Jika Anda sudah membayar, mohon tunggu sebentar.');
      showAlert(msg, title, isError ? 'error' : 'info');
    }
  } catch (err) {
    console.error('Error checking payment status:', err);
    showAlert('Gagal mengecek status: ' + err.message, 'Error', 'error');
  } finally {
    checkingPayment.value = false;
  }
}



// Confirmation Modal State
const showConfirmModal = ref(false);
const confirmTitle = ref('');
const confirmMessage = ref('');
const confirmLabel = ref('Ya');
const confirmButtonClass = ref('');
let confirmActionCallback = null;

function showConfirm(title, message, label, btnClass, callback) {
  confirmTitle.value = title;
  confirmMessage.value = message;
  confirmLabel.value = label;
  confirmButtonClass.value = btnClass || 'bg-blue-600 hover:bg-blue-700';
  confirmActionCallback = callback;
  showConfirmModal.value = true;
}

async function executeConfirmAction() {
  if (confirmActionCallback) {
     await confirmActionCallback();
  }
}

// Message Modal State
const showMessageModal = ref(false);
const messageTitle = ref('');
const messageBody = ref('');
const messageType = ref('info'); // 'info' | 'error'

function showAlert(message, title = 'Informasi', type = 'info') {
  messageBody.value = message;
  messageTitle.value = title;
  messageType.value = type;
  showMessageModal.value = true;
}

function cancelPayment(payment) {
  showConfirm(
    'Batalkan Pembayaran?',
    'Apakah Anda yakin ingin membatalkan pembayaran ini? Anda dapat membuat pembayaran baru dengan metode lain.',
    'Ya, Batalkan',
    'bg-red-600 hover:bg-red-700 focus:ring-red-500',
    () => cancelActivePayment(payment.payment_ref, true)
  );
}

async function cancelActivePayment(paymentRef = null, closeModal = true) {
  const ref = paymentRef || paymentData.value?.payment_ref;
  if (!ref) return false;

  processing.value = true;
  try {
    const res = await fetch(`${API_BASE_URL}/Beauty_Salon/Subscription/cancel`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ payment_ref: ref })
    });
    const data = await res.json();

    if (data.data?.status === 'paid' || data.status === 'paid') {
      await fetchSubscription();
      await fetchPaymentHistory();
      showAlert('Pembayaran sudah berhasil diverifikasi.', 'Sukses', 'success');
      return false;
    }

    if (!data.success && !data.status) {
      showAlert(data.message || 'Gagal membatalkan pembayaran', 'Gagal', 'error');
      return false;
    }

    stopCheckingPayment();
    paymentData.value = null;
    paymentExpired.value = false;
    if (closeModal) {
      showPaymentModal.value = false;
      showAlert(data.message || 'Pembayaran dibatalkan.', 'Dibatalkan', 'success');
    }
    await fetchPaymentHistory();
    return true;
  } catch (err) {
    console.error('Failed to cancel payment:', err);
    showAlert('Terjadi kesalahan: ' + err.message, 'Error', 'error');
    return false;
  } finally {
    processing.value = false;
  }
}

async function doCancelPayment(payment) {
  await cancelActivePayment(payment.payment_ref, true);
}

// ... helper functions ...

function stopCheckingPayment() {
  if (statusInterval) {
    clearInterval(statusInterval);
    statusInterval = null;
  }
  checkingPayment.value = false;
}

function paymentStatusClass(status) {
  if (status === 'paid' || status === 'success') return 'bg-green-100 text-green-700';
  if (status === 'pending') return 'bg-yellow-100 text-yellow-700';
  if (status === 'failed' || status === 'cancelled' || status === 'expired' || !status) return 'bg-red-100 text-red-700';
  return 'bg-gray-100 text-gray-700';
}

function paymentStatusLabel(status) {
  if (status === 'paid' || status === 'success') return 'Berhasil';
  if (status === 'pending') return 'Menunggu';
  if (status === 'expired') return 'Kadaluarsa';
  if (status === 'failed' || status === 'cancelled' || !status) return 'Gagal';
  return status;
}

function closePaymentModal() {
  showPaymentModal.value = false;
  stopCheckingPayment();
  fetchPaymentHistory();
  if (paymentSuccess.value) {
    fetchSubscription();
  }
  paymentExpired.value = false;
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    // Optional: show toast
  });
}

onMounted(async () => {
  await Promise.all([fetchSubscription(), fetchPaymentHistory()]);
  loading.value = false;
});

onUnmounted(() => {
  stopCheckingPayment();
});
</script>

<style scoped>
/* Add any custom styles here */
</style>
