<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import twemoji from 'twemoji';
import { getCaseColor, getCaseLabel } from "../stores/chatStore.js";
import { displayConversationName } from "../utils/phoneMask.js";

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  filteredConversations: { type: Array, default: () => [] },
  activeChatId: { type: [Number, String], default: null },
  authId: { type: String, default: "" },
  currentUserRole: { type: String, default: "crew" },
  isLoadingConversations: { type: Boolean, default: false },
  isLoadingMoreConversations: { type: Boolean, default: false },
  isSearching: { type: Boolean, default: false },
  hasMoreConversations: { type: Boolean, default: true },
  isReconnecting: { type: Boolean, default: false },
  isConnected: { type: Boolean, default: true },
  connectionError: { type: String, default: "" },
  showMobileChat: { type: Boolean, default: false },
  totalUnreadCount: { type: Number, default: 0 },
});

const CASE_FILTER_IDS = [2, 3, 4];

const emit = defineEmits([
  "select-chat",
  "logout",
  "open-settings",
  "update:searchQuery",
  "update:conversationFilter",
  "load-more-conversations",
]);

// Local state
const searchQuery = ref("");
const conversationFilter = ref("all");
const conversationListContainer = ref(null);

// Methods
const openCaseCounts = computed(() => {
  const counts = { 2: 0, 3: 0, 4: 0 };
  for (const chat of props.conversations) {
    if (!chat.cases) continue;
    const openCaseIds = new Set();
    for (const cse of chat.cases) {
      if (cse.case > 0 && (cse.status || "open") !== "closed") {
        openCaseIds.add(parseInt(cse.case));
      }
    }
    for (const caseId of openCaseIds) {
      if (counts[caseId] !== undefined) counts[caseId]++;
    }
  }
  return counts;
});

