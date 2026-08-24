<script setup>
import { ref, computed, onMounted, nextTick, watch, onUnmounted } from "vue";
import { App } from "@capacitor/app";
import { Camera, CameraResultType, CameraSource } from "@capacitor/camera";
import LoginModal from "./components/LoginModal.vue";
import ChatPage from "./components/ChatPage.vue";
import ConversationList from "./components/ConversationList.vue";
import { getDeviceId } from "./utils/deviceId.js";
import { classifyWsClose1008, wsClose1008Message } from "./utils/wsCloseReason.js";

/** Debounce reconnect when visibility + Capacitor resume both fire */
let resumeReconnectTimer = null;
const scheduleResumeReconnect = (delayMs = 400) => {
  if (resumeReconnectTimer) clearTimeout(resumeReconnectTimer);
  resumeReconnectTimer = setTimeout(() => {
    resumeReconnectTimer = null;
    if (!authId.value) {
      isReconnecting.value = false;
      showLoginPrompt.value = true;
      return;
    }
    if (socket.value && socket.value.readyState === WebSocket.OPEN) {
      isReconnecting.value = false;
      return;
    }
    connectWebSocket();
  }, delayMs);
};

// Import stores
import {
  // API
  API_BASE,
  loadQuickRepliesFromLaundry,
  // Auth
  authId, currentUserRole, userName, senderCode,
  isConnected, isConnecting, connectionError,
  showLoginPrompt, duplicateWarning,
  wasConnected, isReconnecting, reconnectAttempts, maxReconnectAttempts, reconnectDelay,
  resumeTimestamp, lastDisconnectTime,
  duplicateRetryAttempts, maxDuplicateRetries, duplicateRetryDelay,
  // Conversations
  conversations, activeChatId, isLoadingConversations, isLoadingMoreConversations, isSearching, hasMoreConversations, conversationsOffset,
  searchQuery, conversationFilter, pendingTargetPhone, autoOpenChatOnIncoming,
  // Message Input
  messageInput, chatDrafts, replyToMessage, chatContainer, messageTextarea,
  // Image Upload
  selectedImage, imagePreview, showImagePreview, isUploadingImage, imageCaption, fileInput,
  // WebSocket
  socket, refreshInterval, eventSource,
  // UI - Mobile & Gestures
  windowWidth, showMobileChat, isEnteringChat,
  touchStartX, touchStartY, touchOffset, isDragging, minSwipeDistance, showExitToast,
  // UI - Menus & Modals
  showChatMenu, showResolveMenu, showSettingsModal, showCustomerPanel, showAddLokasiModal, showDeleteLokasiModal, showDeliveryRequestModal, showEditPermintaanModal, showCreatePermintaanModal, showSendTagihanModal, showSendStatusModal, showSendQrisModal, showCancelDeliveryModal, showCrewSendModal,
  showImageLightbox, lightboxImageUrl, showQuickReplies,
  showInternalBrowser, internalBrowserUrl, isInternalBrowserEntering, isInternalBrowserExiting, isInternalBrowserLoading,
  // Loading States
  isMarkingAsDone, isFollowUp,
  isReopeningConversation, isRefreshingChat, isLoadingQuickReplies,
  // Settings
  fontSize, theme, notificationSoundEnabled, notificationAudio,
  // Quick Reply
  quickReplies, quickReplySearchQuery,
  // Title Blink
  originalTitle, titleBlinkInterval, isTitleRed,
  // Helpers
  getAvatarColor, getCaseColor, getCaseLabel, isCaseOpen, isNativeApp,
  enforceCaseFourExclusivity, mergeOpenCaseLocal, sanitizeActiveCaseIds, CASE_FOLLOW_UP,
  // Computed
  activeConversation, filteredConversations, totalUnreadCount, totalOpenCasesCount,
  // Trigger
  messageUpdateTrigger,
  normalizeMessageStatus,
  shouldApplyMessageStatus,
} from "./stores/chatStore.js";

// Navigation Store (Anti-SLEEP)
import { useNavigationStore } from "./stores/navigationStore.js";
const navStore = useNavigationStore();

// Local-only state (not shared)
let lastBackPress = 0;
const isLoadingMessages = ref(false);
const isLoadingMoreMessages = ref(false);
const pendingRestoreChatId = ref(null); // For restoring chat after refresh

// Search debounce timer
let searchDebounceTimer = null;

// ============================================
// 🎯 SMART IDLE DETECTION
// ============================================
// Track last user activity for dynamic polling interval
const lastActivityTime = ref(Date.now());
const currentPollingInterval = ref(30000); // Start with 30s

// Chat polling for sync
const chatPollingInterval = ref(null);
const localLastMessageAt = ref(null);
const chatPollingPhone = ref(null); // Store phone being polled
const isChatPolling = ref(false); // true while active-chat poll request in flight
const isChatPollIdlePaused = ref(false); // true when poll stopped because user idle
const chatPollBarHideTimer = ref(null);
const CHAT_POLL_BAR_MIN_MS = 900; // API lokal terlalu cepat — bar perlu durasi minimum
const CHAT_POLL_IDLE_MS = 5 * 60 * 1000; // Keep polling while chat open; pause only after long idle

const showChatPollBar = () => {
  if (chatPollBarHideTimer.value) {
    clearTimeout(chatPollBarHideTimer.value);
    chatPollBarHideTimer.value = null;
  }
  isChatPollIdlePaused.value = false;
  isChatPolling.value = true;
};

const hideChatPollBar = (startedAt) => {
  // If polling already stopped (idle/close), don't re-show via delayed hide
  if (!chatPollingInterval.value) {
    isChatPolling.value = false;
    if (chatPollBarHideTimer.value) {
      clearTimeout(chatPollBarHideTimer.value);
      chatPollBarHideTimer.value = null;
    }
    return;
  }
  const remaining = Math.max(0, CHAT_POLL_BAR_MIN_MS - (Date.now() - startedAt));
  if (chatPollBarHideTimer.value) clearTimeout(chatPollBarHideTimer.value);
  chatPollBarHideTimer.value = setTimeout(() => {
    isChatPolling.value = false;
    chatPollBarHideTimer.value = null;
  }, remaining);
};

// Activity events to track
const ACTIVITY_EVENTS = ['mousedown', 'keydown', 'scroll', 'touchstart', 'touchmove'];

// Update last activity timestamp
const updateActivity = () => {
  lastActivityTime.value = Date.now();
};

// Calculate optimal polling interval based on idle time
const getOptimalInterval = () => {
  const idleTime = Date.now() - lastActivityTime.value;
  const MINUTE = 60 * 1000;
  
  if (idleTime < 2 * MINUTE) {
    return 30000; // 30s - Active
  } else if (idleTime < 10 * MINUTE) {
    return 60000; // 60s - Idle
  } else {
    return 120000; // 120s - Very Idle
  }
};


