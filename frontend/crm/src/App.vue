<script setup>
import { ref, computed, onMounted, nextTick, watch, onUnmounted } from "vue";
import { App } from "@capacitor/app";
import { Camera, CameraResultType, CameraSource } from "@capacitor/camera";
import LoginModal from "./components/LoginModal.vue";
import ChatPage from "./components/ChatPage.vue";
import ConversationList from "./components/ConversationList.vue";

// Import store
import {
  // API
  API_BASE,
  // Auth
  authId, currentUserRole, userName, senderCode,
  isConnected, isConnecting, connectionError,
  showLoginPrompt, duplicateWarning,
  wasConnected, isReconnecting, reconnectAttempts, maxReconnectAttempts, reconnectDelay,
  resumeTimestamp, lastDisconnectTime,
  duplicateRetryAttempts, maxDuplicateRetries, duplicateRetryDelay,
  // Conversations
  conversations, activeChatId, isLoadingConversations,
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
  showChatMenu, showResolveMenu, showSettingsModal, showCustomerInfoModal,
  showImageLightbox, lightboxImageUrl, showQuickReplies,
  showInternalBrowser, internalBrowserUrl, isInternalBrowserEntering, isInternalBrowserExiting, isInternalBrowserLoading,
  // Loading States
  isMarkingAsDone, isCheckingPayment, isPickupDelivery, isRequest, isFollowUp,
  isReopeningConversation, isRefreshingChat, isLoadingQuickReplies, copiedPhone,
  // Settings
  fontSize, theme, notificationSoundEnabled, notificationAudio,
  // Quick Reply
  quickReplies, quickReplySearchQuery,
  // Title Blink
  originalTitle, titleBlinkInterval, isTitleRed,
  // Helpers
  getAvatarColor, getCaseColor, getCaseLabel, isCaseOpen, isNativeApp,
  // Computed
  activeConversation, filteredConversations, totalUnreadCount, totalOpenCasesCount,
} from "./stores/chatStore.js";

// Local-only state (not shared)
let lastBackPress = 0;



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
  if (saved && ["medium", "large"].includes(saved)) {
    fontSize.value = saved;
  }
};

// Save font size to localStorage
const setFontSize = (size) => {
  fontSize.value = size;
  localStorage.setItem("cms_font_size", size);
};

// Theme Functions
const loadTheme = () => {
  const saved = localStorage.getItem("cms_theme");
  if (saved && ["dark", "light"].includes(saved)) {
    theme.value = saved;
  }
  applyTheme(theme.value);
};

const setTheme = (newTheme) => {
  theme.value = newTheme;
  localStorage.setItem("cms_theme", newTheme);
  applyTheme(newTheme);
};

const applyTheme = (themeName) => {
  const root = document.documentElement;

  if (themeName === "light") {
    // Light theme colors (WhatsApp-like)
    root.style.setProperty("--wa-bg-primary", "#f0f2f5");
    root.style.setProperty("--wa-bg-secondary", "#ffffff");
    root.style.setProperty("--wa-bg-tertiary", "#f0f2f5");
    root.style.setProperty("--wa-bg-panel", "#ffffff");
    root.style.setProperty("--wa-bg-chat", "#efeae2"); // Chat area background
    root.style.setProperty("--wa-border", "#e9edef");
    root.style.setProperty("--wa-text-primary", "#111b21");
    root.style.setProperty("--wa-text-secondary", "#54656f");
    root.style.setProperty("--wa-text-tertiary", "#8696a0");
    root.style.setProperty("--wa-bubble-out", "#d9fdd3");
    root.style.setProperty("--wa-bubble-outgoing", "#d9fdd3"); // Alias
    root.style.setProperty("--wa-bubble-out-text", "#111b21");
    root.style.setProperty("--wa-bubble-in", "#ffffff");
    root.style.setProperty("--wa-bubble-incoming", "#ffffff"); // Alias
    root.style.setProperty("--wa-bubble-in-text", "#111b21");
    root.style.setProperty("--wa-hover", "#f5f6f6");
    root.style.setProperty("--wa-active", "#f0f2f5");
    root.style.setProperty("--wa-bubble-out-meta", "#54656f"); // Dark gray for light mode
    root.style.setProperty("--wa-bubble-out-quoted-bg", "rgba(0, 0, 0, 0.05)");
    root.style.setProperty("--wa-bubble-out-quoted-text", "#54656f");
    root.style.setProperty("--wa-icon-default", "#54656f");
    root.style.setProperty("--wa-accent-green", "#00a884");
    root.style.setProperty("--wa-divider", "#f0f2f5"); // Light divider merging with bg
    root.style.setProperty("--wa-link-color", "#027eb5");
    root.style.setProperty("--wa-date-badge", "#ffffff");
    root.style.setProperty("--wa-date-badge-text", "#54656f");
    root.style.setProperty("--wa-header-bg", "#f0f2f5");
    root.style.setProperty("--wa-input-bg", "#ffffff");
    root.style.setProperty("--wa-conversation-active", "#f0f2f5");

    // Filter Tabs
    root.style.setProperty("--wa-filter-active-bg", "#d9fdd3");
    root.style.setProperty("--wa-filter-active-text", "#008069");
    root.style.setProperty("--wa-filter-inactive-bg", "transparent");
    root.style.setProperty("--wa-filter-inactive-border", "#e9edef");
    root.style.setProperty("--wa-filter-inactive-text", "#54656f");
  } else {
    // Dark theme colors (default)
    root.style.setProperty("--wa-bg-primary", "#111b21");
    root.style.setProperty("--wa-bg-secondary", "#202c33");
    root.style.setProperty("--wa-bg-tertiary", "#2a3942");
    root.style.setProperty("--wa-bg-panel", "#111b21");
    root.style.setProperty("--wa-bg-chat", "#0b141a"); // Chat area background
    root.style.setProperty("--wa-border", "#2a3942");
    root.style.setProperty("--wa-text-primary", "#e9edef");
    root.style.setProperty("--wa-text-secondary", "#8696a0");
    root.style.setProperty("--wa-text-tertiary", "#667781");
    root.style.setProperty("--wa-bubble-out", "#005c4b");
    root.style.setProperty("--wa-bubble-outgoing", "#005c4b"); // Alias
    root.style.setProperty("--wa-bubble-out-text", "#e9edef");
    root.style.setProperty("--wa-bubble-in", "#202c33");
    root.style.setProperty("--wa-bubble-incoming", "#202c33"); // Alias
    root.style.setProperty("--wa-bubble-in-text", "#e9edef");
    root.style.setProperty("--wa-hover", "#2a3942");
    root.style.setProperty("--wa-active", "#2a3942");
    root.style.setProperty("--wa-bubble-out-meta", "rgba(255, 255, 255, 0.6)"); // White/translucent for dark mode
    root.style.setProperty("--wa-bubble-out-quoted-bg", "rgba(0, 0, 0, 0.2)");
    root.style.setProperty(
      "--wa-bubble-out-quoted-text",
      "rgba(255, 255, 255, 0.7)"
    );
    root.style.setProperty("--wa-icon-default", "#aebac1");
    root.style.setProperty("--wa-accent-green", "#00a884");
    root.style.setProperty("--wa-divider", "#2a3942"); // Dark divider merging with bg
    root.style.setProperty("--wa-link-color", "#53bdeb");
    root.style.setProperty("--wa-date-badge", "#182229");
    root.style.setProperty("--wa-date-badge-text", "#8696a0");
    root.style.setProperty("--wa-header-bg", "#202c33");
    root.style.setProperty("--wa-input-bg", "#2a3942");
    root.style.setProperty("--wa-conversation-active", "#2a3942");

    // Filter Tabs
    root.style.setProperty("--wa-filter-active-bg", "#00a884");
    root.style.setProperty("--wa-filter-active-text", "#111b21");
    root.style.setProperty("--wa-filter-inactive-bg", "#2a3942");
    root.style.setProperty("--wa-filter-inactive-border", "transparent");
    root.style.setProperty("--wa-filter-inactive-text", "#8696a0");
  }
};

