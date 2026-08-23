/**
 * Main Chat Store - Central state management for the CRM Chat
 * Uses Vue 3 Composition API with reactive state
 */
import { ref, computed } from "vue";

// ============================================================================
// API Configuration
// ============================================================================
export const API_BASE = "https://api.nalju.com";
export const LAUNDRY_BASE = "https://ml.nalju.com";

/**
 * Load quick replies from laundry public GET endpoints (rekening + lokasi).
 * Returns [{ id, shortcut, title, message }, ...]
 */
export async function loadQuickRepliesFromLaundry() {
  const [rekeningRes, lokasiRes] = await Promise.all([
    fetch(`${LAUNDRY_BASE}/Get/rekening`).then((r) => r.json()),
    fetch(`${LAUNDRY_BASE}/Get/lokasi`).then((r) => r.json()),
  ]);

  const list = [];
  let id = 1;

  if (rekeningRes?.ok && rekeningRes.message) {
    list.push({
      id: id++,
      shortcut: "/rekening",
      title: "Rekening Pembayaran",
      message: rekeningRes.message,
    });
  }

  if (lokasiRes?.ok && Array.isArray(lokasiRes.data)) {
    for (const cabang of lokasiRes.data) {
      const kode = String(cabang.kode_cabang || "").trim();
      if (!kode || !cabang.maps_url) continue;
      const nama = String(cabang.nama || "Madinah Laundry").trim();
      const alamat = String(cabang.alamat ?? "");
      const message =
        cabang.message ||
        `${nama.toUpperCase()} (${kode.toUpperCase()})\n${alamat}\n${cabang.maps_url}`;
      list.push({
        id: id++,
        shortcut: `/${kode.toLowerCase()}-location`,
        title: `Lokasi ${nama.toUpperCase()} - ${kode.toUpperCase()}`,
        message,
      });
    }
  }

  return list;
}

// ============================================================================
// Authentication State
// ============================================================================
export const authId = ref("");
export const currentUserRole = ref("crew");
export const userName = ref("");
export const senderCode = ref("");
export const isConnected = ref(false);
export const isConnecting = ref(false);
export const connectionError = ref("");
export const showLoginPrompt = ref(false);
export const duplicateWarning = ref(""); // Warning message for duplicate connection

// Reconnection State
export const wasConnected = ref(false);
export const isReconnecting = ref(false);
export const reconnectAttempts = ref(0);
export const maxReconnectAttempts = Infinity; // Unlimited reconnection attempts
export const reconnectDelay = ref(3000);
export const resumeTimestamp = ref(0);
export const lastDisconnectTime = ref(0);

// Duplicate Connection Retry State
// When network switches, old connection may still be "alive" on server
// We retry a few times before giving up and logging out
export const duplicateRetryAttempts = ref(0);
export const maxDuplicateRetries = Infinity; // Never logout - keep retrying forever
export const duplicateRetryDelay = 5000; // 5 seconds between retries (wait for server heartbeat cleanup)

// ============================================================================
// Conversation State
// ============================================================================
export const conversations = ref([]);
export const activeChatId = ref(null);
export const isLoadingConversations = ref(false);
export const isLoadingMoreConversations = ref(false);
export const isSearching = ref(false);
export const hasMoreConversations = ref(true);
export const conversationsOffset = ref(0);
export const searchQuery = ref("");
export const conversationFilter = ref("all"); // 'all', 'unread', 'cases'
export const pendingTargetPhone = ref(null);
export const autoOpenChatOnIncoming = ref(false);

// ⚡ CRITICAL: Force re-compute trigger for nested updates
// Increment this when message status changes to force activeConversation to re-compute
export const messageUpdateTrigger = ref(0);

// WhatsApp / Meta may return "accepted"; UI only treats sent|delivered|read as checkmarks.
// queue/processing = belum pernah ke Meta — jangan tampilkan sebagai 1 centang (terlihat "terkirim").
export const MESSAGE_STATUS_PRIORITY = {
  failed: -1,
  error: -1,
  pending: 0,
  queue: 0,
  processing: 0,
  accepted: 1,
  sent: 1,
  delivered: 2,
  read: 3,
};

export const normalizeMessageStatus = (status) => {
  if (!status) return "pending";
  const s = String(status).toLowerCase();
  if (s === "accepted") return "sent";
  // queue/processing: keep as pending so ChatPage shows clock, not fake single tick
  if (s === "queue" || s === "processing") return "pending";
  return s;
};

export const shouldApplyMessageStatus = (currentStatus, newStatus) => {
  const next = normalizeMessageStatus(newStatus);
  if (next === "failed" || next === "error") return true;
  const cur = normalizeMessageStatus(currentStatus);
  return (MESSAGE_STATUS_PRIORITY[next] ?? 0) >= (MESSAGE_STATUS_PRIORITY[cur] ?? 0);
};

// Active conversation computed
export const activeConversation = computed(() => {
    // Force dependency on messageUpdateTrigger to re-compute on nested changes
    messageUpdateTrigger.value; // eslint-disable-line no-unused-expressions
    
    if (!activeChatId.value) return null;
    return conversations.value.find((c) => c.id === activeChatId.value) || null;
});