// Computed: Filtered Quick Replies based on search query
const filteredQuickReplies = computed(() => {
  if (!quickReplySearchQuery.value) return quickReplies.value;

  const query = quickReplySearchQuery.value.toLowerCase();
  return quickReplies.value.filter((qr) => {
    // Match shortcut (without leading /)
    const shortcutWithoutSlash = (qr.shortcut || "")
      .replace(/^\//, "")
      .toLowerCase();
    const titleLower = (qr.title || "").toLowerCase();

    return shortcutWithoutSlash.includes(query) || titleLower.includes(query);
  });
});

// Initialize notification sound
const initNotificationSound = () => {
  // Sound removed as requested
  notificationAudio.value = null;
};

// Play notification sound
const playNotificationSound = () => {
  // Sound disabled
  return;
};

// Load notification sound setting from localStorage
const loadNotificationSoundSetting = () => {
  const saved = localStorage.getItem("cms_notification_sound");
  if (saved !== null) {
    notificationSoundEnabled.value = saved === "true";
  }
};

// Toggle notification sound
const toggleNotificationSound = () => {
  notificationSoundEnabled.value = !notificationSoundEnabled.value;
  localStorage.setItem(
    "cms_notification_sound",
    notificationSoundEnabled.value.toString()
  );
};

// Load font size from localStorage on mount
const loadFontSize = () => {
  const saved = localStorage.getItem("cms_font_size");
  if (saved && ["medium", "large", "xlarge"].includes(saved)) {
    fontSize.value = saved;
  }
};

// Save font size to localStorage
const setFontSize = (size) => {
  fontSize.value = size;
  localStorage.setItem("cms_font_size", size);
};

// Theme Functions — auto day/night, manual override only for current period
const THEME_DAY_START_HOUR = 6;   // 06:00 → light
const THEME_NIGHT_START_HOUR = 18; // 18:00 → dark
let themeCheckInterval = null;

const getThemePeriod = (date = new Date()) => {
  const hour = date.getHours();
  return hour >= THEME_DAY_START_HOUR && hour < THEME_NIGHT_START_HOUR
    ? "day"
    : "night";
};

const getAutoTheme = (date = new Date()) => {
  return getThemePeriod(date) === "day" ? "light" : "dark";
};

const getThemeOverride = () => {
  try {
    const raw = localStorage.getItem("cms_theme_override");
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (
      parsed &&
      ["dark", "light"].includes(parsed.theme) &&
      ["day", "night"].includes(parsed.period)
    ) {
      return parsed;
    }
  } catch (_) {
    /* ignore */
  }
  return null;
};

const clearThemeOverride = () => {
  localStorage.removeItem("cms_theme_override");
  localStorage.removeItem("cms_theme"); // legacy key
};

const resolveTheme = () => {
  const period = getThemePeriod();
  const override = getThemeOverride();
  if (override && override.period === period) {
    return override.theme;
  }
  if (override) {
    clearThemeOverride();
  }
  return getAutoTheme();
};

const loadTheme = () => {
  // Migrate old permanent preference into a one-period override
  const legacy = localStorage.getItem("cms_theme");
  if (legacy && ["dark", "light"].includes(legacy) && !getThemeOverride()) {
    localStorage.setItem(
      "cms_theme_override",
      JSON.stringify({ theme: legacy, period: getThemePeriod() })
    );
    localStorage.removeItem("cms_theme");
  }

  const resolved = resolveTheme();
  theme.value = resolved;
  applyTheme(resolved);
};

const setTheme = (newTheme, { manual = false } = {}) => {
  theme.value = newTheme;
  if (manual) {
    localStorage.setItem(
      "cms_theme_override",
      JSON.stringify({ theme: newTheme, period: getThemePeriod() })
    );
    localStorage.removeItem("cms_theme");
  }
  applyTheme(newTheme);
};

const syncAutoTheme = () => {
  const resolved = resolveTheme();
  if (resolved !== theme.value) {
    theme.value = resolved;
    applyTheme(resolved);
  }
};

const startThemeAutoSync = () => {
  if (themeCheckInterval) return;
  themeCheckInterval = setInterval(syncAutoTheme, 60 * 1000);
  document.addEventListener("visibilitychange", onThemeVisibilityChange);
};

const stopThemeAutoSync = () => {
  if (themeCheckInterval) {
    clearInterval(themeCheckInterval);
    themeCheckInterval = null;
  }
  document.removeEventListener("visibilitychange", onThemeVisibilityChange);
};

const onThemeVisibilityChange = () => {
  if (document.visibilityState === "visible") {
    syncAutoTheme();
  }
};

const applyTheme = (themeName) => {
  const root = document.documentElement;

  if (themeName === "light") {
    // Light theme — clean white panels (sedikit lebih terang, kurang berkabut)
    root.style.setProperty("--wa-bg-primary", "#f7f8fa");
    root.style.setProperty("--wa-bg-secondary", "#ffffff");
    root.style.setProperty("--wa-bg-tertiary", "#f5f6f8");
    root.style.setProperty("--wa-bg-panel", "#ffffff");
    root.style.setProperty("--wa-bg-chat", "#ece8df");
    root.style.setProperty("--wa-border", "#e2e6ea");
    root.style.setProperty("--wa-text-primary", "#0b141a");
    root.style.setProperty("--wa-text-secondary", "#3b4a54");
    root.style.setProperty("--wa-text-tertiary", "#5e6e78");
    root.style.setProperty("--wa-bubble-out", "#d1f4cc");
    root.style.setProperty("--wa-bubble-outgoing", "#d1f4cc");
    root.style.setProperty("--wa-bubble-out-text", "#0b141a");
    root.style.setProperty("--wa-bubble-in", "#ffffff");
    root.style.setProperty("--wa-bubble-incoming", "#ffffff");
    root.style.setProperty("--wa-bubble-in-text", "#0b141a");
    root.style.setProperty("--wa-hover", "#f5f6f8");
    root.style.setProperty("--wa-active", "#f0f2f5");
    root.style.setProperty("--wa-bubble-out-meta", "#3b4a54");
    root.style.setProperty("--wa-bubble-out-quoted-bg", "rgba(0, 0, 0, 0.07)");
    root.style.setProperty("--wa-bubble-out-quoted-text", "#3b4a54");
    root.style.setProperty("--wa-icon-default", "#3b4a54");
    root.style.setProperty("--wa-accent-green", "#008f72");
    root.style.setProperty("--wa-accent-blue", "#0277bd");
    root.style.setProperty("--wa-divider", "#eceff1");
    root.style.setProperty("--wa-link-color", "#015f96");
    root.style.setProperty("--wa-date-badge", "#ffffff");
    root.style.setProperty("--wa-date-badge-text", "#3b4a54");
    root.style.setProperty("--wa-header-bg", "#ffffff");
    root.style.setProperty("--wa-input-bg", "#ffffff");
    root.style.setProperty("--wa-conversation-active", "#f0f2f5");
    root.style.setProperty("--wa-cabang-text", "#015f96");
    root.style.setProperty("--wa-cabang-bg", "#c8e8f8");
    root.style.setProperty("--wa-cabang-hq-text", "#ad1457");
    root.style.setProperty("--wa-cabang-hq-bg", "#f8d0e0");

    // Filter Tabs
    root.style.setProperty("--wa-filter-active-bg", "#c8f0c4");
    root.style.setProperty("--wa-filter-active-text", "#006b54");
    root.style.setProperty("--wa-filter-inactive-bg", "transparent");
    root.style.setProperty("--wa-filter-inactive-border", "#e2e6ea");
    root.style.setProperty("--wa-filter-inactive-text", "#3b4a54");
    root.style.setProperty("--wa-caption-overlay-bg", "rgba(255, 255, 255, 0.85)");
    root.style.setProperty("--wa-caption-overlay-text", "#000000");
    // Conversation list (WhatsApp Web light)
    root.style.setProperty("--wa-conv-name", "#111b21");
    root.style.setProperty("--wa-conv-preview", "#667781");
    root.style.setProperty("--wa-conv-time", "#667781");
    root.style.setProperty("--wa-conv-unread-preview", "#111b21");
    root.style.setProperty("--wa-conv-unread-time", "#008069");
  } else {
    // Dark theme colors - Soft black/gray like WhatsApp (no blue tint)
    root.style.setProperty("--wa-bg-primary", "#0d0d0d");
    root.style.setProperty("--wa-bg-secondary", "#1a1a1a");
    root.style.setProperty("--wa-bg-tertiary", "#2a2a2a");
    root.style.setProperty("--wa-bg-panel", "#0d0d0d");
    root.style.setProperty("--wa-bg-chat", "#0d0d0d"); // Chat area background
    root.style.setProperty("--wa-border", "#3a3a3a");
    root.style.setProperty("--wa-text-primary", "#e9edef");
    root.style.setProperty("--wa-text-secondary", "#c5c9cc");
    root.style.setProperty("--wa-text-tertiary", "#a6a8a9");
    root.style.setProperty("--wa-bubble-out", "#005c4b");
    root.style.setProperty("--wa-bubble-outgoing", "#005c4b"); // Alias
    root.style.setProperty("--wa-bubble-out-text", "#e9edef");
    root.style.setProperty("--wa-bubble-in", "#1a1a1a");
    root.style.setProperty("--wa-bubble-incoming", "#1a1a1a"); // Alias
    root.style.setProperty("--wa-bubble-in-text", "#e9edef");
    root.style.setProperty("--wa-hover", "#1a1a1a");
    root.style.setProperty("--wa-active", "#2a2a2a");
    root.style.setProperty("--wa-bubble-out-meta", "rgba(255, 255, 255, 0.8)"); // Brighter for better contrast
    root.style.setProperty("--wa-bubble-out-quoted-bg", "rgba(0, 0, 0, 0.3)");
    root.style.setProperty(
      "--wa-bubble-out-quoted-text",
      "rgba(255, 255, 255, 0.9)"
    );
    root.style.setProperty("--wa-icon-default", "#c5c9cc");
    root.style.setProperty("--wa-accent-green", "#00a884");
    root.style.setProperty("--wa-accent-blue", "#53bdeb");
    root.style.setProperty("--wa-divider", "#3a3a3a"); // More visible divider
    root.style.setProperty("--wa-link-color", "#53bdeb");
    root.style.setProperty("--wa-date-badge", "#1a1a1a");
    root.style.setProperty("--wa-date-badge-text", "#c5c9cc");
    root.style.setProperty("--wa-header-bg", "#1a1a1a");
    root.style.setProperty("--wa-input-bg", "#2a2a2a");
    root.style.setProperty("--wa-conversation-active", "#2a2a2a");
    root.style.setProperty("--wa-cabang-text", "#53bdeb");
    root.style.setProperty("--wa-cabang-bg", "rgba(83, 189, 235, 0.18)");
    root.style.setProperty("--wa-cabang-hq-text", "#f472b6");
    root.style.setProperty("--wa-cabang-hq-bg", "rgba(244, 114, 182, 0.18)");

    // Filter Tabs
    root.style.setProperty("--wa-filter-active-bg", "#00a884");
    root.style.setProperty("--wa-filter-active-text", "#ffffff");
    root.style.setProperty("--wa-filter-inactive-bg", "#1a1a1a");
    root.style.setProperty("--wa-filter-inactive-border", "#3a3a3a");
    root.style.setProperty("--wa-filter-inactive-text", "#c5c9cc");
    root.style.setProperty("--wa-caption-overlay-bg", "rgba(0, 0, 0, 0.75)");
    root.style.setProperty("--wa-caption-overlay-text", "#ffffff");
    // Conversation list (WhatsApp Web dark)
    root.style.setProperty("--wa-conv-name", "#e9edef");
    root.style.setProperty("--wa-conv-preview", "#8696a0");
    root.style.setProperty("--wa-conv-time", "#8696a0");
    root.style.setProperty("--wa-conv-unread-preview", "#e9edef");
    root.style.setProperty("--wa-conv-unread-time", "#00a884");
  }
};

const toggleTheme = () => {
  const newTheme = theme.value === "dark" ? "light" : "dark";
  setTheme(newTheme, { manual: true });
};

// Computed: Cases available to resolve based on Role


// Total unread messages count


// Total conversations with open cases


// Title blinking is now handled by shouldBlinkTitle watch below (line 314)
// to avoid conflicts between totalUnreadCount and priority-based blinking

const fetchConversations = async (offset = 0, limit = 30, search = '') => {
  try {
    isLoadingConversations.value = true; // Start loading

    const userIdParam = authId.value ? `user_id=${authId.value}` : "";
    const searchParam = search ? `&search=${encodeURIComponent(search)}` : "";
    const query = userIdParam
      ? `?${userIdParam}&offset=${offset}&limit=${limit}${searchParam}&_t=${Date.now()}`
      : `?offset=${offset}&limit=${limit}${searchParam}&_t=${Date.now()}`;

    const response = await fetch(
      `${API_BASE}/CRM/Chat/getConversations${query}`
    );

    if (!response.ok) {
      const text = await response.text();
      console.error("API Error Response:", text);
      return;
    }

    const result = await response.json();

    // Backend returns new format: { conversations: [], has_more: boolean, ... }
    const conversationsData = result.data?.conversations || result.data || [];
    const hasMore = result.data?.has_more ?? false;

    // Update pagination state
    hasMoreConversations.value = hasMore;
    conversationsOffset.value = offset + conversationsData.length;

    // Backend returns "status": true, not "success"
    if (result.status && Array.isArray(conversationsData)) {
      // SMART MERGE STRATEGY
      // 1. Create Map of existing convos
      const existingMap = new Map(conversations.value.map((c) => [c.id, c]));
      const newOrder = [];

      conversationsData.forEach((c) => {
        // Normalizer helper for cases
        const parseCases = (c) => {
          let cases = [];
          // 1. Try case_history (array from backend)
          if (Array.isArray(c.case_history)) {
            cases = c.case_history;
          }
          // 2. Try parsing raw 'conv_case' OR 'case' column if string JSON
          else {
            // 2. Try parsing raw 'conv_case' OR 'case' column if string JSON
            const rawCase = c.conv_case || c.case;
            if (
              typeof rawCase === "string" &&
              (rawCase.startsWith("[") || rawCase.startsWith("{"))
            ) {
              try {
                const parsed = JSON.parse(rawCase);
                if (Array.isArray(parsed)) cases = parsed;
                else if (parsed.case) cases = [parsed];
              } catch (e) {}
            }

            // 3. Fallback: Legacy Priority/Case Val (Only if still empty)
            if (cases.length === 0 && (c.priority > 0 || c.case_val > 0)) {
              cases = [{ case: parseInt(c.priority || c.case_val || 0) }];
            }
          }

          // Filter out 0 case if there are others, or just keep distinct
          // FIX: Deduplicate cases - keep only latest open entry per case value
          const dedupedCases = [];
          const seenCases = new Map(); // Map<caseValue, caseEntry>

          // Process in order (already sorted by timestamp in backend)
          for (const cse of cases) {
            const caseVal = parseInt(cse.case);
            if (isNaN(caseVal) || caseVal === 0) continue;

            const status = cse.status || "open";

            // Normalize the case object - ensure case is integer
            const normalizedCase = { ...cse, case: caseVal };

            if (!seenCases.has(caseVal)) {
              // First occurrence of this case value
              seenCases.set(caseVal, normalizedCase);
            } else {
              // Already seen - prefer open over closed, and newer timestamp
              const existing = seenCases.get(caseVal);
              const existingStatus = existing.status || "open";

              // If existing is closed but new is open, replace
              if (existingStatus === "closed" && status !== "closed") {
                seenCases.set(caseVal, normalizedCase);
              }
              // If both are open/both are closed, keep the newer one (later in array = newer)
              else if (existingStatus === status) {
                seenCases.set(caseVal, normalizedCase);
              }
            }
          }

          return enforceCaseFourExclusivity(Array.from(seenCases.values()));
        };

        let convo = existingMap.get(c.id);

        if (convo) {
          // Update existing
          convo.wa_number = c.wa_number;
          convo.name = c.contact_name || c.wa_number;
          convo.kode_cabang = c.kode_cabang;
          convo.cust_id = c.cust_id;
          convo.is_pelanggan = !!c.is_pelanggan || Number(c.cust_id) > 0;
          convo.partner =
            c.partner === 1 || c.partner === "1" ? 1 : null;
          // convo.priority = parseInt(c.priority) || 0; // Legacy ignored
          convo.cases = parseCases(c); // New Array

          convo.initials = (c.contact_name || c.wa_number || "?")
            .substring(0, 1)
            .toUpperCase();
          convo.color = getAvatarColor(c.id);
          convo.status = c.status;
          convo.line_csw = c.line_csw || {};
          convo.default_reply_line = c.default_reply_line || null;
          convo.ycloud_open = !!c.ycloud_open;
          convo.fonnte_open = !!c.fonnte_open;
          convo.default_reply_channel = c.default_reply_channel || null;
          convo.can_reply = c.can_reply ?? (convo.ycloud_open || convo.fonnte_open);
          applyConversationLastMessage(convo, {
            message: c.last_message || c.last_message_text || "No messages yet",
            time: c.last_message_time,
          });
          convo.unread = parseInt(c.unread_count) || 0;
          convo.assignment_user_id = c.assigned_user_id; // Fix: map from backend assigned_user_id
          // MESSAGES PRESERVED AUTOMATICALLY as we are modifying the object ref
        } else {
          // Create new
          convo = {
            id: c.id,
            wa_number: c.wa_number,
            name: c.contact_name || c.wa_number,
            kode_cabang: c.kode_cabang,
            cust_id: c.cust_id,
            is_pelanggan: !!c.is_pelanggan || Number(c.cust_id) > 0,
            partner:
              c.partner === 1 || c.partner === "1" ? 1 : null,
            // priority: parseInt(c.priority) || 0,
            cases: parseCases(c),

            initials: (c.contact_name || c.wa_number || "?")
              .substring(0, 1)
              .toUpperCase(),
            color: getAvatarColor(c.id),
            status: c.status,
      ycloud_open: !!(c.line_csw?.cs?.open ?? c.ycloud_open),
      fonnte_open: !!(c.line_csw?.admin?.open ?? c.fonnte_open),
      line_csw: c.line_csw || {},
      default_reply_line: c.default_reply_line || null,
      default_reply_channel: c.default_reply_channel || null,
      can_reply: c.can_reply ?? (!!(c.line_csw?.cs?.open ?? c.ycloud_open) || !!(c.line_csw?.admin?.open ?? c.fonnte_open)),
            lastMessage:
              c.last_message || c.last_message_text || "No messages yet",
            lastTime: formatLastTime(c.last_message_time),
            lastMessageTime: c.last_message_time, // Raw timestamp for sorting
            unread: parseInt(c.unread_count) || 0,
            assignment_user_id: c.assigned_user_id, // Fix: map from backend assigned_user_id
            messages: [],
          };
        }
        newOrder.push(convo);
      });

      newOrder.sort(
        (a, b) => messageTimeMs(b.lastMessageTime) - messageTimeMs(a.lastMessageTime)
      );

      // Re-assign to update list order/membership
      conversations.value = newOrder;

      // Auto-restore chat after refresh (if pendingRestoreChatId is set)
      if (pendingRestoreChatId.value) {
        const chatIdToRestore = pendingRestoreChatId.value;
        pendingRestoreChatId.value = null; // Clear it first to prevent re-triggering
        
        const conversation = conversations.value.find(
          (c) => String(c.id) === String(chatIdToRestore)
        );
        
        if (conversation) {
          selectChat(conversation.id);
        } else {
          // Reset to home view
          activeChatId.value = null;
          showMobileChat.value = false;
          navStore.reset();
        }
      }

      // Auto-open chat if deep link pending
      if (pendingTargetPhone.value) {
        const target = conversations.value.find((c) => {
          const cleanA = (c.wa_number || "").replace(/\D/g, "");
          const cleanB = (pendingTargetPhone.value || "").replace(/\D/g, "");
          return cleanA.endsWith(cleanB) || cleanB.endsWith(cleanA);
        });

        if (target) {
          pendingTargetPhone.value = null; // Clear it first to prevent re-triggering

          // Use selectChat to properly load messages from API
          selectChat(target.id);
        }
      }
    } else {
      console.error("API format error:", result);
    }
  } catch (e) {
    console.error("Error fetching conversations:", e);
  } finally {
    isLoadingConversations.value = false; // End loading
  }
};

// Load more conversations (for infinite scroll)
const loadMoreConversations = async () => {
  if (!hasMoreConversations.value || isLoadingMoreConversations.value) return;
  
  isLoadingMoreConversations.value = true;
  
  try {
    const offset = conversationsOffset.value;
    const userIdParam = authId.value ? `user_id=${authId.value}` : "";
    const searchParam = searchQuery.value ? `&search=${encodeURIComponent(searchQuery.value)}` : "";
    const query = userIdParam
      ? `?${userIdParam}&offset=${offset}&limit=30${searchParam}&_t=${Date.now()}`
      : `?offset=${offset}&limit=30${searchParam}&_t=${Date.now()}`;

    const response = await fetch(
      `${API_BASE}/CRM/Chat/getConversations${query}`
    );
    
    if (!response.ok) {
      console.error("API Error Response");
      return;
    }

    const result = await response.json();
    const conversationsData = result.data?.conversations || result.data || [];
    const hasMore = result.data?.has_more ?? false;

    // Update pagination state
    hasMoreConversations.value = hasMore;
    conversationsOffset.value = offset + conversationsData.length;

    if (result.status && Array.isArray(conversationsData)) {
      // Append new conversations to existing list
      const newConvos = conversationsData.map((c) => {
        const parseCases = (c) => {
          let cases = [];
          if (Array.isArray(c.case_history)) {
            cases = c.case_history;
          } else {
            const rawCase = c.conv_case || c.case;
            if (
              typeof rawCase === "string" &&
              (rawCase.startsWith("[") || rawCase.startsWith("{"))
            ) {
              try {
                const parsed = JSON.parse(rawCase);
                if (Array.isArray(parsed)) cases = parsed;
                else if (parsed.case) cases = [parsed];
              } catch (e) {}
            }
            if (cases.length === 0 && (c.priority > 0 || c.case_val > 0)) {
              cases = [{ case: parseInt(c.priority || c.case_val || 0) }];
            }
          }

          const dedupedCases = [];
          const seenCases = new Map();
          for (const cse of cases) {
            const caseVal = parseInt(cse.case);
            if (isNaN(caseVal) || caseVal === 0) continue;
            const status = cse.status || "open";
            const normalizedCase = { ...cse, case: caseVal };
            if (!seenCases.has(caseVal)) {
              seenCases.set(caseVal, normalizedCase);
            } else {
              const existing = seenCases.get(caseVal);
              const existingStatus = existing.status || "open";
              if (existingStatus === "closed" && status !== "closed") {
                seenCases.set(caseVal, normalizedCase);
              } else if (existingStatus === status) {
                seenCases.set(caseVal, normalizedCase);
              }
            }
          }
          return enforceCaseFourExclusivity(Array.from(seenCases.values()));
        };

        return {
          id: c.id,
          wa_number: c.wa_number,
          name: c.contact_name || c.wa_number,
          kode_cabang: c.kode_cabang,
          cust_id: c.cust_id,
          is_pelanggan: !!c.is_pelanggan || Number(c.cust_id) > 0,
          partner:
            c.partner === 1 || c.partner === "1" ? 1 : null,
          cases: parseCases(c),
          initials: (c.contact_name || c.wa_number || "?")
            .substring(0, 1)
            .toUpperCase(),
          color: getAvatarColor(c.id),
          status: c.status,
          ycloud_open: !!c.ycloud_open,
          fonnte_open: !!c.fonnte_open,
          default_reply_channel: c.default_reply_channel || null,
          can_reply: c.can_reply ?? (!!c.ycloud_open || !!c.fonnte_open),
          lastMessage:
            c.last_message || c.last_message_text || "No messages yet",
          lastTime: formatLastTime(c.last_message_time),
          lastMessageTime: c.last_message_time,
          unread: parseInt(c.unread_count) || 0,
          assignment_user_id: c.assigned_user_id,
          messages: [],
        };
      });
      
      // Append to existing conversations
      conversations.value = [...conversations.value, ...newConvos];
    }
  } catch (e) {
    console.error("Error loading more conversations:", e);
  } finally {
    isLoadingMoreConversations.value = false;
  }
};

const fetchUserRole = async () => {
  try {
    const res = await fetch(`${API_BASE}/CRM/Roles`);
    const result = await res.json();
    if (result.status && result.data) {
      const roles = result.data;
      const myId = authId.value;
      let role = "crew"; // Default

      // Loose comparison in case ID is string vs int
      const includesId = (arr, id) => arr.some((x) => String(x) === String(id));

      if (roles.admin && includesId(roles.admin, myId)) role = "admin";
      else if (roles.crew && includesId(roles.crew, myId)) role = "crew";
      else if (roles.driver && includesId(roles.driver, myId)) role = "driver";

      currentUserRole.value = role;
      localStorage.setItem("cms_chat_role", role);
    }
  } catch (e) {
    console.error("Failed to fetch roles:", e);
  }
};

// OneSignal Integration for Push Notifications
const oneSignalLogin = (userId) => {
  try {
    // IMPORTANT: Always use UPPERCASE to match server-side OneSignal push targeting
    const uppercaseUserId = String(userId).toUpperCase();
    
    // For WebView: Call Android interface if available
    if (window.OneSignalInterface) {
      window.OneSignalInterface.login(uppercaseUserId);
    }
    // For Web: Use OneSignal Web SDK if available
    else if (window.OneSignalDeferred) {
      window.OneSignalDeferred.push(async function (OneSignal) {
        await OneSignal.login(uppercaseUserId);
      });
    } else if (window.OneSignal) {
      window.OneSignal.login(uppercaseUserId);
    }
  } catch (e) {
    console.error("OneSignal login error:", e);
  }
};

const oneSignalLogout = () => {
  try {
    // For WebView: Call Android interface if available
    if (window.OneSignalInterface) {
      window.OneSignalInterface.logout();
    }
    // For Web: Use OneSignal Web SDK if available
    else if (window.OneSignalDeferred) {
      window.OneSignalDeferred.push(async function (OneSignal) {
        await OneSignal.logout();
      });
    } else if (window.OneSignal) {
      window.OneSignal.logout();
    }
  } catch (e) {
    console.error("OneSignal logout error:", e);
  }
};


const handleLogin = (username) => {
  authId.value = username;
  duplicateWarning.value = ""; // Clear warning on new login attempt
  connect();
};

const connect = async () => {
  if (!authId.value) {
    connectionError.value = "Please enter your Username";
    return;
  }

  isConnecting.value = true;
  connectionError.value = "";
  duplicateWarning.value = ""; // Clear warning

  try {
    const deviceId = getDeviceId();

    // Step 1: Login to Backend (claims device lock)
    const res = await fetch(`${API_BASE}/CRM/Auth/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        username: authId.value,
        device_id: deviceId,
      }),
    });

    let data = null;
    try {
      data = await res.json();
    } catch (_) {
      data = null;
    }

    if (!res.ok || !data?.success) {
      const msg =
        data?.message ||
        (res.status === 409
          ? "ID dikunci di device lain"
          : "Login Failed");
      connectionError.value = msg;
      duplicateWarning.value = res.status === 409 ? msg : "";
      isConnecting.value = false;
      showLoginPrompt.value = true;
      return;
    }

    // Step 2: Login Success
    // Use Role from backend
    if (data.user) {
      currentUserRole.value = data.user.role || "crew";
      userName.value = data.user.name || "";
      senderCode.value = data.user.code || "";
      if (data.user.username) {
        authId.value = String(data.user.username).toUpperCase();
      }

      localStorage.setItem("cms_chat_role", currentUserRole.value);
      localStorage.setItem("cms_chat_name", userName.value);
      localStorage.setItem("cms_chat_sender_code", senderCode.value);
      localStorage.setItem("cms_chat_token", "true");
      localStorage.setItem("cms_chat_id", authId.value);
    }

    connectWebSocket();
    fetchConversations();
    if (currentUserRole.value !== "driver") {
      fetchQuickReplies();
    }

    if (currentUserRole.value !== "driver") {
      oneSignalLogin(authId.value);
    }
    resetPollingTimer();
  } catch (e) {
    console.error(e);
    const msg =
      e.message && e.message.includes("Unexpected token")
        ? "Server Error (Invalid JSON)"
        : e.message || "Connection Error";

    connectionError.value = msg;
    isConnecting.value = false;
  }
};

// Helper to reset polling timer (called on WS events to delay next poll)
const resetPollingTimer = () => {
  if (refreshInterval.value) {
    clearInterval(refreshInterval.value);
  }

  // 🎯 SMART POLLING: Start with initial interval
  const startPolling = () => {
    const interval = getOptimalInterval();
    currentPollingInterval.value = interval;
    
    refreshInterval.value = setInterval(() => {
      // 🔄 IMPROVED: Poll even when WebSocket is disconnected (backup mechanism)
      // Only skip if user is not authenticated or page is hidden
      if (authId.value && !document.hidden) {
        // Check if interval needs adjustment based on activity
        const optimalInterval = getOptimalInterval();
        if (optimalInterval !== currentPollingInterval.value) {
          currentPollingInterval.value = optimalInterval;
          // Restart timer with new interval
          clearInterval(refreshInterval.value);
          startPolling();
          return;
        }
        
        // Fetch conversations
        fetchConversations(0, 30, '');
      }
    }, interval);
  };
  
  startPolling();
};

// --- Computed ---

// --- Computed ---


// Total Priority Conversations
const totalPriority = computed(() => {
  return conversations.value.filter(
    (chat) => chat.priority && chat.priority > 0
  ).length;
});

// Should Title Blink (any conversation with unread messages)
const shouldBlinkTitle = computed(() => {
  const anyUnread = conversations.value.filter((chat) => chat.unread > 0);
  return anyUnread.length > 0;
});

// Watch for title blinking
watch(shouldBlinkTitle, (shouldBlink) => {
  if (shouldBlink) {
    // Start blinking
    if (!titleBlinkInterval.value) {
      titleBlinkInterval.value = setInterval(() => {
        isTitleRed.value = !isTitleRed.value;
        const count = totalUnreadCount.value;
        document.title = isTitleRed.value
          ? `🔴 (${count}) New Messages`
          : originalTitle;
      }, 1000); // Blink every 1 second
    }
  } else {
    // Stop blinking
    if (titleBlinkInterval.value) {
      clearInterval(titleBlinkInterval.value);
      titleBlinkInterval.value = null;
      isTitleRed.value = false;
      document.title = originalTitle;
    }
  }
});

// Scroll when active chat changes
watch(activeChatId, () => {
  scrollToBottom({ force: true });
});

// ============================================
// 🔄 SYNC NAVIGATION STATE (ANTI-SLEEP)
// ============================================
// Automatically update Pinia navigation store when user navigates
// This ensures localStorage is always in sync for Android back/resume handlers

watch(showMobileChat, (isInChat) => {
  if (isInChat && activeChatId.value) {
    // User entered chat view
    navStore.setView('chat', activeChatId.value);
  } else {
    // User returned to conversation list
    navStore.setView('home', null);
  }
});

watch(activeChatId, (chatId) => {
  if (chatId && showMobileChat.value) {
    // Chat ID changed while in chat view
    navStore.setView('chat', chatId);
  }
});


// --- Methods ---

// Parse WhatsApp Formatting to HTML
const parseWhatsAppFormatting = (text) => {
  if (!text) return "";

  let formatted = text;

  // Escape HTML first to prevent XSS
  formatted = formatted
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");

  // Convert URLs to clickable links BEFORE other formatting
  // This prevents URLs from being broken by formatting tags
  // Pattern matches: http://, https://, www., or domain.com patterns
  const urlPattern =
    /(https?:\/\/[^\s]+)|(www\.[^\s]+)|([a-zA-Z0-9][-a-zA-Z0-9]{0,62}\.(?:com|net|org|id|co\.id|ac\.id|io|dev|app|ai|me|info|biz|edu|gov|mil|xyz|online|store|tech|site|web|cloud|link|blog)[^\s]*)/gi;

  formatted = formatted.replace(urlPattern, (match) => {
    let href = match;

    // Add protocol if missing
    if (!href.match(/^https?:\/\//i)) {
      href = "http://" + href;
    }

    // Truncate display text if too long (keep first 40 chars + ...)
    const displayText =
      match.length > 50 ? match.substring(0, 47) + "..." : match;

    return `<a href="${href}" rel="noopener noreferrer" class="text-blue-400 hover:text-blue-300 underline">${displayText}</a>`;
  });

  // Parse WhatsApp formatting patterns
  // Bold: *text* → <strong>text</strong>
  formatted = formatted.replace(/\*([^*]+)\*/g, "<strong>$1</strong>");

  // Italic: _text_ → <em>text</em>
  formatted = formatted.replace(/_([^_]+)_/g, "<em>$1</em>");

  // Strikethrough: ~text~ → <del>text</del>
  formatted = formatted.replace(/~([^~]+)~/g, "<del>$1</del>");

  // Monospace/Code: ```text``` → <code>text</code>
  formatted = formatted.replace(
    /```([^`]+)```/g,
    '<code class="bg-slate-900/50 px-1.5 py-0.5 rounded text-xs font-mono">$1</code>'
  );

  return formatted;
};

// Format Reaction Text (safely handle null)
const formatReactionText = (text) => {
  if (!text) return "👍"; // Default emoji if text is null/undefined
  return text.replace("Reacted: ", "").replace("Removed reaction", "👎");
};

// Format Last Time for Conversation List (WhatsApp Style)
const formatLastTime = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const now = new Date();

  // Reset time part for accurate date comparison
  const d = new Date(date);
  d.setHours(0, 0, 0, 0);
  const n = new Date(now);
  n.setHours(0, 0, 0, 0);
  const y = new Date(n);
  y.setDate(y.getDate() - 1);

  if (d.getTime() === n.getTime()) {
    return date.toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    });
  } else if (d.getTime() === y.getTime()) {
    return "Yesterday";
  } else {
    // DD/MM/YY
    return date.toLocaleDateString("id-ID", {
      day: "2-digit",
      month: "2-digit",
      year: "2-digit",
    });
  }
};

// Format date for separator (Today, Yesterday, or date)
const formatDateSeparator = (dateString) => {
  const msgDate = new Date(dateString);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  // Reset time to compare dates only
  msgDate.setHours(0, 0, 0, 0);
  today.setHours(0, 0, 0, 0);
  yesterday.setHours(0, 0, 0, 0);

  if (msgDate.getTime() === today.getTime()) {
    return "Today";
  } else if (msgDate.getTime() === yesterday.getTime()) {
    return "Yesterday";
  } else {
    return new Date(dateString).toLocaleDateString("id-ID", {
      day: "numeric",
      month: "long",
      year: "numeric",
    });
  }
};

const messageTimeMs = (dateString) => {
  if (!dateString) return 0;
  const ms = new Date(dateString).getTime();
  return Number.isFinite(ms) ? ms : 0;
};

const isNewerMessageTime = (candidate, current) => {
  const cMs = messageTimeMs(candidate);
  const curMs = messageTimeMs(current);
  if (cMs <= 0) return false;
  if (curMs <= 0) return true;
  return cMs >= curMs;
};

/** Hanya terapkan preview jika timestamp kandidat >= yang sudah ada (cegah flip Fonnte/yCloud). */
const applyConversationLastMessage = (convo, { message, time } = {}) => {
  if (!convo) return;
  const nextTime = time || null;
  if (!isNewerMessageTime(nextTime, convo.lastMessageTime)) return;
  if (message != null && message !== "") {
    convo.lastMessage = message;
  }
  if (nextTime) {
    convo.lastMessageTime = nextTime;
    convo.lastTime = formatLastTime(nextTime);
  }
};

// Check if date separator is needed between two messages
const needsDateSeparator = (currentMsg, previousMsg) => {
  if (!previousMsg || !currentMsg.rawTime || !previousMsg.rawTime) return false;

  const currentDate = new Date(currentMsg.rawTime);
  const previousDate = new Date(previousMsg.rawTime);

  currentDate.setHours(0, 0, 0, 0);
  previousDate.setHours(0, 0, 0, 0);

  return currentDate.getTime() !== previousDate.getTime();
};

// --- Methods ---

/** Caption/text helper: ignore media placeholders like [image] / [video] */
const isMediaPlaceholderText = (t) => {
  const s = String(t || "").trim();
  if (!s) return true;
  return /^\[[a-z_]+\]$/i.test(s);
};

const resolveMessageText = (m) => {
  const caption = m?.caption || m?.media_caption || "";
  const raw = m?.text;
  if (!isMediaPlaceholderText(raw)) return String(raw);
  if (caption) return String(caption);
  return "";
};

const mediaTypeLastMessageLabel = (type) => {
  const labels = {
    image: "📷 Image",
    video: "🎥 Video",
    audio: "🎵 Audio",
    voice: "🎤 Voice",
    document: "📄 Document",
    sticker: "🏷️ Sticker",
    location: "📍 Location",
  };
  return labels[type] || (type ? `[${type}]` : "");
};

const isMediaMessage = (m) =>
  ["image", "video", "audio", "document", "sticker"].includes(m?.type);

const LINE_ADMIN = "admin";
const LINE_CS = "cs";

const providerToLineKey = (provider) => {
  if (provider === LINE_ADMIN || provider === "F" || provider === "A") return LINE_ADMIN;
  if (provider === LINE_CS || provider === "Y" || provider === "B") return LINE_CS;
  const s = String(provider || "");
  if (s.startsWith("admin")) return LINE_ADMIN;
  if (s.startsWith("cs")) return LINE_CS;
  return LINE_CS;
};

const isProviderPrefixedId = (id) => /^(admin|cs|F|Y)-\d+$/.test(String(id || ""));

/** WS/getMessages: admin-{id} / cs-{id} (legacy F-/Y-). */
const resolveMessageProvider = (provider, id) => {
  const s = String(id || "");
  if (s.startsWith("admin-") || s.startsWith("F-")) return LINE_ADMIN;
  if (s.startsWith("cs-") || s.startsWith("Y-")) return LINE_CS;
  if (provider === LINE_ADMIN || provider === LINE_CS) return provider;
  if (provider === "F" || provider === "Y") return providerToLineKey(provider);
  return LINE_CS;
};

const applyLineCswOpen = (conversation, messageData) => {
  const lineKey = messageData?.line_key || providerToLineKey(messageData?.provider);
  const label = messageData?.line_label || (lineKey === LINE_ADMIN ? "A" : "B");
  if (!conversation.line_csw) conversation.line_csw = {};
  conversation.line_csw[lineKey] = {
    ...(conversation.line_csw[lineKey] || {}),
    open: true,
    line_label: label,
  };
  conversation.ycloud_open = !!conversation.line_csw[LINE_CS]?.open;
  conversation.fonnte_open = !!conversation.line_csw[LINE_ADMIN]?.open;
  conversation.default_reply_line = lineKey;
  conversation.default_reply_channel = lineKey === LINE_ADMIN ? "fonnte" : "ycloud";
  conversation.can_reply = conversation.ycloud_open || conversation.fonnte_open;
};

const canonicalMessageId = (id, provider) => {
  const s = String(id ?? "").trim();
  if (!s) return "";
  if (isProviderPrefixedId(s)) return s;
  // Optimistic Date.now() ids must stay unprefixed
  if (/^\d{13,}$/.test(s)) return s;
  if (/^\d+$/.test(s)) {
    return `${resolveMessageProvider(provider, s)}-${s}`;
  }
  return s;
};

const messageIdsMatch = (aId, bId, aProvider, bProvider) => {
  if (aId == null || bId == null || aId === "" || bId === "") return false;
  if (String(aId) === String(bId)) return true;
  const ca = canonicalMessageId(aId, aProvider);
  const cb = canonicalMessageId(bId, bProvider);
  return !!(ca && cb && ca === cb);
};

// --- Helper: Centralized Message Sanitizer ---
// Aggressively cleans duplicates based on ID, WAMID, and Fuzzy Content/Time
const sanitizeMessages = (messages) => {
  if (!Array.isArray(messages)) return [];

  // 1. Sort by Time (Robust)
  messages.sort((a, b) => {
    let ta = new Date(a.rawTime || a.time).getTime();
    let tb = new Date(b.rawTime || b.time).getTime();

    // Fallback for "10:30 PM" format if Date parse fails
    if (isNaN(ta) && a.time) ta = new Date("1970/01/01 " + a.time).getTime();
    if (isNaN(tb) && b.time) tb = new Date("1970/01/01 " + b.time).getTime();

    // Final fallback: keep original order (0)
    return (isNaN(ta) ? 0 : ta) - (isNaN(tb) ? 0 : tb);
  });

  const uniqueMap = new Map();
  const result = [];

  messages.forEach((msg) => {
    // Create multiple keys to check for duplicates
    const idKey = canonicalMessageId(msg.id, msg.provider) || String(msg.id);
    const wamidKey = msg.wamid ? String(msg.wamid) : null;

    // Check for existing by ID
    let existing = uniqueMap.get(idKey);

    // Check for existing by WAMID
    if (!existing && wamidKey) {
      // Find any message that shares this wamid
      for (const m of uniqueMap.values()) {
        if (m.wamid === wamidKey) {
          existing = m;
          break;
        }
      }
    }

    // Fuzzy Check (The "Healer")
    // IMPORTANT: Never merge inbound media messages (customer images) — each is a separate bubble.
    // Outgoing optimistic bubbles (pending/temp id) MUST merge with the server copy.
    const isTempId = (id) => /^\d{13,}$/.test(String(id || "")); // Date.now() style
    const isOptimistic = (m) =>
      m?.sender === "me" &&
      (m?.status === "pending" || isTempId(m?.id) || String(m?.media_url || "").startsWith("data:"));

    if (!existing) {
      const normalize = (str) =>
        String(str || "")
          .replace(/\s+/g, " ")
          .trim();
      const msgTime = new Date(msg.rawTime || msg.time).getTime();
      const msgText = normalize(msg.text);
      // Optimistic ↔ server: window lebar.
      // Dua pesan nyata outbound: hanya merge echo WS ganda (id DB vs provider),
      // bukan dua kiriman WA berbeda (wamid keduanya ada dan beda).
      const msgOptimistic = isOptimistic(msg);

      for (let i = result.length - 1; i >= 0 && i >= result.length - 15; i--) {
        const cand = result[i];
        if (cand.sender !== msg.sender) continue;
        if ((cand.provider || "Y") !== (msg.provider || "Y")) continue;

        const candOptimistic = isOptimistic(cand);
        const bothReal = !msgOptimistic && !candOptimistic;
        if (bothReal) {
          if (msg.sender !== "me") continue;
          const w1 = msg.wamid ? String(msg.wamid) : "";
          const w2 = cand.wamid ? String(cand.wamid) : "";
          if (w1 && w2 && w1 !== w2) continue; // dua WA send nyata
        }

        const timeWindowMs =
          msgOptimistic || candOptimistic ? 120000 : 12000;

        // Outgoing media: merge optimistic preview with server message
        if (isMediaMessage(msg) || isMediaMessage(cand)) {
          if (
            msg.sender === "me" &&
            isMediaMessage(msg) &&
            isMediaMessage(cand) &&
            (msgOptimistic || candOptimistic || bothReal) &&
            normalize(cand.text) === msgText
          ) {
            const candTime = new Date(cand.rawTime || cand.time).getTime();
            if (
              isNaN(msgTime) ||
              isNaN(candTime) ||
              Math.abs(candTime - msgTime) < timeWindowMs
            ) {
              existing = cand;
              break;
            }
          }
          continue; // never fuzzy-merge customer media
        }

        if (normalize(cand.text) === msgText && msgText !== "") {
          const candTime = new Date(cand.rawTime || cand.time).getTime();
          if (
            isNaN(msgTime) ||
            isNaN(candTime) ||
            Math.abs(candTime - msgTime) < timeWindowMs
          ) {
            existing = cand;
            break;
          }
        }
      }
    }

    if (existing) {
      // MERGE STRATEGY: Prefer real DB id over temp Date.now() id; keep WAMID; upgrade status

      if (isTempId(existing.id) && msg.id != null && !isTempId(msg.id)) {
        existing.id = msg.id;
      } else if (isTempId(msg.id) && existing.id != null && !isTempId(existing.id)) {
        // keep existing real id
      } else {
        const existingIdIsInt = /^\d+$/.test(String(existing.id));
        const msgIdIsInt = /^\d+$/.test(String(msg.id));
        const existingHasProvider = isProviderPrefixedId(existing.id);
        const msgHasProvider = isProviderPrefixedId(msg.id);
        if (msgHasProvider && !existingHasProvider) {
          existing.id = msg.id;
        } else if (msgIdIsInt && !existingIdIsInt && !existingHasProvider) {
          existing.id = msg.id;
        }
      }

      if (msg.provider && !existing.provider) {
        existing.provider = msg.provider;
      }

      if (msg.wamid && !existing.wamid) {
        existing.wamid = msg.wamid;
      }

      // Prefer real https media URL over data: preview
      if (
        msg.media_url &&
        (!existing.media_url || String(existing.media_url).startsWith("data:"))
      ) {
        existing.media_url = msg.media_url;
      }

      // Prefer real caption over placeholder like [image]
      const resolvedText = resolveMessageText(msg);
      if (
        resolvedText &&
        (isMediaPlaceholderText(existing.text) || !existing.text)
      ) {
        existing.text = resolvedText;
      }

      if (
        msg.status &&
        shouldApplyMessageStatus(existing.status, msg.status)
      ) {
        existing.status = normalizeMessageStatus(msg.status);
      }

      uniqueMap.set(idKey, existing);
      uniqueMap.set(String(existing.id), existing);
      if (existing.wamid) uniqueMap.set(existing.wamid, existing);
    } else {
      // New message — normalize numeric DB id to Y-/F- so Vue :key matches REST
      if (idKey && String(msg.id) !== idKey && isProviderPrefixedId(idKey)) {
        msg.id = idKey;
      }
      result.push(msg);
      uniqueMap.set(idKey, msg);
      if (wamidKey) uniqueMap.set(wamidKey, msg);
    }
  });

  return result;
};

// --- Methods ---
const fetchMessages = async (phone, offset = 0, limit = 20) => {
  try {
    const safeOffset = Math.max(0, parseInt(offset, 10) || 0);
    const safeLimit = Math.max(1, Math.min(100, parseInt(limit, 10) || 20));
    const phoneParam = encodeURIComponent(phone || "");
    const response = await fetch(
      `${API_BASE}/CRM/Chat/getMessages?phone=${phoneParam}&offset=${safeOffset}&limit=${safeLimit}&_t=${Date.now()}`
    );
    const result = await response.json();

    if (!response.ok || !result.status) {
      console.error("getMessages failed:", result?.error || result?.message || response.status);
      return { messages: [], has_more: false };
    }
      // Handle both old format (array) and new format (object with messages)
      const messagesData = Array.isArray(result.data) ? result.data : (result.data?.messages || []);
      const hasMore = result.data?.has_more ?? false;
      
      const mappedMessages = messagesData.map((m) => {
        return {
          id: m.id,
          wamid: m.wamid,
          text: resolveMessageText(m),
          type: m.type,
          media_id: m.media_id,
          media_url: m.media_url,
          sender: m.sender,
          time: m.time
            ? new Date(m.time).toLocaleTimeString([], {
                hour: "2-digit",
                minute: "2-digit",
                hour12: false,
              })
            : "",
          rawTime: m.time,
          status: normalizeMessageStatus(m.status),
          private: m.private !== undefined ? (typeof m.private === 'number' ? m.private : parseInt(m.private) || 0) : 0,
          sender_code: m.sender_code,
          quoted_message_id: m.quoted_message_id || null,
          quoted_message_body: m.quoted_message_body || null,
          provider: resolveMessageProvider(m.provider, m.id),
          line_key: m.line_key || resolveMessageProvider(m.provider, m.id),
          line_label: m.line_label || (resolveMessageProvider(m.provider, m.id) === LINE_ADMIN ? "A" : "B"),
        };
      });

      // Use Centralized Sanitizer
      return {
        messages: sanitizeMessages(mappedMessages),
        has_more: hasMore
      };
  } catch (e) {
    console.error("Error loading messages:", e);
  }
  return { messages: [], has_more: false };
};

// Load more messages (for infinite scroll)
const loadMoreMessages = async () => {
  if (!activeConversation.value || isLoadingMoreMessages.value) return;
  if (!activeConversation.value.hasMoreMessages) return;
  
  isLoadingMoreMessages.value = true;
  
  try {
    const offset = activeConversation.value.messageOffset || activeConversation.value.messages.length;
    const result = await fetchMessages(activeConversation.value.wa_number, offset, 20);
    
    if (result.messages.length > 0) {
      // Prepend older messages to the beginning
      activeConversation.value.messages = [...result.messages, ...activeConversation.value.messages];
      activeConversation.value.hasMoreMessages = result.has_more;
      activeConversation.value.messageOffset = offset + result.messages.length;
    } else {
      activeConversation.value.hasMoreMessages = false;
    }
  } catch (e) {
    console.error("Error loading more messages:", e);
  } finally {
    isLoadingMoreMessages.value = false;
  }
};

const NEAR_BOTTOM_THRESHOLD = 140;

const isNearBottom = () => {
  const el = chatContainer.value;
  if (!el) return true;
  return el.scrollHeight - el.scrollTop - el.clientHeight <= NEAR_BOTTOM_THRESHOLD;
};

/** @param {boolean|{force?:boolean}} [opts] force=true saat buka chat / kirim sendiri */
const scrollToBottom = (opts = {}) => {
  const force = opts === true || (typeof opts === "object" && opts?.force === true);
  nextTick(() => {
    if (!chatContainer.value) return;
    if (!force && !isNearBottom()) return;
    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
  });
};

const markMessagesRead = async (phone) => {
  try {
    await fetch(`${API_BASE}/CRM/Chat/markRead`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        phone: phone, // Send phone
        user_id: authId.value, // Add sender ID
      }),
    });

    // Update local state if needed
    const chat = conversations.value.find((c) => c.wa_number === phone);
    if (chat) chat.unread = 0;
  } catch (e) {
    console.error("Failed to mark read", e);
  }
};