const toggleTheme = () => {
  const newTheme = theme.value === "dark" ? "light" : "dark";
  setTheme(newTheme);
};

// Computed font sizes for messages
const messageFontSize = computed(() => {
  const sizes = {
    medium: "14.2px",
    large: "16px",
  };
  return sizes[fontSize.value] || sizes.medium;
});



// Computed: Cases available to resolve based on Role


// Total unread messages count


// Total conversations with open cases


// Title blinking is now handled by shouldBlinkTitle watch below (line 314)
// to avoid conflicts between totalUnreadCount and priority-based blinking

const fetchConversations = async () => {
  try {
    isLoadingConversations.value = true; // Start loading

    const userIdParam = authId.value ? `user_id=${authId.value}` : "";
    const separator = userIdParam ? "&" : "?";
    const query = userIdParam
      ? `?${userIdParam}${separator}_t=${Date.now()}`
      : `?_t=${Date.now()}`;

    const response = await fetch(
      `${API_BASE}/CRM/Chat/getConversations${query}`
    );

    if (!response.ok) {
      const text = await response.text();
      console.error("API Error Response:", text);
      return;
    }

    const result = await response.json();

    // Backend returns "status": true, not "success"
    if (result.status && Array.isArray(result.data)) {
      if (result.data.length === 0) {
        console.log("API returned 0 conversations.");
      }

      // SMART MERGE STRATEGY
      // 1. Create Map of existing convos
      const existingMap = new Map(conversations.value.map((c) => [c.id, c]));
      const newOrder = [];

      result.data.forEach((c) => {
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

          return Array.from(seenCases.values());
        };

        let convo = existingMap.get(c.id);

        if (convo) {
          // Update existing
          convo.wa_number = c.wa_number;
          convo.name = c.contact_name || c.wa_number;
          convo.kode_cabang = c.kode_cabang;
          // convo.priority = parseInt(c.priority) || 0; // Legacy ignored
          convo.cases = parseCases(c); // New Array

          convo.initials = (c.contact_name || c.wa_number || "?")
            .substring(0, 1)
            .toUpperCase();
          convo.color = getAvatarColor(c.id);
          convo.status = c.status;
          convo.lastMessage =
            c.last_message || c.last_message_text || "No messages yet";
          convo.lastTime = formatLastTime(c.last_message_time);
          convo.lastMessageTime = c.last_message_time; // Raw timestamp for sorting
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
            // priority: parseInt(c.priority) || 0,
            cases: parseCases(c),

            initials: (c.contact_name || c.wa_number || "?")
              .substring(0, 1)
              .toUpperCase(),
            color: getAvatarColor(c.id),
            status: c.status,
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

      // Re-assign to update list order/membership
      // Re-assign to update list order/membership
      conversations.value = newOrder;

      // Auto-open chat if deep link pending
      if (pendingTargetPhone.value) {
        const target = conversations.value.find((c) => {
          const cleanA = (c.wa_number || "").replace(/\D/g, "");
          const cleanB = (pendingTargetPhone.value || "").replace(/\D/g, "");
          return cleanA.endsWith(cleanB) || cleanB.endsWith(cleanA);
        });

        if (target) {
          console.log("✅ Auto-opening chat from deep link:", target.name);
          pendingTargetPhone.value = null; // Clear it first to prevent re-triggering

          // Use selectChat to properly load messages from API
          selectChat(target.id);
        } else {
          console.log(
            "⚠️ Deep link target not found in list (yet):",
            pendingTargetPhone.value
          );
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
      else if (roles.driver && includesId(roles.driver, myId)) role = "driver";
      else if (roles.crew && includesId(roles.crew, myId)) role = "crew";

      currentUserRole.value = role;
      localStorage.setItem("cms_chat_role", role);
      console.log("User Role Detected:", role);
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
      console.log("OneSignal: Logged in via Android interface:", uppercaseUserId);
    }
    // For Web: Use OneSignal Web SDK if available
    else if (window.OneSignalDeferred) {
      window.OneSignalDeferred.push(async function (OneSignal) {
        await OneSignal.login(uppercaseUserId);
        console.log("OneSignal: Logged in via Web SDK:", uppercaseUserId);
      });
    } else if (window.OneSignal) {
      window.OneSignal.login(uppercaseUserId);
      console.log("OneSignal: Logged in:", uppercaseUserId);
    } else {
      console.log("OneSignal: Not available (running in browser without SDK)");
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
      console.log("OneSignal: Logged out via Android interface");
    }
    // For Web: Use OneSignal Web SDK if available
    else if (window.OneSignalDeferred) {
      window.OneSignalDeferred.push(async function (OneSignal) {
        await OneSignal.logout();
        console.log("OneSignal: Logged out via Web SDK");
      });
    } else if (window.OneSignal) {
      window.OneSignal.logout();
      console.log("OneSignal: Logged out");
    } else {
      console.log("OneSignal: Not available (running in browser without SDK)");
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
    // Step 1: Login to Backend
    const res = await fetch(`${API_BASE}/CRM/Auth/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        username: authId.value,
      }),
    });

    // Handle non-JSON response gracefully
    if (!res.ok) {
      const text = await res.text();
      throw new Error(text || res.statusText);
    }

    const data = await res.json();

    if (!data.success) {
      connectionError.value = data.message || "Login Failed";
      isConnecting.value = false;
      return;
    }

    // Step 2: Login Success
    // Use Role from backend
    if (data.user) {
      currentUserRole.value = data.user.role || "crew";
      userName.value = data.user.name || "";
      // Use sender code from database (crm_users.code field)
      senderCode.value = data.user.code || "";
      
      localStorage.setItem("cms_chat_role", currentUserRole.value);
      localStorage.setItem("cms_chat_name", userName.value);
      localStorage.setItem("cms_chat_sender_code", senderCode.value);
      localStorage.setItem("cms_chat_token", "true"); // Flag logged in
    }

    // Step 3: Connect WebSocket same as before (using authId as ID)
    connectWebSocket();
    fetchConversations();
    fetchQuickReplies();
    // fetchUserRole(); // REPLACED: Role now comes from Auth Response

    oneSignalLogin(authId.value);
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

  refreshInterval.value = setInterval(() => {
    if (isConnected.value && !document.hidden) {
      // console.log('Checking for missed updates...');
      fetchConversations();
    }
  }, 30000); // 30 seconds
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
  scrollToBottom();
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

// Show Customer Info Modal
const showCustomerInfo = () => {
  if (activeConversation.value) {
    showCustomerInfoModal.value = true;
  }
};

// Copy phone number to clipboard
const copyPhoneNumber = async () => {
  if (!activeConversation.value) return;

  try {
    // Convert to 08xx format
    let phone = activeConversation.value.wa_number || "";
    // Remove all non-digits
    phone = phone.replace(/\D/g, "");

    // Convert to 08xx format
    if (phone.startsWith("628")) {
      phone = "0" + phone.substring(2);
    } else if (phone.startsWith("62")) {
      phone = "0" + phone.substring(2);
    } else if (!phone.startsWith("0")) {
      phone = "0" + phone;
    }

    // Copy to clipboard
    await navigator.clipboard.writeText(phone);

    // Show copied feedback
    copiedPhone.value = true;
    setTimeout(() => {
      copiedPhone.value = false;
    }, 2000);
  } catch (err) {
    console.error("Failed to copy:", err);
    // Fallback for older browsers
    const textArea = document.createElement("textarea");
    textArea.value = formatPhoneTo08(activeConversation.value.wa_number);
    document.body.appendChild(textArea);
    textArea.select();
    try {
      document.execCommand("copy");
      copiedPhone.value = true;
      setTimeout(() => {
        copiedPhone.value = false;
      }, 2000);
    } catch (err2) {
      console.error("Fallback copy failed:", err2);
    }
    document.body.removeChild(textArea);
  }
};

// Format phone to 08xx
const formatPhoneTo08 = (phone) => {
  if (!phone) return "";
  let cleaned = phone.replace(/\D/g, "");
  if (cleaned.startsWith("628")) {
    return "0" + cleaned.substring(2);
  } else if (cleaned.startsWith("62")) {
    return "0" + cleaned.substring(2);
  } else if (!cleaned.startsWith("0")) {
    return "0" + cleaned;
  }
  return cleaned;
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
    const idKey = String(msg.id);
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
    if (!existing) {
      const normalize = (str) =>
        String(str || "")
          .replace(/\s+/g, " ")
          .trim();
      const msgTime = new Date(msg.rawTime || msg.time).getTime();
      const msgText = normalize(msg.text);

      // Look backwards for a fuzzy match (optimisation: only check last 10 messages)
      // We iterate result array which contains 'kept' messages
      for (let i = result.length - 1; i >= 0 && i >= result.length - 10; i--) {
        const cand = result[i];
        if (cand.sender === msg.sender && normalize(cand.text) === msgText) {
          const candTime = new Date(cand.rawTime || cand.time).getTime();
          if (Math.abs(candTime - msgTime) < 5000) {
            // 5s window
            existing = cand; // Found a fuzzy match!
            break;
          }
        }
      }
    }

    if (existing) {
      // MERGE STRATEGY: Keep the "Better" version
      // Prefer Integer IDs over Long Strings (Hex/UUID)
      // Prefer Existing WAMID over Null

      const existingIdIsInt = /^\d+$/.test(String(existing.id));
      const msgIdIsInt = /^\d+$/.test(String(msg.id));

      // If incoming is "better" (e.g. Real ID vs Hex ID), update the existing object
      if (msgIdIsInt && !existingIdIsInt) {
        existing.id = msg.id; // Upgrade ID
      }

      if (msg.wamid && !existing.wamid) {
        existing.wamid = msg.wamid; // Upgrade WAMID
      }

      if (
        msg.status &&
        msg.status !== "read" &&
        existing.status !== msg.status
      ) {
        existing.status = msg.status; // Update status
      }

      // Don't add 'msg' to result, we merged it into 'existing'
      // Update map keys to point to the merged object
      uniqueMap.set(String(existing.id), existing);
      if (existing.wamid) uniqueMap.set(existing.wamid, existing);
    } else {
      // New message
      result.push(msg);
      uniqueMap.set(idKey, msg);
      if (wamidKey) uniqueMap.set(wamidKey, msg);
    }
  });

  return result;
};

// --- Methods ---
const fetchMessages = async (phone) => {
  try {
    // Add cache buster
    const response = await fetch(
      `${API_BASE}/CRM/Chat/getMessages?phone=${phone}&_t=${Date.now()}`
    );
    const result = await response.json();

    if (result.status && Array.isArray(result.data)) {
      const mappedMessages = result.data.map((m) => ({
        id: m.id,
        wamid: m.wamid,
        text: m.text || m.caption,
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
        status: m.status,
        sender_code: m.sender_code,
      }));

      // Use Centralized Sanitizer
      return sanitizeMessages(mappedMessages);
    }
  } catch (e) {
    console.error("Error loading messages:", e);
  }
  return [];
};

const scrollToBottom = () => {
  nextTick(() => {
    if (chatContainer.value) {
      // Instant scroll without animation
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
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
      // Update local cases
      activeConversation.value.cases = [{ case: 0 }];
      console.log("✓ Conversation marked as done");

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

const checkPayment = async () => {
  if (!activeConversation.value || isCheckingPayment.value) return;

  try {
    isCheckingPayment.value = true;
    showChatMenu.value = false; // Close menu

    const response = await fetch(`${API_BASE}/CRM/Chat/updateCase`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        phone: activeConversation.value.wa_number,
        case: 1,
        user_id: authId.value,
      }),
    });

    const res = await response.json();

    if (res.status) {
      // Update local cases
      // Update local cases (Append for multi-case)
      if (!activeConversation.value.cases) activeConversation.value.cases = [];
      // Close case 4 if exists, remove case 0
      activeConversation.value.cases = activeConversation.value.cases
        .map((c) => (c.case === 4 ? { ...c, status: "closed" } : c))
        .filter((c) => c.case !== 0);
      // Add case 1 with status open
      if (
        !activeConversation.value.cases.some(
          (c) => c.case === 1 && c.status === "open"
        )
      ) {
        activeConversation.value.cases.push({ case: 1, status: "open" });
      }
      console.log("✓ Conversation marked for payment check");
    } else {
      console.error("Failed to mark for payment check:", res.message);
    }

    // Keep loading for 3 seconds
    setTimeout(() => {
      isCheckingPayment.value = false;
    }, 3000);
  } catch (e) {
    console.error("Error marking for payment check:", e);
    isCheckingPayment.value = false;
  }
};

const pickupDelivery = async () => {
  if (!activeConversation.value || isPickupDelivery.value) return;

  try {
    isPickupDelivery.value = true;
    showChatMenu.value = false; // Close menu

    const response = await fetch(`${API_BASE}/CRM/Chat/updateCase`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        phone: activeConversation.value.wa_number,
        case: 2,
        user_id: authId.value,
      }),
    });

    const res = await response.json();

    if (res.status) {
      // Update local cases
      // Update local cases (Append for multi-case)
      if (!activeConversation.value.cases) activeConversation.value.cases = [];
      // Close case 4 if exists, remove case 0
      activeConversation.value.cases = activeConversation.value.cases
        .map((c) => (c.case === 4 ? { ...c, status: "closed" } : c))
        .filter((c) => c.case !== 0);
      // Add case 2 with status open
      if (
        !activeConversation.value.cases.some(
          (c) => c.case === 2 && c.status === "open"
        )
      ) {
        activeConversation.value.cases.push({ case: 2, status: "open" });
      }
      console.log("✓ Conversation marked for pickup/delivery");
    } else {
      console.error("Failed to mark for pickup/delivery:", res.message);
    }

    // Keep loading for 3 seconds
    setTimeout(() => {
      isPickupDelivery.value = false;
    }, 3000);
  } catch (e) {
    console.error("Error marking for pickup/delivery:", e);
    isPickupDelivery.value = false;
  }
};

const requestPriority = async () => {
  if (!activeConversation.value || isRequest.value) return;

  try {
    isRequest.value = true;
    showChatMenu.value = false; // Close menu

    const response = await fetch(`${API_BASE}/CRM/Chat/updateCase`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        phone: activeConversation.value.wa_number,
        case: 3,
        user_id: authId.value,
      }),
    });

    const res = await response.json();

    if (res.status) {
      // Update local cases
      // Update local cases (Append for multi-case)
      if (!activeConversation.value.cases) activeConversation.value.cases = [];
      // Close case 4 if exists, remove case 0
      activeConversation.value.cases = activeConversation.value.cases
        .map((c) => (c.case === 4 ? { ...c, status: "closed" } : c))
        .filter((c) => c.case !== 0);
      // Add case 3 with status open
      if (
        !activeConversation.value.cases.some(
          (c) => c.case === 3 && c.status === "open"
        )
      ) {
        activeConversation.value.cases.push({ case: 3, status: "open" });
      }
      console.log("✓ Conversation marked as request");
    } else {
      console.error("Failed to mark as request:", res.message);
    }

    // Keep loading for 3 seconds
    setTimeout(() => {
      isRequest.value = false;
    }, 3000);
  } catch (e) {
    console.error("Error marking as request:", e);
    isRequest.value = false;
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
      // Update local cases (Append for multi-case)
      if (!activeConversation.value.cases) activeConversation.value.cases = [];
      activeConversation.value.cases = activeConversation.value.cases.filter(
        (c) => c.case !== 0
      );
      // Add case 4 with status open
      if (
        !activeConversation.value.cases.some(
          (c) => c.case === 4 && c.status === "open"
        )
      ) {
        activeConversation.value.cases.push({ case: 4, status: "open" });
      }
      console.log("✓ Conversation marked for follow up");
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
      console.log("✓ Conversation reopened - needs attention");
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
      console.log("Case resolved:", caseId);
    }
  } catch (e) {
    console.error("Error resolving case:", e);
  }
};

const selectChat = async (id) => {
  // Save current draft before switching chats
  if (activeChatId.value && messageInput.value.trim()) {
    chatDrafts.value[activeChatId.value] = messageInput.value;
  } else if (activeChatId.value) {
    // Clear draft if input is empty
    delete chatDrafts.value[activeChatId.value];
  }

  activeChatId.value = id;

  // WhatsApp-style slide-in animation (mobile only)
  if (window.innerWidth < 768) {
    isEnteringChat.value = true;
    showMobileChat.value = true;

    // Allow CSS transition to complete
    await new Promise((resolve) => setTimeout(resolve, 30));
    isEnteringChat.value = false;
  } else {
    showMobileChat.value = true;
  }

  // Persist state
  localStorage.setItem("active_chat_id", id);

  // Push history state to handle Android back button
  if (window.innerWidth < 768) {
    // Only on mobile
    window.history.pushState({ chatOpen: true }, "", "#chat=" + id);
  }

  // Restore draft for the new chat (or clear input)
  messageInput.value = chatDrafts.value[id] || "";

  // Reset textarea height based on new content
  nextTick(() => autoResizeTextarea());

  const chat = conversations.value.find((c) => c.id === id);
  if (chat) {
    // Optimistic read status
    chat.unread = 0;

    // Load messages
    // If we have cached messages, show them immediately and fetch in background
    if (chat.messages && chat.messages.length > 0) {
      scrollToBottom(); // Show cache immediately
      // Background fetch to sync and merge
      // Background fetch to sync and merge
      fetchMessages(chat.wa_number).then((msgs) => {
        if (msgs.length > 0) {
          // Merge simply by combining and then Sanitizing
          // This allows the Healer to work its magic on the combined set
          const combined = [...chat.messages, ...msgs];
          chat.messages = sanitizeMessages(combined);
          scrollToBottom();
        }
      });
    } else {
      // No cache, wait for fetch
      chat.messages = await fetchMessages(chat.wa_number);
    }

    // Mark read in DB
    markMessagesRead(chat.wa_number);
  }

  // Save active chat state for restoration after returning from external links
  saveActiveChatState();

  scrollToBottom();
};

const refreshActiveChat = async () => {
  if (!activeChatId.value) return;

  isRefreshingChat.value = true;
  try {
    // 1. Fetch latest metadata (CSW status, etc) for all chats
    await fetchConversations();

    // 2. Refresh messages for active chat
    await selectChat(activeChatId.value);
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
    console.log("🔄 Restoring chat state:", savedChatId);

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
        fetchMessages(target.wa_number).then((msgs) => {
          target.messages = msgs;
          scrollToBottom();
        });
      } else {
        scrollToBottom();
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
  // Save current draft before going back to menu
  if (activeChatId.value && messageInput.value.trim()) {
    chatDrafts.value[activeChatId.value] = messageInput.value;
  } else if (activeChatId.value) {
    delete chatDrafts.value[activeChatId.value];
  }

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
    const response = await fetch(`${API_BASE}/CRM/QuickReply/getAll`);
    const res = await response.json();
    if (res.status && res.data) {
      quickReplies.value = res.data;
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

// Get short preview of message for quoted display
const getMessagePreview = (msg) => {
  if (!msg) return "";
  if (msg.type === "image") return "📷 Image";
  if (msg.type === "video") return "🎥 Video";
  if (msg.type === "audio" || msg.type === "voice") return "🎤 Voice";
  if (msg.type === "document") return "📄 Document";
  if (msg.type === "location") return "📍 Location";
  if (msg.type === "sticker") return "🎨 Sticker";
  const text = msg.text || msg.caption || "";
  return text.length > 60 ? text.substring(0, 60) + "..." : text;
  return text.length > 60 ? text.substring(0, 60) + "..." : text;
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
    scrollToBottom();

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
      console.log("Native file picker failed:", err);
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
      console.log("Capacitor Camera error:", err);
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

    scrollToBottom();

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
  // DEBUG: Log ALL incoming messages to see what we really get
  console.log("📡 WS Payload:", payload);

  // Check if this is a status update
  if (payload.type === "status_update") {
    // ... existing status logic ...
    const { conversation_id, message, phone } = payload;
    const conversation = conversations.value.find(
      (c) =>
        (conversation_id && c.id == conversation_id) ||
        (phone && c.wa_number == phone)
    );

    if (conversation) {
      const msgToUpdate = conversation.messages.find(
        (m) => m.id == message.id || m.wamid == message.wamid
      );
      if (msgToUpdate) {
        msgToUpdate.status = message.status;
      }
    }
    return;
  }

  // Handle priority update (Standard)
  if (payload.type === "priority_updated") {
    const { phone, priority } = payload;
    console.log("[WebSocket] Received priority_updated:", { phone, priority });

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

  const text = messageData.text;
  const type = messageData.type || "text";
  const sender = messageData.sender || "customer";

  let displayText = text;
  if (!displayText && type !== "text") {
    displayText = `[${type}]`;
    if (messageData.media_caption)
      displayText += " " + messageData.media_caption;
  }
  const name = payload.contact_name || payload.name;

  // Find or create conversation
  let conversation = conversations.value.find(
    (c) =>
      (conversationId && c.id == conversationId) ||
      (phone && c.wa_number == phone)
  );

  if (!conversation) {
    // New conversation
    conversation = {
      id: conversationId,
      wa_number: phone, // ✅ Add wa_number
      name: name || payload.phone || "Unknown User",
      kode_cabang: payload.kode_cabang || "00", // Set from payload
      // priority: parseInt(payload.priority) || 0, // Legacy
      cases: [{ case: parseInt(payload.case || payload.priority || 0) }], // Initialize cases
      initials: (name || payload.phone || "?").substring(0, 1).toUpperCase(),
      color: getAvatarColor(conversationId),
      status: "online", // Assume online on new msg
      messages: [],
      unread: 0,
    };
    conversations.value.unshift(conversation);
  } else {
    // Update existing conversation details if available
    if (payload.kode_cabang) {
      conversation.kode_cabang = payload.kode_cabang;
    }
    if (payload.priority !== undefined) {
      conversation.priority = parseInt(payload.priority) || 0;
    }
    // Update assignment if provided
    if (payload.assignment_user_id !== undefined) {
      conversation.assignment_user_id = payload.assignment_user_id;
    }
    // Update case if provided. Prioritize 'active_cases' list from server for accuracy.
    if (
      payload.active_cases &&
      Array.isArray(payload.active_cases) &&
      payload.active_cases.length > 0
    ) {
      conversation.cases = payload.active_cases.map((c) => ({
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
        // Smart Merge: Open the new case
        const existing = conversation.cases.find((c) => c.case === newCase);
        if (existing) {
          existing.status = "open";
        } else {
          conversation.cases.push({ case: newCase, status: "open" });
        }

        // Auto-close Case 4 (Follow Up) if the new open case is NOT 4
        if (newCase !== 4) {
          const c4 = conversation.cases.find((c) => c.case === 4);
          if (c4) c4.status = "closed";
        }
      }
    }
  }

  const newMsg = {
    id: messageData.id || Date.now(),
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
  };

  // DEBUG: Log every incoming message attempt
  console.log("[handleIncomingMessage] Processing:", {
    conversation: conversationId || phone,
    sender: sender,
    text: displayText,
    id: newMsg.id,
    source: "handleIncomingMessage",
  });

  // Avoid duplicate messages if already present
  // Enhanced check: ID match OR (same sender + same text + within 2 seconds)
  const isDuplicate = conversation.messages.find((m) => {
    // Exact ID match (string comparison for safety)
    if (String(m.id) === String(newMsg.id)) return true;

    // Wamid match
    if (m.wamid && newMsg.wamid && String(m.wamid) === String(newMsg.wamid))
      return true;

    // Fuzzy match: same sender + same NORMALIZED text + close timestamp
    const normalize = (str) =>
      String(str || "")
        .replace(/\s+/g, " ")
        .trim();

    if (
      m.sender === newMsg.sender &&
      normalize(m.text) === normalize(newMsg.text)
    ) {
      // Check if timestamps are within 5 seconds of each other
      const time1 = new Date(m.rawTime || m.time).getTime();
      const time2 = new Date(newMsg.rawTime || newMsg.time).getTime();

      if (!isNaN(time1) && !isNaN(time2) && Math.abs(time1 - time2) < 5000) {
        console.log(
          "⚠️ Duplicate detected (fuzzy match):",
          newMsg.id,
          "matches existing:",
          m.id
        );
        return true;
      }
    }

    return false;
  });

  if (!isDuplicate) {
    console.log("✓ Adding message to conversation:", newMsg.id);
    // Simply push to array
    conversation.messages.push(newMsg);

    // Sort entire array by rawTime to ensure chronological order
    conversation.messages.sort((a, b) => {
      if (!a.rawTime || !b.rawTime) return 0;
      return new Date(a.rawTime) - new Date(b.rawTime);
    });

    // Re-sanitize entire conversation to be sure
    conversation.messages = sanitizeMessages(conversation.messages);

    conversation.lastMessage = displayText;
    conversation.lastTime = formatLastTime(newMsg.rawTime);

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
        console.log("🔔 Auto-opening conversation:", conversation.name);
        selectChat(conversation.id);
      }
    } else {
      console.log("✓ Chat is visible, scrolling to bottom. Current messages count:", conversation.messages.length);
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
    console.log("Force disconnecting old socket...");
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

const connectWebSocket = () => {
  if (!authId.value) return;

  // Cleanup existing socket to prevent zombies
  forceDisconnect();

  console.log("Connecting to WebSocket with ID:", authId.value);

  try {
    // Always connect to Production Server (as per user workflow)
    const wsUrl = `wss://waserver.nalju.com?id=${authId.value.trim()}`;
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
      console.log("WebSocket connected (awaiting server confirmation...)");
      
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
      // DEBUG: Log raw WebSocket data to confirm messages are received
      console.log("🔌 [WS RAW]", event.data.substring(0, 200));
      
      try {
        const payload = JSON.parse(event.data);

        // EFFICIENCY: Reset 30s polling timer whenever specific events arrive
        // If we get real-time data, we don't need to poll immediately
        if (
          [
            "status_update",
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
            console.log("User Role Set:", payload.role);
          }
          console.log("✅ Connection stable - welcome received");
          return;
        }

        if (payload.type === "status_update") {
          handleIncomingMessage(payload);
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
          console.log("[WS] case_updated received:", payload);

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
            console.log("✓ Updating case for conversation:", conv.name, newC);

            if (!conv.cases) conv.cases = [];

            if (newC === 0) {
              // Reset/Clear all active? usually 0 means "reset"
              conv.cases = [{ case: 0, status: "open" }];
            } else {
              // 1. Update/Add Target Case
              const existing = conv.cases.find((c) => c.case === newC);
              if (existing) {
                existing.status = "open"; // Re-open/Update
              } else {
                conv.cases.push({ case: newC, status: "open" });
              }

              // 2. Auto-close Case 4 if new case is not 4
              if (newC !== 4) {
                const case4 = conv.cases.find((c) => c.case === 4);
                if (case4) {
                  case4.status = "closed";
                }
              }

              // 3. Remove dummy case 0
              conv.cases = conv.cases.filter((c) => c.case !== 0);
            }
          } else {
            console.log(
              "⚠️ Conversation not found for case update - reloading list:",
              payload.phone
            );
            fetchConversations(); // Reload to get new conversation
          }

          return;
        }

        if (payload.type === "case_resolved") {
          console.log("[WS] case_resolved received:", payload);
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

          // DEBUG: Log all agent messages for troubleshooting
          console.log("[WS] agent_message_sent:", {
            conversation: conversationId,
            sender: senderId,
            myId: authId.value,
            text: messageData.text,
            match: senderId == authId.value,
          });

          // Skip if this message was sent by current user (use == for type safety)
          // This prevents the duplicate when server echoes our own message back
          if (senderId == authId.value) {
            console.log("✓ Ignoring self-broadcast (already in optimistic UI)");
            return;
          }

          const conversation = conversations.value.find(
            (c) =>
              (conversationId && c.id == conversationId) ||
              (payload.phone && c.wa_number == payload.phone)
          );
          if (conversation) {
            // Enhanced duplicate check: ID, wamid, OR media_url for images
            const existingMessage = conversation.messages.find(
              (m) =>
                m.id == messageData.id ||
                (m.wamid &&
                  messageData.wamid &&
                  m.wamid == messageData.wamid) ||
                (messageData.type === "image" &&
                  m.media_url &&
                  messageData.media_url &&
                  m.media_url == messageData.media_url)
            );

            if (existingMessage) {
              // Update existing message (from optimistic UI after API response)
              existingMessage.id = messageData.id;
              existingMessage.wamid = messageData.wamid;
              existingMessage.status = messageData.status || "sent";
              if (messageData.media_url)
                existingMessage.media_url = messageData.media_url;
              console.log("Updated existing message:", existingMessage.id);
              // Don't add as new - already exists
            } else {
              // NEW DEFENSE: Robust Fuzzy Match
              // Search backwards for the most recent message from 'me' with same text
              // This handles race conditions where the order might be slightly off or not the very last item
              let pendingMatch = null;
              const cleanIncomingText = (messageData.text || "").trim();

              // Scan last 5 messages
              for (let i = conversation.messages.length - 1; i >= 0; i--) {
                if (conversation.messages.length - i > 5) break;

                const m = conversation.messages[i];
                const cleanLocalText = (m.text || "").trim();

                // Check match: Sender is me AND text matches
                if (m.sender === "me" && cleanLocalText === cleanIncomingText) {
                  // If it's already "read", we probably shouldn't merge (it's old)
                  // But if it's pending, sent, or delivered, it's a candidate
                  if (m.status !== "read") {
                    pendingMatch = m;
                    break;
                  }
                }
              }

              if (pendingMatch) {
                console.log(
                  "Matched duplicate (Fuzzy Refined):",
                  pendingMatch.id
                );

                // Update IDs to server values
                pendingMatch.id = messageData.id;
                if (messageData.wamid) pendingMatch.wamid = messageData.wamid;
                pendingMatch.status = messageData.status || "sent";
                if (messageData.media_url)
                  pendingMatch.media_url = messageData.media_url;
                return; // Stop, don't add new
              }

              // Add new message (from another agent/device)
              const newMsg = {
                id: messageData.id,
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
                status: messageData.status || "sent",
              };

              conversation.messages.push(newMsg);

              // Sort messages by rawTime to ensure chronological order
              conversation.messages.sort((a, b) => {
                if (!a.rawTime || !b.rawTime) return 0;
                return new Date(a.rawTime) - new Date(b.rawTime);
              });

              conversation.lastMessage =
                messageData.type === "image"
                  ? "You: 📷 Image"
                  : "You: " + messageData.text;
              conversation.lastTime = newMsg.time;

              console.log("Added new message from other device:", newMsg.id);

              // Auto-scroll if viewing this conversation
              if (activeChatId.value == conversationId) {
                scrollToBottom();
              }
            }
          }
          return;
        }

        if (payload.type === "wa_masuk") {
          // Real incoming WA message (wrapped format)
          console.log("📨 [WS] wa_masuk received");
          handleIncomingMessage(payload.data);
        } else if (payload.conversation_id || payload.conversationId) {
          // Direct message format (snake_case or camelCase)
          // This is the format sent directly from server without type wrapper
          console.log("📨 [WS] Direct message received for conversation:", payload.conversation_id || payload.conversationId);
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
        console.log(
          "WebSocket disconnected, code:",
          event.code,
          "reason:",
          event.reason
        );
        isConnected.value = false;

        // Check if disconnected due to connection limit (code 1008 = duplicate connection)
        if (event.code === 1008) {
          // GUARD: If this happens shortly after resume (within 5s), it's likely a false positive involving the old socket
          const isRecentResume = Date.now() - resumeTimestamp.value < 5000;

          if (isRecentResume) {
            console.warn(
              "Ignoring duplicate connection error during resume grace period. Retrying..."
            );
            // Retry once after delay
            setTimeout(() => {
              if (!isConnected.value && authId.value) connectWebSocket();
            }, 1500);
          } else if (duplicateRetryAttempts.value < maxDuplicateRetries) {
            // NETWORK SWITCH HANDLING:
            // When network switches (WiFi <-> Mobile), the old connection may still be "alive"
            // on the server because the server heartbeat (30s) hasn't detected it as dead yet.
            // We retry a few times with delay to give server time to clean up the zombie connection.
            
            duplicateRetryAttempts.value++;
            const attempt = duplicateRetryAttempts.value;
            
            console.warn(
              `Duplicate connection detected (attempt ${attempt}/${maxDuplicateRetries}). ` +
              `Waiting ${duplicateRetryDelay/1000}s for server cleanup before retry...`
            );
            
            connectionError.value = `Reconnecting... (${attempt})`;
            isReconnecting.value = true;
            
            setTimeout(() => {
              if (!isConnected.value && authId.value) {
                console.log(`Retrying connection after duplicate error (attempt ${attempt})...`);
                connectWebSocket();
              }
            }, duplicateRetryDelay);
          } else {
            // All retries exhausted - this is likely a real duplicate connection
            // (user is truly connected on another device/tab)
            console.warn(
              "Connection closed: Max duplicate retries exceeded. Another connection with same ID exists. Logging out..."
            );
            
            // Reset retry counter for next time
            duplicateRetryAttempts.value = 0;
            
            // Set warning message for login card
            duplicateWarning.value = "ID Anda sudah terkoneksi di tab/device lain. Silakan login ulang atau logout dari device lain.";
            
            // Clear session and show login
            localStorage.removeItem("cms_chat_id");
            localStorage.removeItem("cms_chat_password");
            localStorage.removeItem("cms_chat_expiry");
            authId.value = "";
            wasConnected.value = false;
            isReconnecting.value = false;
            showLoginPrompt.value = true;
          }
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
          console.log(
            `Auto-reconnecting in ${delay}ms (attempt ${reconnectAttempts.value})`
          );

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
        if (event.code === 1008) {
          // Check if this is a recent resume (grace period)
          const isRecentResume = Date.now() - resumeTimestamp.value < 5000;
          
          if (isRecentResume) {
            console.warn("Ignoring duplicate connection error during resume (pre-welcome). Retrying...");
            setTimeout(() => {
              if (!isConnected.value && authId.value) connectWebSocket();
            }, 1500);
            return; // Don't show error, just retry
          } else if (duplicateRetryAttempts.value < maxDuplicateRetries) {
            // Retry logic for duplicate connection
            duplicateRetryAttempts.value++;
            const attempt = duplicateRetryAttempts.value;
            
            console.warn(
              `Duplicate connection detected (pre-welcome, attempt ${attempt}/${maxDuplicateRetries}). ` +
              `Waiting ${duplicateRetryDelay/1000}s for server cleanup before retry...`
            );
            
            connectionError.value = `Reconnecting... (${attempt})`;
            isReconnecting.value = true;
            
            setTimeout(() => {
              if (!isConnected.value && authId.value) {
                console.log(`Retrying connection after duplicate error (attempt ${attempt})...`);
                connectWebSocket();
              }
            }, duplicateRetryDelay);
            return; // Don't show login yet
          } else {
            // All retries exhausted - show duplicate warning and logout
            console.warn("Max duplicate retries exceeded (pre-welcome). Logging out...");
            duplicateRetryAttempts.value = 0;
            duplicateWarning.value = "ID Anda sudah terkoneksi di tab/device lain. Silakan login ulang atau logout dari device lain.";
            localStorage.removeItem("cms_chat_id");
            localStorage.removeItem("cms_chat_password");
            localStorage.removeItem("cms_chat_expiry");
            authId.value = "";
            wasConnected.value = false;
            isReconnecting.value = false;
            showLoginPrompt.value = true;
            return;
          }
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
          console.log(
            `Reconnect attempt ${reconnectAttempts.value} in ${delay}ms`
          );

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

const resumeChatState = () => {
  const savedId = localStorage.getItem("active_chat_id");
  const savedMobileChat = localStorage.getItem("show_mobile_chat");

  if (savedId && !activeChatId.value) {
    const id = parseInt(savedId);
    // Only switch if conversation exists
    if (conversations.value.some((c) => c.id === id)) {
      selectChat(id);

      // CRITICAL: Restore showMobileChat state for mobile devices
      // This ensures back button handler works correctly after Android resume
      if (savedMobileChat === "true" && windowWidth.value < 768) {
        showMobileChat.value = true;
        // Re-push history state to ensure back button works after long background
        window.history.pushState({ chatOpen: true }, "", "#chat=" + id);
      }
    }
  } else if (activeChatId.value && showMobileChat.value && windowWidth.value < 768) {
    // Chat already open - just ensure history state exists for back button
    window.history.pushState({ chatOpen: true }, "", "#chat=" + activeChatId.value);
  }
};

onMounted(() => {
  // Check for Deep Link / Notification Click (URL Param)
  const urlParams = new URLSearchParams(window.location.search);
  const deepLinkPhone = urlParams.get("phone");
  if (deepLinkPhone) {
    console.log("🔗 Deep link detected for phone:", deepLinkPhone);
    pendingTargetPhone.value = deepLinkPhone;
    // Clean URL silently
    window.history.replaceState({}, document.title, "/");
  }

  // Add Paste Listener

  window.addEventListener("paste", handlePaste);

  scrollToBottom();

  // --- LOADING TIMEOUT SAFETY NET ---
  // If app is stuck on loading screen for more than 5 seconds, force show login
  // This prevents the "infinite loading" bug after Android sleep
  setTimeout(() => {
    if (!isConnected.value && !showLoginPrompt.value && !isConnecting.value) {
      console.log("⚠️ Loading timeout - forcing login prompt");
      showLoginPrompt.value = true;
    }
  }, 3000);

  // --- NO CACHE LOADING ---
  // Always fetch fresh data from server for 100% accuracy

  // --- VISIBILITY CHANGE HANDLER ---
  // Fix for blank screen/disconnect after long backgrounding (Android sleep)
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
      console.log("App resumed from background, checking connection...");

      // Update resume timestamp FIRST
      resumeTimestamp.value = Date.now();

      // Check if socket is dead or not connected
      if (!socket.value || socket.value.readyState !== WebSocket.OPEN) {
        console.log("Socket disconnected, initiating reconnect...");

        // Reset reconnect state to allow fresh reconnection
        reconnectAttempts.value = 0;
        reconnectDelay.value = 3000;
        isReconnecting.value = true;

        // SMART DELAY: If we recently disconnected (network change scenario),
        // wait longer to give server time to cleanup old connection
        const timeSinceDisconnect = Date.now() - lastDisconnectTime.value;
        const needsDelay = timeSinceDisconnect < 10000; // Within 10 seconds of disconnect
        const reconnectDelayMs = needsDelay ? 3000 : 500; // 3s delay if recent disconnect, else quick

        connectionError.value = needsDelay
          ? "Reconnecting..."
          : "Reconnecting...";

        console.log(
          `Reconnect delay: ${reconnectDelayMs}ms (recent disconnect: ${needsDelay})`
        );

        // Only reconnect if we have ID
        if (authId.value) {
          // Force disconnect any zombie socket first
          forceDisconnect();

          // Delayed reconnect to allow server cleanup
          setTimeout(() => {
            if (!isConnected.value && authId.value) {
              connectWebSocket();
            }
          }, reconnectDelayMs);
        } else {
          // No ID - show login
          isReconnecting.value = false;
          showLoginPrompt.value = true;
        }
      }

      // Refresh data to ensure sync
      fetchConversations();

      // Restore active chat state if user was viewing a chat before leaving
      // This handles the case when user clicks a link and comes back
      resumeChatState();
    }
  });

  // --- ANDROID WEBVIEW RESUME HANDLER ---
  // Handle custom event from Android MainActivity.onResume()
  window.addEventListener("androidResume", () => {
    console.log("📱 Android Resume event received");

    resumeTimestamp.value = Date.now();

    // Check if socket is dead and reconnect
    if (!socket.value || socket.value.readyState !== WebSocket.OPEN) {
      console.log("Socket disconnected, reconnecting from Android resume...");

      // Reset reconnect state
      reconnectAttempts.value = 0;
      reconnectDelay.value = 3000;
      isReconnecting.value = true;

      // Smart delay for network change scenario
      const timeSinceDisconnect = Date.now() - lastDisconnectTime.value;
      const needsDelay = timeSinceDisconnect < 10000;
      const reconnectDelayMs = needsDelay ? 3000 : 500;

      connectionError.value = needsDelay
        ? "Reconnecting..."
        : "Reconnecting...";

      if (authId.value) {
        forceDisconnect();
        setTimeout(() => {
          if (!isConnected.value && authId.value) {
            connectWebSocket();
          }
        }, reconnectDelayMs);
      } else {
        isReconnecting.value = false;
        showLoginPrompt.value = true;
      }
    }

    // Refresh conversations
    fetchConversations();
    resumeChatState();
  });

  // --- EXPOSE GLOBAL FUNCTION FOR ANDROID ---
  // Android WebView can call: window.triggerReconnect()
  window.triggerReconnect = () => {
    console.log("📱 triggerReconnect called from Android");

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
  // Save state when page is about to be hidden (covers all navigation cases)
  window.addEventListener("pagehide", () => {
    persistChatState();
  });

  window.addEventListener("beforeunload", () => {
    persistChatState();
  });

  // --- ANDROID BACK BUTTON HANDLER ---
  // Push initial history state to prevent immediate exit on back
  if (window.innerWidth < 768) {
    window.history.replaceState({ appRoot: true }, "", window.location.href);
  }

  // Track last back press for double-back-to-exit
  let lastBackPressTime = 0;

  window.addEventListener("popstate", (event) => {
    // If mobile chat ui is open, just close it and stay on page
    if (showMobileChat.value) {
      console.log("🔙 Android Back: Closing chat overlay");
      showMobileChat.value = false;
      activeChatId.value = null;
      localStorage.removeItem("active_chat_id");
      // Re-push state to allow another back press
      window.history.pushState({ appRoot: true }, "", window.location.href.split('#')[0]);
    } else if (window.innerWidth < 768) {
      // No chat open - implement double-back-to-exit
      const now = Date.now();
      if (now - lastBackPressTime < 2000) {
        // Second back press within 2 seconds - allow exit (do nothing, let default happen)
        console.log("🔙 Android Back: Exiting app");
        return;
      }
      
      // First back press - show toast and prevent exit
      lastBackPressTime = now;
      console.log("🔙 Android Back: Press again to exit");
      showExitToast.value = true;
      setTimeout(() => {
        showExitToast.value = false;
      }, 2000);
      
      // Re-push state to stay on page
      window.history.pushState({ appRoot: true }, "", window.location.href.split('#')[0]);
    }
  });

  // Load font size preference
  loadFontSize();

  // Load theme preference
  loadTheme();

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

  if (storedRole) currentUserRole.value = storedRole;
  if (storedName) userName.value = storedName;
  if (storedSenderCode) senderCode.value = storedSenderCode;

  // Case 1: Valid session (ID + Valid Expiry)
  if (storedId && storedExpiry && now < parseInt(storedExpiry)) {
    // Force uppercase for OneSignal compatibility
    const uppercaseId = storedId.toUpperCase();
    console.log("Restoring session for ID:", uppercaseId);
    authId.value = uppercaseId;

    // Update localStorage if it was lowercase
    if (storedId !== uppercaseId) {
      localStorage.setItem("cms_chat_id", uppercaseId);
    }

    // Renew expiry for another 3 days
    const newExpiry = new Date().getTime() + 3 * 24 * 60 * 60 * 1000;
    localStorage.setItem("cms_chat_expiry", newExpiry.toString());

    connectWebSocket();
    fetchConversations().then(() => {
      // Restore active chat if persisted for resume
      resumeChatState();
    });

    // Re-login to OneSignal with uppercase ID
    oneSignalLogin(uppercaseId);
  }
  // Case 2: Has ID but expired - Keep ID, prompt to reconnect
  else if (storedId && storedExpiry && now >= parseInt(storedExpiry)) {
    // Force uppercase for OneSignal compatibility
    const uppercaseId = storedId.toUpperCase();
    console.log("Session expired. ID found, please reconnect.");
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
    console.log(
      `📲 openChatByPhone called from Android: ${phone} (retry: ${retryCount})`
    );

    if (!phone) {
      console.log("❌ openChatByPhone: No phone provided");
      return;
    }

    // Normalize phone number for matching
    const cleanPhone = String(phone).replace(/\D/g, "");
    console.log(
      `📲 Cleaned phone: ${cleanPhone}, Conversations loaded: ${conversations.value.length}`
    );

    // If conversations not loaded yet, retry with exponential backoff
    if (conversations.value.length === 0 && retryCount < 10) {
      console.log(
        `⏳ Conversations not loaded, setting pendingTargetPhone and retrying in 500ms...`
      );
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
      console.log("✅ Found conversation:", target.name, target.id);
      pendingTargetPhone.value = null; // Clear pending

      // Use selectChat to properly load messages from API
      selectChat(target.id);
    } else {
      console.log(
        "⚠️ Conversation not found, setting pending target:",
        cleanPhone
      );
      // Store for later (will be handled when fetchConversations completes)
      pendingTargetPhone.value = cleanPhone;

      // Trigger refresh if not already loading
      if (!isLoadingConversations.value) {
        fetchConversations();
      }
    }
  };

  // Also expose a debug function
  window.debugChatState = () => {
    console.log("=== DEBUG CHAT STATE ===");
    console.log("Conversations count:", conversations.value.length);
    console.log("pendingTargetPhone:", pendingTargetPhone.value);
    console.log("activeChatId:", activeChatId.value);
    console.log("isConnected:", isConnected.value);
    console.log("showMobileChat:", showMobileChat.value);
    console.log("========================");
  };
}

// Handle Android Back Button (Capacitor)

App.addListener("backButton", () => {
  handleBackButtonPress();
});

// Handle App State Change (Capacitor) - Resume from background/sleep
App.addListener("appStateChange", ({ isActive }) => {
  console.log(
    "Capacitor App State Changed:",
    isActive ? "ACTIVE" : "BACKGROUND"
  );

  if (isActive) {
    // App resumed from background - same logic as visibilitychange
    if (!socket.value || socket.value.readyState !== WebSocket.OPEN) {
      console.log(
        "App resumed (Capacitor), socket disconnected, reconnecting..."
      );

      // Reset reconnect state
      reconnectAttempts.value = 0;
      reconnectDelay.value = 3000;
      isReconnecting.value = true;
      connectionError.value = "Reconnecting...";

      if (authId.value) {
        connectWebSocket();
      } else {
        isReconnecting.value = false;
        showLoginPrompt.value = true;
      }
    }

    // Refresh conversations
    fetchConversations();

    // Update resume timestamp
    resumeTimestamp.value = Date.now();
  }
});

// Expose global function for Android WebView (non-Capacitor)
// Android Studio can call: webView.evaluateJavascript("window.onAndroidBackPressed()", null)
window.onAndroidBackPressed = () => {
  return handleBackButtonPress();
};

// Unified back button handler for both Capacitor and WebView
function handleBackButtonPress() {
  console.log("🔙 handleBackButtonPress called");
  console.log("   showInternalBrowser:", showInternalBrowser.value);
  console.log("   showImageLightbox:", showImageLightbox.value);
  console.log("   showSettingsModal:", showSettingsModal.value);
  console.log("   showMobileChat:", showMobileChat.value);
  console.log("   activeChatId:", activeChatId.value);
  
  // Priority 0: Close Internal Browser if open
  if (showInternalBrowser.value) {
    closeInternalBrowser();
    return "internal_browser_closed";
  }

  // Priority 1: Close Image Lightbox if open
  if (showImageLightbox.value) {
    closeImageLightbox();
    return "lightbox_closed"; // Return status for Android
  }

  // Priority 2: Close Settings Modal if open
  if (showSettingsModal.value) {
    showSettingsModal.value = false;
    return "settings_closed";
  }

  // Priority 3: If chat view is open, go back to menu with animation
  // ROBUST CHECK: On mobile, if activeChatId exists, user is in chat even if showMobileChat got desynced
  const isMobile = windowWidth.value < 768;
  let isInChatView = showMobileChat.value || (isMobile && activeChatId.value);
  
  // **FALLBACK CHECK**: If Vue state appears empty but localStorage says we were in chat,
  // this means the app lost state during long sleep. Handle it gracefully.
  if (!isInChatView && isMobile) {
    const savedChatId = localStorage.getItem("active_chat_id");
    const savedMobileChat = localStorage.getItem("show_mobile_chat");
    
    console.log("   localStorage fallback - savedChatId:", savedChatId, "savedMobileChat:", savedMobileChat);
    
    if (savedChatId && savedMobileChat === "true") {
      console.log("🔙 State lost during sleep - recovering from localStorage");
      // Vue state was lost, but localStorage still has chat info
      // Recover the state
      const chatIdNum = parseInt(savedChatId);
      if (!isNaN(chatIdNum)) {
        activeChatId.value = chatIdNum;
        showMobileChat.value = true;
        isInChatView = true;
      }
    }
  }

  if (isInChatView && activeChatId.value) {
    // SYNC FIX: Ensure showMobileChat is true before closing (in case it got desynced)
    if (!showMobileChat.value && isMobile) {
      showMobileChat.value = true;
    }
    backToMenu(true); // Use animated back
    return "chat_closed"; // Return status for Android
  }

  // Priority 4: If already in menu, handle double-press to exit
  const timeNow = Date.now();
  if (timeNow - lastBackPress < 2000) {
    // Double press -> tell Android to exit
    return "should_exit";
  } else {
    lastBackPress = timeNow;
    showExitToast.value = true;
    setTimeout(() => {
      showExitToast.value = false;
    }, 2000);
    return "toast_shown"; // First press, show toast
  }
}

// Login Modal Delay Logic
setTimeout(() => {
  // Only show login prompt if we are not connected, not connecting, AND don't have a saved ID
  if (!isConnected.value && !isConnecting.value && !authId.value) {
    showLoginPrompt.value = true;
  }
}, 1500); // Wait 1.5s before showing modal if not connected

const logout = () => {
  if (socket.value) {
    socket.value.close();
    socket.value = null;
  }
  isConnected.value = false;
  authId.value = "";
  isConnecting.value = false;
  showLoginPrompt.value = true;

  // Clear Session
  localStorage.removeItem("cms_chat_id");
  localStorage.removeItem("cms_chat_expiry");
  // Note: conversations cache removed - data always from server

  // OneSignal: Logout from push notifications
  oneSignalLogout();
};

// Cleanup on unmount
onUnmounted(() => {
  if (titleBlinkInterval.value) {
    clearInterval(titleBlinkInterval.value);
    document.title = originalTitle;
  }
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

// Open location in Google Maps
const openLocation = (mapUrl) => {
  if (mapUrl) {
    window.open(mapUrl, "_blank");
  }
};

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

// --- Internal Browser Functions (for nalju.com links) ---
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

const openInternalBrowser = async (url) => {
  internalBrowserUrl.value = url;
  isInternalBrowserLoading.value = true;
  isInternalBrowserEntering.value = true;
  showInternalBrowser.value = true;

  // Allow CSS transition to complete
  await new Promise((resolve) => setTimeout(resolve, 30));
  isInternalBrowserEntering.value = false;
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

// Handle link clicks - intercept nalju.com links
const handleLinkClick = (e) => {
  const link = e.target.closest("a[href]");
  if (link && link.href) {
    // Check if it's a nalju.com link
    if (isNaljuDomain(link.href)) {
      e.preventDefault();
      e.stopPropagation();
      openInternalBrowser(link.href);
      return;
    }

    // External link - save state before navigating
    if (link.href.startsWith("http://") || link.href.startsWith("https://")) {
      persistChatState();
      console.log("📎 External link clicked, saving chat state");
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
      :is-reconnecting="isReconnecting"
      :is-connected="isConnected"
      :connection-error="connectionError"
      :show-mobile-chat="showMobileChat"
      :total-unread-count="totalUnreadCount"
      :total-open-cases-count="totalOpenCasesCount"
      @select-chat="selectChat"
      @logout="logout"
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
      :is-connected="isConnected"
      :font-size="fontSize"
      @back-to-menu="backToMenu"
      @open-image-lightbox="openImageLightbox"
      @refresh-active-chat="refreshActiveChat"
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
                    Ubah tampilan aplikasi
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

    <!-- Customer Info Modal -->
    <div
      v-if="showCustomerInfoModal"
      class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[600] flex items-center justify-center p-4"
      @click="showCustomerInfoModal = false"
    >
      <div
        class="bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-2xl shadow-2xl max-w-sm w-full p-6 animate-scale-in"
        @click.stop
      >
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-[var(--wa-text-primary)]">
            Info Customer
          </h2>
          <button
            @click="showCustomerInfoModal = false"
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

        <!-- Customer Avatar -->
        <div class="flex justify-center mb-6">
          <div
            class="w-20 h-20 rounded-full flex items-center justify-center text-white font-bold text-2xl shadow-lg"
            :style="{ backgroundColor: activeConversation?.color }"
          >
            {{ activeConversation?.initials }}
          </div>
        </div>

        <!-- Customer Info -->
        <div class="space-y-4">
          <!-- Name -->
          <div
            class="bg-[var(--wa-bg-secondary)] rounded-xl p-4 border border-[var(--wa-border)]"
          >
            <label class="text-xs text-[var(--wa-text-tertiary)] mb-1 block"
              >Nama</label
            >
            <p
              class="text-base font-medium text-[var(--wa-text-primary)] uppercase"
            >
              {{ activeConversation?.name }}
            </p>
          </div>

          <!-- Phone Number -->
          <div
            class="bg-[var(--wa-bg-secondary)] rounded-xl p-4 border border-[var(--wa-border)]"
          >
            <label class="text-xs text-[var(--wa-text-tertiary)] mb-1 block"
              >Nomor WhatsApp</label
            >
            <div class="flex items-center justify-between gap-3">
              <p
                class="text-lg font-mono font-semibold text-[var(--wa-text-primary)]"
              >
                {{ formatPhoneTo08(activeConversation?.wa_number) }}
              </p>
              <button
                @click="copyPhoneNumber"
                class="flex items-center gap-2 px-4 py-2 rounded-lg transition-all flex-shrink-0"
                :class="
                  copiedPhone
                    ? 'bg-green-500/20 text-green-400'
                    : 'bg-[var(--wa-accent-green)] text-black hover:bg-[#00a884]/90'
                "
              >
                <!-- Copy Icon -->
                <svg
                  v-if="!copiedPhone"
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                  />
                </svg>
                <!-- Check Icon -->
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                <span class="text-sm font-medium">{{
                  copiedPhone ? "Copied!" : "Copy"
                }}</span>
              </button>
            </div>
          </div>

          <!-- Branch Code (if exists) -->
          <div
            v-if="activeConversation?.kode_cabang"
            class="bg-[var(--wa-bg-secondary)] rounded-xl p-4 border border-[var(--wa-border)]"
          >
            <label class="text-xs text-[var(--wa-text-tertiary)] mb-1 block"
              >Cabang</label
            >
            <p
              class="text-base font-mono font-semibold text-[var(--wa-text-primary)]"
            >
              {{ activeConversation?.kode_cabang }}
            </p>
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

      <!-- Loading Indicator -->
      <div
        v-if="isInternalBrowserLoading"
        class="absolute inset-0 top-14 flex items-center justify-center bg-[var(--wa-bg-chat)]"
      >
        <div class="flex flex-col items-center gap-3">
          <div
            class="w-8 h-8 border-3 border-[var(--wa-accent-green)] border-t-transparent rounded-full animate-spin"
          ></div>
          <p class="text-sm text-[var(--wa-text-secondary)]">Memuat...</p>
        </div>
      </div>

      <!-- Iframe -->
      <iframe
        :src="internalBrowserUrl"
        class="flex-1 w-full border-0 bg-white"
        @load="handleInternalBrowserLoad"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-modals allow-downloads"
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