// Filtered conversations computed
// NOTE: Search is now server-side, this only applies tab filters
export const filteredConversations = computed(() => {
    let list = conversations.value;

    // Search is now handled server-side in fetchConversations()
    // No client-side search filtering needed

    // Apply tab filter
    if (conversationFilter.value === "unread") {
        list = list.filter((c) => c.unread > 0);
    } else if (conversationFilter.value === "cases") {
        list = list.filter(
            (c) =>
                c.cases &&
                c.cases.some(
                    (cs) => cs.case > 0 && (cs.status || "open") !== "closed"
                )
        );
    } else if (conversationFilter.value.startsWith("case-")) {
        const caseId = parseInt(conversationFilter.value.replace("case-", ""), 10);
        if (caseId > 0) {
            list = list.filter(
                (c) =>
                    c.cases &&
                    c.cases.some(
                        (cs) =>
                            parseInt(cs.case) === caseId &&
                            (cs.status || "open") !== "closed"
                    )
            );
        }
    }

    return list;
});

// Total counts
export const totalUnreadCount = computed(() =>
    conversations.value.reduce((sum, c) => sum + (c.unread || 0), 0)
);

export const totalOpenCasesCount = computed(() =>
    conversations.value.filter(
        (c) =>
            c.cases &&
            c.cases.some((cs) => cs.case > 0 && (cs.status || "open") !== "closed")
    ).length
);

// ============================================================================
// Message Input State
// ============================================================================
export const messageInput = ref("");
export const chatDrafts = ref({});
export const replyToMessage = ref(null);
export const chatContainer = ref(null);
export const messageTextarea = ref(null);

// ============================================================================
// Image Upload State
// ============================================================================
export const selectedImage = ref(null);
export const imagePreview = ref("");
export const showImagePreview = ref(false);
export const isUploadingImage = ref(false);
export const imageCaption = ref("");
export const fileInput = ref(null);

// ============================================================================
// WebSocket State
// ============================================================================
export const socket = ref(null);
export const refreshInterval = ref(null);
export const eventSource = ref(null);

// ============================================================================
// UI State - Mobile & Gestures
// ============================================================================
export const windowWidth = ref(typeof window !== "undefined" ? window.innerWidth : 1024);
export const showMobileChat = ref(false);
export const isEnteringChat = ref(false);
export const touchStartX = ref(0);
export const touchStartY = ref(0);
export const touchOffset = ref(0);
export const isDragging = ref(false);
export const minSwipeDistance = 75;
export const showExitToast = ref(false);

// ============================================================================
// UI State - Menus & Modals
// ============================================================================
export const showChatMenu = ref(false);
export const showResolveMenu = ref(false);
export const showSettingsModal = ref(false);
export const showCustomerPanel = ref(false);
export const showAddLokasiModal = ref(false);
export const showDeleteLokasiModal = ref(false);
export const showDeliveryRequestModal = ref(false);
export const showEditPermintaanModal = ref(false);
export const showCreatePermintaanModal = ref(false);
export const showImageLightbox = ref(false);
export const lightboxImageUrl = ref("");
export const showQuickReplies = ref(false);

// Internal Browser
export const showInternalBrowser = ref(false);
export const internalBrowserUrl = ref("");
export const isInternalBrowserEntering = ref(false);
export const isInternalBrowserExiting = ref(false);
export const isInternalBrowserLoading = ref(true);

// ============================================================================
// UI State - Loading/Action States
// ============================================================================
export const isMarkingAsDone = ref(false);
export const isCheckingPayment = ref(false);
export const isPickupDelivery = ref(false);
export const isFollowUp = ref(false);
export const isReopeningConversation = ref(false);
export const isRefreshingChat = ref(false);
export const isLoadingQuickReplies = ref(false);

// ============================================================================
// Settings State
// ============================================================================
export const fontSize = ref("medium");
export const theme = ref("dark");
export const notificationSoundEnabled = ref(false);
export const notificationAudio = ref(null);

// ============================================================================
// Quick Reply State
// ============================================================================
export const quickReplies = ref([]);
export const quickReplySearchQuery = ref("");

// ============================================================================
// Title Blinking State
// ============================================================================
export const originalTitle = "MDL Chat";
export const titleBlinkInterval = ref(null);
export const isTitleRed = ref(false);

// ============================================================================
// Helper Functions
// ============================================================================
export const getAvatarColor = (seed) => {
    const colors = [
        "#6366f1", "#8b5cf6", "#ec4899", "#f43f5e", "#ef4444",
        "#f59e0b", "#10b981", "#06b6d4", "#3b82f6", "#64748b",
    ];
    if (!seed) return colors[0];
    const num =
        typeof seed === "string"
            ? seed.split("").reduce((acc, char) => acc + char.charCodeAt(0), 0)
            : seed;
    return colors[num % colors.length];
};

export const getCaseColor = (caseId) => {
    switch (parseInt(caseId)) {
        case 1: return "bg-blue-500";
        case 2: return "bg-yellow-500";
        case 3: return "bg-red-500";
        case 4: return "bg-purple-500";
        case 0: return "bg-slate-500";
        default: return "bg-gray-500";
    }
};

export const getCaseLabel = (caseId) => {
    switch (parseInt(caseId)) {
        case 1: return "Check Payment";
        case 2: return "Delivery Request";
        case 3: return "Permintaan";
        case 4: return "Follow Up";
        default: return "Case " + caseId;
    }
};

export const isCaseOpen = (caseId) => {
    if (!activeConversation.value || !activeConversation.value.cases) return false;
    return activeConversation.value.cases.some(
        (c) => parseInt(c.case) === parseInt(caseId) && (c.status || "open") !== "closed"
    );
};

// Helper to detect native app environment
export const isNativeApp = () => {
    return (
        typeof window !== "undefined" &&
        window.Capacitor &&
        window.Capacitor.isNativePlatform &&
        window.Capacitor.isNativePlatform()
    );
};

// Window resize handler
if (typeof window !== "undefined") {
    window.addEventListener("resize", () => {
        windowWidth.value = window.innerWidth;
    });
}