const markAsDone = async () => {
  if (!activeConversation.value || isMarkingAsDone.value) return;

  try {
    isMarkingAsDone.value = true;
    showChatMenu.value = false; // Close menu

    const response = await fetch(`${API_BASE}/CRM/Chat/markAsDone`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        phone: activeConversation.value.wa_number,
        user_id: authId.value,
      }),
    });

    const res = await response.json();

    if (res.status) {
      // Update local cases — pertahankan case 2 open (dituntaskan via laundry Delivery)
      const keepPickup = (activeConversation.value.cases || []).filter(
        (c) => parseInt(c.case) === 2 && (c.status || "open") !== "closed"
      );
      activeConversation.value.cases = keepPickup.length
        ? keepPickup.map((c) => ({ case: 2, status: "open" }))
        : [{ case: 0 }];

      // ℹ️ SSE will broadcast update to all other clients automatically!
      // No manual refresh needed - real-time magic! ✨
    } else {
      console.error("Failed to mark as done:", res.message);
    }

    // Keep loading for 3 seconds
    setTimeout(() => {
      isMarkingAsDone.value = false;
    }, 3000);
  } catch (e) {
    console.error("Error marking as done:", e);
    isMarkingAsDone.value = false;
  }
};

const followUp = async () => {
  if (!activeConversation.value || isFollowUp.value) return;

  try {
    isFollowUp.value = true;
    showChatMenu.value = false; // Close menu

    const response = await fetch(`${API_BASE}/CRM/Chat/updateCase`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        phone: activeConversation.value.wa_number,
        case: 4,
        user_id: authId.value,
      }),
    });

    const res = await response.json();

    if (res.status) {
      if (!activeConversation.value.cases) activeConversation.value.cases = [];
      activeConversation.value.cases = mergeOpenCaseLocal(
        activeConversation.value.cases,
        CASE_FOLLOW_UP
      );
    } else {
      console.error("Failed to mark for follow up:", res.message);
    }

    // Keep loading for 3 seconds
    setTimeout(() => {
      isFollowUp.value = false;
    }, 3000);
  } catch (e) {
    console.error("Error marking for follow up:", e);
    isFollowUp.value = false;
  }
};

const reopenConversation = async () => {
  if (!activeConversation.value || isReopeningConversation.value) return;

  try {
    isReopeningConversation.value = true;
    showChatMenu.value = false; // Close menu

    const response = await fetch(`${API_BASE}/CRM/Chat/reopenConversation`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        phone: activeConversation.value.wa_number,
        user_id: authId.value,
      }),
    });

    const res = await response.json();

    if (res.status) {
      // Update local cases
      activeConversation.value.cases = [{ case: 4 }];
    } else {
      console.error("Failed to reopen conversation:", res.message);
    }

    // Keep loading for 3 seconds
    setTimeout(() => {
      isReopeningConversation.value = false;
    }, 3000);
  } catch (e) {
    console.error("Error reopening conversation:", e);
    isReopeningConversation.value = false;
  }
};

const resolveCase = async (caseId) => {
  if (!activeConversation.value) return;
  try {
    const response = await fetch(`${API_BASE}/CRM/Chat/resolveCase`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        phone: activeConversation.value.wa_number,
        case: parseInt(caseId),
        user_id: authId.value,
      }),
    });
    const res = await response.json();
    if (res.status) {
      // Optimistic Update: Remove from local list (use parseInt for type-safe comparison)
      if (activeConversation.value.cases) {
        activeConversation.value.cases = activeConversation.value.cases.filter(
          (x) => parseInt(x.case) !== parseInt(caseId)
        );
      }
      showResolveMenu.value = false;
    }
  } catch (e) {
    console.error("Error resolving case:", e);
  }
};

const selectChat = async (id, isRefresh = false) => {
  // Check if chat exists first before setting any state
  const chat = conversations.value.find((c) => c.id === id);
  if (!chat) {
    // Chat not found - return to home page immediately
    console.warn(`Chat with id ${id} not found, returning to home`);
    backToMenu(false); // No animation needed
    // Clear saved state since chat doesn't exist
    clearSavedChatState();
    return;
  }

  // Save current draft before switching chats
  if (activeChatId.value && messageInput.value.trim()) {
    chatDrafts.value[activeChatId.value] = messageInput.value;
  } else if (activeChatId.value) {
    // Clear draft if input is empty
    delete chatDrafts.value[activeChatId.value];
  }

  activeChatId.value = id;

  // WhatsApp-style slide-in animation (mobile only)
  if (window.innerWidth < 768 && !isRefresh) {
    isEnteringChat.value = true;
    showMobileChat.value = true;

    // Allow CSS transition to complete
    await new Promise((resolve) => setTimeout(resolve, 30));
    isEnteringChat.value = false;
  } else {
    showMobileChat.value = true;
  }

  // ✅ Save navigation state to Pinia (Anti-SLEEP)
  // Watchers will automatically update navStore when showMobileChat/activeChatId change

  // Legacy: Push history state for URL hash (optional)
  if (window.innerWidth < 768) {
    window.history.pushState({ chatOpen: true }, "", "#chat=" + id);
  }

  // Restore draft for the new chat (or clear input)
  messageInput.value = chatDrafts.value[id] || "";

  // Reset textarea height based on new content
  nextTick(() => autoResizeTextarea());

  // Optimistic read status
  chat.unread = 0;

  // Load messages
  // If we have cached messages, show them immediately and fetch in background
  if (chat.messages && chat.messages.length > 0) {
    scrollToBottom({ force: true }); // Show cache immediately
    // Background fetch to sync and merge
    fetchMessages(chat.wa_number, 0, 20).then((result) => {
      if (result.messages.length > 0) {
        // Merge simply by combining and then Sanitizing
        // This allows the Healer to work its magic on the combined set
        const combined = [...chat.messages, ...result.messages];
        chat.messages = sanitizeMessages(combined);
        chat.hasMoreMessages = result.has_more;
        chat.messageOffset = chat.messages.length; // Total messages loaded
        // Update local last_message_at from conversation
        if (chat.lastMessageTime) {
          localLastMessageAt.value = chat.lastMessageTime;
        }
        scrollToBottom({ force: true });
      }
    });
  } else {
    // No cache, wait for fetch
    isLoadingMessages.value = true;
    try {
      const result = await fetchMessages(chat.wa_number, 0, 20);
      chat.messages = result.messages;
      chat.hasMoreMessages = result.has_more;
      chat.messageOffset = result.messages.length; // Initial messages loaded
      // Update local last_message_at from conversation
      if (chat.lastMessageTime) {
        localLastMessageAt.value = chat.lastMessageTime;
      }
    } finally {
      isLoadingMessages.value = false;
    }
  }

  // Mark read in DB
  markMessagesRead(chat.wa_number);
  
  // Update last_message_at if opened from search or loaded more conversations
  // Only update if:
  // 1. There's an active search, OR
  // 2. Conversation position is beyond the initial fetch (position > 30)
  const hasActiveSearch = searchQuery.value && searchQuery.value.trim().length > 0;
  const chatPosition = conversations.value.findIndex((c) => c.id === id);
  const isBeyondInitialFetch = chatPosition > 29; // Position 31+ (0-indexed)
  
  if (hasActiveSearch || isBeyondInitialFetch) {
    // Update last_message_at to current time so conversation appears at top
    try {
      await fetch(`${API_BASE}/CRM/Chat/updateLastMessageAt`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone: chat.wa_number })
      });
    } catch (error) {
      console.error('Failed to update last_message_at:', error);
    }
  }
  
  // Save active chat state for restoration after returning from external links
  saveActiveChatState();
  scrollToBottom({ force: true });
  
  // Start polling to check for new messages every 5 seconds
  startChatPolling(chat.wa_number);
};

// Sync latest messages into an open chat (used by polling + manual refresh)
const syncActiveChatMessages = async (chat, { forceScroll = false } = {}) => {
  if (!chat?.wa_number) return false;

  const beforeIds = new Set((chat.messages || []).map((m) => String(m.id)));
  const msgResult = await fetchMessages(chat.wa_number, 0, 30);
  if (!msgResult.messages.length) return false;

  const combined = [...(chat.messages || []), ...msgResult.messages];
  chat.messages = sanitizeMessages(combined);
  // Keep pagination cursor based on newest page size, not total local length
  if (typeof chat.messageOffset !== "number" || chat.messageOffset < msgResult.messages.length) {
    chat.messageOffset = msgResult.messages.length;
  }
  chat.hasMoreMessages = msgResult.has_more || chat.hasMoreMessages;

  // Upgrade outgoing ticks from server snapshot
  for (const serverMsg of msgResult.messages) {
    if (serverMsg.sender !== "me") continue;
    const local = chat.messages.find(
      (m) =>
        messageIdsMatch(m.id, serverMsg.id, m.provider, serverMsg.provider) ||
        (m.wamid && serverMsg.wamid && m.wamid == serverMsg.wamid)
    );
    if (local && shouldApplyMessageStatus(local.status, serverMsg.status)) {
      local.status = normalizeMessageStatus(serverMsg.status);
      if (serverMsg.wamid && !local.wamid) local.wamid = serverMsg.wamid;
    }
  }

  const newest = chat.messages[chat.messages.length - 1];
  if (newest) {
    const previewText =
      newest.text ||
      mediaTypeLastMessageLabel(newest.type) ||
      "Message";
    applyConversationLastMessage(chat, {
      message: newest.sender === "me" ? `You: ${previewText}` : previewText,
      time: newest.rawTime,
    });
  }

  messageUpdateTrigger.value++;

  let added = false;
  for (const msg of chat.messages) {
    if (!beforeIds.has(String(msg.id))) {
      added = true;
      break;
    }
  }
  if (added || forceScroll) {
    scrollToBottom(forceScroll ? { force: true } : undefined);
  }
  return added;
};

