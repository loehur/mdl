/**
 * Main Chat Store - Central state management for the CRM Chat
 * Uses Vue 3 Composition API with reactive state
 */
import { ref, computed } from "vue";

// ============================================================================
// API Configuration
// ============================================================================
export const API_BASE = "https://api.nalju.com";

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
export const hasMoreConversations = ref(true);
export const conversationsOffset = ref(0);
export const searchQuery = ref("");
export const conversationFilter = ref("all"); // 'all', 'unread', 'cases'
export const pendingTargetPhone = ref(null);
export const autoOpenChatOnIncoming = ref(false);

// ⚡ CRITICAL: Force re-compute trigger for nested updates
// Increment this when message status changes to force activeConversation to re-compute
export const messageUpdateTrigger = ref(0);

// Active conversation computed
export const activeConversation = computed(() => {
    // Force dependency on messageUpdateTrigger to re-compute on nested changes
    messageUpdateTrigger.value; // eslint-disable-line no-unused-expressions
    
    if (!activeChatId.value) return null;
    return conversations.value.find((c) => c.id === activeChatId.value) || null;
});

// Filtered conversations computed
export const filteredConversations = computed(() => {
    let list = conversations.value;

    // Apply search filter
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (c) =>
                (c.name || "").toLowerCase().includes(q) ||
                (c.wa_number || "").includes(q) ||
                (c.kode_cabang || "").toLowerCase().includes(q)
        );
    }

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
export const showCustomerInfoModal = ref(false);
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
export const isRequest = ref(false);
export const isFollowUp = ref(false);
export const isReopeningConversation = ref(false);
export const isRefreshingChat = ref(false);
export const isLoadingQuickReplies = ref(false);
export const copiedPhone = ref(false);

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
        case 1: return "bg-green-500";
        case 2: return "bg-yellow-500";
        case 3: return "bg-red-500";
        case 4: return "bg-purple-500";
        case 0: return "bg-slate-500";
        default: return "hidden";
    }
};

export const getCaseLabel = (caseId) => {
    switch (parseInt(caseId)) {
        case 1: return "Check Payment";
        case 2: return "Pickup/Delivery";
        case 3: return "Request";
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
