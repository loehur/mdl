<script setup>
import { ref, computed } from "vue";

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  filteredConversations: { type: Array, default: () => [] },
  activeChatId: { type: [Number, String], default: null },
  authId: { type: String, default: "" },
  currentUserRole: { type: String, default: "crew" },
  isLoadingConversations: { type: Boolean, default: false },
  isReconnecting: { type: Boolean, default: false },
  isConnected: { type: Boolean, default: true },
  connectionError: { type: String, default: "" },
  showMobileChat: { type: Boolean, default: false },
  totalUnreadCount: { type: Number, default: 0 },
  totalOpenCasesCount: { type: Number, default: 0 },
});

const emit = defineEmits([
  "select-chat",
  "logout",
  "open-settings",
  "update:searchQuery",
  "update:conversationFilter",
]);

// Local state
const searchQuery = ref("");
const conversationFilter = ref("all");

// Methods
const getCaseColor = (caseId) => {
  switch (parseInt(caseId)) {
    case 1: return "bg-blue-500";
    case 2: return "bg-yellow-500";
    case 3: return "bg-red-500";
    case 4: return "bg-purple-500";
    default: return "bg-gray-500";
  }
};

const selectChat = (id) => {
  emit("select-chat", id);
};

const logout = () => {
  emit("logout");
};

const openSettings = () => {
  emit("open-settings");
};

// Watch for filter/search changes to emit to parent
const updateFilter = (val) => {
  conversationFilter.value = val;
  emit("update:conversationFilter", val);
};

const clearSearch = () => {
  searchQuery.value = "";
  emit("update:searchQuery", "");
};

// Emit search on input
const onSearchInput = (e) => {
  searchQuery.value = e.target.value;
  emit("update:searchQuery", e.target.value);
};
</script>