// Poll open chat for new messages every 5 seconds
const startChatPolling = (phone) => {
  stopChatPolling();
  isChatPollIdlePaused.value = false;
  chatPollingPhone.value = phone;

  const chat = conversations.value.find((c) => c.wa_number === phone);
  if (chat?.lastMessageTime) {
    localLastMessageAt.value = chat.lastMessageTime;
  }

  const pollOnce = async () => {
    if (!activeChatId.value || !phone) {
      stopChatPolling();
      return;
    }

    const idleTime = Date.now() - lastActivityTime.value;
    if (idleTime > CHAT_POLL_IDLE_MS) {
      stopChatPolling({ reason: "idle" }); // bar jadi warna idle; gerak mouse untuk aktif lagi
      return;
    }

    const chat = conversations.value.find((c) => c.wa_number === phone);
    if (!chat || chat.id !== activeChatId.value) return;

    const pollStartedAt = Date.now();
    showChatPollBar();
    try {
      let serverLastMessageAt = null;
      const metaRes = await fetch(
        `${API_BASE}/CRM/Chat/getLastMessageAt?phone=${encodeURIComponent(phone)}&_t=${Date.now()}`
      );
      if (metaRes.ok) {
        const result = await metaRes.json();
        serverLastMessageAt = result.data?.last_message_at ?? null;
        if (result.data?.status) {
          chat.status = result.data.status;
        }
      }

      // Always merge recent messages while chat is open.
      // Relying only on last_message_at string equality missed updates (format drift / stale local).
      await syncActiveChatMessages(chat);

      if (serverLastMessageAt) {
        localLastMessageAt.value = serverLastMessageAt;
      } else if (chat.lastMessageTime) {
        localLastMessageAt.value = chat.lastMessageTime;
      }
    } catch (error) {
      console.error("Failed to poll/sync active chat:", error);
    } finally {
      hideChatPollBar(pollStartedAt);
    }
  };

  // Immediate sync when opening chat, then every 5s
  pollOnce();
  chatPollingInterval.value = setInterval(pollOnce, 5000);
};

// Stop chat polling (keeps chatPollingPhone so we can restart when user becomes active again)
const stopChatPolling = ({ reason } = {}) => {
  if (chatPollingInterval.value) {
    clearInterval(chatPollingInterval.value);
    chatPollingInterval.value = null;
  }
  localLastMessageAt.value = null;
  if (chatPollBarHideTimer.value) {
    clearTimeout(chatPollBarHideTimer.value);
    chatPollBarHideTimer.value = null;
  }
  // Idle/close: animasi aktif harus ikut mati, jangan tertahan min-duration timer
  isChatPolling.value = false;
  // Idle: biarkan bar full dengan warna khusus; close chat: hilangkan indikator
  isChatPollIdlePaused.value = reason === "idle";
  // Don't clear chatPollingPhone - needed for restart when user becomes active after idle
};

const refreshActiveChat = async () => {
  if (!activeChatId.value) return;

  isRefreshingChat.value = true;
  try {
    const conversation = conversations.value.find(
      (c) => c.id === activeChatId.value
    );
    if (!conversation) return;

    // Refresh CSW/status metadata
    const userIdParam = authId.value ? `user_id=${authId.value}` : "";
    const query = userIdParam
      ? `?${userIdParam}&conversation_id=${activeChatId.value}&_t=${Date.now()}`
      : `?conversation_id=${activeChatId.value}&_t=${Date.now()}`;

    const response = await fetch(
      `${API_BASE}/CRM/Chat/getConversations${query}`
    );

    if (response.ok) {
      const result = await response.json();
      const conversationsData = result.data?.conversations || result.data || [];

      if (result.status && Array.isArray(conversationsData) && conversationsData.length > 0) {
        const updatedConv = conversationsData[0];
        conversation.status = updatedConv.status;
        conversation.ycloud_open = !!updatedConv.ycloud_open;
        conversation.fonnte_open = !!updatedConv.fonnte_open;
        conversation.default_reply_channel = updatedConv.default_reply_channel || null;
        conversation.can_reply = updatedConv.can_reply ?? (conversation.ycloud_open || conversation.fonnte_open);
        if (updatedConv.last_message_time || updatedConv.last_message) {
          applyConversationLastMessage(conversation, {
            message: updatedConv.last_message,
            time: updatedConv.last_message_time,
          });
          if (updatedConv.last_message_time) {
            localLastMessageAt.value = updatedConv.last_message_time;
          }
        }
      }
    }

    // Also reload latest messages (manual refresh must actually sync chat)
    await syncActiveChatMessages(conversation, { forceScroll: true });
  } catch (error) {
    console.error("Failed to refresh chat", error);
  } finally {
    setTimeout(() => {
      isRefreshingChat.value = false;
    }, 500);
  }
};

// Save active chat state to sessionStorage (for restoring after opening links)
const saveActiveChatState = () => {
  if (activeChatId.value) {
    sessionStorage.setItem("cms_active_chat_id", String(activeChatId.value));
    sessionStorage.setItem(
      "cms_show_mobile_chat",
      showMobileChat.value ? "true" : "false"
    );
  }
};

// Restore active chat state from sessionStorage
const restoreActiveChatState = () => {
  const savedChatId = sessionStorage.getItem("cms_active_chat_id");
  const savedShowMobile = sessionStorage.getItem("cms_show_mobile_chat");

  if (savedChatId && !activeChatId.value) {
    // Find the conversation
    const target = conversations.value.find(
      (c) => String(c.id) === savedChatId
    );
    if (target) {
      activeChatId.value = target.id;

      if (savedShowMobile === "true" && window.innerWidth < 768) {
        showMobileChat.value = true;
      }

      // Re-fetch messages if needed
      if (!target.messages || target.messages.length === 0) {
        fetchMessages(target.wa_number, 0, 20).then((result) => {
          target.messages = result.messages;
          target.hasMoreMessages = result.has_more;
          target.messageOffset = result.messages.length;
          scrollToBottom({ force: true });
        });
      } else {
        scrollToBottom({ force: true });
      }
    }
  }
};

// Clear saved chat state (called when user explicitly goes back to menu)
const clearSavedChatState = () => {
  sessionStorage.removeItem("cms_active_chat_id");
  sessionStorage.removeItem("cms_show_mobile_chat");
  localStorage.removeItem("active_chat_id");
  localStorage.removeItem("show_mobile_chat");
};

const backToMenu = (animated = true) => {
  showCustomerPanel.value = false;
  // Stop chat polling when closing chat
  stopChatPolling();
  
  // Save current draft before going back to menu
  if (activeChatId.value && messageInput.value.trim()) {
    chatDrafts.value[activeChatId.value] = messageInput.value;
  } else if (activeChatId.value) {
    delete chatDrafts.value[activeChatId.value];
  }

  // ✅ Update Pinia navigation state (Anti-SLEEP)
  navStore.reset();

  // Clear saved state since user explicitly wants to go back
  clearSavedChatState();

  // Clear message input when going back
  messageInput.value = "";
  resetTextareaHeight();

  if (animated && windowWidth.value < 768) {
    // Animate slide-out to the right (same as swipe gesture)
    touchOffset.value = window.innerWidth;

    // Wait for transition (300ms) to finish before hiding
    setTimeout(() => {
      showMobileChat.value = false;
      activeChatId.value = null;
      // Reset offset after hidden
      setTimeout(() => {
        touchOffset.value = 0;
      }, 50);
    }, 300);
  } else {
    // No animation (desktop or explicit call)
    touchOffset.value = 0;
    showMobileChat.value = false;
    activeChatId.value = null;
  }
};

// Quick Reply Functions
const fetchQuickReplies = async () => {
  if (quickReplies.value.length > 0) return; // Already loaded

  isLoadingQuickReplies.value = true;
  try {
    const list = await loadQuickRepliesFromLaundry();
    if (list.length > 0) {
      quickReplies.value = list;
    }
  } catch (e) {
    console.error("Failed to load quick replies:", e);
  } finally {
    isLoadingQuickReplies.value = false;
  }
};

// Watch messageInput for "/" command to trigger quick replies
watch(messageInput, (newVal) => {
  if (newVal && newVal.startsWith("/")) {
    // Extract search query (text after "/")
    quickReplySearchQuery.value = newVal.substring(1).trim();
    showQuickReplies.value = true;

    // Load quick replies if not loaded yet
    if (quickReplies.value.length === 0) {
      fetchQuickReplies();
    }
  } else {
    showQuickReplies.value = false;
    quickReplySearchQuery.value = "";
  }
});

// Watch lastActivityTime to restart chat polling when user becomes active again
watch(lastActivityTime, () => {
  // If chat is open and polling was stopped due to idle, restart it
  if (activeChatId.value && chatPollingPhone.value && !chatPollingInterval.value) {
    const idleTime = Date.now() - lastActivityTime.value;

    // Only restart if user is not idle
    if (idleTime < CHAT_POLL_IDLE_MS) {
      isChatPollIdlePaused.value = false;
      startChatPolling(chatPollingPhone.value);
    }
  }
});

const selectQuickReply = (qr) => {
  messageInput.value = qr.message;
  showQuickReplies.value = false;
  quickReplySearchQuery.value = "";
  // Trigger auto-resize after inserting quick reply text
  nextTick(() => autoResizeTextarea());
};

// Auto-resize textarea like WhatsApp (max 6 lines / ~150px)
const autoResizeTextarea = () => {
  const textarea = messageTextarea.value;
  if (!textarea) return;

  // Reset height to auto to get correct scrollHeight
  textarea.style.height = "auto";

  // Set height to scrollHeight but cap at max-height (150px ~6 lines)
  const maxHeight = 150;
  const newHeight = Math.min(textarea.scrollHeight, maxHeight);
  textarea.style.height = newHeight + "px";
};

// Reset textarea height after sending message
const resetTextareaHeight = () => {
  const textarea = messageTextarea.value;
  if (textarea) {
    textarea.style.height = "auto";
  }
};

// Emoji Picker Functions


// Set message to reply to (quoted reply)
const setReplyTo = (msg) => {
  replyToMessage.value = msg;
  // Focus on input after selecting reply
  nextTick(() => {
    const textarea = messageTextarea.value;
    if (textarea) textarea.focus();
  });
};

// Cancel reply
const cancelReply = () => {
  replyToMessage.value = null;
};

// Find quoted message in current conversation
const findQuotedMessage = (quotedMessageId) => {
  if (!quotedMessageId || !activeConversation.value?.messages) return null;
  return activeConversation.value.messages.find(
    (m) => m.wamid === quotedMessageId || m.message_id === quotedMessageId
  );
};

// --- Swipe to Reply Logic (Android/Touch) ---
const swipeReplyState = ref({
  startX: 0,
  currentX: 0,
  msgId: null,
  threshold: 60, // px to trigger reply
});

const handleSwipeReplyStart = (e, msgId) => {
  // Only single touch
  if (e.touches.length > 1) return;
  swipeReplyState.value.startX = e.touches[0].clientX;
  swipeReplyState.value.currentX = e.touches[0].clientX;
  swipeReplyState.value.msgId = msgId;
};

const handleSwipeReplyMove = (e) => {
  if (!swipeReplyState.value.msgId) return;
  const diff = e.touches[0].clientX - swipeReplyState.value.startX;

  // Only allow swipe LEFT (diff < 0) and limit max drag distance
  if (diff < 0 && diff > -120) {
    swipeReplyState.value.currentX = e.touches[0].clientX;
  }
};

const handleSwipeReplyEnd = (e, msg) => {
  if (!swipeReplyState.value.msgId) return;

  const diff = swipeReplyState.value.currentX - swipeReplyState.value.startX;

  // Check threshold (swipe LEFT)
  if (diff < -swipeReplyState.value.threshold) {
    if (navigator.vibrate) navigator.vibrate(50);
    setReplyTo(msg);
  }

  // Reset
  swipeReplyState.value = {
    startX: 0,
    currentX: 0,
    msgId: null,
    threshold: 60,
  };
};

const getSwipeReplyStyle = (msgId) => {
  if (swipeReplyState.value.msgId === msgId) {
    const diff = swipeReplyState.value.currentX - swipeReplyState.value.startX;
    if (diff < 0) {
      return { transform: `translateX(${diff}px)`, transition: "none" }; // Move cleanly
    }
  }
  return { transition: "transform 0.2s ease-out", transform: "translateX(0)" };
};

// Scroll to a specific message (for quoted message click)
const scrollToMessage = (quotedMessageId) => {
  if (!quotedMessageId) return;

  // Find the message element by wamid or message_id
  const msg = activeConversation.value?.messages?.find(
    (m) => m.wamid === quotedMessageId || m.message_id === quotedMessageId
  );
  if (!msg) return;

  const element = document.getElementById("msg-" + msg.id);
  if (element) {
    element.scrollIntoView({ behavior: "smooth", block: "center" });
    // Highlight briefly
    element.classList.add("bg-yellow-500/20");
    setTimeout(() => element.classList.remove("bg-yellow-500/20"), 1500);
  }
};

const sendMessage = async () => {
  const text = messageInput.value.trim();
  if (!text) return;

  if (activeConversation.value) {
    const tempId = Date.now();
    const replyingTo = replyToMessage.value; // Capture before clearing

    const newMsg = {
      id: tempId,
      text: text,
      sender: "me",
      time: new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
      }),
      rawTime: new Date().toISOString(), // Fixed: Add rawTime for proper sorting
      timestamp: Date.now(), // Add timestamp for duplicate detection
      status: "pending",
      quoted_message_id: replyingTo?.wamid || null, // Store quoted reference
      sender_code: senderCode.value || localStorage.getItem("cms_chat_sender_code") || "", // Optimistic Sender Code
    };

    // Optimistic UI
    activeConversation.value.messages.push(newMsg);
    activeConversation.value.lastMessage = "You: " + text;
    activeConversation.value.lastTime = newMsg.time;

    messageInput.value = "";
    replyToMessage.value = null; // Clear reply UI
    // Clear draft for this chat since message is sent
    if (activeChatId.value) {
      delete chatDrafts.value[activeChatId.value];
    }
    resetTextareaHeight(); // Reset textarea size after sending
    scrollToBottom({ force: true });

    // API Call
    try {
      const response = await fetch(`${API_BASE}/CRM/Chat/reply`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          phone: activeConversation.value.wa_number, // Use wa_number
          message: text,
          user_id: authId.value, // Add sender ID
          sender_code: senderCode.value || localStorage.getItem("cms_chat_sender_code") || "", // First 2 chars of name
          reply_to: replyingTo?.wamid || null, // Send quoted message WAMID
        }),
      });
      const res = await response.json();

      if (res.status) {
        // Update status (not strictly needed if we reload, but good for UI)
        const sentMsg = activeConversation.value.messages.find(
          (m) => m.id === tempId
        );
        if (sentMsg) {
          sentMsg.status = "sent";
          if (res.data && res.data.local_id) {
            sentMsg.id = res.data.local_id; // Swap temp ID with real DB ID
            if (res.data.wamid) sentMsg.wamid = res.data.wamid;
            else if (res.data.id) sentMsg.wamid = res.data.id;
          }
          // Force UI refresh for nested status change
          const idx = activeConversation.value.messages.indexOf(sentMsg);
          if (idx !== -1) {
            activeConversation.value.messages.splice(idx, 1, { ...sentMsg });
          }
          messageUpdateTrigger.value++;
        }
      } else {
        // Error state
        const sentMsg = activeConversation.value.messages.find(
          (m) => m.id === tempId
        );
        if (sentMsg) sentMsg.status = "failed";
        alert("Failed to send: " + (res.message || "Unknown error"));
      }
    } catch (e) {
      console.error("Reply error:", e);
      const sentMsg = activeConversation.value.messages.find(
        (m) => m.id === tempId
      );
      if (sentMsg) sentMsg.status = "error";
    }
  }
};

// Handle Image Selection
// Native Image Picker with Android WebView Support
const isAndroidWebView = () => {
  const userAgent = navigator.userAgent || navigator.vendor || "";
  return /Android/i.test(userAgent) && /wv|WebView/i.test(userAgent);
};

// Global callback for Android WebView file picker
// Android app should call: window.onImageSelected(base64DataUrl)
if (typeof window !== "undefined") {
  window.onImageSelected = async (dataUrl) => {
    try {
      const response = await fetch(dataUrl);
      const blob = await response.blob();
      const file = new File([blob], `image_${Date.now()}.jpg`, {
        type: "image/jpeg",
      });
      await processSelectedImage(file, dataUrl);
    } catch (err) {
      console.error("Error processing native image:", err);
    }
  };
}

const openImagePicker = async () => {
  // Strategy 1: Check if Android WebView has native file picker interface
  // (Requires Android app to expose: window.FilePickerInterface.openImagePicker())
  if (
    window.FilePickerInterface &&
    typeof window.FilePickerInterface.openImagePicker === "function"
  ) {
    try {
      window.FilePickerInterface.openImagePicker();
      return; // Android will call window.onImageSelected with result
    } catch (err) {
      // Fall through
    }
  }

  // Strategy 2: Capacitor native environment
  if (isNativeApp()) {
    try {
      const image = await Camera.getPhoto({
        quality: 80,
        allowEditing: false,
        resultType: CameraResultType.DataUrl,
        source: CameraSource.Prompt, // Let user choose Camera or Gallery
        promptLabelPhoto: "Gallery",
        promptLabelPicture: "Camera",
        presentationStyle: "popover",
      });

      if (image && image.dataUrl) {
        const response = await fetch(image.dataUrl);
        const blob = await response.blob();
        const file = new File([blob], `image_${Date.now()}.jpg`, {
          type: "image/jpeg",
        });
        await processSelectedImage(file, image.dataUrl);
      }
      return;
    } catch (err) {
      // Fall through to file input
    }
  }

  // Strategy 3: Standard file input (works in browsers and some WebViews)
  // For Android WebView, ensure the input has proper attributes
  if (fileInput.value) {
    // Set capture attribute for Android WebView compatibility
    if (isAndroidWebView()) {
      fileInput.value.setAttribute("capture", "environment");
    }
    fileInput.value.click();
  }
};

const selectImage = async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  if (!file.type.startsWith("image/")) {
    alert("Please select an image file");
    return;
  }

  if (file.size > 10 * 1024 * 1024) {
    alert("Image size must be less than 10MB");
    return;
  }

  await processSelectedImage(file);
  event.target.value = "";
};

// Shared function to process selected image (used by both native picker and file input)
const processSelectedImage = async (file, precomputedDataUrl = null) => {
  try {
    // Compress image to ~500KB
    const compressedBlob = await compressImage(file, 500 * 1024); // 500KB target

    // Create new File from compressed blob
    const compressedFile = new File(
      [compressedBlob],
      file.name || "image.jpg",
      {
        type: file.type || "image/jpeg",
        lastModified: Date.now(),
      }
    );

    selectedImage.value = compressedFile;

    // Use precomputed dataUrl if available (from Capacitor Camera), else create new
    if (precomputedDataUrl) {
      imagePreview.value = precomputedDataUrl;
      showImagePreview.value = true;
    } else {
      // Create preview
      const reader = new FileReader();
      reader.onload = (e) => {
        imagePreview.value = e.target.result;
        showImagePreview.value = true;
      };
      reader.readAsDataURL(compressedFile);
    }
  } catch (err) {
    console.error("Compression error:", err);
    alert("Failed to process image");
  }
};

// Compress image to target size
const compressImage = (file, targetSizeBytes) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (e) => {
      const img = new Image();
      img.src = e.target.result;
      img.onload = () => {
        const canvas = document.createElement("canvas");
        const ctx = canvas.getContext("2d");

        // Calculate new dimensions (max 1920x1920)
        let width = img.width;
        let height = img.height;
        const maxDim = 1920;

        if (width > maxDim || height > maxDim) {
          if (width > height) {
            height = (height / width) * maxDim;
            width = maxDim;
          } else {
            width = (width / height) * maxDim;
            height = maxDim;
          }
        }

        canvas.width = width;
        canvas.height = height;
        ctx.drawImage(img, 0, 0, width, height);

        // Try different quality levels to hit target size
        let quality = 0.9;
        const tryCompress = (q) => {
          canvas.toBlob(
            (blob) => {
              if (blob.size <= targetSizeBytes || q <= 0.1) {
                resolve(blob);
              } else {
                // Reduce quality and try again
                tryCompress(q - 0.1);
              }
            },
            file.type,
            q
          );
        };

        tryCompress(quality);
      };
      img.onerror = reject;
    };
    reader.onerror = reject;
  });
};

const cancelImage = () => {
  selectedImage.value = null;
  imagePreview.value = "";
  showImagePreview.value = false;
  imageCaption.value = "";
};

// Handle Paste Event (Windows Screenshot / Clipboard)
const handlePaste = async (event) => {
  // Only allow paste if a chat is active
  if (!activeChatId.value && !showMobileChat.value) return;

  const items = (event.clipboardData || event.originalEvent.clipboardData)
    .items;
  let file = null;

  for (const item of items) {
    if (item.type.indexOf("image") !== -1) {
      file = item.getAsFile();
      break;
    }
  }

  if (!file) return;

  // Prevent default paste behavior
  event.preventDefault();

  if (file.size > 10 * 1024 * 1024) {
    alert("Image size must be less than 10MB");
    return;
  }

  try {
    // Compress image to ~500KB using existing compressImage function
    const compressedBlob = await compressImage(file, 500 * 1024);

    // Create new File from compressed blob
    const compressedFile = new File(
      [compressedBlob],
      "pasted_image_" + Date.now() + ".jpg",
      {
        type: file.type || "image/jpeg",
        lastModified: Date.now(),
      }
    );

    selectedImage.value = compressedFile;

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      imagePreview.value = e.target.result;
      showImagePreview.value = true;
      // Focus caption input if available (optional)
    };
    reader.readAsDataURL(compressedFile);
  } catch (err) {
    console.error("Paste processing error:", err);
    alert("Failed to process pasted image");
  }
};

// Close menu when clicking outside
const handleClickOutside = (event) => {
  if (showChatMenu.value) showChatMenu.value = false;
  if (showResolveMenu.value) showResolveMenu.value = false;

};

onUnmounted(() => {
  window.removeEventListener("paste", handlePaste);
  window.removeEventListener("click", handleClickOutside);

  // Clear polling interval
  if (refreshInterval.value) {
    clearInterval(refreshInterval.value);
  }

  stopThemeAutoSync();
  
  // Stop chat polling
  stopChatPolling();
});

