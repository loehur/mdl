<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-800">Manajemen Pengguna</h2>
      <button 
        @click="showModal = true"
        class="bg-fuchsia-600 hover:bg-fuchsia-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span>Tambah User</span>
      </button>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden md:block bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gradient-to-r from-fuchsia-50 to-pink-50 text-gray-700 font-semibold border-b border-gray-200">
            <tr>
              <th class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                  <span>Nama</span>
                </div>
              </th>
              <th class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                  <span>Email</span>
                </div>
              </th>
              <th class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                  <span>Role</span>
                </div>
              </th>
              <th class="px-6 py-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <template v-if="loading">
               <tr v-for="i in 3" :key="i" class="animate-pulse">
                 <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
                 <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                 <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-16"></div></td>
                 <td class="px-6 py-4"></td>
               </tr>
            </template>
            <template v-else-if="users.length">
                <tr v-for="user in users" :key="user.id" class="hover:bg-fuchsia-50/30 transition group">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-fuchsia-400 to-pink-500 flex items-center justify-center text-white font-bold shadow-sm">
                      {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <span class="font-medium text-gray-800">{{ user.name }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 text-gray-600 font-mono text-sm">{{ user.phone_number }}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1.5 rounded-full text-xs font-semibold capitalize inline-flex items-center gap-1.5 bg-blue-100 text-blue-700">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                      {{ user.role }}
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <button @click="deleteUser(user.id)" class="text-red-500 hover:text-red-700 text-sm font-medium inline-flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                      Hapus
                    </button>
                </td>
                </tr>
            </template>
            <tr v-else>
               <td colspan="4" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center gap-3 text-gray-400">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <p class="text-sm font-medium">Belum ada user kasir</p>
                  </div>
               </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden space-y-3">
      <template v-if="loading">
        <div v-for="i in 3" :key="i" class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-3/4 mb-3"></div>
          <div class="h-3 bg-gray-200 rounded w-1/2"></div>
        </div>
      </template>
      <template v-else-if="users.length">
        <div v-for="user in users" :key="user.id" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <!-- Header with avatar -->
          <div class="bg-gradient-to-r from-fuchsia-500 to-pink-500 px-4 py-3 flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-white font-bold text-lg shadow-lg">
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="font-semibold text-white truncate">{{ user.name }}</h3>
              <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white capitalize mt-1">
                {{ user.role }}
              </span>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-4 space-y-3">
            <!-- Email -->
            <div class="flex items-center gap-3 text-sm">
              <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs text-gray-500 mb-0.5">Email</p>
                <p class="font-medium text-gray-800 font-mono">{{ user.phone_number }}</p>
              </div>
            </div>
            
            <!-- Actions -->
            <button @click="deleteUser(user.id)" class="w-full py-2.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 font-medium text-sm transition flex items-center justify-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
              Hapus User
            </button>
          </div>
        </div>
      </template>
      <div v-else class="bg-white rounded-xl p-8 text-center shadow-sm border border-gray-100">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        <p class="text-gray-500">Belum ada user kasir</p>
      </div>
    </div>

    <!-- Modal Create -->
    <Teleport to="body">
       <div v-if="showModal" class="fixed inset-0 z-[99999] flex items-center justify-center p-4">
         <!-- Backdrop -->
         <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showModal = false"></div>
         
         <!-- Dialog -->
         <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
                <h3 class="font-bold text-gray-800">Tambah User Baru</h3>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 hover:bg-gray-200 rounded-lg p-1 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form @submit.prevent="createUser" class="p-6 grid gap-4">
                <div>
                   <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                   <input v-model="form.name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-fuchsia-200 focus:border-fuchsia-400 outline-none transition" required placeholder="Contoh: Siti Aminah" />
                </div>
                
                <div>
                   <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                   <input v-model="form.phone_number" type="email" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-fuchsia-200 focus:border-fuchsia-400 outline-none transition" required placeholder="Contoh: user@email.com" />
                </div>

                <div>
                   <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                   <select v-model="form.role" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-fuchsia-200 focus:border-fuchsia-400 outline-none transition bg-white" required disabled>
                      <option value="cashier">Cashier</option>
                   </select>
                   <p class="text-xs text-gray-500 mt-1">Saat ini hanya role Cashier yang tersedia.</p>
                </div>

                <div>
                   <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                   <input v-model="form.password" type="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-fuchsia-200 focus:border-fuchsia-400 outline-none transition" required placeholder="******" />
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" @click="showModal = false" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium transition">Batal</button>
                    <button type="submit" :disabled="submitting" class="flex-1 px-4 py-2 bg-fuchsia-600 text-white rounded-lg hover:bg-fuchsia-700 font-medium transition shadow-sm disabled:opacity-50">
                        {{ submitting ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </form>
         </div>
       </div>
    </Teleport>

    <!-- Custom Notification Toast -->
    <Teleport to="body">
       <div v-if="toast.show" class="fixed top-4 right-4 z-[100000] flex items-center p-4 rounded-xl shadow-2xl transition-all duration-300 transform translate-y-0"
            :class="{
                'bg-red-50 text-red-800 border-l-4 border-red-500': toast.type === 'error',
                'bg-green-50 text-green-800 border-l-4 border-green-500': toast.type === 'success'
            }"
       >
         <div class="mr-3">
            <svg v-if="toast.type === 'error'" class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <svg v-else class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
         </div>
         <div>
             <h4 class="font-bold text-sm">{{ toast.type === 'error' ? 'Error' : 'Sukses' }}</h4>
             <p class="text-sm opacity-90">{{ toast.message }}</p>
         </div>
         <button @click="toast.show = false" class="ml-4 text-gray-400 hover:text-gray-600">
             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
         </button>
       </div>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
       <div v-if="deleteConfirm.show" class="fixed inset-0 z-[100001] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
         <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
           <!-- Header -->
           <div class="bg-gradient-to-r from-red-500 to-pink-500 px-6 py-4 flex items-center gap-3">
             <div class="w-12 h-12 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
               <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
             </div>
             <div class="flex-1">
               <h3 class="font-bold text-white text-lg">Konfirmasi Hapus</h3>
               <p class="text-white/80 text-sm">Tindakan ini tidak dapat dibatalkan</p>
             </div>
           </div>
           
           <!-- Body -->
           <div class="p-6">
             <p class="text-gray-600 text-center mb-2">Apakah Anda yakin ingin menghapus user ini?</p>
             <p class="text-gray-800 font-semibold text-center">{{ deleteConfirm.userName }}</p>
           </div>
           
           <!-- Actions -->
           <div class="px-6 pb-6 flex gap-3">
             <button @click="deleteConfirm.show = false" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium transition">
               Batal
             </button>
             <button @click="confirmDelete" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-lg hover:from-red-600 hover:to-pink-600 font-medium transition shadow-lg">
               Ya, Hapus
             </button>
           </div>
         </div>
       </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from "vue";

const users = ref([]);
const loading = ref(true);
const showModal = ref(false);
const submitting = ref(false);

const form = reactive({
    name: '',
    phone_number: '',
    role: 'cashier',
    password: ''
});

const toast = reactive({
    show: false,
    message: '',
    type: 'success'
});

const deleteConfirm = reactive({
    show: false,
    userId: null,
    userName: ''
});

function showToast(message, type = 'success') {
    toast.message = message;
    toast.type = type;
    toast.show = true;
    setTimeout(() => {
        toast.show = false;
    }, 3000);
}

async function fetchUsers() {
    loading.value = true;
    try {
        const res = await fetch("/api/Beauty_Salon/Users");
        const data = await res.json();
        if (data.success) {
            users.value = data.data;
        }
    } catch(e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function createUser() {
    submitting.value = true;
    try {
        const res = await fetch("/api/Beauty_Salon/Users/create", {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(form)
        });
        const data = await res.json();
        if (res.ok && data.success) {
            showModal.value = false;
            // Reset form
            form.name = '';
            form.phone_number = '';
            form.role = 'cashier';
            form.password = '';
            
            showToast('User berhasil dibuat', 'success');
            fetchUsers(); // Refresh list
        } else {
            showToast(data.message || 'Gagal membuat user', 'error');
        }
    } catch(e) {
        showToast('Terjadi kesalahan jaringan', 'error');
    } finally {
        submitting.value = false;
    }
}

async function deleteUser(id) {
    // Find user name for display
    const user = users.value.find(u => u.id === id);
    deleteConfirm.userId = id;
    deleteConfirm.userName = user ? user.name : 'User';
    deleteConfirm.show = true;
}

async function confirmDelete() {
    const id = deleteConfirm.userId;
    deleteConfirm.show = false;
    
    try {
        const res = await fetch(`/api/Beauty_Salon/Users/delete/${id}`, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            showToast('User berhasil dihapus', 'success');
            fetchUsers();
        } else {
            showToast(data.message || 'Gagal menghapus', 'error');
        }
    } catch(e) {
         showToast('Gagal menghapus user', 'error');
    }
}

onMounted(() => {
    fetchUsers();
});
</script>