<template>
  <!-- Sidebar -->
  <aside
    class="flex flex-col border-r border-[var(--wa-border)] bg-[var(--wa-bg-panel)] transition-all duration-300 absolute md:static z-0 h-full w-full md:w-96"
  >
    <!-- Search Header -->
    <div class="px-4 pt-4 pb-1 bg-[var(--wa-bg-panel)] backdrop-blur-md sticky top-0 z-10 transition-colors duration-300">
      <div class="flex items-center gap-2">
        <div class="relative group flex-1">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--wa-icon-default)] group-focus-within:text-[var(--wa-accent-green)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            :value="searchQuery"
            @input="onSearchInput"
            type="text"
            placeholder="Search or start new chat"
            class="block w-full pl-10 pr-10 py-2.5 border border-[var(--wa-border)] rounded-lg leading-5 bg-[var(--wa-bg-tertiary)] text-[var(--wa-text-primary)] placeholder-[var(--wa-text-tertiary)] focus:outline-none focus:bg-[var(--wa-bg-tertiary)] focus:border-[var(--wa-accent-green)] focus:ring-0 sm:text-sm transition-all"
          />
          <div v-if="searchQuery" class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" @click="clearSearch">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 hover:text-slate-300 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
        </div>

        <!-- Settings Button -->
        <button @click="openSettings" class="p-2.5 text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)] hover:bg-[var(--wa-hover)] rounded-lg transition-colors" title="Settings">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Filter Tabs (hidden for driver role) -->
    <div v-if="currentUserRole !== 'driver'" class="px-4 py-3 bg-[var(--wa-bg-panel)]">
      <div class="flex gap-2">
        <!-- All Tab -->
        <button
          @click="updateFilter('all')"
          class="px-3 py-1.5 text-sm font-medium rounded-full transition-all border"
          :class="conversationFilter === 'all' ? 'bg-[var(--wa-filter-active-bg)] text-[var(--wa-filter-active-text)] border-transparent' : 'bg-[var(--wa-filter-inactive-bg)] text-[var(--wa-filter-inactive-text)] border-[var(--wa-filter-inactive-border)] hover:bg-[var(--wa-hover)]'"
        >Semua</button>

        <!-- Unread Tab -->
        <button
          @click="updateFilter('unread')"
          class="px-3 py-1.5 text-sm font-medium rounded-full transition-all flex items-center gap-1.5 border"
          :class="conversationFilter === 'unread' ? 'bg-[var(--wa-filter-active-bg)] text-[var(--wa-filter-active-text)] border-transparent' : 'bg-[var(--wa-filter-inactive-bg)] text-[var(--wa-filter-inactive-text)] border-[var(--wa-filter-inactive-border)] hover:bg-[var(--wa-hover)]'"
        >
          <span>Belum dibaca</span>
          <span v-if="totalUnreadCount > 0" class="text-xs font-bold min-w-[20px] h-5 px-1.5 rounded-full flex items-center justify-center" :class="conversationFilter === 'unread' ? 'bg-black/10 text-[var(--wa-filter-active-text)]' : 'bg-[var(--wa-accent-green)] text-black'">{{ totalUnreadCount }}</span>
        </button>

        <!-- Cases Tab -->
        <button
          @click="updateFilter('cases')"
          class="px-3 py-1.5 text-sm font-medium rounded-full transition-all flex items-center gap-1.5 border"
          :class="conversationFilter === 'cases' ? 'bg-[var(--wa-filter-active-bg)] text-[var(--wa-filter-active-text)] border-transparent' : 'bg-[var(--wa-filter-inactive-bg)] text-[var(--wa-filter-inactive-text)] border-[var(--wa-filter-inactive-border)] hover:bg-[var(--wa-hover)]'"
        >
          <span>Cases</span>
          <span v-if="totalOpenCasesCount > 0" class="text-xs font-bold min-w-[20px] h-5 px-1.5 rounded-full flex items-center justify-center" :class="conversationFilter === 'cases' ? 'bg-black/10 text-[var(--wa-filter-active-text)]' : 'bg-red-500 text-white'">{{ totalOpenCasesCount }}</span>
        </button>
      </div>
    </div>

    <!-- Reconnecting Banner -->
    <div v-if="isReconnecting && !isConnected" class="px-4 py-2 bg-yellow-500/10 border-b border-yellow-500/20">
      <div class="flex items-center gap-2 text-yellow-400 text-sm">
        <div class="w-4 h-4 border-2 border-yellow-400/30 border-t-yellow-400 rounded-full animate-spin"></div>
        <span>{{ connectionError || "Reconnecting..." }}</span>
      </div>
    </div>

    <!-- Conversation List -->
    <div class="flex-1 overflow-y-auto custom-scrollbar">
      <!-- Skeleton Loading -->
      <div v-if="isLoadingConversations && conversations.length === 0" class="space-y-0">
        <div v-for="n in 8" :key="'skeleton-' + n" class="p-3 flex items-center gap-3 border-b border-[var(--wa-divider)]">
          <div class="w-12 h-12 rounded-full skeleton-shimmer"></div>
          <div class="flex-1 space-y-2">
            <div class="flex justify-between items-center">
              <div class="h-4 skeleton-shimmer rounded w-32"></div>
              <div class="h-3 skeleton-shimmer rounded w-12"></div>
            </div>
            <div class="h-3 skeleton-shimmer rounded w-48"></div>
          </div>
        </div>
      </div>

      <!-- Actual Conversations -->
      <div
        v-for="chat in filteredConversations"
        :key="chat.id"
        @click="selectChat(chat.id)"
        class="p-3 flex items-center gap-3 cursor-pointer transition-colors duration-150 border-b border-[var(--wa-divider)] hover:bg-[var(--wa-hover)]"
        :class="{ 'bg-[var(--wa-active)]': activeChatId === chat.id }"
      >
        <div class="relative">
          <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold border-0" :style="{ backgroundColor: chat.color }">{{ chat.initials }}</div>
          <span v-if="chat.status === 'online'" class="absolute bottom-0 right-0 w-3 h-3 bg-[var(--wa-accent-green)] border-2 border-[var(--wa-bg-panel)] rounded-full"></span>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex justify-between items-baseline mb-1 gap-2">
            <h3 class="font-normal text-[16px] truncate text-[var(--wa-text-primary)] max-w-[240px] uppercase" :title="chat.name">
              <span v-if="chat.kode_cabang" class="font-mono text-xs mr-1" :class="chat.kode_cabang === '00' ? 'text-pink-400' : 'text-[var(--wa-accent-blue)]'">[{{ chat.kode_cabang }}]</span>
              {{ chat.name }}
            </h3>
            <span class="text-xs text-[var(--wa-text-tertiary)] flex-shrink-0">{{ chat.lastTime }}</span>
          </div>

          <!-- Case Badges -->
          <div v-if="chat.cases && chat.cases.some((c) => c.case > 0 && (c.status || 'open') !== 'closed')" class="flex flex-wrap gap-1.5 mb-1.5">
            <template v-for="(cse, idx) in chat.cases" :key="idx">
              <div v-if="cse.case > 0 && (cse.status || 'open') !== 'closed'" class="w-3.5 h-3.5 rounded-full ring-1 ring-black/20" :class="getCaseColor(cse.case)" :title="'Case: ' + cse.case"></div>
            </template>
          </div>
          <div class="flex justify-between items-center">
            <p class="text-sm text-[var(--wa-text-secondary)] truncate w-64" :class="{ 'font-normal text-[var(--wa-text-primary)]': chat.unread > 0 }">{{ chat.lastMessage }}</p>
            <span v-if="chat.unread > 0" class="bg-[var(--wa-accent-green)] text-black text-[11px] font-semibold px-2 py-0.5 rounded-full min-w-[20px] text-center">{{ chat.unread }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- User Profile -->
    <div class="p-4 border-t border-[var(--wa-border)] bg-[var(--wa-bg-panel)] flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-[var(--wa-accent-green)] flex items-center justify-center text-black font-bold border-0">A</div>
        <div>
          <div class="text-sm font-medium text-[var(--wa-text-primary)]">MDL Agent <span class="text-[var(--wa-accent-blue)] font-mono">#{{ authId }}</span></div>
          <div class="text-xs text-[var(--wa-accent-green)] flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-[var(--wa-accent-green)]"></span>Online
          </div>
        </div>
      </div>
      <button @click="logout" title="Logout" class="p-2 text-[var(--wa-icon-default)] hover:text-red-400 hover:bg-[var(--wa-hover)] rounded-lg transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
      </button>
    </div>
  </aside>
</template>