const sendImage = async () => {
  // ❌ GUARD: Prevent multiple simultaneous sends - CHECK FIRST!
  if (isUploadingImage.value) {
    console.warn("Image upload already in progress, ignoring duplicate call");
    return;
  }

  // Validate required data
  if (!selectedImage.value) {
    console.error("No image selected");
    return;
  }

  if (!activeConversation.value) {
    console.error("No active conversation");
    return;
  }

  // Set guard IMMEDIATELY before any async operations
  isUploadingImage.value = true;

  const caption = imageCaption.value.trim();

  // ✨ Hide modal immediately for snappy UX
  showImagePreview.value = false;

  try {
    const formData = new FormData();
    formData.append("image", selectedImage.value);
    formData.append("phone", activeConversation.value.wa_number); // Use wa_number
    formData.append("user_id", authId.value);
    formData.append("sender_code", senderCode.value || localStorage.getItem("cms_chat_sender_code") || "");
    if (caption) formData.append("caption", caption);

    const tempId = Date.now();
    const newMsg = {
      id: tempId,
      text: caption || "", // ✅ Empty string if no caption (not "[Image]")
      type: "image",
      media_url: imagePreview.value,
      sender: "me",
      time: new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
      }),
      sender_code: senderCode.value || localStorage.getItem("cms_chat_sender_code") || "", // Optimistic Sender Code
      rawTime: new Date().toISOString(), // FIXED: Add rawTime for proper sorting and date separator
      status: "pending",
    };

    activeConversation.value.messages.push(newMsg);
    activeConversation.value.lastMessage = "You: 📷 Image";
    activeConversation.value.lastTime = newMsg.time;

    scrollToBottom({ force: true });

    const response = await fetch(`${API_BASE}/CRM/Chat/sendImage`, {
      method: "POST",
      body: formData,
    });

    const res = await response.json();

    if (res.status) {
      const sentMsg = activeConversation.value.messages.find(
        (m) => m.id === tempId
      );
      if (sentMsg) {
        sentMsg.status = "sent";
        if (res.data && res.data.local_id) {
          sentMsg.id = res.data.local_id;
          if (res.data.media_url) sentMsg.media_url = res.data.media_url;
        }
        const idx = activeConversation.value.messages.indexOf(sentMsg);
        if (idx !== -1) {
          activeConversation.value.messages.splice(idx, 1, { ...sentMsg });
        }
        messageUpdateTrigger.value++;
      }
      cancelImage();
    } else {
      const sentMsg = activeConversation.value.messages.find(
        (m) => m.id === tempId
      );
      if (sentMsg) sentMsg.status = "failed";
      alert(
        "Failed to send image: " + (res.message || res.error || "Unknown error")
      );
    }
  } catch (e) {
    console.error("Send image error:", e);
    const sentMsg = activeConversation.value.messages.find(
      (m) => m.id === tempId
    );
    if (sentMsg) sentMsg.status = "failed";
    alert("Error: " + e.message);
  } finally {
    isUploadingImage.value = false;
  }
};

const handleIncomingMessage = (payload) => {
  if (!payload || typeof payload !== "object") return;

  // System WS events must never become chat bubbles (e.g. [case_updated])
  const systemTypes = [
    "case_updated",
    "case_resolved",
    "conversation_read",
    "agent_message_sent",
    "connection",
    "priority_updated",
  ];
  if (payload.type && systemTypes.includes(payload.type)) {
    return;
  }

  // Check if this is a status update
  if (payload.type === "status_update") {
    const { conversation_id, message, phone } = payload;
    if (!message) return;

    const digits = (p) => String(p || "").replace(/\D/g, "").slice(-10);

    // Find conversation (id, exact phone, or last-10-digit match)
    const conversation = conversations.value.find(
      (c) =>
        (conversation_id && c.id == conversation_id) ||
        (phone && c.wa_number == phone) ||
        (phone && digits(c.wa_number) && digits(c.wa_number) === digits(phone))
    );

    if (conversation && Array.isArray(conversation.messages)) {
      // Find message by local id, wamid, or message_id
      const msgIndex = conversation.messages.findIndex(
        (m) =>
          (message.id != null &&
            messageIdsMatch(m.id, message.id, m.provider, message.provider)) ||
          (message.wamid && m.wamid && m.wamid == message.wamid) ||
          (message.wamid && m.message_id && m.message_id == message.wamid) ||
          (message.id != null && m.wamid && m.wamid == message.id)
      );

      if (msgIndex !== -1) {
        const currentStatus = conversation.messages[msgIndex].status;
        const newStatus = normalizeMessageStatus(message.status);

        if (shouldApplyMessageStatus(currentStatus, newStatus)) {
          conversation.messages[msgIndex] = {
            ...conversation.messages[msgIndex],
            status: newStatus,
            ...(message.wamid && !conversation.messages[msgIndex].wamid
              ? { wamid: message.wamid }
              : {}),
          };

          conversation.messages = [...conversation.messages];

          const convIndex = conversations.value.findIndex(
            (c) => c.id === conversation.id
          );
          if (convIndex !== -1) {
            conversations.value[convIndex] = { ...conversation };
            conversations.value = [...conversations.value];
          }

          messageUpdateTrigger.value++;
        }
      }
    }
    return;
  }

  // Handle priority update (Standard)
  if (payload.type === "priority_updated") {
    const { phone, priority } = payload;

    const conversation = conversations.value.find((c) => c.wa_number === phone);

    if (conversation) {
      conversation.priority = parseInt(priority) || 0;
      conversations.value = [...conversations.value];
    }
    return;
  }

  // Or fallback if direct
  const conversationId = payload.conversation_id;
  const phone = payload.phone;
  const messageData = payload.message || payload; // if message is nested or flat

  const type = messageData.type || "text";
  const sender = messageData.sender || "customer";

  // WS payload uses `caption`; some paths use `media_caption`. Never keep `[image]` in bubble.
  const displayText = resolveMessageText(messageData);
  const name = payload.contact_name || payload.name;

  // Find or create conversation
  let conversation = conversations.value.find(
    (c) =>
      (conversationId && c.id == conversationId) ||
      (phone && c.wa_number == phone)
  );

  if (!conversation) {
    // New conversation — inbound customer message opens CSW
    conversation = {
      id: conversationId,
      wa_number: phone, // ✅ Add wa_number
      name: name || payload.phone || "Unknown User",
      kode_cabang: payload.kode_cabang || "00", // Set from payload
      cust_id: payload.cust_id || null,
      is_pelanggan: !!payload.is_pelanggan || Number(payload.cust_id) > 0,
      // priority: parseInt(payload.priority) || 0, // Legacy
      cases: [{ case: parseInt(payload.case || payload.priority || 0) }], // Initialize cases
      initials: (name || payload.phone || "?").substring(0, 1).toUpperCase(),
      color: getAvatarColor(conversationId),
      status: payload.status || "open",
      ycloud_open: messageData.line_key ? messageData.line_key === LINE_CS : messageData.provider !== "F" && messageData.provider !== LINE_ADMIN,
      fonnte_open: messageData.line_key ? messageData.line_key === LINE_ADMIN : messageData.provider === "F" || messageData.provider === LINE_ADMIN,
      line_csw: {},
      default_reply_line: messageData.line_key || providerToLineKey(messageData.provider),
      default_reply_channel: (messageData.line_key || providerToLineKey(messageData.provider)) === LINE_ADMIN ? "fonnte" : "ycloud",
      can_reply: true,
      messages: [],
      unread: 0,
    };
    conversations.value.unshift(conversation);
    applyLineCswOpen(conversation, messageData);
  } else {
    // Inbound message always re-opens CSW (DB already set status=open before WS push)
    if (sender === "customer" || payload.type === "wa_masuk") {
      conversation.status = payload.status || "open";
      applyLineCswOpen(conversation, messageData);
    } else if (payload.status) {
      conversation.status = payload.status;
    }
    // Update existing conversation details if available
    if (payload.kode_cabang) {
      conversation.kode_cabang = payload.kode_cabang;
    }
    if (payload.cust_id !== undefined) {
      conversation.cust_id = payload.cust_id;
      conversation.is_pelanggan = Number(payload.cust_id) > 0;
    }
    if (payload.is_pelanggan !== undefined) {
      conversation.is_pelanggan = !!payload.is_pelanggan;
    }
    if (payload.priority !== undefined) {
      conversation.priority = parseInt(payload.priority) || 0;
    }
    // Update assignment if provided
    if (payload.assignment_user_id !== undefined) {
      conversation.assignment_user_id = payload.assignment_user_id;
    }
    // Update case if provided. Prioritize 'active_cases' list from server for accuracy.
    if (Array.isArray(payload.active_cases) && payload.active_cases.length > 0) {
      const ids = sanitizeActiveCaseIds(payload.active_cases);
      conversation.cases = ids.map((c) => ({
        case: parseInt(c),
        status: "open",
      }));
    } else if (payload.case !== undefined && payload.case !== null) {
      const newCase = parseInt(payload.case);

      if (!conversation.cases) conversation.cases = [];

      if (newCase === 0) {
        // If 0 received explicitly without active_cases, it might mean reset or legacy
        // But usually we preserve existing if 0 is just "no update"
        // Assuming 0 means "Unknown/General" case here
        // conversation.cases = [{case: 0, status: 'open'}];
      } else {
        conversation.cases = mergeOpenCaseLocal(conversation.cases, newCase);
      }
    }
  }

  const msgProvider = resolveMessageProvider(messageData.provider, messageData.id);
  const newMsg = {
    id: canonicalMessageId(messageData.id, msgProvider) || Date.now(),
    wamid: messageData.wamid, // WhatsApp Message ID
    text: displayText, // Use the safe display text
    type: type,
    media_id: messageData.media_id,
    media_url: messageData.media_url,
    sender: sender,
    time: messageData.time
      ? new Date(messageData.time).toLocaleTimeString([], {
          hour: "2-digit",
          minute: "2-digit",
          hour12: false,
        })
      : new Date().toLocaleTimeString([], {
          hour: "2-digit",
          minute: "2-digit",
          hour12: false,
        }),
    rawTime: messageData.time || new Date().toISOString(), // Keep raw timestamp for date separator
    sender_code: messageData.sender_code, // Map sender_code from WebSocket payload
    quoted_message_id: messageData.quoted_message_id || null, // Quote reference
    quoted_message_body: messageData.quoted_message_body || null, // Quote content
    quoted_message_from: messageData.quoted_message_from || null, // Quote sender
    provider: msgProvider,
  };

  // Avoid duplicate messages if already present
  // Enhanced check: ID match OR (same sender + same text + within 2 seconds)
  const isDuplicate = conversation.messages.find((m) => {
    // Exact ID match — also Y-123 vs 123 (WS numeric vs REST prefixed)
    if (messageIdsMatch(m.id, newMsg.id, m.provider, newMsg.provider)) return true;

    // Wamid match (same channel only — Fonnte inboxid vs yCloud wamid must not collide)
    if (
      m.wamid &&
      newMsg.wamid &&
      String(m.wamid) === String(newMsg.wamid) &&
      (m.provider || "Y") === (newMsg.provider || "Y")
    )
      return true;

    // Outgoing media echo: same provider + type + close time
    if (
      m.sender === "me" &&
      newMsg.sender === "me" &&
      isMediaMessage(m) &&
      isMediaMessage(newMsg) &&
      m.type === newMsg.type &&
      (m.provider || "Y") === (newMsg.provider || "Y")
    ) {
      const time1 = new Date(m.rawTime || m.time).getTime();
      const time2 = new Date(newMsg.rawTime || newMsg.time).getTime();
      if (!isNaN(time1) && !isNaN(time2) && Math.abs(time1 - time2) < 15000) {
        return true;
      }
    }

    // Fuzzy match: same sender + same NORMALIZED text + close timestamp
    const normalize = (str) =>
      String(str || "")
        .replace(/\s+/g, " ")
        .trim();

    if (
      m.sender === newMsg.sender &&
      (m.provider || "Y") === (newMsg.provider || "Y") &&
      normalize(m.text) === normalize(newMsg.text)
    ) {
      // Check if timestamps are within 5 seconds of each other
      const time1 = new Date(m.rawTime || m.time).getTime();
      const time2 = new Date(newMsg.rawTime || newMsg.time).getTime();

      if (!isNaN(time1) && !isNaN(time2) && Math.abs(time1 - time2) < 5000) {
        return true;
      }
    }

    return false;
  });

  if (!isDuplicate) {
    // Simply push to array
    conversation.messages.push(newMsg);

    // Sort entire array by rawTime to ensure chronological order
    conversation.messages.sort((a, b) => {
      if (!a.rawTime || !b.rawTime) return 0;
      return new Date(a.rawTime) - new Date(b.rawTime);
    });

    // Re-sanitize entire conversation to be sure
    conversation.messages = sanitizeMessages(conversation.messages);

    applyConversationLastMessage(conversation, {
      message:
        displayText ||
        (sender === "me"
          ? "You: " + (mediaTypeLastMessageLabel(type) || "Message")
          : mediaTypeLastMessageLabel(type) || displayText || "Message"),
      time: newMsg.rawTime,
    });
    
    // Update local last_message_at if this is the active chat
    if (activeChatId.value == conversationId && newMsg.rawTime) {
      localLastMessageAt.value = newMsg.rawTime;
    }

    // Check visibility: Active ID matches AND (Desktop OR Mobile Chat View Open)
    const isChatVisible =
      activeChatId.value == conversationId &&
      (windowWidth.value >= 768 || showMobileChat.value);

    if (!isChatVisible) {
      conversation.unread++;

      // 🔊 Play notification sound for incoming customer messages
      if (sender === "customer") {
        playNotificationSound();
      }

      // 🆕 AUTO-OPEN FEATURE: Open conversation automatically for incoming customer messages
      if (
        autoOpenChatOnIncoming.value &&
        sender === "customer" &&
        windowWidth.value >= 768
      ) {
        selectChat(conversation.id);
      }
    } else {
      scrollToBottom();
      markMessagesRead(conversation.wa_number); // Use phone number, not conversation ID
    }

    // Move conversation to top AND force Vue reactivity
    const idx = conversations.value.findIndex((c) => c.id == conversation.id);
    if (idx > 0) {
      // Move to top: remove from current position and add to front
      const [moved] = conversations.value.splice(idx, 1);
      conversations.value.unshift(moved);
    }
    
    // FORCE REACTIVITY: Trigger Vue to detect the change by reassigning
    // This is needed because Vue sometimes doesn't detect deep mutations
    conversations.value = [...conversations.value];
  }
};

// Force disconnect old socket before reconnecting
const forceDisconnect = () => {
  if (socket.value) {
    // Remove listeners to prevent 'onclose' from triggering UI changes
    socket.value.onopen = null;
    socket.value.onmessage = null;
    socket.value.onerror = null;
    socket.value.onclose = null;
    try {
      // Send explicit disconnect message if socket is still open
      if (socket.value.readyState === WebSocket.OPEN) {
        socket.value.send(
          JSON.stringify({ type: "disconnect", reason: "reconnect" })
        );
      }
      socket.value.close();
    } catch (e) {
      /* ignore */
    }
    socket.value = null;
    lastDisconnectTime.value = Date.now();
  }
};

const clearLoginSession = () => {
  localStorage.removeItem("cms_chat_id");
  localStorage.removeItem("cms_chat_password");
  localStorage.removeItem("cms_chat_expiry");
  authId.value = "";
  wasConnected.value = false;
};

/** Handle WebSocket close 1008 — jangan semua dianggap "duplicate connection". */
const handleWsClose1008 = (event, isPreWelcome = false) => {
  const reason = String(event.reason || "");
  const kind = classifyWsClose1008(reason);
  const logTag = isPreWelcome ? "pre-welcome" : "connected";

  if (kind === "device_lock") {
    duplicateWarning.value = wsClose1008Message(reason, kind);
    connectionError.value = duplicateWarning.value;
    isReconnecting.value = false;
    showLoginPrompt.value = true;
    return;
  }

  if (kind === "unauthorized" || kind === "config_error") {
    duplicateRetryAttempts.value = 0;
    connectionError.value = wsClose1008Message(reason, kind);
    isReconnecting.value = false;
    showLoginPrompt.value = true;
    return;
  }

  if (kind === "lock_race") {
    console.warn(`WS lock race (${logTag}): ${reason || "(empty)"}`);
    connectionError.value = "Menghubungkan…";
    isReconnecting.value = true;
    setTimeout(() => {
      if (!isConnected.value && authId.value) connectWebSocket();
    }, isPreWelcome ? 800 : 1200);
    return;
  }

  const isRecentResume = Date.now() - resumeTimestamp.value < 5000;
  if (isRecentResume) {
    console.warn(`WS resume grace (${logTag}), retrying…`);
    setTimeout(() => {
      if (!isConnected.value && authId.value) connectWebSocket();
    }, 1500);
    return;
  }

  if (kind === "verify_failed") {
    duplicateRetryAttempts.value++;
    const attempt = duplicateRetryAttempts.value;
    console.warn(`WS verify failed (${logTag}, attempt ${attempt})`);
    connectionError.value = `Verifikasi koneksi… (${attempt})`;
    isReconnecting.value = true;
    setTimeout(() => {
      if (!isConnected.value && authId.value) connectWebSocket();
    }, 2000);
    return;
  }

  if (duplicateRetryAttempts.value < maxDuplicateRetries) {
    duplicateRetryAttempts.value++;
    const attempt = duplicateRetryAttempts.value;
    console.warn(`WS reconnect (${logTag}, attempt ${attempt})`);
    connectionError.value = `Reconnecting... (${attempt})`;
    isReconnecting.value = true;
    setTimeout(() => {
      if (!isConnected.value && authId.value) connectWebSocket();
    }, duplicateRetryDelay);
    return;
  }

  duplicateRetryAttempts.value = 0;
  duplicateWarning.value =
    "Koneksi gagal setelah beberapa percobaan. Silakan login ulang.";
  connectionError.value = duplicateWarning.value;
  clearLoginSession();
  isReconnecting.value = false;
  showLoginPrompt.value = true;
};

