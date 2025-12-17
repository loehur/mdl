<template>
  <div class="min-h-screen flex bg-gray-50 font-sans relative overflow-x-hidden">
    
    <!-- Mobile Drawer Overlay & Sidebar (Teleported) -->
    <Teleport to="body">
      <div v-if="isOpen" class="fixed inset-0 bg-black/60 z-[9998] md:hidden transition-opacity" @click="isOpen = false"></div>

      <aside
        class="fixed inset-y-0 left-0 w-72 bg-slate-900 text-white z-[9999] transform transition-transform duration-300 ease-in-out shadow-2xl md:hidden flex flex-col"
        :class="isOpen ? 'translate-x-0' : '-translate-x-full'"
      >
        <div class="h-16 flex items-center justify-between px-6 border-b border-white/5 bg-slate-950/30">
          <span class="font-bold text-xl bg-gradient-to-r from-pink-400 to-purple-400 bg-clip-text text-transparent">Beauty Salon</span>
          <button @click="isOpen = false" class="text-slate-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
          <router-link to="/order" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-pink-600 text-white shadow-lg"
            :class="$route.path === '/order' ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
             Order
          </router-link>

          <router-link to="/worksteps" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-pink-600 text-white shadow-lg"
            :class="$route.path.includes('/worksteps') ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
             Langkah Kerja
          </router-link>

          <router-link to="/products" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-pink-600 text-white shadow-lg"
            :class="$route.path.includes('/products') ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
             Produk
          </router-link>

          <router-link to="/customers" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-pink-600 text-white shadow-lg"
            :class="$route.path.includes('/customers') ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
             Pelanggan
          </router-link>

          <router-link to="/therapists" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-pink-600 text-white shadow-lg"
            :class="$route.path.includes('/therapists') ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
             Terapis
          </router-link>

          <router-link to="/performance" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-pink-600 text-white shadow-lg"
            :class="$route.path.includes('/performance') ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 012-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
             Kinerja
          </router-link>

          <router-link to="/cash-flow" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-pink-600 text-white shadow-lg"
            :class="$route.path.includes('/cash-flow') ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
             Laporan Kas
          </router-link>

          <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 px-2 mt-4 ml-2">Arsip</div>

          <router-link to="/archive/orders" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-pink-600 text-white shadow-lg"
            :class="$route.path.includes('/archive/orders') ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
             Order Selesai
          </router-link>
          
          <router-link v-if="isAdmin()" to="/users" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-pink-600 text-white shadow-lg"
            :class="$route.path.includes('/users') ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
             Users
          </router-link>

          <router-link v-if="isAdmin()" to="/settings" @click="isOpen = false"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
            active-class="bg-pink-600 text-white shadow-lg"
            :class="$route.path.includes('/settings') ? '' : 'text-slate-300 hover:bg-white/5'"
          >
             <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
             Settings
          </router-link>


        </nav>
      </aside>
    </Teleport>

    <!-- Desktop Sidebar -->
    <aside class="fixed inset-y-0 left-0 w-72 bg-slate-900 text-white hidden md:flex flex-col shadow-xl z-20">
      <div class="h-16 flex items-center px-6 border-b border-white/5 bg-slate-950/30 flex-shrink-0">
        <div class="font-bold text-xl tracking-wide bg-gradient-to-r from-pink-400 to-purple-400 bg-clip-text text-transparent">
          Beauty Salon
        </div>
      </div>

      <nav class="flex-1 p-4 space-y-1 overflow-y-auto custom-scrollbar">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 px-2">Menu</div>
        
        <router-link
          to="/order"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-pink-600 text-white shadow-lg shadow-pink-900/50 font-medium"
          :class="$route.path === '/order' ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
          </svg>
          <span>Order</span>
        </router-link>

        <router-link
          to="/worksteps"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-pink-600 text-white shadow-lg shadow-pink-900/50 font-medium"
          :class="$route.path.includes('/worksteps') ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
          </svg>
          <span>Langkah Kerja</span>
        </router-link>

        <router-link
          to="/products"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-pink-600 text-white shadow-lg shadow-pink-900/50 font-medium"
          :class="$route.path.includes('/products') ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
          </svg>
          <span>Produk</span>
        </router-link>

        <router-link
          to="/customers"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-pink-600 text-white shadow-lg shadow-pink-900/50 font-medium"
          :class="$route.path.includes('/customers') ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
          </svg>
          <span>Pelanggan</span>
        </router-link>

        <router-link
          to="/therapists"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-pink-600 text-white shadow-lg shadow-pink-900/50 font-medium"
          :class="$route.path.includes('/therapists') ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <span>Terapis</span>
        </router-link>

        <router-link
          to="/performance"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-pink-600 text-white shadow-lg shadow-pink-900/50 font-medium"
          :class="$route.path.includes('/performance') ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 012-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
          </svg>
          <span>Kinerja</span>
        </router-link>

        <router-link
          to="/cash-flow"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-pink-600 text-white shadow-lg shadow-pink-900/50 font-medium"
          :class="$route.path.includes('/cash-flow') ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <span>Laporan Kas</span>
        </router-link>

        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 px-2 mt-6">Arsip</div>

        <router-link
          to="/archive/orders"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-pink-600 text-white shadow-lg shadow-pink-900/50 font-medium"
          :class="$route.path.includes('/archive/orders') ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
          </svg>
          <span>Order Selesai</span>
        </router-link>

        <router-link
          v-if="isAdmin()"
          to="/users"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-pink-600 text-white shadow-lg shadow-pink-900/50 font-medium"
          :class="$route.path.includes('/users') ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
          </svg>
          <span>Users</span>
        </router-link>

        <router-link
          v-if="isAdmin()"
          to="/settings"
          class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
          active-class="bg-pink-600 text-white shadow-lg shadow-pink-900/50 font-medium"
          :class="$route.path.includes('/settings') ? '' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        >
          <svg class="w-5 h-5 transition-transform group-hover:scale-110 duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
             <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
          </svg>
          <span>Settings</span>
        </router-link>
      </nav>


    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 bg-gray-50/50 md:ml-72 transition-all duration-300 h-screen">
      <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-10">
        <button @click="isOpen = true" class="md:hidden text-gray-500 hover:text-gray-700">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>

        <h1 class="text-xl font-bold text-gray-800 hidden md:block">
          {{ getPageTitle() }}
        </h1>
        <h1 class="text-lg font-bold text-gray-800 md:hidden">Salon</h1>

        <!-- User Dropdown -->
        <div class="relative">
          <button @click="showUserMenu = !showUserMenu" class="flex items-center gap-3 hover:bg-gray-50 rounded-xl px-3 py-2 transition">
            <div class="flex flex-col items-end hidden sm:flex">
              <span class="text-sm font-semibold text-gray-800">{{ userName }}</span>
              <span class="text-xs text-gray-500 capitalize">{{ userRole }}</span>
            </div>
            <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-pink-500 to-purple-500 p-0.5 shadow-md">
               <div class="w-full h-full rounded-full bg-white flex items-center justify-center text-pink-600 font-bold text-lg">
                  {{ userName.charAt(0) }}
               </div>
            </div>
            <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </button>

          <!-- Dropdown Menu -->
          <Teleport to="body">
            <div v-if="showUserMenu" @click="showUserMenu = false" class="fixed inset-0 z-[9997]"></div>
            <div v-if="showUserMenu" class="fixed top-16 right-6 z-[9999] bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden w-56">
              <!-- User Info -->
              <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-pink-50 to-fuchsia-50">
                <p class="font-semibold text-gray-800">{{ userName }}</p>
                <p class="text-xs text-gray-500 capitalize">{{ userRole }}</p>
              </div>
              
              <!-- Menu Items -->
              <div class="py-2">
                <button @click="logout" class="w-full px-4 py-2.5 text-left hover:bg-red-50 transition flex items-center gap-3 text-red-600 font-medium">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                  <span>Keluar</span>
                </button>
              </div>
            </div>
          </Teleport>
        </div>
      </header>

      <main class="flex-1 p-6 overflow-y-auto scroll-smooth">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
             <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRoute, useRouter } from "vue-router";