const toggleCaseFilter = (caseId) => {
  const filterKey = `case-${caseId}`;
  updateFilter(conversationFilter.value === filterKey ? "all" : filterKey);
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

// Parse WhatsApp formatting: *bold* _italic_ ~strikethrough~ ```monospace```
const parseWhatsAppFormatting = (text) => {
  if (!text) return '';
  
  // Bold: *text*
  text = text.replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
  
  // Italic: _text_
  text = text.replace(/\_([^_]+)\_/g, '<em>$1</em>');
  
  // Strikethrough: ~text~
  text = text.replace(/\~([^~]+)\~/g, '<del>$1</del>');
  
  // Monospace: ```text```
  text = text.replace(/\`\`\`([^`]+)\`\`\`/g, '<code>$1</code>');
  
  return text;
};

// Infinite scroll handler for conversations
const handleScroll = () => {
  if (!conversationListContainer.value || props.isLoadingMoreConversations) return;
  
  const container = conversationListContainer.value;
  const scrollTop = container.scrollTop;
  const scrollHeight = container.scrollHeight;
  const clientHeight = container.clientHeight;
  const scrollBottom = scrollHeight - scrollTop - clientHeight;
  
  const threshold = 100; // Trigger when 100px from bottom
  
  // Check if scrolled to near bottom AND has more conversations
  if (scrollBottom <= threshold && props.hasMoreConversations) {
    emit('load-more-conversations');
  }
};

// Lifecycle
onMounted(() => {
  if (conversationListContainer.value) {
    conversationListContainer.value.addEventListener('scroll', handleScroll);
  }
});

onUnmounted(() => {
  if (conversationListContainer.value) {
    conversationListContainer.value.removeEventListener('scroll', handleScroll);
  }
});

// Parse emoji with Twemoji for consistent rendering
const parseEmoji = (text) => {
  if (!text) return '';
  // Escape HTML first (but preserve our formatting tags)
  let escaped = text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
  
  // Apply WhatsApp formatting
  escaped = parseWhatsAppFormatting(escaped);
  
  // Then parse emoji
  let parsed = twemoji.parse(escaped, {
    folder: 'svg',
    ext: '.svg',
    base: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/',
    className: 'twemoji-inline'
  });

  // Replace i- and o- with SVG Direction Icons (Only at start of string)
  // i- : Inbound (Arrow Down Left - Masuk)
  parsed = parsed.replace(/^i- /, `<svg class="inline-block w-2.5 h-2.5 mr-0.5 text-emerald-500 align-middle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19.5 4.5l-15 15m0 0h11.25m-11.25 0V8.25" /></svg>`); // Panah Masuk (↙️)
  
  // o- : Outbound (Arrow Up Right - Keluar)
  parsed = parsed.replace(/^o- /, `<svg class="inline-block w-2.5 h-2.5 mr-0.5 text-gray-400 align-middle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" /></svg>`); // Panah Keluar (↗️)

  return parsed;
};
</script>

<template>
  <!-- Sidebar -->
  <aside
    data-conversation-list
    class="flex flex-col border-r border-[var(--wa-border)] bg-[var(--wa-bg-panel)] transition-all duration-300 absolute md:static z-0 h-full w-full md:w-96"
  >
    <!-- Search Header -->
    <div class="px-4 pt-4 pb-1 bg-[var(--wa-bg-panel)] sticky top-0 z-10 transition-colors duration-300">
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
            class="block w-full pl-10 pr-10 py-2.5 border border-[var(--wa-border)] rounded-lg leading-5 bg-[var(--wa-bg-secondary)] text-[var(--wa-text-primary)] placeholder-[var(--wa-text-tertiary)] focus:outline-none focus:bg-[var(--wa-bg-secondary)] focus:border-[var(--wa-accent-green)] focus:ring-0 sm:text-sm transition-all"
          />
          <!-- Searching Spinner -->
          <div v-if="isSearching" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
            <div class="w-4 h-4 border-2 border-[var(--wa-text-tertiary)] border-t-[var(--wa-accent-green)] rounded-full animate-spin"></div>
          </div>
          <!-- Clear Search Button -->
          <div v-else-if="searchQuery" class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" @click="clearSearch">
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

    <!-- Filter Tabs -->
    <div class="px-4 py-3 bg-[var(--wa-bg-panel)]">
      <div class="flex flex-wrap gap-2 items-center">
        <!-- All Tab -->
        <button
          @click="updateFilter('all')"
          class="px-3 py-1.5 text-sm font-medium rounded-full transition-all border"
          :class="conversationFilter === 'all' ? 'bg-[var(--wa-filter-active-bg)] text-[var(--wa-filter-active-text)] border-transparent' : 'bg-[var(--wa-filter-inactive-bg)] text-[var(--wa-filter-inactive-text)] border-[var(--wa-filter-inactive-border)] hover:bg-[var(--wa-hover)]'"
        >All</button>

        <!-- Unread Tab -->
        <button
          @click="updateFilter('unread')"
          class="px-3 py-1.5 text-sm font-medium rounded-full transition-all flex items-center gap-1.5 border"
          :class="conversationFilter === 'unread' ? 'bg-[var(--wa-filter-active-bg)] text-[var(--wa-filter-active-text)] border-transparent' : 'bg-[var(--wa-filter-inactive-bg)] text-[var(--wa-filter-inactive-text)] border-[var(--wa-filter-inactive-border)] hover:bg-[var(--wa-hover)]'"
        >
          <span>Unread</span>
          <span v-if="totalUnreadCount > 0" class="text-xs font-bold min-w-[20px] h-5 px-1.5 rounded-full flex items-center justify-center" :class="conversationFilter === 'unread' ? 'bg-black/10 text-[var(--wa-filter-active-text)]' : 'bg-[var(--wa-accent-green)] text-white'">{{ totalUnreadCount }}</span>
        </button>

        <!-- Case count badges (per case type) -->
        <button
          v-for="caseId in CASE_FILTER_IDS"
          :key="'case-filter-' + caseId"
          v-show="openCaseCounts[caseId] > 0"
          @click="toggleCaseFilter(caseId)"
          :title="getCaseLabel(caseId)"
          class="px-3 py-1.5 text-sm font-medium rounded-full transition-all flex items-center border"
          :class="conversationFilter === 'case-' + caseId
            ? 'bg-[var(--wa-filter-active-bg)] text-[var(--wa-filter-active-text)] border-transparent'
            : 'bg-[var(--wa-filter-inactive-bg)] text-[var(--wa-filter-inactive-text)] border-[var(--wa-filter-inactive-border)] hover:bg-[var(--wa-hover)]'"
        >
          <span
            class="text-xs font-bold min-w-[20px] h-5 px-1.5 rounded-full flex items-center justify-center"
            :class="[
              getCaseColor(caseId),
              caseId === 2 ? 'text-black' : 'text-white',
            ]"
          >{{ openCaseCounts[caseId] }}</span>
        </button>
      </div>
    </div>

    <!-- Reconnecting banner removed - status now shown in footer -->

    <!-- Conversation List -->
    <div ref="conversationListContainer" class="flex-1 overflow-y-auto custom-scrollbar">
      <!-- Skeleton Loading -->
      <div v-if="isLoadingConversations && conversations.length === 0" class="space-y-0">
        <div v-for="n in 8" :key="'skeleton-' + n" class="p-3 flex items-start gap-3 border-b border-[var(--wa-divider)]">
          <!-- Skeleton Kolom Kiri -->
          <div class="flex flex-col items-center gap-1.5 pt-1 min-w-[42px]">
            <div class="h-5 w-8 skeleton-shimmer rounded"></div>
            <div class="flex gap-1">
              <div class="w-3 h-3 skeleton-shimmer rounded-full"></div>
              <div class="w-3 h-3 skeleton-shimmer rounded-full"></div>
            </div>
          </div>
          <!-- Skeleton Kolom Kanan -->
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
        class="p-3 flex items-start gap-3 cursor-pointer transition-colors duration-150 border-b border-[var(--wa-divider)] hover:bg-[var(--wa-hover)]"
        :class="{ 'bg-[var(--wa-active)]': activeChatId === chat.id }"
      >
        <!-- Kolom Kiri: Kode Cabang + Cust ID + Case Badges -->
        <div class="flex flex-col items-center justify-start gap-1.5 pt-1 min-w-[42px] flex-shrink-0">
          <!-- Kode Cabang (tanpa []) -->
          <div v-if="chat.kode_cabang" class="text-xs font-bold px-2 py-0.5 rounded" :style="chat.kode_cabang === '00'
            ? { color: 'var(--wa-cabang-hq-text)', backgroundColor: 'var(--wa-cabang-hq-bg)' }
            : { color: 'var(--wa-cabang-text)', backgroundColor: 'var(--wa-cabang-bg)' }">
            {{ chat.kode_cabang }}
          </div>
          
          <!-- Case Badges (lingkaran warna) -->
          <div v-if="chat.cases && chat.cases.some((c) => c.case > 0 && c.case !== 1 && (c.status || 'open') !== 'closed')" class="flex flex-wrap gap-1 justify-center">
            <template v-for="(cse, idx) in chat.cases" :key="idx">
              <div v-if="cse.case > 0 && cse.case !== 1 && (cse.status || 'open') !== 'closed'" class="w-3 h-3 rounded-full ring-1 ring-black/20" :class="getCaseColor(cse.case)" :title="'Case: ' + cse.case"></div>
            </template>
          </div>
        </div>

        <!-- Kolom Kanan: Nama + Message -->
        <div class="flex-1 min-w-0">
          <div class="flex justify-between items-baseline mb-0.5 gap-2">
            <h3
              class="text-[17px] leading-snug truncate max-w-[240px] font-medium uppercase"
              style="color: var(--wa-conv-name);"
              :title="displayConversationName(chat)"
            >
              {{ displayConversationName(chat) }}
            </h3>
            <span
              class="text-xs leading-none flex-shrink-0 font-normal"
              :style="{ color: chat.unread > 0 ? 'var(--wa-conv-unread-time)' : 'var(--wa-conv-time)' }"
            >{{ chat.lastTime }}</span>
          </div>

          <div class="flex justify-between items-center gap-2">
            <p
              class="text-[14px] leading-snug truncate w-64 font-normal"
              :style="{
                color: chat.unread > 0 ? 'var(--wa-conv-unread-preview)' : 'var(--wa-conv-preview)',
                fontWeight: chat.unread > 0 ? '500' : '400',
              }"
              v-html="parseEmoji(chat.lastMessage)"
            ></p>
            <span v-if="chat.unread > 0" class="bg-[var(--wa-accent-green)] text-white text-[11px] font-semibold px-2 py-0.5 rounded-full min-w-[20px] text-center">{{ chat.unread }}</span>
          </div>
        </div>
      </div>
      
      <!-- Loading More Indicator -->
      <div v-if="isLoadingMoreConversations" class="p-4 flex justify-center">
        <div class="flex items-center gap-2 text-[var(--wa-text-secondary)]">
          <div class="w-4 h-4 border-2 border-[var(--wa-accent-green)] border-t-transparent rounded-full animate-spin"></div>
          <span class="text-xs">Loading more...</span>
        </div>
      </div>
    </div>

    <!-- User Profile -->
    <div class="p-4 border-t border-[var(--wa-border)] bg-[var(--wa-bg-panel)] flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <div>
          <div class="text-sm font-medium text-[var(--wa-text-primary)]">AgentID <span class="text-[var(--wa-accent-blue)] font-mono">#{{ authId }}</span></div>
          <div class="text-xs flex items-center gap-1.5" :class="isConnected ? 'text-[var(--wa-accent-green)]' : (isReconnecting ? 'text-yellow-400' : 'text-red-400')">
            <!-- Status indicator -->
            <span v-if="isReconnecting && !isConnected" class="w-3 h-3 border-2 border-yellow-400/30 border-t-yellow-400 rounded-full animate-spin"></span>
            <span v-else class="w-1.5 h-1.5 rounded-full" :class="isConnected ? 'bg-[var(--wa-accent-green)]' : 'bg-red-400'"></span>
            <!-- Status text -->
            <span v-if="isConnected">Online</span>
            <span v-else-if="isReconnecting">{{ connectionError || 'Reconnecting...' }}</span>
            <span v-else>Offline</span>
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