const connectWebSocket = () => {
  if (!authId.value) return;

  // Cleanup existing socket to prevent zombies
  forceDisconnect();

  try {
    // Always connect to Production Server (as per user workflow)
    const deviceId = encodeURIComponent(getDeviceId());
    const wsUrl = `wss://waserver.nalju.com?id=${encodeURIComponent(authId.value.trim())}&device=${deviceId}`;
    const ws = new WebSocket(wsUrl);
    socket.value = ws;

    // Connection Timeout: Force close if not connected within 10s
    const connTimeout = setTimeout(() => {
      if (ws.readyState !== WebSocket.OPEN) {
        console.warn("WebSocket connection timed out (10s), forcing close...");
        ws.close();
      }
    }, 10000);

    ws.onopen = () => {
      // Guard: Ignore if this is not the latest socket
      if (socket.value && ws !== socket.value) return;

      clearTimeout(connTimeout);
      
      // NOTE: isConnected is NOT set to true here!
      // Server might immediately reject with 1008 after onopen.
      // isConnected will be set to true in welcome message handler.
      isConnecting.value = false;

      // Mark as successfully connected (for reconnect logic)
      // Note: isReconnecting, duplicateRetryAttempts, reconnectAttempts are NOT reset here
      // because server might immediately close with 1008 after onopen.
      // They are reset in welcome message handler to prevent banner flicker.
      wasConnected.value = true;

      // Save session (3 days)
      const expiry = new Date().getTime() + 3 * 24 * 60 * 60 * 1000;
      localStorage.setItem("cms_chat_id", authId.value);
      localStorage.setItem("cms_chat_expiry", expiry.toString());

      showLoginPrompt.value = false;
    };

    ws.onmessage = (event) => {
      try {
        const payload = JSON.parse(event.data);

        // EFFICIENCY: Reset 30s polling timer whenever specific events arrive
        // If we get real-time data, we don't need to poll immediately
        if (
          [
            "status_update",
            "message_deleted",
            "wa_masuk",
            "case_updated",
            "case_resolved",
            "conversation_read",
          ].includes(payload.type)
        ) {
          resetPollingTimer();
        }

        // Handle Connection Welcome
        // This is the REAL confirmation that connection is stable
        // (server sends this only after accepting the connection)
        if (payload.type === "connection") {
          // Check if this is a RECONNECT (not first connect)
          const wasReconnecting = isReconnecting.value || reconnectAttempts.value > 0;
          
          // NOW we can safely set isConnected to true
          isConnected.value = true;
          connectionError.value = ""; // Clear any error message
          
          // Reset all retry counters - connection is now truly stable
          duplicateRetryAttempts.value = 0;
          reconnectAttempts.value = 0;
          reconnectDelay.value = 3000;
          isReconnecting.value = false;
          
          if (payload.role) {
            currentUserRole.value = payload.role;
            localStorage.setItem("cms_chat_role", payload.role);
          }
          
          // 🔄 AUTO-SYNC: Refresh conversations after reconnect to catch missed messages
          // Only do this on RECONNECT (not first connect) to avoid duplicate fetch
          if (wasReconnecting) {
            // Small delay to ensure connection is stable
            setTimeout(() => {
              fetchConversations();
              // Also refresh active chat messages if viewing one
              if (activeChatId.value) {
                const activeConv = conversations.value.find(c => c.id == activeChatId.value);
                if (activeConv) {
                  fetchMessages(activeConv.wa_number);
                }
              }
            }, 500);
          }
          
          return;
        }

        if (payload.type === "status_update") {
          handleIncomingMessage(payload);
          return;
        }

        if (payload.type === "message_deleted") {
          const { conversation_id, message, phone } = payload;
          if (!message) return;

          const digits = (p) => String(p || "").replace(/\D/g, "").slice(-10);
          const conversation = conversations.value.find(
            (c) =>
              (conversation_id && c.id == conversation_id) ||
              (phone && c.wa_number == phone) ||
              (phone &&
                digits(c.wa_number) &&
                digits(c.wa_number) === digits(phone))
          );

          if (conversation && Array.isArray(conversation.messages)) {
            const msgIndex = conversation.messages.findIndex(
              (m) =>
                (message.id != null && m.id == message.id) ||
                (message.wamid && m.wamid && m.wamid == message.wamid) ||
                (message.wamid &&
                  m.message_id &&
                  m.message_id == message.wamid)
            );

            if (msgIndex !== -1) {
              conversation.messages.splice(msgIndex, 1);
              conversation.messages = [...conversation.messages];

              const convIndex = conversations.value.findIndex(
                (c) => c.id === conversation.id
              );
              if (convIndex !== -1) {
                conversations.value[convIndex] = { ...conversation };
                conversations.value = [...conversations.value];
              }

              messageUpdateTrigger.value++;
            }
          }
          return;
        }

        // Handle Read Receipt Sync
        if (payload.type === "conversation_read") {
          const conv = conversations.value.find(
            (c) =>
              (payload.conversation_id && c.id == payload.conversation_id) ||
              (payload.phone && c.wa_number == payload.phone)
          );
          if (conv) {
            conv.unread = 0;
          }
          return;
        }

        // Handle Case Update (Replaces Priority Update)
        // Handle Case Update
        if (payload.type === "case_updated") {
          const normalizePhone = (phone) => {
            if (!phone) return "";
            return phone.toString().replace(/\D/g, "");
          };

          const targetPhone = normalizePhone(payload.phone);
          const conv = conversations.value.find(
            (c) => normalizePhone(c.wa_number) === targetPhone
          );

          if (conv) {
            const newC = parseInt(payload.case);

            if (!conv.cases) conv.cases = [];

            if (newC === 0) {
              // Reset/Clear all active? usually 0 means "reset"
              conv.cases = [{ case: 0, status: "open" }];
            } else {
              conv.cases = mergeOpenCaseLocal(conv.cases || [], newC);
            }
          } else {
            fetchConversations(); // Reload to get new conversation
          }

          return;
        }

        if (payload.type === "case_resolved") {
          const normalizePhone = (phone) => {
            if (!phone) return "";
            return phone.toString().replace(/\D/g, "");
          };
          const targetPhone = normalizePhone(payload.phone);
          const conv = conversations.value.find(
            (c) => normalizePhone(c.wa_number) === targetPhone
          );

          if (conv && conv.cases) {
            // Update status to closed
            const resolvedCase = parseInt(payload.case);
            const target = conv.cases.find((x) => x.case === resolvedCase);
            if (target) {
              target.status = "closed";
            }
          }
          return;
        }

        // Handle Agent Message Sent (from other devices)
        if (payload.type === "agent_message_sent") {
          const conversationId = payload.conversation_id;
          const messageData = payload.message;
          const senderId = payload.sender_id;

          let conversation = conversations.value.find(
            (c) =>
              (conversationId && c.id == conversationId) ||
              (payload.phone && c.wa_number == payload.phone)
          );

          // Outbound-only chat (e.g. template) may not be in crew list yet — create sidebar row
          if (!conversation && (conversationId || payload.phone)) {
            const name = payload.contact_name || payload.phone || "Unknown User";
            const previewText =
              messageData?.type === "image"
                ? "You: 📷 Image"
                : messageData?.type === "template"
                  ? "You: " + (messageData.text || "Template")
                  : "You: " + (messageData?.text || "");
            conversation = {
              id: conversationId || payload.phone,
              wa_number: payload.phone,
              name,
              kode_cabang: payload.kode_cabang || "00",
              cust_id: payload.cust_id || null,
              is_pelanggan: !!payload.is_pelanggan || Number(payload.cust_id) > 0,
              cases: [],
              initials: name.substring(0, 1).toUpperCase(),
              color: getAvatarColor(conversationId || payload.phone),
              status: payload.status || "closed",
              assignment_user_id: payload.assignment_user_id ?? null,
              lastMessage: previewText,
              lastTime: messageData?.time
                ? new Date(messageData.time).toLocaleTimeString([], {
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: false,
                  })
                : "",
              lastMessageTime: messageData?.time || null,
              unread: 0,
              messages: [],
            };
            conversations.value.unshift(conversation);
          }

          if (conversation) {
            if (payload.assignment_user_id !== undefined) {
              conversation.assignment_user_id = payload.assignment_user_id;
            }
            if (payload.kode_cabang) {
              conversation.kode_cabang = payload.kode_cabang;
            }
            if (payload.cust_id !== undefined) {
              conversation.cust_id = payload.cust_id;
            }
            if (payload.contact_name) {
              conversation.name = payload.contact_name;
            }

            const isTempId = (id) => /^\d{13,}$/.test(String(id || ""));
            const isOptimistic = (m) =>
              m?.status === "pending" ||
              isTempId(m?.id) ||
              String(m?.media_url || "").startsWith("data:");
            const normalizeText = (str) =>
              String(str || "")
                .replace(/\s+/g, " ")
                .trim();

            // Enhanced duplicate check: ID, wamid, OR media_url for images
            let existingMessage = conversation.messages.find(
              (m) =>
                messageIdsMatch(
                  m.id,
                  messageData.id,
                  m.provider,
                  messageData.provider
                ) ||
                (m.wamid &&
                  messageData.wamid &&
                  m.wamid == messageData.wamid) ||
                (messageData.type === "image" &&
                  m.media_url &&
                  messageData.media_url &&
                  m.media_url == messageData.media_url)
            );

            // Match optimistic outgoing bubble (temp id / data: preview / pending)
            if (!existingMessage) {
              const incomingText = normalizeText(messageData.text);
              const incomingType = messageData.type || "text";

              if (incomingType === "image") {
                // FIFO: oldest optimistic image without wamid (avoid swapping 2 quick sends)
                const start = Math.max(0, conversation.messages.length - 8);
                for (let i = start; i < conversation.messages.length; i++) {
                  const m = conversation.messages[i];
                  if (
                    m.sender === "me" &&
                    (m.type || "text") === "image" &&
                    isOptimistic(m) &&
                    !m.wamid &&
                    normalizeText(m.text) === incomingText
                  ) {
                    existingMessage = m;
                    break;
                  }
                }
              } else {
                for (let i = conversation.messages.length - 1; i >= 0; i--) {
                  if (conversation.messages.length - i > 8) break;
                  const m = conversation.messages[i];
                  if (m.sender !== "me" || !isOptimistic(m)) continue;
                  if (
                    (m.type || "text") === incomingType &&
                    normalizeText(m.text) === incomingText
                  ) {
                    existingMessage = m;
                    break;
                  }
                }
              }
            }

            if (existingMessage) {
              // Update existing message (from optimistic UI after API response)
              const canonId = canonicalMessageId(
                messageData.id,
                messageData.provider
              );
              if (canonId) existingMessage.id = canonId;
              existingMessage.wamid = messageData.wamid;
              if (shouldApplyMessageStatus(existingMessage.status, messageData.status || "sent")) {
                existingMessage.status = normalizeMessageStatus(messageData.status || "sent");
              }
              if (messageData.media_url && !String(messageData.media_url).startsWith("data:"))
                existingMessage.media_url = messageData.media_url;
              // Update sender_code if provided (from message or payload level)
              const senderCode = messageData.sender_code ?? payload.sender_code;
              if (senderCode !== undefined)
                existingMessage.sender_code = senderCode;
              const msgIdx = conversation.messages.indexOf(existingMessage);
              if (msgIdx !== -1) {
                conversation.messages.splice(msgIdx, 1, { ...existingMessage });
              }
              conversation.messages = sanitizeMessages(conversation.messages);
              messageUpdateTrigger.value++;
              // Don't add as new - already exists
            } else {
              // NEW DEFENSE: Robust Fuzzy Match — hanya untuk bubble optimistic/temp
              // Jangan gabungkan dua outbound nyata dengan teks sama (mis. autoreply berulang)
              let pendingMatch = null;
              const cleanIncomingText = normalizeText(messageData.text);
              const isTempId = (id) => /^\d{13,}$/.test(String(id || ""));
              const isOptimisticMsg = (m) =>
                m?.status === "pending" ||
                isTempId(m?.id) ||
                String(m?.media_url || "").startsWith("data:");

              // Scan last 8 messages
              for (let i = conversation.messages.length - 1; i >= 0; i--) {
                if (conversation.messages.length - i > 8) break;

                const m = conversation.messages[i];
                if (!isOptimisticMsg(m)) continue;
                const cleanLocalText = normalizeText(m.text);

                if (m.sender === "me" && cleanLocalText === cleanIncomingText) {
                  pendingMatch = m;
                  break;
                }
              }

              if (pendingMatch) {
                // Update IDs to server values
                pendingMatch.id =
                  canonicalMessageId(messageData.id, messageData.provider) ||
                  messageData.id;
                if (messageData.wamid) pendingMatch.wamid = messageData.wamid;
                if (shouldApplyMessageStatus(pendingMatch.status, messageData.status || "sent")) {
                  pendingMatch.status = normalizeMessageStatus(messageData.status || "sent");
                }
                if (messageData.media_url && !String(messageData.media_url).startsWith("data:"))
                  pendingMatch.media_url = messageData.media_url;
                // Update sender_code if provided (from message or payload level)
                const senderCode = messageData.sender_code ?? payload.sender_code;
                if (senderCode !== undefined)
                  pendingMatch.sender_code = senderCode;
                const pendingIdx = conversation.messages.indexOf(pendingMatch);
                if (pendingIdx !== -1) {
                  conversation.messages.splice(pendingIdx, 1, { ...pendingMatch });
                }
                conversation.messages = sanitizeMessages(conversation.messages);
                messageUpdateTrigger.value++;
                return; // Stop, don't add new
              }

              // Echo defense: WS ganda (id DB dari WhatsAppService + id provider dari push lama)
              // Merge jika teks sama, jendela pendek, dan wamid tidak konflik
              const incomingText = normalizeText(messageData.text);
              const incomingType = messageData.type || "text";
              const incomingWamid = messageData.wamid
                ? String(messageData.wamid)
                : "";
              const incomingTime = new Date(messageData.time).getTime();
              let echoMatch = null;
              for (let i = conversation.messages.length - 1; i >= 0; i--) {
                if (conversation.messages.length - i > 10) break;
                const m = conversation.messages[i];
                if (m.sender !== "me") continue;
                if ((m.type || "text") !== incomingType) continue;
                if (normalizeText(m.text) !== incomingText || incomingText === "")
                  continue;
                const mw = m.wamid ? String(m.wamid) : "";
                if (mw && incomingWamid && mw !== incomingWamid) continue;
                const mt = new Date(m.rawTime || m.time).getTime();
                if (
                  !isNaN(mt) &&
                  !isNaN(incomingTime) &&
                  Math.abs(mt - incomingTime) > 12000
                ) {
                  continue;
                }
                echoMatch = m;
                break;
              }
              if (echoMatch) {
                const canonEchoId = canonicalMessageId(
                  messageData.id,
                  messageData.provider
                );
                if (canonEchoId && !isProviderPrefixedId(echoMatch.id)) {
                  echoMatch.id = canonEchoId;
                } else if (messageData.id != null && echoMatch.id == null) {
                  echoMatch.id = canonEchoId || messageData.id;
                }
                if (messageData.wamid && !echoMatch.wamid)
                  echoMatch.wamid = messageData.wamid;
                if (
                  shouldApplyMessageStatus(
                    echoMatch.status,
                    messageData.status || "sent"
                  )
                ) {
                  echoMatch.status = normalizeMessageStatus(
                    messageData.status || "sent"
                  );
                }
                const senderCode =
                  messageData.sender_code ?? payload.sender_code;
                if (senderCode !== undefined)
                  echoMatch.sender_code = senderCode;
                const echoIdx = conversation.messages.indexOf(echoMatch);
                if (echoIdx !== -1) {
                  conversation.messages.splice(echoIdx, 1, { ...echoMatch });
                }
                conversation.messages = sanitizeMessages(conversation.messages);
                messageUpdateTrigger.value++;
                return;
              }

              // Own echo (same agent): never add a second bubble
              if (senderId == authId.value) {
                return;
              }

              // Add new message (from another agent/device / system outbound)
              const outProvider = resolveMessageProvider(messageData.provider, messageData.id);
              const newMsg = {
                id:
                  canonicalMessageId(messageData.id, outProvider) ||
                  messageData.id,
                wamid: messageData.wamid,
                text: messageData.text,
                type: messageData.type || "text",
                media_url: messageData.media_url,
                sender: "me",
                time: new Date(messageData.time).toLocaleTimeString([], {
                  hour: "2-digit",
                  minute: "2-digit",
                  hour12: false,
                }),
                rawTime: messageData.time,
                status: normalizeMessageStatus(messageData.status || "sent"),
                sender_code: messageData.sender_code || payload.sender_code || null, // Use message sender_code or fallback to payload level
                quoted_message_id: messageData.quoted_message_id || null,
                quoted_message_body: messageData.quoted_message_body || null,
                quoted_message_from: messageData.quoted_message_from || null,
                provider: outProvider,
              };

              conversation.messages.push(newMsg);
              conversation.messages = sanitizeMessages(conversation.messages);

              // Sort messages by rawTime to ensure chronological order
              conversation.messages.sort((a, b) => {
                if (!a.rawTime || !b.rawTime) return 0;
                return new Date(a.rawTime) - new Date(b.rawTime);
              });

              applyConversationLastMessage(conversation, {
                message:
                  messageData.type === "image"
                    ? "You: 📷 Image"
                    : "You: " + messageData.text,
                time: messageData.time,
              });
              
              // Update local last_message_at if this is the active chat
              if (activeChatId.value == conversationId && messageData.time) {
                localLastMessageAt.value = messageData.time;
              }

              // Auto-scroll if viewing this conversation
              if (activeChatId.value == conversationId) {
                scrollToBottom();
              }
            }
          }
          return;
        }

        if (payload.type === "wa_masuk") {
          // Real incoming WA message (wrapped format). Ignore if inner payload is a system event.
          const inner = payload.data || payload;
          if (
            inner?.type &&
            [
              "case_updated",
              "case_resolved",
              "conversation_read",
              "agent_message_sent",
              "status_update",
            ].includes(inner.type)
          ) {
            return;
          }
          handleIncomingMessage(inner);
        } else if (
          (payload.conversation_id || payload.conversationId) &&
          (!payload.type || payload.type === "wa_masuk")
        ) {
          // Direct message format only — never treat case_updated/etc as chat
          handleIncomingMessage(payload);
        }
      } catch (e) {
        console.error("Error parsing WS message", e);
      }
    };

    ws.onclose = (event) => {
      // Guard: Ignore if this is not the latest socket
      if (socket.value && ws !== socket.value) return;

      if (isConnected.value) {
        isConnected.value = false;

        // Same-device reclaim on server — this socket was replaced; ignore if stale
        if (event.code === 4000) {
          return;
        }

        // Device lock / unauthorized (1008)
        if (event.code === 1008) {
          handleWsClose1008(event, false);
          return;
        } else if (
          wasConnected.value &&
          reconnectAttempts.value < maxReconnectAttempts
        ) {
          // AUTO-RECONNECT: If was connected and not auth error, try reconnecting
          isReconnecting.value = true;
          reconnectAttempts.value++;

          // Calculate exponential backoff delay (max 30 seconds)
          const delay = Math.min(
            reconnectDelay.value * Math.pow(1.5, reconnectAttempts.value - 1),
            30000
          );

          connectionError.value = `Reconnecting...`;

          setTimeout(() => {
            if (authId.value && !isConnected.value) {
              connectWebSocket();
            } else {
              // Cannot reconnect - no credentials or already connected
              isReconnecting.value = false;
              connectionError.value = "";
            }
          }, delay);
        } else {
          // Max attempts reached - stop reconnecting, fall back to polling
          isReconnecting.value = false;
          connectionError.value =
            "⚠️ WebSocket terputus. Polling backup aktif (update setiap 30 detik)";
        }
      } else {
        // Connection failed during attempt (isConnected was still false)
        isConnecting.value = false;
        
        let msg = "Connection failed.";
        
        // IMPORTANT: Handle 1008 (duplicate connection) the same way as when isConnected was true
        // This happens when server rejects BEFORE sending welcome message
        if (event.code === 4000) {
          return;
        }

        if (event.code === 1008) {
          handleWsClose1008(event, true);
          return;
        } else if (
          event.code === 1006 &&
          wasConnected.value &&
          reconnectAttempts.value < maxReconnectAttempts
        ) {
          // Network error during reconnect attempt - keep trying
          isReconnecting.value = true;
          reconnectAttempts.value++;
          const delay = Math.min(
            reconnectDelay.value * Math.pow(1.5, reconnectAttempts.value - 1),
            30000
          );

          const statusMsg = `Reconnecting...`;
          msg = statusMsg;
          connectionError.value = statusMsg; // FORCE UPDATE UI

          setTimeout(() => {
            if (authId.value && !isConnected.value) {
              connectWebSocket();
            } else if (!authId.value) {
              // No ID, show login
              isReconnecting.value = false;
              showLoginPrompt.value = true;
            } else {
              // Already connected
              isReconnecting.value = false;
              connectionError.value = "";
            }
          }, delay);
        } else {
          // Max attempts reached or unknown error - show login
          isReconnecting.value = false;
          if (event.code === 1006) {
            msg = "Koneksi terputus. Silakan login ulang.";
          } else if (event.reason) {
            msg = `Error: ${event.reason}`;
          }
          showLoginPrompt.value = true;
        }
        connectionError.value = msg;
      }
    };

    ws.onerror = (err) => {
      // console.error('WS Error', err);
    };
  } catch (e) {
    console.error(e);
  }
};


const handleTouchStart = (e) => {
  touchStartX.value = e.touches[0].screenX;
  touchStartY.value = e.touches[0].screenY;
  isDragging.value = false;
};

const handleTouchMove = (e) => {
  if (!showMobileChat.value) return;

  const currentX = e.touches[0].screenX;
  const currentY = e.touches[0].screenY;

  const diffX = currentX - touchStartX.value;
  const diffY = currentY - touchStartY.value;

  // Only start dragging if substantially horizontal
  if (!isDragging.value) {
    if (diffX > 10 && Math.abs(diffX) > Math.abs(diffY)) {
      isDragging.value = true;
    }
  }

  if (isDragging.value && diffX > 0) {
    // Prevent default scrolling only if we are dragging horizontally
    if (e.cancelable) e.preventDefault();
    touchOffset.value = diffX;
  }
};

const handleTouchEnd = (e) => {
  if (!showMobileChat.value) return;

  if (isDragging.value) {
    // Enable transition for the snap/exit animation
    isDragging.value = false;

    // If dragged more than 25% of screen width, close it
    const screenWidth = window.innerWidth;
    if (touchOffset.value > screenWidth * 0.25) {
      // Animate out to the right
      touchOffset.value = screenWidth;

      // Wait for transition (300ms) to finish before unmounting/hiding
      setTimeout(() => {
        backToMenu(false); // No animation needed - already animated above
        // Reset offset after hidden
        setTimeout(() => {
          touchOffset.value = 0;
        }, 50);
      }, 300);
    } else {
      // Snap back to 0
      touchOffset.value = 0;
    }
  }
};

// Mock incoming message for demonstration
const mockIncomingMessage = () => {
  // Mock disabled - using real API data
};

// --- Persistence DISABLED ---
// localStorage cache removed to ensure 100% accurate data from server
// Data will always be fresh from storage logic below

// --- STATE PERSISTENCE HELPERS ---
// Persist critical UI state for Android sleep/resume recovery
const persistChatState = () => {
  if (activeChatId.value) {
    localStorage.setItem("active_chat_id", activeChatId.value.toString());
    localStorage.setItem(
      "show_mobile_chat",
      showMobileChat.value ? "true" : "false"
    );
  } else {
    localStorage.removeItem("active_chat_id");
    localStorage.removeItem("show_mobile_chat");
  }
};

// Fetch a specific conversation by ID (for resume scenarios)
const fetchConversationById = async (id) => {
  try {
    const userIdParam = authId.value ? `user_id=${authId.value}` : "";
    const query = userIdParam
      ? `?${userIdParam}&conversation_id=${id}&_t=${Date.now()}`
      : `?conversation_id=${id}&_t=${Date.now()}`;

    const response = await fetch(
      `${API_BASE}/CRM/Chat/getConversations${query}`
    );

    if (!response.ok) {
      console.error("Failed to fetch conversation by ID:", response.statusText);
      return null;
    }

    const result = await response.json();
    const conversationsData = result.data?.conversations || result.data || [];

    if (result.status && Array.isArray(conversationsData) && conversationsData.length > 0) {
      return conversationsData[0]; // Return the first (and should be only) conversation
    }

    return null;
  } catch (error) {
    console.error("Error fetching conversation by ID:", error);
    return null;
  }
};