const isOpen = ref(false);
const showUserMenu = ref(false);
const route = useRoute();
const router = useRouter();
const userName = ref("Guest");
const userRole = ref("customer");

onMounted(() => {
  try {
    const raw = localStorage.getItem("salon_user");
    if (raw) {
      const u = JSON.parse(raw);
      userName.value = u.name || "Guest";
      userRole.value = u.role || "customer";
    }
  } catch {}
});

watch(() => route.fullPath, () => {
    isOpen.value = false;
});

function logout() {
    showUserMenu.value = false;
    localStorage.removeItem("salon_user");
    router.push("/login");
}

function getPageTitle() {
  if (route.path === '/order') return 'Buat Pesanan';
  if (route.path.includes('/worksteps')) return 'Langkah Kerja';
  if (route.path.includes('/products')) return 'Produk Layanan';
  if (route.path.includes('/customers')) return 'Pelanggan';
  if (route.path.includes('/therapists')) return 'Data Terapis';
  if (route.path.includes('/performance')) return 'Kinerja Terapis';
  if (route.path.includes('/cash-flow')) return 'Laporan Kas';
  if (route.path.includes('/archive/orders')) return 'Arsip Order Selesai';
  if (route.path.includes('/users')) return 'Manajemen Pengguna';
  if (route.path.includes('/settings')) return 'Pengaturan Salon';
  return 'Salon Area';
}

// Check if user is admin (for RBAC)
const isAdmin = () => userRole.value === 'admin';
</script>

<style>
/* Custom Scrollbar Global for UserLayout area */
.custom-scrollbar::-webkit-scrollbar {
  width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent; 
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #334155; 
  border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #475569; 
}

/* Main content scrollbar */
main::-webkit-scrollbar {
  width: 8px;
}
main::-webkit-scrollbar-track {
  background: transparent;
}
main::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
main::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