const resumeChatState = async () => {
  const savedId = localStorage.getItem("active_chat_id");
  const savedMobileChat = localStorage.getItem("show_mobile_chat");

  if (savedId && !activeChatId.value) {
    const id = parseInt(savedId);
    
    // First check if conversation exists in current list
    let chat = conversations.value.find((c) => c.id === id);
    
    // If not found, try to fetch it specifically (for search results that aren't in first 20)
    if (!chat) {
      const fetchedChat = await fetchConversationById(id);
      
      if (fetchedChat) {
        // Add fetched conversation to conversations list
        // Use the same processing logic as fetchConversations
        const existingMap = new Map(conversations.value.map((c) => [c.id, c]));
        
        // Process the fetched conversation (same logic as fetchConversations)
        const parseCases = (c) => {
          let cases = [];
          // 1. Try case_history (array from backend)
          if (Array.isArray(c.case_history)) {
            cases = c.case_history;
          }
          // 2. Try parsing raw 'conv_case' OR 'case' column if string JSON
          else {
            const rawCase = c.conv_case || c.case;
            if (
              typeof rawCase === "string" &&
              (rawCase.startsWith("[") || rawCase.startsWith("{"))
            ) {
              try {
                const parsed = JSON.parse(rawCase);
                if (Array.isArray(parsed)) cases = parsed;
                else if (parsed.case) cases = [parsed];
              } catch (e) {}
            }

            // 3. Fallback: Legacy Priority/Case Val (Only if still empty)
            if (cases.length === 0 && (c.priority > 0 || c.case_val > 0)) {
              cases = [{ case: parseInt(c.priority || c.case_val || 0) }];
            }
          }

          // Filter out 0 case if there are others, or just keep distinct
          // FIX: Deduplicate cases - keep only latest open entry per case value
          const dedupedCases = [];
          const seenCases = new Map(); // Map<caseValue, caseEntry>

          // Process in order (already sorted by timestamp in backend)
          for (const cse of cases) {
            const caseVal = parseInt(cse.case);
            if (isNaN(caseVal) || caseVal === 0) continue;

            const status = cse.status || "open";

            // Normalize the case object - ensure case is integer
            const normalizedCase = { ...cse, case: caseVal };

            if (!seenCases.has(caseVal)) {
              // First occurrence of this case value
              seenCases.set(caseVal, normalizedCase);
            } else {
              // Already seen - prefer open over closed, and newer timestamp
              const existing = seenCases.get(caseVal);
              const existingStatus = existing.status || "open";

              // If existing is closed but new is open, replace
              if (existingStatus === "closed" && status !== "closed") {
                seenCases.set(caseVal, normalizedCase);
              }
              // If both are open/both are closed, keep the newer one (later in array = newer)
              else if (existingStatus === status) {
                seenCases.set(caseVal, normalizedCase);
              }
            }
          }

          return enforceCaseFourExclusivity(Array.from(seenCases.values()));
        };
        
        const c = fetchedChat;
        let convo = existingMap.get(c.id);
        
        if (!convo) {
          convo = {
            id: c.id,
            wa_number: c.wa_number,
            name: c.contact_name || c.wa_number,
            kode_cabang: c.kode_cabang,
            cust_id: c.cust_id,
            is_pelanggan: !!c.is_pelanggan || Number(c.cust_id) > 0,
            cases: parseCases(c),
            initials: (c.contact_name || c.wa_number || "?")
              .substring(0, 1)
              .toUpperCase(),
            color: getAvatarColor(c.id),
            status: c.status,
            lastMessage: c.last_message || c.last_message_text || "No messages yet",
            lastTime: formatLastTime(c.last_message_time),
            lastMessageTime: c.last_message_time,
            unread: parseInt(c.unread_count) || 0,
            assignment_user_id: c.assigned_user_id,
            messages: [],
            hasMoreMessages: false,
            messageOffset: 0,
          };
          
          conversations.value.push(convo);
          chat = convo;
        } else {
          // Update existing
          convo.wa_number = c.wa_number;
          convo.name = c.contact_name || c.wa_number;
          convo.kode_cabang = c.kode_cabang;
          convo.cust_id = c.cust_id;
          convo.is_pelanggan = !!c.is_pelanggan || Number(c.cust_id) > 0;
          convo.cases = parseCases(c);
          convo.initials = (c.contact_name || c.wa_number || "?")
            .substring(0, 1)
            .toUpperCase();
          convo.color = getAvatarColor(c.id);
          convo.status = c.status;
          convo.line_csw = c.line_csw || {};
          convo.default_reply_line = c.default_reply_line || null;
          convo.ycloud_open = !!c.ycloud_open;
          convo.fonnte_open = !!c.fonnte_open;
          convo.default_reply_channel = c.default_reply_channel || null;
          convo.can_reply = c.can_reply ?? (convo.ycloud_open || convo.fonnte_open);
          applyConversationLastMessage(convo, {
            message: c.last_message || c.last_message_text || "No messages yet",
            time: c.last_message_time,
          });
          convo.unread = parseInt(c.unread_count) || 0;
          convo.assignment_user_id = c.assigned_user_id;
        }
      }
    }
    
    // Now check again if chat exists
    if (chat) {
      selectChat(id);

      // CRITICAL: Restore showMobileChat state for mobile devices
      // This ensures back button handler works correctly after Android resume
      if (savedMobileChat === "true" && windowWidth.value < 768) {
        showMobileChat.value = true;
        // Re-push history state to ensure back button works after long background
        window.history.pushState({ chatOpen: true }, "", "#chat=" + id);
      }
    } else {
      // Conversation not found even after fetch - clear saved state and return to home
      console.warn(`Saved chat id ${id} not found, clearing state and returning to home`);
      clearSavedChatState();
      // Clear search query when conversation not found on resume
      searchQuery.value = "";
      if (showMobileChat.value) {
        backToMenu(false); // Return to home if chat view is open
      }
    }
  } else if (activeChatId.value && showMobileChat.value && windowWidth.value < 768) {
    // Chat already open - verify it still exists
    let chat = conversations.value.find((c) => c.id === activeChatId.value);
    
    // If not found, try to fetch it
    if (!chat) {
      const fetchedChat = await fetchConversationById(activeChatId.value);
      
      if (!fetchedChat) {
        // Current chat no longer exists - return to home
        console.warn(`Active chat id ${activeChatId.value} not found, returning to home`);
        clearSavedChatState();
        // Clear search query when conversation not found on resume
        searchQuery.value = "";
        backToMenu(false);
        return;
      }
      
      // Add fetched conversation (same logic as above would go here, but simplified)
      // For now, just return to home if not in list
      console.warn(`Active chat id ${activeChatId.value} not in list, returning to home`);
      clearSavedChatState();
      // Clear search query when conversation not found on resume
      searchQuery.value = "";
      backToMenu(false);
    } else {
      // Chat still exists - just ensure history state exists for back button
      window.history.pushState({ chatOpen: true }, "", "#chat=" + activeChatId.value);
    }
  }
};

// ============================================================================
// Watch for search query changes (debounced server-side search)
// ============================================================================
watch(searchQuery, (newQuery) => {
  // Clear existing timer
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer);
  }
  
  // Reset loading state when typing
  isSearching.value = false;
  
  // Reset pagination when search changes
  conversationsOffset.value = 0;
  
  const trimmedQuery = (newQuery || '').trim();
  
  // If empty (search cleared), reset to home view
  if (!trimmedQuery) {
    // Close any open chat and return to conversation list
    activeChatId.value = null;
    showMobileChat.value = false;
    
    // Fetch default 20 conversations (reset from search results)
    fetchConversations(0, 20, '');
    
    return;
  }
  
  // If less than 2 characters, don't search yet
  if (trimmedQuery.length < 2) {
    return;
  }
  
  // Debounce: wait 500ms after user stops typing
  searchDebounceTimer = setTimeout(async () => {
    isSearching.value = true;
    try {
      await fetchConversations(0, 20, trimmedQuery);
    } finally {
      isSearching.value = false;
    }
  }, 500);
});

onMounted(() => {
  // ============================================
  // 📦 RESTORE NAVIGATION STATE (Anti-SLEEP)
  // ============================================
  const restoredState = navStore.restore();
  
  // Set pending restore chat ID - will be processed after conversations are fetched
  if (restoredState.current === 'chat' && restoredState.chatId) {
    pendingRestoreChatId.value = restoredState.chatId;
  } else {
    // Start at root (conversation list)
    activeChatId.value = null;
    showMobileChat.value = false;
  }
  
  // Clean up legacy recovery mode flags
  sessionStorage.removeItem('android_recovery_mode');
  sessionStorage.removeItem('android_recovery_chat_id');
  sessionStorage.removeItem('back_recovery_performed');
  
  // Clean up legacy localStorage
  localStorage.removeItem('active_chat_id');
  localStorage.removeItem('show_mobile_chat');
  
  // Clear URL hash if present
  if (window.location.hash && window.location.hash.startsWith('#chat=')) {
    window.history.replaceState({appRoot: true}, '', window.location.pathname);
  }
  
  // Check for Deep Link / Notification Click (URL Param)
  const urlParams = new URLSearchParams(window.location.search);
  const deepLinkPhone = urlParams.get("phone");
  if (deepLinkPhone) {
    pendingTargetPhone.value = deepLinkPhone;
    // Clean URL silently
    window.history.replaceState({}, document.title, "/");
  }

  // Add Paste Listener

  window.addEventListener("paste", handlePaste);

  scrollToBottom({ force: true });

  // --- LOADING TIMEOUT SAFETY NET ---
  // Only force login if truly idle (no auth / not reconnecting)
  setTimeout(() => {
    if (
      !isConnected.value &&
      !showLoginPrompt.value &&
      !isConnecting.value &&
      !isReconnecting.value &&
      !authId.value
    ) {
      showLoginPrompt.value = true;
    }
  }, 5000);

  // --- NO CACHE LOADING ---
  // Always fetch fresh data from server for 100% accuracy

  // --- VISIBILITY CHANGE HANDLER ---
  // Keep socket alive in background when possible; only reconnect if dead on resume.
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {

      // Clear search query on resume
      searchQuery.value = "";

      // Update resume timestamp FIRST
      resumeTimestamp.value = Date.now();

      // Check if socket is dead or not connected
      if (!socket.value || socket.value.readyState !== WebSocket.OPEN) {

        reconnectAttempts.value = 0;
        reconnectDelay.value = 3000;
        isReconnecting.value = true;
        connectionError.value = "Reconnecting...";

        scheduleResumeReconnect(500);
      } else {
      }

      // Refresh data to ensure sync
      fetchConversations().then(() => {
        resumeChatState();
      });
    } else if (document.visibilityState === "hidden") {
      // Do NOT force-disconnect — keeps session stable and avoids false "device locked"/duplicate logout
    }
  });

  // --- ANDROID WEBVIEW RESUME HANDLER ---
  window.addEventListener("androidResume", () => {

    searchQuery.value = "";
    resumeTimestamp.value = Date.now();

    if (!socket.value || socket.value.readyState !== WebSocket.OPEN) {
      reconnectAttempts.value = 0;
      reconnectDelay.value = 3000;
      isReconnecting.value = true;
      connectionError.value = "Reconnecting...";
      scheduleResumeReconnect(500);
    }

    fetchConversations().then(() => {
      resumeChatState();
    });
  });

  // ============================================
  // 🚀 ANDROID GLOBAL HANDLERS (PURE WEBVIEW)
  // ============================================
  // ChatGPT Solution: Android only triggers, JavaScript handles all logic
  // Principle: "Android can kill WebView, but cannot determine navigation"
  
  /**
   * __ANDROID_BACK: Called by Android when back button is pressed
   * Priority order:
   * 0. Close internal browser (if open)
   * 1. Close image lightbox (if open)
   * 2. Close settings modal (if open)
   * 3. Close chat → back to conversation list
   * 4. Exit app (if at root)
   */
  window.__ANDROID_BACK = () => {
    // Priority 0: Close Internal Browser
    if (showInternalBrowser.value) {
      closeInternalBrowser();
      return 'internal_browser_closed';
    }
    
    // Priority 1: Close Image Lightbox
    if (showImageLightbox.value) {
      closeImageLightbox();
      return 'lightbox_closed';
    }
    
    // Priority 2: Close Settings Modal
    if (showSettingsModal.value) {
      showSettingsModal.value = false;
      return 'settings_closed';
    }

    if (showDeleteLokasiModal.value) {
      showDeleteLokasiModal.value = false;
      return 'delete_lokasi_closed';
    }

    if (showAddLokasiModal.value) {
      showAddLokasiModal.value = false;
      return 'add_lokasi_closed';
    }

    if (showEditPermintaanModal.value) {
      showEditPermintaanModal.value = false;
      return 'edit_permintaan_closed';
    }

    if (showCreatePermintaanModal.value) {
      showCreatePermintaanModal.value = false;
      return 'create_permintaan_closed';
    }

    if (showDeliveryRequestModal.value) {
      showDeliveryRequestModal.value = false;
      return 'delivery_request_closed';
    }

    if (showSendTagihanModal.value) {
      showSendTagihanModal.value = false;
      return 'send_tagihan_closed';
    }

    if (showSendStatusModal.value) {
      showSendStatusModal.value = false;
      return 'send_status_closed';
    }

    if (showSendQrisModal.value) {
      showSendQrisModal.value = false;
      return 'send_qris_closed';
    }

    if (showCancelDeliveryModal.value) {
      showCancelDeliveryModal.value = false;
      return 'cancel_delivery_closed';
    }

    if (showCrewSendModal.value) {
      showCrewSendModal.value = false;
      return 'crew_send_closed';
    }

    if (showCustomerPanel.value) {
      showCustomerPanel.value = false;
      return 'customer_panel_closed';
    }
    
    // Priority 3: Navigate back from chat to list
    if (navStore.current === 'chat' || showMobileChat.value) {
      // Use backToMenu() for smooth slide animation
      backToMenu(true);
      
      return 'chat_closed';
    }
    
    // Priority 4: Exit app (at root)
    
    // Call Android bridge to close app
    if (window.Android && window.Android.exitApp) {
      window.Android.exitApp();
    }
    
    return 'should_exit';
  };
  
  /**
   * __ANDROID_RESUME: Called by Android when app resumes from background/sleep
   * Logic:
   * - ONLY restore navigation state from localStorage (Pinia)
   * - Sync Vue reactive state with restored Pinia state
   * - DO NOT reconnect WebSocket (let visibilitychange handler do it)
   */
  window.__ANDROID_RESUME = () => {
    // Silent resume - restore navigation state
    const restoredState = navStore.restore();
    
    // Sync Vue reactive state with Pinia
    if (restoredState.current === 'chat' && restoredState.chatId) {
      // Restore chat view
      
      // Find conversation and restore messages
      const conversation = conversations.value.find(
        (c) => String(c.id) === String(restoredState.chatId)
      );
      
      if (conversation) {
        showMobileChat.value = true;
        activeChatId.value = restoredState.chatId;
        
        // ✅ CRITICAL: Fetch messages if not loaded
        if (!conversation.messages || conversation.messages.length === 0) {
          fetchMessages(conversation.wa_number).then((result) => {
            conversation.messages = result.messages;
            conversation.hasMoreMessages = result.has_more;
            conversation.messageOffset = result.messages.length;
            nextTick(() => scrollToBottom({ force: true }));
          });
        } else {
          nextTick(() => scrollToBottom({ force: true }));
        }
      } else {
        // Silent reset - conversation not found
        showMobileChat.value = false;
        activeChatId.value = null;
      }
    } else {
      // Restore home view
      showMobileChat.value = false;
      activeChatId.value = null;
    }
    
    // ⚠️ DO NOT reconnect here - visibilitychange handler will do it
    return 'resumed';
  };
  
  // --- EXPOSE GLOBAL FUNCTION FOR ANDROID ---
  // Android WebView can call: window.triggerReconnect()
  window.triggerReconnect = () => {

    if (!socket.value || socket.value.readyState !== WebSocket.OPEN) {
      reconnectAttempts.value = 0;
      reconnectDelay.value = 3000;
      isReconnecting.value = true;
      connectionError.value = "Reconnecting...";

      if (authId.value) {
        connectWebSocket();
        fetchConversations();
        return true;
      }
    }
    return false;
  };

  // --- CLICK OUTSIDE HANDLER ---
  // Close menu when clicking anywhere
  window.addEventListener("click", handleClickOutside);

  // --- PASTE HANDLER ---
  // Handle pasted images
  window.addEventListener("paste", handlePaste);

  // --- LINK CLICK HANDLER ---
  // Intercept nalju.com links for internal browser, save state for others
  document.addEventListener("click", handleLinkClick, true);

  // --- PAGE HIDE HANDLER ---
  // Save state AND cleanup socket when page is hidden
  window.addEventListener("pagehide", () => {
    persistChatState();
    
    // Properly close socket to prevent reconnect issues
    if (socket.value) {
      forceDisconnect();
    }
  });

  window.addEventListener("beforeunload", () => {
    persistChatState();
    
    // Properly close socket
    if (socket.value) {
      forceDisconnect();
    }
  });

  // --- OLD ANDROID BACK BUTTON HANDLER (DISABLED) ---
  // ❌ ChatGPT Solution: DO NOT use window.history / popstate for Android back
  // ✅ Use __ANDROID_BACK global handler instead (see above)
  /*
  if (window.innerWidth < 768) {
    window.history.replaceState({ appRoot: true }, "", window.location.href);
  }

  let lastBackPressTime = 0;

  window.addEventListener("popstate", (event) => {
    if (showMobileChat.value) {
      showMobileChat.value = false;
      activeChatId.value = null;
      localStorage.removeItem("active_chat_id");
      window.history.pushState({ appRoot: true }, "", window.location.href.split('#')[0]);
    } else if (window.innerWidth < 768) {
      const now = Date.now();
      if (now - lastBackPressTime < 2000) {
        return;
      }
      
      lastBackPressTime = now;
      showExitToast.value = true;
      setTimeout(() => {
        showExitToast.value = false;
      }, 2000);
      
      window.history.pushState({ appRoot: true }, "", window.location.href.split('#')[0]);
    }
  });
  */

  // Load font size preference
  loadFontSize();

  // Load theme preference (auto day/night + temporary manual override)
  loadTheme();
  startThemeAutoSync();

  // Initialize notification sound
  initNotificationSound();
  loadNotificationSoundSetting();


  const storedId = localStorage.getItem("cms_chat_id");
  const storedExpiry = localStorage.getItem("cms_chat_expiry");
  const storedRole = localStorage.getItem("cms_chat_role");
  const storedName = localStorage.getItem("cms_chat_name");
  const storedSenderCode = localStorage.getItem("cms_chat_sender_code");
  const now = new Date().getTime();

  // Clean up old password storage (migration)
  localStorage.removeItem("cms_chat_password");

  if (storedRole) {
    currentUserRole.value = storedRole;
  }
  if (storedName) userName.value = storedName;
  if (storedSenderCode) senderCode.value = storedSenderCode;

  // Case 1: Valid session (ID + Valid Expiry) — re-claim device lock then WS
  if (storedId && storedExpiry && now < parseInt(storedExpiry)) {
    // Force uppercase for OneSignal compatibility
    const uppercaseId = storedId.toUpperCase();
    authId.value = uppercaseId;

    // Update localStorage if it was lowercase
    if (storedId !== uppercaseId) {
      localStorage.setItem("cms_chat_id", uppercaseId);
    }

    // Renew expiry for another 3 days
    const newExpiry = new Date().getTime() + 3 * 24 * 60 * 60 * 1000;
    localStorage.setItem("cms_chat_expiry", newExpiry.toString());

    // Full login path claims/refreshes device lock (required before WS verify)
    connect();
  }
  // Case 2: Has ID but expired - Keep ID, prompt to reconnect
  else if (storedId && storedExpiry && now >= parseInt(storedExpiry)) {
    // Force uppercase for OneSignal compatibility
    const uppercaseId = storedId.toUpperCase();
    authId.value = uppercaseId; // Keep the ID for convenience (uppercase)
    showLoginPrompt.value = true;
  }
  // Case 3: No session - Start fresh
  else {
    // Clean up any partial/expired data
    localStorage.removeItem("cms_chat_id");
    localStorage.removeItem("cms_chat_expiry");

    // Check URL param?
    const urlParams = new URLSearchParams(window.location.search);
    const idParam = urlParams.get("id");
    if (idParam) {
      // Force uppercase for OneSignal compatibility
      authId.value = idParam.toUpperCase();
      // Auto-connect with ID from URL
      connect();
      // Clean URL
      window.history.replaceState({}, document.title, window.location.pathname);
    } else {
      // Show Login Prompt
      setTimeout(() => {
        showLoginPrompt.value = true;
      }, 500);
    }
  }
});

// ⭐ Global function for Android native to call when notification is clicked
if (typeof window !== "undefined") {
  window.openChatByPhone = (phone, retryCount = 0) => {
    if (!phone) {
      return;
    }

    // Normalize phone number for matching
    const cleanPhone = String(phone).replace(/\D/g, "");

    // If conversations not loaded yet, retry with exponential backoff
    if (conversations.value.length === 0 && retryCount < 10) {
      pendingTargetPhone.value = cleanPhone;

      setTimeout(() => {
        window.openChatByPhone(phone, retryCount + 1);
      }, 500);
      return;
    }

    // Find conversation by phone
    const target = conversations.value.find((c) => {
      const cleanA = (c.wa_number || "").replace(/\D/g, "");
      return cleanA.endsWith(cleanPhone) || cleanPhone.endsWith(cleanA);
    });

    if (target) {
      pendingTargetPhone.value = null; // Clear pending

      // Use selectChat to properly load messages from API
      selectChat(target.id);
    } else {
      // Store for later (will be handled when fetchConversations completes)
      pendingTargetPhone.value = cleanPhone;

      // Trigger refresh if not already loading
      if (!isLoadingConversations.value) {
        fetchConversations();
      }
    }
  };
}

// Handle Android Back Button (Capacitor)

App.addListener("backButton", () => {
  handleBackButtonPress();
});

// Handle App State Change (Capacitor) - Resume from background/sleep
App.addListener("appStateChange", ({ isActive }) => {
  if (isActive) {
    resumeTimestamp.value = Date.now();
    searchQuery.value = "";

    if (!socket.value || socket.value.readyState !== WebSocket.OPEN) {
      reconnectAttempts.value = 0;
      reconnectDelay.value = 3000;
      isReconnecting.value = true;
      connectionError.value = "Reconnecting...";
      scheduleResumeReconnect(500);
    }

    fetchConversations();
  }
});

// Expose global function for Android WebView (non-Capacitor)
// Android Studio can call: webView.evaluateJavascript("window.onAndroidBackPressed()", null)
window.onAndroidBackPressed = () => {
  return handleBackButtonPress();
};

// Helper to re-expose back handler (called from Android onResume if handler seems dead)
window.__reExposeBackHandler = () => {
  
  // Re-assign the back handler (in case it was garbage collected after long sleep)
  window.onAndroidBackPressed = () => {
    return handleBackButtonPress();
  };
  
  // Restore Pinia navigation state from localStorage
  const state = navStore.restore();
  
  // Sync Vue state with Pinia
  if (state.current === 'chat' && state.chatId) {
    activeChatId.value = state.chatId;
    showMobileChat.value = true;
  } else {
    activeChatId.value = null;
    showMobileChat.value = false;
  }
  
  return true;
};

// ============================================
// 🔙 ANDROID BACK BUTTON HANDLER - CLEAN VERSION
// ============================================
// Uses Pinia Navigation Store for reliable state management
// Handles app sleep/resume gracefully via localStorage persistence
function handleBackButtonPress() {
  // Priority 0: Close Internal Browser
  if (showInternalBrowser.value) {
    closeInternalBrowser();
    return "internal_browser_closed";
  }

  // Priority 1: Close Image Lightbox
  if (showImageLightbox.value) {
    closeImageLightbox();
    return "lightbox_closed";
  }

  // Priority 2: Close Settings Modal
  if (showSettingsModal.value) {
    showSettingsModal.value = false;
    return "settings_closed";
  }

  if (showDeleteLokasiModal.value) {
    showDeleteLokasiModal.value = false;
    return "delete_lokasi_closed";
  }

  if (showAddLokasiModal.value) {
    showAddLokasiModal.value = false;
    return "add_lokasi_closed";
  }

  if (showEditPermintaanModal.value) {
    showEditPermintaanModal.value = false;
    return "edit_permintaan_closed";
  }

  if (showCreatePermintaanModal.value) {
    showCreatePermintaanModal.value = false;
    return "create_permintaan_closed";
  }

  if (showDeliveryRequestModal.value) {
    showDeliveryRequestModal.value = false;
    return "delivery_request_closed";
  }

  if (showSendTagihanModal.value) {
    showSendTagihanModal.value = false;
    return "send_tagihan_closed";
  }

  if (showSendStatusModal.value) {
    showSendStatusModal.value = false;
    return "send_status_closed";
  }

  if (showSendQrisModal.value) {
    showSendQrisModal.value = false;
    return "send_qris_closed";
  }

  if (showCancelDeliveryModal.value) {
    showCancelDeliveryModal.value = false;
    return "cancel_delivery_closed";
  }

  if (showCrewSendModal.value) {
    showCrewSendModal.value = false;
    return "crew_send_closed";
  }

  if (showCustomerPanel.value) {
    showCustomerPanel.value = false;
    return "customer_panel_closed";
  }

  // Priority 3: Navigate back from chat to list
  const isMobile = windowWidth.value < 768;
  
  // Check if user is in a child view (chat)
  if (isMobile && (showMobileChat.value || activeChatId.value)) {
    // Update Pinia state
    navStore.reset();
    
    // Update Vue reactive state
    backToMenu(true); // Animated transition
    
    return "chat_closed";
  }

  // Priority 4: Check navigation state for fallback (after app sleep)
  if (navStore.current === 'chat') {
    // Update both Pinia and Vue state
    navStore.reset();
    activeChatId.value = null;
    showMobileChat.value = false;
    
    return "chat_closed";
  }

  // Priority 5: Double-press to exit (at root)
  const timeNow = Date.now();
  if (timeNow - lastBackPress < 2000) {
    return "should_exit";
  } else {
    lastBackPress = timeNow;
    showExitToast.value = true;
    setTimeout(() => {
      showExitToast.value = false;
    }, 2000);
    return "toast_shown";
  }
}

// Login Modal Delay Logic
setTimeout(() => {
  // Only show login prompt if we are not connected, not connecting, AND don't have a saved ID
  if (!isConnected.value && !isConnecting.value && !authId.value) {
    showLoginPrompt.value = true;
  }
}, 1500); // Wait 1.5s before showing modal if not connected

const logout = async () => {
  const user = authId.value;
  const deviceId = getDeviceId();

  try {
    await fetch(`${API_BASE}/CRM/Auth/logout`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        username: user,
        device_id: deviceId,
      }),
    });
  } catch (e) {
    console.warn("Logout API failed", e);
  }

  if (socket.value) {
    try {
      socket.value.onclose = null;
      socket.value.close();
    } catch (_) { /* ignore */ }
    socket.value = null;
  }
  isConnected.value = false;
  authId.value = "";
  isConnecting.value = false;
  isReconnecting.value = false;
  showLoginPrompt.value = true;
  duplicateWarning.value = "";

  // Clear Session (keep crm_device_id — device identity persists)
  localStorage.removeItem("cms_chat_id");
  localStorage.removeItem("cms_chat_expiry");

  // OneSignal: Logout from push notifications
  oneSignalLogout();
};

// ============================================
// 🎯 ACTIVITY TRACKING FOR SMART IDLE DETECTION
// ============================================
// Track user activity to dynamically adjust polling interval
ACTIVITY_EVENTS.forEach(eventName => {
  window.addEventListener(eventName, updateActivity, { passive: true });
});
// When user switches back to tab, treat as activity (restart chat polling if was paused)
window.addEventListener("focus", updateActivity);

// Cleanup on unmount
onUnmounted(() => {
  if (titleBlinkInterval.value) {
    clearInterval(titleBlinkInterval.value);
    document.title = originalTitle;
  }
  window.removeEventListener("focus", updateActivity);
});

// Stop blinking when window is focused
window.addEventListener("focus", () => {
  if (titleBlinkInterval.value) {
    clearInterval(titleBlinkInterval.value);
    titleBlinkInterval.value = null;
    document.title = originalTitle;
    isTitleRed.value = false;
  }
});

// Image Lightbox Functions (for in-app image viewing)
const openImageLightbox = (imageUrl) => {
  if (imageUrl) {
    lightboxImageUrl.value = imageUrl;
    showImageLightbox.value = true;
  }
};

const closeImageLightbox = () => {
  showImageLightbox.value = false;
  lightboxImageUrl.value = "";
};

// --- External Browser (nalju.com links: invoice, portal, dll) ---
const isNaljuDomain = (url) => {
  try {
    const urlObj = new URL(url);
    // Check if domain is nalju.com or any subdomain *.nalju.com
    return (
      urlObj.hostname === "nalju.com" || urlObj.hostname.endsWith(".nalju.com")
    );
  } catch {
    return false;
  }
};

const normalizeExternalUrl = (url) => {
  try {
    const urlObj = new URL(url);
    urlObj.pathname = urlObj.pathname.replace(/\/+/g, "/");
    return urlObj.toString();
  } catch (e) {
    console.error("❌ Invalid URL:", url, e);
    return url;
  }
};

const openExternalBrowser = (url) => {
  const normalizedUrl = normalizeExternalUrl(url);
  persistChatState();

  // Android WebView app: buka lewat Intent native
  if (window.Android && typeof window.Android.openUrl === "function") {
    try {
      window.Android.openUrl(normalizedUrl);
      return;
    } catch (e) {
      console.warn("Android.openUrl failed, fallback", e);
    }
  }

  // Browser / WebView dengan support window.open / target=_blank
  const opened = window.open(normalizedUrl, "_blank", "noopener,noreferrer");
  if (opened) return;

  // Fallback terakhir: <a target=_blank> (ditangkap onCreateWindow di app Android)
  const a = document.createElement("a");
  a.href = normalizedUrl;
  a.target = "_blank";
  a.rel = "noopener noreferrer";
  document.body.appendChild(a);
  a.click();
  a.remove();
};

// Kept as alias so old callers still work (now redirects to external browser)
const openInternalBrowser = async (url) => {
  openExternalBrowser(url);
};

const closeInternalBrowser = () => {
  // Animate slide-out to the right
  isInternalBrowserExiting.value = true;

  // Wait for animation to complete (300ms) then hide
  setTimeout(() => {
    showInternalBrowser.value = false;
    internalBrowserUrl.value = "";
    isInternalBrowserLoading.value = true;
    isInternalBrowserExiting.value = false;
  }, 300);
};

const handleInternalBrowserLoad = () => {
  isInternalBrowserLoading.value = false;
};

const handleInternalBrowserError = (e) => {
  console.error('❌ Internal browser error:', e);
  isInternalBrowserLoading.value = false;
  
  // On Android, if iframe fails, offer to open in external browser
  if (isNativeApp.value) {
    const openExternal = confirm(
      'Unable to load page in internal browser.\n\nOpen in external browser?'
    );
    if (openExternal && internalBrowserUrl.value) {
      openExternalBrowser(internalBrowserUrl.value);
      closeInternalBrowser();
    }
  }
};

// Handle link clicks - nalju.com dibuka di browser eksternal
const handleLinkClick = (e) => {
  const link = e.target.closest("a[href]");
  if (link && link.href) {
    // Check if it's a nalju.com link
    if (isNaljuDomain(link.href)) {
      e.preventDefault();
      e.stopPropagation();
      openExternalBrowser(link.href);
      return;
    }

    // External link - save state before navigating
    if (link.href.startsWith("http://") || link.href.startsWith("https://")) {
      persistChatState();
    }
  }
};
</script>

<template>
  <!-- Use fixed inset-0 to prevent body scroll issues on mobile -->
  <div
    class="fixed inset-0 w-full bg-[#0f172a] text-slate-200 overflow-hidden font-sans selection:bg-indigo-500 selection:text-white"
  >
    <!-- Initial App Loading Screen (only on first load, not during reconnects) -->
    <div
      v-if="
        !showLoginPrompt && !isConnected && !wasConnected && !isReconnecting
      "
      class="fixed inset-0 z-[70] bg-[#0f172a] flex flex-col items-center justify-center"
    >
      <div class="flex flex-col items-center gap-6">
        <!-- Animated Logo/Icon -->
        <div class="relative">
          <div
            class="w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-2xl shadow-indigo-500/30 animate-pulse"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-10 w-10 text-white"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
              />
            </svg>
          </div>
          <!-- Pulsing ring -->
          <div
            class="absolute inset-0 rounded-2xl border-2 border-indigo-400/50 animate-ping"
          ></div>
        </div>

        <!-- App Name -->
        <div class="text-center">
          <h1
            class="text-2xl font-bold bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent"
          >
            MDL Chat
          </h1>
          <p class="text-slate-500 text-sm mt-1">Loading...</p>
        </div>

        <!-- Animated Dots -->
        <div class="flex gap-1.5">
          <div
            class="w-2 h-2 rounded-full bg-indigo-500 animate-bounce"
            style="animation-delay: 0ms"
          ></div>
          <div
            class="w-2 h-2 rounded-full bg-indigo-500 animate-bounce"
            style="animation-delay: 150ms"
          ></div>
          <div
            class="w-2 h-2 rounded-full bg-indigo-500 animate-bounce"
            style="animation-delay: 300ms"
          ></div>
        </div>
      </div>
    </div>

    <!-- Login Modal (Overlay) -->
    <!-- Login Modal (Premium Design) -->
    <!-- Login Modal (Overlay) -->
    <!-- Login Modal (Premium Design) -->
    <LoginModal
      v-if="!isConnected && showLoginPrompt"
      :loading="isConnecting"
      :error="connectionError"
      :warning="duplicateWarning"
      :initial-username="authId"
      @login="handleLogin"
    />


    <!-- Sidebar - Now uses ConversationList Component -->
    <ConversationList
      v-if="authId"
      :conversations="conversations"
      :filtered-conversations="filteredConversations"
      :active-chat-id="activeChatId"
      :auth-id="authId"
      :current-user-role="currentUserRole"
      :is-loading-conversations="isLoadingConversations"
      :is-loading-more-conversations="isLoadingMoreConversations"
      :is-searching="isSearching"
      :has-more-conversations="hasMoreConversations"
      :is-reconnecting="isReconnecting"
      :is-connected="isConnected"
      :connection-error="connectionError"
      :show-mobile-chat="showMobileChat"
      :total-unread-count="totalUnreadCount"
      @select-chat="selectChat"
      @logout="logout"
      @load-more-conversations="loadMoreConversations"
      @open-settings="showSettingsModal = true"
      @update:searchQuery="searchQuery = $event"
      @update:conversationFilter="conversationFilter = $event"
    />


    <!-- Main Chat Area - Now uses ChatPage Component -->
    <ChatPage
      :active-conversation="activeConversation"
      :active-chat-id="activeChatId"
      :auth-id="authId"
      :current-user-role="currentUserRole"
      :sender-code="senderCode"
      :window-width="windowWidth"
      :show-mobile-chat="showMobileChat"
      :is-entering-chat="isEnteringChat"
      :touch-offset="touchOffset"
      :API_BASE="API_BASE"
      :is-refreshing-chat="isRefreshingChat"
      :is-loading-messages="isLoadingMessages"
      :is-loading-more-messages="isLoadingMoreMessages"
      :is-connected="isConnected"
      :is-chat-polling="isChatPolling"
      :is-chat-poll-idle-paused="isChatPollIdlePaused"
      :font-size="fontSize"
      @back-to-menu="backToMenu"
      @load-more-messages="loadMoreMessages"
      @open-image-lightbox="openImageLightbox"
      @refresh-active-chat="refreshActiveChat"
      @open-internal-browser="openExternalBrowser"
    />

    <!-- Exit Toast -->
    <div
      v-if="showExitToast"
      class="fixed bottom-8 left-1/2 transform -translate-x-1/2 bg-slate-800/90 backdrop-blur text-white px-6 py-3 rounded-full shadow-xl border border-slate-700/50 z-[100] transition-opacity duration-300 pointer-events-none"
    >
      <span class="text-sm font-medium">Press back again to exit</span>
    </div>

    <!-- Image Lightbox Modal (for viewing images in-app, Android back button friendly) -->
    <div
      v-if="showImageLightbox"
      class="fixed inset-0 bg-black z-[600] flex items-center justify-center p-2"
      @click="closeImageLightbox"
    >
      <!-- Close Button -->
      <button
        @click.stop="closeImageLightbox"
        class="absolute top-2 right-2 p-2 bg-white/20 hover:bg-white/30 rounded-full text-white transition-colors z-20"
        style="top: env(safe-area-inset-top, 8px)"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-6 w-6"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M6 18L18 6M6 6l12 12"
          />
        </svg>
      </button>

      <!-- Image Container -->
      <div class="w-full h-full flex items-center justify-center">
        <img
          :src="lightboxImageUrl"
          class="block"
          style="
            max-width: 95vw;
            max-height: 90vh;
            width: auto;
            height: auto;
            object-fit: contain;
          "
          @click.stop
          alt="Image Preview"
        />
      </div>

      <!-- Hint Text -->
      <div
        class="absolute bottom-2 left-1/2 transform -translate-x-1/2 text-white/50 text-xs bg-black/50 px-3 py-1 rounded-full"
      >
        Tap anywhere or press back to close
      </div>
    </div>

    <!-- Settings Modal -->
    <div
      v-if="showSettingsModal"
      class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[500] flex items-center justify-center p-4"
      @click="showSettingsModal = false"
    >
      <div
        class="bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-2xl shadow-2xl max-w-md w-full p-6"
        @click.stop
      >
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-[var(--wa-text-primary)]">
            Settings
          </h2>
          <button
            @click="showSettingsModal = false"
            class="p-2 text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)] hover:bg-[var(--wa-hover)] rounded-lg transition-colors"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>

        <!-- Font Size Setting -->
        <div class="space-y-4">
          <div>
            <h3
              class="text-sm font-medium text-[var(--wa-text-secondary)] mb-3"
            >
              Font Size
            </h3>
            <div class="space-y-2">
              <!-- Medium Option (Default) -->
              <button
                @click="setFontSize('medium')"
                class="w-full flex items-center justify-between p-3 rounded-lg border transition-all"
                :class="
                  fontSize === 'medium'
                    ? 'border-[var(--wa-accent-green)] bg-[var(--wa-accent-green)]/10'
                    : 'border-[var(--wa-border)] hover:bg-[var(--wa-hover)]'
                "
              >
                <span
                  class="text-[var(--wa-text-primary)]"
                  style="font-size: 14.2px"
                  >Medium (Default)</span
                >
                <svg
                  v-if="fontSize === 'medium'"
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5 text-[var(--wa-accent-green)]"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                    clip-rule="evenodd"
                  />
                </svg>
              </button>

              <!-- Large Option -->
              <button
                @click="setFontSize('large')"
                class="w-full flex items-center justify-between p-3 rounded-lg border transition-all"
                :class="
                  fontSize === 'large'
                    ? 'border-[var(--wa-accent-green)] bg-[var(--wa-accent-green)]/10'
                    : 'border-[var(--wa-border)] hover:bg-[var(--wa-hover)]'
                "
              >
                <span
                  class="text-[var(--wa-text-primary)]"
                  style="font-size: 16px"
                  >Large</span
                >
                <svg
                  v-if="fontSize === 'large'"
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5 text-[var(--wa-accent-green)]"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                    clip-rule="evenodd"
                  />
                </svg>
              </button>

              <!-- X-Large Option -->
              <button
                @click="setFontSize('xlarge')"
                class="w-full flex items-center justify-between p-3 rounded-lg border transition-all"
                :class="
                  fontSize === 'xlarge'
                    ? 'border-[var(--wa-accent-green)] bg-[var(--wa-accent-green)]/10'
                    : 'border-[var(--wa-border)] hover:bg-[var(--wa-hover)]'
                "
              >
                <span
                  class="text-[var(--wa-text-primary)]"
                  style="font-size: 18px"
                  >X-Large</span
                >
                <svg
                  v-if="fontSize === 'xlarge'"
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5 text-[var(--wa-accent-green)]"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                    clip-rule="evenodd"
                  />
                </svg>
              </button>
            </div>
          </div>

          <!-- Theme Setting -->
          <div class="mt-6 pt-6 border-t border-[var(--wa-border)]">
            <h3
              class="text-sm font-medium text-[var(--wa-text-secondary)] mb-3"
            >
              Theme
            </h3>
            <div
              class="flex items-center justify-between p-3 rounded-lg border border-[var(--wa-border)]"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-full bg-[var(--wa-bg-tertiary)] flex items-center justify-center"
                >
                  <svg
                    v-if="theme === 'light'"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 text-yellow-500"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"
                      clip-rule="evenodd"
                    />
                  </svg>
                  <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 text-indigo-400"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                  >
                    <path
                      d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"
                    />
                  </svg>
                </div>
                <div>
                  <p class="text-[var(--wa-text-primary)] font-medium">
                    {{ theme === "dark" ? "Dark Mode" : "Light Mode" }}
                  </p>
                  <p class="text-xs text-[var(--wa-text-tertiary)]">
                    Auto siang/malam · manual sementara
                  </p>
                </div>
              </div>
              <button
                @click="toggleTheme"
                class="relative w-12 h-6 rounded-full transition-colors duration-300"
                :class="theme === 'dark' ? 'bg-indigo-600' : 'bg-yellow-500'"
              >
                <div
                  class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow-md transition-transform duration-300"
                  :class="theme === 'dark' ? 'left-0.5' : 'left-6'"
                ></div>
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Internal Browser (for nalju.com links) -->
    <div
      v-if="showInternalBrowser"
      class="fixed inset-0 z-[1000] bg-[var(--wa-bg-panel)] flex flex-col internal-browser-panel"
      :class="{
        'internal-browser-entering': isInternalBrowserEntering,
        'internal-browser-exiting': isInternalBrowserExiting,
      }"
    >
      <!-- Header -->
      <header
        class="h-14 bg-[var(--wa-bg-panel)] border-b border-[var(--wa-border)] flex items-center px-4 gap-3 flex-shrink-0"
      >
        <button
          @click="closeInternalBrowser"
          class="p-2 -ml-2 text-[var(--wa-icon-default)] hover:text-[var(--wa-text-primary)] hover:bg-[var(--wa-hover)] rounded-lg transition-colors"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-6 w-6"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 19l-7-7 7-7"
            />
          </svg>
        </button>

        <div class="flex-1 min-w-0">
          <p class="text-sm text-[var(--wa-text-primary)] truncate font-medium">
            {{ internalBrowserUrl }}
          </p>
        </div>

        <!-- Open in External Browser -->
        <a
          :href="internalBrowserUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="p-2 text-[var(--wa-icon-default)] hover:text-[var(--wa-text-primary)] hover:bg-[var(--wa-hover)] rounded-lg transition-colors"
          title="Open in browser"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
            />
          </svg>
        </a>
      </header>

      <!-- Loading Indicator (Premium Design) -->
      <div
        v-if="isInternalBrowserLoading"
        class="absolute inset-0 top-14 flex items-center justify-center bg-[var(--wa-bg-chat)] backdrop-blur-md z-10"
      >
        <div class="flex flex-col items-center gap-4">
          <div class="relative">
             <div class="w-12 h-12 border-4 border-[var(--wa-accent-green)]/20 rounded-full"></div>
             <div class="absolute top-0 left-0 w-12 h-12 border-4 border-[var(--wa-accent-green)] border-t-transparent rounded-full animate-spin"></div>
          </div>
          <div class="flex flex-col items-center">
            <p class="text-base font-medium text-[var(--wa-text-primary)]">Memuat Halaman</p>
            <p v-if="internalBrowserUrl && internalBrowserUrl.startsWith('http')" class="text-xs text-[var(--wa-text-tertiary)]">
              {{ internalBrowserUrl.split('//')[1]?.split('/')[0] || internalBrowserUrl }}
            </p>
          </div>
        </div>
      </div>

      <!-- Iframe - Enhanced for Android WebView -->
      <iframe
        :src="internalBrowserUrl"
        class="flex-1 w-full border-0 bg-white"
        @load="handleInternalBrowserLoad"
        @error="handleInternalBrowserError"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-modals allow-downloads allow-top-navigation-by-user-activation"
        referrerpolicy="strict-origin-when-cross-origin"
      ></iframe>
    </div>
  </div>
</template>

<style>
/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: var(--wa-border);
  border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: var(--wa-text-tertiary);
}

/* WhatsApp Formatting Styles */
p strong {
  font-weight: 700;
  color: inherit;
}

p em {
  font-style: italic;
  color: inherit;
}

p del {
  text-decoration: line-through;
  opacity: 0.7;
}

p code {
  font-family: "Courier New", monospace;
  background-color: rgba(15, 23, 42, 0.5);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.85em;
}

/* Link Styles */
p a {
  color: #22d3ee; /* cyan-400 for better contrast on dark background */
  text-decoration: underline;
  transition: color 0.2s ease;
  word-break: break-all;
}

p a:hover {
  color: #67e8f9; /* cyan-300 */
  text-decoration: underline;
}

/* Links in indigo message bubble (my messages) need different color */
.bg-indigo-600 p a {
  color: #bfdbfe; /* blue-200 for contrast on indigo background */
}

.bg-indigo-600 p a:hover {
  color: #ffffff;
}

/* WhatsApp-style Chat Panel Slide-in Animation (Mobile) */
.chat-panel-mobile {
  transform: translateX(0);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.chat-panel-mobile.chat-entering {
  transform: translateX(100%);
}

/* Internal Browser Slide-in Animation */
.internal-browser-panel {
  transform: translateX(0);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.internal-browser-panel.internal-browser-entering {
  transform: translateX(100%);
}

.internal-browser-panel.internal-browser-exiting {
  transform: translateX(100%);
}

/* Skeleton Shimmer Effect */
.skeleton-shimmer {
  background: linear-gradient(
    90deg,
    var(--wa-bg-tertiary) 0%,
    rgba(99, 102, 241, 0.1) 50%,
    var(--wa-bg-tertiary) 100%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite ease-in-out;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

/* Modal Scale-in Animation */
.animate-scale-in {
  animation: scaleIn 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes scaleIn {
  0% {
    opacity: 0;
    transform: scale(0.9);
  }
  100% {
    opacity: 1;
    transform: scale(1);
  }
}

/* Login Gradient Animation */
.animate-gradient-shift {
  animation: gradientShift 3s ease-in-out infinite;
}

@keyframes gradientShift {
  0%,
  100% {
    opacity: 0.3;
    transform: rotate(0deg);
  }
  50% {
    opacity: 0.5;
    transform: rotate(1deg);
  }
}
</style>
