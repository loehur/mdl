<script setup>
import { ref, computed, nextTick, watch, onMounted, onUnmounted } from "vue";
import { Camera, CameraResultType, CameraSource } from "@capacitor/camera";
import EmojiPicker from "./EmojiPicker.vue";
import CustomerPanel from "./CustomerPanel.vue";
import twemoji from 'twemoji';
import { messageUpdateTrigger, chatContainer, loadQuickRepliesFromLaundry, isNativeApp, showCustomerPanel } from "../stores/chatStore.js";

const props = defineProps({
  activeConversation: {
    type: Object,
    default: null,
  },
  activeChatId: {
    type: [Number, String],
    default: null,
  },
  authId: {
    type: String,
    default: "",
  },
  currentUserRole: {
    type: String,
    default: "crew",
  },
  senderCode: {
    type: String,
    default: "",
  },
  windowWidth: {
    type: Number,
    default: 1024,
  },
  showMobileChat: {
    type: Boolean,
    default: false,
  },
  isEnteringChat: {
    type: Boolean,
    default: false,
  },
  touchOffset: {
    type: Number,
    default: 0,
  },
  API_BASE: {
    type: String,
    default: "https://api.nalju.com"
  },
  isRefreshingChat: {
    type: Boolean,
    default: false,
  },
  isConnected: {
    type: Boolean,
    default: true,
  },
  fontSize: {
    type: String,
    default: "medium",
  },
  isLoadingMessages: {
    type: Boolean,
    default: false,
  },
  isLoadingMoreMessages: {
    type: Boolean,
    default: false,
  },
  isChatPolling: {
    type: Boolean,
    default: false,
  },
  isChatPollIdlePaused: {
    type: Boolean,
    default: false,
  },
});

// Computed font size class based on prop
const messageFontClass = computed(() => {
  if (props.fontSize === 'xlarge') return 'text-lg';
  if (props.fontSize === 'large') return 'text-base';
  return 'text-sm';
});

const emit = defineEmits([
  "back-to-menu",
  "refresh-active-chat",
  "open-image-lightbox",
  "update:activeConversation", // For optimistic updates to bubble up if needed, though objects are ref passed
  "trigger-connect", // If we need to reconnect
  "load-more-messages", // For infinite scroll
  "open-internal-browser", // Open URL in internal browser (ml.nalju.com)
]);

// --- LOCAL STATE ---
const messageInput = ref("");
const fileInput = ref(null);
const messageTextarea = ref(null);
const replyToMessage = ref(null);

// Image Upload
const showImagePreview = ref(false);
const imagePreview = ref("");
const selectedImage = ref(null);
const selectedMediaKind = ref("image"); // image | video
const isUploadingImage = ref(false);
const imageCaption = ref("");

// Audio playback
const playingAudioId = ref(null);
const audioRefsMap = new Map();
const setAudioRef = (msgId, el) => {
  if (el) audioRefsMap.set(msgId, el);
  else audioRefsMap.delete(msgId);
};
const toggleAudioPlay = (msgId) => {
  const el = audioRefsMap.get(msgId);
  if (!el) return;
  if (playingAudioId.value === msgId) {
    el.pause();
  } else {
    // Pause any other playing audio
    audioRefsMap.forEach((a, id) => { if (id !== msgId) a.pause(); });
    el.play();
  }
};

// Quoted Message Detail Modal (tap untuk lihat pesan lengkap)
const showQuotedMessageModal = ref(false);
const quotedMessageToShow = ref(null);

// Chat Action Menus
const showChatMenu = ref(false);
const showResolveMenu = ref(false);
const isCheckingPayment = ref(false);
const isPickupDelivery = ref(false);
const isRequest = ref(false);
const isFollowUp = ref(false);
const resolvingCaseId = ref(null);

// Emoji & Quick Reply
const showEmojiPicker = ref(false);
const activeEmojiCategory = ref("recent");
const recentEmojis = ref([]);
const showQuickReplies = ref(false);
const quickReplies = ref([]);
const isLoadingQuickReplies = ref(false);
const quickReplySearchQuery = ref("");

// Touch Swipe State
const swipeReplyState = ref({
  startX: 0,
  currentX: 0,
  msgId: null,
  threshold: 60,
});

// Emoji handling - using EmojiPicker component
const handleEmojiSelect = (emoji) => {
  messageInput.value += emoji;
  // Save to recent emojis
  if (!recentEmojis.value.includes(emoji)) {
    recentEmojis.value = [emoji, ...recentEmojis.value.slice(0, 19)];
    localStorage.setItem("recent_emojis", JSON.stringify(recentEmojis.value));
  }
};

// --- COMPUTED ---
const resolveableCases = computed(() => {
  if (!props.activeConversation || !props.activeConversation.cases) return [];
  const openCases = props.activeConversation.cases.filter(
    (c) =>
      (c.status || "open") !== "closed" &&
      parseInt(c.case) > 0
  );
  // Role based filtering
  if (props.currentUserRole === "admin") return openCases;
  if (props.currentUserRole === "crew") return openCases.filter((c) => parseInt(c.case) === 3);
  return [];
});

const cswOpen = computed(() => {
  if (!props.activeConversation) return false;
  if (props.activeConversation.can_reply === true) return true;
  if (props.activeConversation.can_reply === false) return false;
  return !!(props.activeConversation.ycloud_open || props.activeConversation.fonnte_open);
});

const canReplyChat = computed(() => {
  return props.currentUserRole === "admin" && cswOpen.value;
});

const bothChannelsOpen = computed(() => {
  if (!props.activeConversation) return false;
  return !!(props.activeConversation.ycloud_open && props.activeConversation.fonnte_open);
});

const replyChannel = ref("auto");

watch(
  () => props.activeConversation?.id,
  () => {
    const ch = props.activeConversation?.default_reply_channel;
    replyChannel.value = ch === "fonnte" || ch === "ycloud" ? ch : "ycloud";
  },
  { immediate: true }
);

const providerTag = (msg) => {
  if (!msg?.provider) return "";
  return msg.provider === "F" ? "F" : "Y";
};

// Check if user is admin
const isAdmin = computed(() => {
  return props.currentUserRole === "admin";
});

const isPrivateMessage = (msg) => {
  if (!msg) return false;
  const privateVal = msg.private;
  if (privateVal === undefined || privateVal === null) return false;
  return privateVal == 1 ||
    privateVal === true ||
    parseInt(privateVal, 10) === 1 ||
    String(privateVal) === "1";
};

// Check if message should be hidden (private and not admin)
const shouldHideMessage = (msg) => {
  return isPrivateMessage(msg) && !isAdmin.value;
};

const filteredQuickReplies = computed(() => {
  if (!quickReplySearchQuery.value) return quickReplies.value;
  const q = quickReplySearchQuery.value.toLowerCase();
  return quickReplies.value.filter(
    (qr) => (qr.shortcut || "").replace(/^\//, "").toLowerCase().includes(q) || (qr.title || "").toLowerCase().includes(q)
  );
});

// --- METHODS: UTILS ---
const NEAR_BOTTOM_THRESHOLD = 140;

const isNearBottom = () => {
  const el = chatContainer.value;
  if (!el) return true;
  return el.scrollHeight - el.scrollTop - el.clientHeight <= NEAR_BOTTOM_THRESHOLD;
};

/** @param {boolean|{force?:boolean}} [opts] */
const scrollToBottom = (opts = {}) => {
  const force = opts === true || (typeof opts === "object" && opts?.force === true);
  nextTick(() => {
    if (!chatContainer.value) return;
    if (!force && !isNearBottom()) return;
    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
  });
};

const getCaseColor = (caseId) => {
  switch (parseInt(caseId)) {
    case 1: return "bg-blue-500";
    case 2: return "bg-yellow-500";
    case 3: return "bg-red-500";
    case 4: return "bg-purple-500";
    default: return "bg-gray-500";
  }
};

const getCaseLabel = (caseId) => {
  switch (parseInt(caseId)) {
    case 1: return "Check Payment";
    case 2: return "Pickup/Delivery";
    case 3: return "Request";
    case 4: return "Follow Up";
    default: return "Case " + caseId;
  }
};

const isCaseOpen = (caseId) => {
    if(!props.activeConversation?.cases) return false;
    return props.activeConversation.cases.some(c => parseInt(c.case) === parseInt(caseId) && (c.status || 'open') !== 'closed');
};

// --- HANDLERS ---
const showCustomerInfo = () => {
    showCustomerPanel.value = !showCustomerPanel.value;
};

const handleBubbleLinkClick = (e) => {
    const link = e.target.closest("a[href]");
    if (!link || !link.href) return;
    if (!(link.href.startsWith("http://") || link.href.startsWith("https://"))) return;

    try {
        const host = new URL(link.href).hostname.toLowerCase();
        if (host === "nalju.com" || host.endsWith(".nalju.com")) {
            e.preventDefault();
            e.stopPropagation();
            emit("open-internal-browser", link.href);
        }
    } catch (_) {
        // ignore invalid URL
    }
};

const backToMenu = () => {
    showCustomerPanel.value = false;
    emit('back-to-menu');
};

const openImageLightbox = (url) => {
    emit('open-image-lightbox', url);
};

const openLocation = (url) => {
    if (url && (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('maps.google.com'))) {
        // Ensure it's a full URL
        const fullUrl = url.startsWith('http') ? url : `https://${url}`;
        window.open(fullUrl, '_blank');
    }
};

// --- CASE ACTIONS (API CALLS) ---
// These update the activeConversation state which is passed by reference/prop
const updateCase = async (caseId, loadingRef) => {
    if (!props.activeConversation || loadingRef.value) return;
    try {
        loadingRef.value = true;
        const res = await fetch(`${props.API_BASE}/CRM/Chat/updateCase`, {
            method: "POST", headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ phone: props.activeConversation.wa_number, case: caseId, user_id: props.authId }),
        }).then(r => r.json());

        if (res.status) {
             // Optimistic Update
             if (!props.activeConversation.cases) props.activeConversation.cases = [];
             // Close case 4 logic
             props.activeConversation.cases = props.activeConversation.cases
                .map((c) => (c.case === 4 ? { ...c, status: "closed" } : c))
                .filter((c) => c.case !== 0);
             if (!props.activeConversation.cases.some((c) => c.case === caseId && c.status === "open")) {
                props.activeConversation.cases.push({ case: caseId, status: "open" });
             }
             showChatMenu.value = false;
        }
    } catch(e) { console.error(e); }
    finally { setTimeout(() => loadingRef.value = false, 3000); }
};

const checkPayment = () => updateCase(1, isCheckingPayment);
const pickupDelivery = () => updateCase(2, isPickupDelivery);
const requestPriority = () => updateCase(3, isRequest);
const followUp = () => updateCase(4, isFollowUp);

const resolveCase = async (caseId) => {
    if (!props.activeConversation || resolvingCaseId.value) return;
    try {
        resolvingCaseId.value = caseId;
        const res = await fetch(`${props.API_BASE}/CRM/Chat/resolveCase`, {
            method: "POST", headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ phone: props.activeConversation.wa_number, case: parseInt(caseId), user_id: props.authId }),
        }).then(r => r.json());
        if(res.status) {
             if (props.activeConversation.cases) {
                props.activeConversation.cases = props.activeConversation.cases.filter(
                  (x) => parseInt(x.case) !== parseInt(caseId)
                );
              }
              showResolveMenu.value = false;
        }
    } catch(e) { console.error(e); }
    finally { resolvingCaseId.value = null; }
};

// --- MESSAGE SENDING ---
const formatLocalDateTime = (d = new Date()) => {
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

const bumpMessageStatus = (msg, status, patch = {}) => {
  if (!msg || !props.activeConversation?.messages) return;
  Object.assign(msg, patch, { status });

  let idx = props.activeConversation.messages.indexOf(msg);
  if (idx === -1) {
    // Message object may have been replaced by polling/WS merge
    idx = props.activeConversation.messages.findIndex(
      (m) =>
        m.id === msg.id ||
        (patch.id != null && m.id === patch.id) ||
        (msg.wamid && m.wamid && m.wamid === msg.wamid) ||
        (patch.wamid && m.wamid === patch.wamid)
    );
  }

  if (idx !== -1) {
    const current = props.activeConversation.messages[idx];
    Object.assign(current, patch, { status });
    props.activeConversation.messages.splice(idx, 1, { ...current });
  }
  messageUpdateTrigger.value++;
};

const sendMessage = async () => {
  const text = messageInput.value.trim();
  if (!text || !canReplyChat.value) return;
  if (props.activeConversation) {
    const tempId = Date.now();
    const replyingTo = replyToMessage.value;
    const newMsg = {
      id: tempId, text: text, sender: "me",
      time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", hour12: false }),
      rawTime: formatLocalDateTime(), timestamp: Date.now(), status: "pending",
      quoted_message_id: replyingTo?.wamid || null,
      quoted_message_body: replyingTo?.text || replyingTo?.caption || null,
      sender_code: props.senderCode || localStorage.getItem("cms_chat_sender_code") || "",
      provider: replyChannel.value === "fonnte" ? "F" : "Y",
    };

    props.activeConversation.messages.push(newMsg);
    props.activeConversation.lastMessage = "You: " + text;
    props.activeConversation.lastTime = newMsg.time;

    messageInput.value = "";
    replyToMessage.value = null;
    scrollToBottom({ force: true });
    resetTextareaHeight();

    try {
      const res = await fetch(`${props.API_BASE}/CRM/Chat/reply`, {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          phone: props.activeConversation.wa_number, message: text, user_id: props.authId,
          sender_code: props.senderCode, reply_to: replyingTo?.wamid || null,
          channel: bothChannelsOpen.value ? replyChannel.value : "auto",
        }),
      }).then(r => r.json());

      // Use object reference (not find-by-id) — polling/sanitize may change id mid-flight
      if (res.status) {
        const sentProvider = res.data?.provider === "F" ? "F" : "Y";
        bumpMessageStatus(newMsg, "sent", {
          ...(res.data?.local_id != null ? { id: sentProvider + "-" + res.data.local_id } : {}),
          ...(res.data?.wamid || res.data?.id || res.data?.message_id
            ? { wamid: res.data.wamid || res.data.id || res.data.message_id }
            : {}),
          provider: sentProvider,
        });
      } else {
        bumpMessageStatus(newMsg, "failed");
      }
    } catch(e) {
      bumpMessageStatus(newMsg, "error");
    }
  }
};

const handleMessageKeydown = (e) => {
  if (e.key !== "Enter") return;

  if (isNativeApp()) {
    if (e.ctrlKey || e.metaKey) {
      e.preventDefault();
      if (props.isConnected) sendMessage();
    }
    return;
  }

  if (e.shiftKey) return;
  e.preventDefault();
  if (props.isConnected) sendMessage();
};

// ... Image Handling ...
// Minimal version for length, assume compressImage similar to before
const sendImage = async () => {
    if(isUploadingImage.value || !selectedImage.value || !props.activeConversation) return;
    if (!canReplyChat.value) return;
    isUploadingImage.value = true;
    const caption = imageCaption.value.trim();
    const isVideo = selectedMediaKind.value === "video";
    showImagePreview.value = false;

    const tempId = Date.now();
    const sentProvider = replyChannel.value === "fonnte" ? "F" : "Y";
    const newMsg = {
      id: tempId,
      text: caption || "",
      type: isVideo ? "video" : "image",
      media_url: imagePreview.value,
      sender: "me",
      time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", hour12: false }),
      rawTime: formatLocalDateTime(),
      sender_code: props.senderCode,
      status: "pending",
      provider: sentProvider,
    };
    props.activeConversation.messages.push(newMsg);
    props.activeConversation.lastMessage = isVideo ? "You: 🎥 Video" : "You: 📷 Image";
    scrollToBottom({ force: true });

    try {
        const formData = new FormData();
        const field = isVideo ? "video" : "image";
        formData.append(field, selectedImage.value);
        formData.append("phone", props.activeConversation.wa_number);
        formData.append("user_id", props.authId);
        formData.append("sender_code", props.senderCode);
        formData.append("channel", bothChannelsOpen.value ? replyChannel.value : "auto");
        if(caption) formData.append("caption", caption);

        const endpoint = isVideo ? "sendVideo" : "sendImage";
        const res = await fetch(`${props.API_BASE}/CRM/Chat/${endpoint}`, { method: "POST", body: formData }).then(r => r.json());
        if (res.status) {
            const provider = res.data?.provider === "F" ? "F" : "Y";
            const localId = res.data?.local_id;
            bumpMessageStatus(newMsg, "sent", {
              ...(localId != null ? { id: provider + "-" + localId } : {}),
              ...(res.data?.media_url ? { media_url: res.data.media_url } : {}),
              ...(res.data?.wamid || res.data?.message_id || res.data?.id
                ? { wamid: res.data.wamid || res.data.message_id || res.data.id }
                : {}),
              provider,
              type: isVideo ? "video" : "image",
            });
        } else {
            bumpMessageStatus(newMsg, "failed");
        }
    } catch(e) {
        console.error(e);
        bumpMessageStatus(newMsg, "error");
    }
    finally {
        isUploadingImage.value = false;
        selectedImage.value = null;
        selectedMediaKind.value = "image";
        imagePreview.value = "";
    }
};

// ... Open Image Picker implementation ...
const openImagePicker = async () => {
    if(fileInput.value) fileInput.value.click();
}
const selectImage = async (event) => {
    const file = event.target.files[0];
    if (file) {
        selectedMediaKind.value = String(file.type || "").startsWith("video/") ? "video" : "image";
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target.result;
            selectedImage.value = file;
            showImagePreview.value = true;
        }
        reader.readAsDataURL(file);
    }
    event.target.value = "";
};

const cancelImage = () => {
    selectedImage.value = null;
    selectedMediaKind.value = "image";
    imagePreview.value = "";
    showImagePreview.value = false;
    imageCaption.value = "";
};

// Textarea Resize
const autoResizeTextarea = () => {
  const textarea = messageTextarea.value;
  if (!textarea) return;
  textarea.style.height = "auto";
  const newHeight = Math.min(textarea.scrollHeight, 150);
  textarea.style.height = newHeight + "px";
};
const resetTextareaHeight = () => { if(messageTextarea.value) messageTextarea.value.style.height = "auto"; };

// Helpers
const formatDateSeparator = (dateString) => {
  const msgDate = new Date(dateString);
  const today = new Date();
  if (msgDate.toDateString() === today.toDateString()) return "Today";
  return msgDate.toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" });
};
const needsDateSeparator = (curr, prev) => {
    if(!prev || !curr.rawTime || !prev.rawTime) return false;
    return new Date(curr.rawTime).toDateString() !== new Date(prev.rawTime).toDateString();
};
// Use a basic parser for now or import from utils if available. Implementing simple one:
const parseWhatsAppFormatting = (text) => {
    if(!text) return "";
    let f = text.replace(/</g, "&lt;").replace(/>/g, "&gt;") // Escape HTML
       .replace(/\s+\|\s+\|\s+/g, "\n") // Convert " |  | " to newline
       .replace(/\*([^*]+)\*/g, "<strong>$1</strong>") // Bold
       .replace(/_([^_]+)_/g, "<em>$1</em>") // Italic
       .replace(/~([^~]+)~/g, "<del>$1</del>") // Strike
       .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" class="text-blue-400">$1</a>'); // Links
    
    // Parse emojis to Twemoji images for consistent rendering
    f = twemoji.parse(f, {
        folder: 'svg',
        ext: '.svg',
        base: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/',
        className: 'twemoji-inline'
    });
    
    return f;
};
const formatReactionText = (t) => (t || "👍").replace("Reacted: ", "").replace("Removed reaction", "👎");
const getMessagePreview = (m) => {
    if(!m) return "";
    if(m.type === 'image') return m.text && !/^\[[a-z_]+\]$/i.test(String(m.text).trim()) ? m.text : "📷 Image";
    if(m.type === 'video') return m.text && !/^\[[a-z_]+\]$/i.test(String(m.text).trim()) ? m.text : "🎥 Video";
    if(m.type === 'audio' || m.type === 'voice') return "🎵 Audio";
    if(m.type === 'document') return "📄 Document";
    if(m.type === 'sticker') return "🏷️ Sticker";
    return (m.text || m.caption || "").substring(0, 60);
};
/** Caption untuk bubble media: sembunyikan placeholder [image]/[video] */
const mediaCaptionText = (msg) => {
    const t = String(msg?.text || msg?.caption || msg?.media_caption || "").trim();
    if (!t || /^\[[a-z_]+\]$/i.test(t)) return "";
    return t;
};
// Check if message is plain text (no media type)
const isPlainTextMessage = (msg) => {
    return !msg.type || msg.type === 'text' || msg.type === '' || msg.type === 'template' || msg.type === 'button' || msg.type === 'reaction';
};
const findQuotedMessage = (id) => props.activeConversation?.messages?.find(m => m.wamid === id || m.id === id);
const scrollToMessage = (idOrMsg) => {
    const id = typeof idOrMsg === 'object' && idOrMsg?.id != null ? idOrMsg.id : idOrMsg;
    const el = document.getElementById("msg-" + id);
    if (el) el.scrollIntoView({ behavior: "smooth", block: "center" });
};
const setReplyTo = (m) => {
  if (!isAdmin.value) return;
  replyToMessage.value = m;
  nextTick(() => messageTextarea.value?.focus());
};
const cancelReply = () => replyToMessage.value = null;

/** Buka modal pesan quoted reply lengkap + scroll ke pesan jika ditemukan */
const openQuotedMessageDetail = (msg) => {
    if (!msg?.quoted_message_id) return;
    const quoted = findQuotedMessage(msg.quoted_message_id);
    quotedMessageToShow.value = quoted ? {
        full: quoted,
        text: quoted.text || quoted.caption || '',
        type: quoted.type || 'text',
        sender: quoted.sender,
        fromName: quoted.sender === 'me' ? 'You' : (msg.quoted_message_from || props.activeConversation?.name || 'Customer'),
        media_url: quoted.media_url,
        media_id: quoted.media_id,
    } : {
        full: null,
        text: msg.quoted_message_body || 'Message not found',
        type: 'text',
        fromName: msg.quoted_message_from || props.activeConversation?.name || 'Customer',
    };
    showQuotedMessageModal.value = true;
    if (quoted) scrollToMessage(quoted);
};
const closeQuotedMessageDetail = () => {
    showQuotedMessageModal.value = false;
    quotedMessageToShow.value = null;
};

// Watchers
// Jangan deep-watch seluruh conversation (status/case/patch → loncat ke bawah).
// Scroll soft hanya saat ada bubble baru; load-more tetap restore posisi.
watch(
  () => props.activeConversation?.messages?.length,
  (newCount, oldCount) => {
    if (shouldRestoreScroll.value && newCount > oldCount) {
      nextTick(() => {
        if (chatContainer.value) {
          const newScrollHeight = chatContainer.value.scrollHeight;
          const scrollDiff = newScrollHeight - savedScrollHeight.value;
          chatContainer.value.scrollTop = savedScrollTop.value + scrollDiff;
          shouldRestoreScroll.value = false;
        }
      });
      return;
    }
    if (newCount > (oldCount || 0)) {
      scrollToBottom(); // soft: hanya jika dekat bawah
    }
  }
);

// Separate watcher: Only reset input when switching to a DIFFERENT conversation
watch(() => props.activeChatId, (newId, oldId) => {
    if (newId !== oldId) {
        messageInput.value = "";
        replyToMessage.value = null;
        resetTextareaHeight();
    }
});

// Watch messageInput for "/" command to trigger quick replies
watch(messageInput, (newVal) => {
  if (newVal && newVal.startsWith("/")) {
    // Extract search query (text after "/")
    quickReplySearchQuery.value = newVal.substring(1).trim();
    showQuickReplies.value = true;

    // Load quick replies if not loaded yet
    if (quickReplies.value.length === 0 && !isLoadingQuickReplies.value) {
      fetchQuickReplies();
    }
  } else {
    showQuickReplies.value = false;
    quickReplySearchQuery.value = "";
  }
});

// Fetch quick replies from laundry
const fetchQuickReplies = async () => {
  if (isLoadingQuickReplies.value) return;
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

// Select a quick reply and insert into input
const selectQuickReply = (qr) => {
  messageInput.value = qr.message;
  showQuickReplies.value = false;
  quickReplySearchQuery.value = "";
  // Trigger auto-resize after inserting quick reply text
  nextTick(() => autoResizeTextarea());
};

// Track previous message count for scroll restoration
const previousMessageCount = ref(0);
const shouldRestoreScroll = ref(false);
const savedScrollHeight = ref(0);
const savedScrollTop = ref(0);

// Infinite scroll handler
const handleScroll = () => {
  if (!chatContainer.value || props.isLoadingMoreMessages) return;
  
  const scrollTop = chatContainer.value.scrollTop;
  const threshold = 100; // Trigger load when 100px from top
  
  // Check if scrolled to near top AND has more messages
  if (scrollTop <= threshold && props.activeConversation?.hasMoreMessages) {
    // Save scroll position for restoration
    previousMessageCount.value = props.activeConversation.messages?.length || 0;
    savedScrollHeight.value = chatContainer.value.scrollHeight;
    savedScrollTop.value = scrollTop;
    shouldRestoreScroll.value = true;
    
    // Trigger load more
    emit('load-more-messages');
  }
};

// Track if listener is attached
let scrollListenerAttached = false;

// Remove scroll listener
const removeScrollListener = () => {
  if (chatContainer.value && scrollListenerAttached) {
    chatContainer.value.removeEventListener('scroll', handleScroll);
    scrollListenerAttached = false;
  }
};

// Attach scroll listener
const attachScrollListener = () => {
  if (chatContainer.value && !scrollListenerAttached) {
    chatContainer.value.addEventListener('scroll', handleScroll);
    scrollListenerAttached = true;
  }
};

// Watch for activeConversation changes to re-attach listener
watch(() => props.activeConversation, (newVal, oldVal) => {
  // Remove old listener when conversation changes
  if (oldVal !== newVal) {
    removeScrollListener();
  }
  
  // Attach new listener for new conversation
  if (newVal) {
    nextTick(() => {
      attachScrollListener();
    });
  }
}, { immediate: true });

onMounted(() => {
    scrollToBottom({ force: true });
    
    // Load recent emojis from localStorage
    const savedEmojis = localStorage.getItem("recent_emojis");
    if (savedEmojis) {
      try {
        recentEmojis.value = JSON.parse(savedEmojis);
      } catch (e) {
        recentEmojis.value = [];
      }
    }
    
    // Load Quick Replies from laundry
    loadQuickRepliesFromLaundry().then((list) => {
      if (list.length > 0) quickReplies.value = list;
    }).catch(() => {});
    
    // Click outside handler to close dropdown menus
    document.addEventListener('click', handleClickOutside);
    
    // Try to attach scroll listener
    nextTick(() => {
      attachScrollListener();
    });
});

// Handle click outside to close menus
const handleClickOutside = () => {
    showChatMenu.value = false;
    showResolveMenu.value = false;
    showEmojiPicker.value = false;
    showQuickReplies.value = false;
};

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    
    // Remove scroll listener
    removeScrollListener();
});

</script>

<template>
    <!-- Main Chat Area -->
    <main
      data-chat-panel
      class="flex flex-row bg-[var(--wa-bg-chat)] h-full overflow-x-hidden"
      :class="{
        'fixed inset-0 z-50 w-full chat-panel-mobile':
          showMobileChat && windowWidth < 768,
        'chat-entering': isEnteringChat,
        hidden: !showMobileChat && windowWidth < 768,
        'fixed top-0 right-0 bottom-0 md:left-96 z-0 !w-auto':
          windowWidth >= 768,
      }"
      :style="{
        transform:
          showMobileChat && windowWidth < 768 && !isEnteringChat && touchOffset > 0
            ? `translateX(${touchOffset}px)`
            : '',
        transition: 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
      }"
    >
      <div class="flex-1 min-w-0 h-full relative flex flex-col overflow-x-hidden">
      <!-- Background Pattern -->
      <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9IndhLWJnIiB4PSIwIiB5PSIwIiB3aWR0aD0iODAiIGhlaWdodD0iODAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiPjxwYXRoIGQ9Ik0wIDIwIEwgMjAgMCBMIDQwIDIwIEwgNjAgMCBMIDgwIDIwIiBzdHJva2U9IiNhZWJhYzEiIHN0cm9rZS13aWR0aD0iMC41IiBmaWxsPSJub25lIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI3dhLWJnKSIvPjwvc3ZnPg=='); background-size: 80px 80px;"></div>

      <div v-if="activeConversation" class="w-full h-full relative z-10 flex flex-col">
         <!-- Chat Header -->
          <header class="h-16 relative flex items-center justify-between px-4 md:px-6 z-30 bg-[var(--wa-bg-panel)] flex-shrink-0">
               <div class="flex items-center gap-3 flex-1 min-w-0">
                  <button @click="backToMenu" class="md:hidden p-1 -ml-2 text-[var(--wa-icon-default)]"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg></button>
                  <div @click="showCustomerInfo" class="min-w-0 flex-1 cursor-pointer">
                      <h2 class="font-medium text-[var(--wa-text-primary)] text-base md:text-lg truncate uppercase">{{ activeConversation.name }}</h2>
                      <div class="flex items-center gap-2 flex-wrap">
                        <span v-if="activeConversation.kode_cabang" class="text-xs font-bold text-[var(--wa-accent-green)]">{{ activeConversation.kode_cabang }}</span>
                        <span v-if="activeConversation.cust_id" class="text-xs text-[var(--wa-text-tertiary)]">#{{ activeConversation.cust_id }}</span>
                           <div v-if="activeConversation.cases" class="flex gap-1">
                                <template v-for="(cse, idx) in activeConversation.cases" :key="idx">
                                    <div v-if="cse.case > 0 && (cse.status || 'open') !== 'closed'" class="w-3 h-3 rounded-full" :class="getCaseColor(cse.case)"></div>
                                </template>
                           </div>
                      </div>
                  </div>
               </div>
               <!-- Actions -->
               <div class="flex items-center gap-2 text-[var(--wa-icon-default)] relative">
                    <button
                      type="button"
                      class="hover:text-[var(--wa-text-primary)] p-2 rounded-full"
                      title="Customer Panel"
                      @click.stop="showCustomerInfo"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5h4a2 2 0 012 2v10a2 2 0 01-2 2h-4M15 5v14M15 5H5a2 2 0 00-2 2v10a2 2 0 002 2h10" />
                      </svg>
                    </button>
                    <!-- Resolve Menu -->
                    <div class="relative">
                        <button v-if="resolveableCases.length > 0" @click.stop="showResolveMenu = !showResolveMenu; showChatMenu = false" class="hover:text-[var(--wa-text-primary)] p-2 rounded-full text-green-500">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        </button>
                        <div v-if="showResolveMenu" @click.stop class="absolute right-0 top-full mt-2 w-44 bg-[var(--wa-bg-secondary)] rounded-xl shadow-2xl overflow-hidden z-50 py-1">
                             <button v-for="c in resolveableCases" :key="c.case" @click="resolveCase(c.case)" class="w-full px-4 py-2.5 text-left hover:bg-[var(--wa-hover)] text-sm text-[var(--wa-text-primary)] flex items-center gap-3">
                                  <!-- Checkmark in colored circle or Spinner -->
                                  <span class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0" :class="getCaseColor(c.case)">
                                       <div v-if="resolvingCaseId === c.case" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                       <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-white" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                       </svg>
                                  </span>
                                  {{ getCaseLabel(c.case) }}
                             </button>
                        </div>
                    </div>
                    <!-- Chat Menu -->
                    <div class="relative">
                        <button @click.stop="showChatMenu = !showChatMenu; showResolveMenu = false" class="hover:text-[var(--wa-text-primary)] p-2 rounded-full"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg></button>
                         <div v-if="showChatMenu" @click.stop class="absolute right-0 top-full mt-2 w-44 bg-[var(--wa-bg-secondary)] rounded-xl shadow-2xl overflow-hidden z-50 py-1">
                               <button v-if="!isCaseOpen(1)" @click="checkPayment" :disabled="isCheckingPayment" class="w-full px-4 py-2.5 text-left hover:bg-[var(--wa-hover)] text-sm text-[var(--wa-text-primary)] flex items-center gap-3">
                                    <div v-if="isCheckingPayment" class="w-3 h-3 border-2 border-blue-200 border-t-blue-500 rounded-full animate-spin flex-shrink-0"></div>
                                    <span v-else class="w-2.5 h-2.5 rounded-full bg-blue-500 flex-shrink-0"></span>
                                    Check Payment
                               </button>
                               <button v-if="!isCaseOpen(2)" @click="pickupDelivery" :disabled="isPickupDelivery" class="w-full px-4 py-2.5 text-left hover:bg-[var(--wa-hover)] text-sm text-[var(--wa-text-primary)] flex items-center gap-3">
                                    <div v-if="isPickupDelivery" class="w-3 h-3 border-2 border-yellow-200 border-t-yellow-500 rounded-full animate-spin flex-shrink-0"></div>
                                    <span v-else class="w-2.5 h-2.5 rounded-full bg-yellow-500 flex-shrink-0"></span>
                                    Pickup/Delivery
                               </button>
                               <button v-if="!isCaseOpen(3)" @click="requestPriority" :disabled="isRequest" class="w-full px-4 py-2.5 text-left hover:bg-[var(--wa-hover)] text-sm text-[var(--wa-text-primary)] flex items-center gap-3">
                                    <div v-if="isRequest" class="w-3 h-3 border-2 border-red-200 border-t-red-500 rounded-full animate-spin flex-shrink-0"></div>
                                    <span v-else class="w-2.5 h-2.5 rounded-full bg-red-500 flex-shrink-0"></span>
                                    Request
                               </button>
                               <button v-if="!isCaseOpen(4)" @click="followUp" :disabled="isFollowUp" class="w-full px-4 py-2.5 text-left hover:bg-[var(--wa-hover)] text-sm text-[var(--wa-text-primary)] flex items-center gap-3">
                                    <div v-if="isFollowUp" class="w-3 h-3 border-2 border-purple-200 border-t-purple-500 rounded-full animate-spin flex-shrink-0"></div>
                                    <span v-else class="w-2.5 h-2.5 rounded-full bg-purple-500 flex-shrink-0"></span>
                                    Follow Up
                               </button>
                         </div>
                    </div>
               </div>
               <div
                 class="absolute bottom-0 inset-x-0 h-[3px] overflow-hidden pointer-events-none transition-colors"
                 :class="isChatPollIdlePaused ? 'bg-amber-500' : 'bg-[var(--wa-border)]'"
                 :title="isChatPollIdlePaused ? 'Polling paused — move mouse or touch to resume' : ''"
                 aria-hidden="true"
               >
                 <div
                   v-if="isChatPolling"
                   class="chat-poll-bar h-full w-1/3 bg-[var(--wa-accent-green)]"
                 />
                 <div
                   v-else-if="isChatPollIdlePaused"
                   class="chat-poll-idle-bar h-full w-full bg-amber-500"
                 />
               </div>
          </header>

         <!-- Messages -->
         <div ref="chatContainer" class="flex-1 overflow-y-auto overflow-x-hidden custom-scrollbar pt-4 pb-2 relative" @click.capture="handleBubbleLinkClick">
              <!-- Loading Indicator Overlay (initial load) -->
              <div v-if="isLoadingMessages" class="absolute inset-0 z-20 flex items-center justify-center bg-[var(--wa-bg-chat)]/50 backdrop-blur-[1px]">
                   <div class="bg-[var(--wa-bg-panel)] p-3 rounded-full shadow-lg border border-[var(--wa-border)]">
                        <div class="w-6 h-6 border-2 border-[var(--wa-accent-green)] border-t-transparent rounded-full animate-spin"></div>
                   </div>
              </div>

              <!-- Loading More Messages Indicator (top) -->
              <div v-if="isLoadingMoreMessages" class="sticky top-0 z-10 flex justify-center py-2">
                   <div class="bg-[var(--wa-bg-panel)] px-3 py-1.5 rounded-full shadow-md border border-[var(--wa-border)] flex items-center gap-2">
                        <div class="w-3 h-3 border-2 border-[var(--wa-accent-green)] border-t-transparent rounded-full animate-spin"></div>
                        <span class="text-xs text-[var(--wa-text-secondary)]">Loading...</span>
                   </div>
              </div>

              <div class="px-4 space-y-2 overflow-hidden">
                   <div v-for="(msg, index) in activeConversation.messages" :key="msg.id" :id="'msg-' + msg.id" class="flex flex-col relative group">
                        <!-- Date Separator -->
                        <div v-if="index === 0 || needsDateSeparator(msg, activeConversation.messages[index-1])" class="flex justify-center my-4">
                            <div class="bg-[var(--wa-bg-panel)] text-[var(--wa-text-secondary)] text-xs px-3 py-1 rounded-lg">{{ formatDateSeparator(msg.rawTime) }}</div>
                        </div>

                        <!-- Messages -->
                        <div class="flex max-w-[85%] md:max-w-[70%] items-center gap-1" :class="msg.sender === 'me' ? 'self-end' : 'self-start'">
                             <!-- Reply Button - LEFT side for OUTGOING messages -->
                             <button 
                               v-if="isAdmin && msg.sender === 'me'"
                               @click="setReplyTo(msg)" 
                               class="opacity-40 hover:opacity-100 active:opacity-100 transition-opacity p-1.5 rounded-full hover:bg-[var(--wa-hover)] active:bg-[var(--wa-hover)] text-[var(--wa-text-tertiary)] hover:text-[var(--wa-accent-green)] active:text-[var(--wa-accent-green)] flex-shrink-0"
                               title="Reply"
                             >
                               <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                               </svg>
                             </button>

                             <!-- Bubble -->
                             <div :class="[
                               'rounded-lg shadow-sm relative overflow-hidden',
                               isPlainTextMessage(msg) ? 'px-2.5 py-1' : 'p-0.5',
                               msg.sender === 'me' ? 'bg-[var(--wa-bubble-outgoing)] rounded-tr-none' : 'bg-[var(--wa-bubble-incoming)] rounded-tl-none'
                             ]">
                                 <!-- Reply Quote - klik untuk lihat pesan lengkap -->
                                 <div 
                                   v-if="msg.quoted_message_id" 
                                   class="bg-black/10 rounded px-2 py-0.5 border-l-2 border-[var(--wa-accent-green)] mb-0.5 cursor-pointer hover:bg-black/15 active:bg-black/20 transition-colors"
                                   @click="openQuotedMessageDetail(msg)">
                                      <span class="text-[10px] font-bold text-[var(--wa-accent-green)] block leading-none" style="line-height: 1.05;">
                                        {{ findQuotedMessage(msg.quoted_message_id)?.sender === 'me' ? 'You' : (msg.quoted_message_from ? 'Customer' : activeConversation.name) }}
                                      </span>
                                      <span class="text-xs truncate block text-[var(--wa-text-secondary)] leading-none" style="line-height: 1.05;">
                                        {{ findQuotedMessage(msg.quoted_message_id) ? getMessagePreview(findQuotedMessage(msg.quoted_message_id)) : (msg.quoted_message_body || 'Message not found') }}
                                      </span>
                                      <span class="text-[9px] text-[var(--wa-text-tertiary)] italic block mt-0.5">Tap untuk lihat lengkap</span>
                                 </div>

                                  <!-- Image -->
                                  <div v-if="msg.type === 'image'" class="relative max-w-sm">
                                      <!-- Blur image if private and not admin -->
                                      <div v-if="shouldHideMessage(msg)" class="relative">
                                           <img :src="msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`" class="max-h-80 object-cover blur-md" />
                                           <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                                <div class="text-center text-white p-4">
                                                     <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                     </svg>
                                                     <p class="text-sm font-medium">Pesan Private</p>
                                                     <p class="text-xs mt-1">Hanya admin yang dapat melihat</p>
                                                </div>
                                           </div>
                                      </div>
                                      <img v-else-if="msg.media_url || msg.media_id" :src="msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`" @click="openImageLightbox(msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`)" class="max-h-80 object-cover cursor-pointer" />
                                      <div v-else class="px-3 py-4 text-sm text-[var(--wa-text-tertiary)] italic bg-[var(--wa-bg-secondary)] rounded-lg">📷 Gambar tidak tersedia</div>
                                      <div
                                        v-if="mediaCaptionText(msg) || msg.time"
                                        class="absolute bottom-0 inset-x-0 px-2.5 py-2"
                                        style="background: var(--wa-caption-overlay-bg); color: var(--wa-caption-overlay-text);"
                                      >
                                           <div v-if="isPrivateMessage(msg)" class="inline-flex items-center gap-1 text-xs font-semibold mb-1 opacity-90">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                     <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                                <span>Private</span>
                                           </div>
                                           <p v-if="mediaCaptionText(msg) && !shouldHideMessage(msg)" :class="[messageFontClass, 'mb-1 whitespace-pre-wrap break-words leading-snug']">{{ mediaCaptionText(msg) }}</p>
                                           <p v-else-if="mediaCaptionText(msg) && shouldHideMessage(msg)" class="text-xs italic mb-1 leading-snug opacity-80">🔒 Pesan ini bersifat private</p>
                                           <div class="flex justify-end items-center gap-1 text-[10px] opacity-70">
                                              <span v-if="providerTag(msg)" class="opacity-80">~{{ providerTag(msg) }}</span>
                                              <span v-if="msg.sender_code">~{{ msg.sender_code }}</span>
                                              <span>{{ msg.time }}</span>
                                           </div>
                                      </div>
                                  </div>

                                  <!-- Video -->
                                  <div v-else-if="msg.type === 'video'" class="relative max-w-sm">
                                      <div v-if="shouldHideMessage(msg)" class="relative">
                                           <video
                                             :src="msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`"
                                             class="max-h-80 w-full object-cover blur-md rounded-lg"
                                             preload="metadata"
                                           ></video>
                                           <div class="absolute inset-0 flex items-center justify-center bg-black/30 rounded-lg">
                                                <div class="text-center text-white p-4">
                                                     <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                     </svg>
                                                     <p class="text-sm font-medium">Pesan Private</p>
                                                     <p class="text-xs mt-1">Hanya admin yang dapat melihat</p>
                                                </div>
                                           </div>
                                      </div>
                                      <video
                                        v-else
                                        :src="msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`"
                                        class="max-h-80 w-full object-contain rounded-lg bg-black"
                                        controls
                                        playsinline
                                        preload="metadata"
                                      ></video>
                                      <div
                                        v-if="mediaCaptionText(msg) || msg.time"
                                        class="mt-1.5 px-2.5 py-2 rounded-lg"
                                        style="background: var(--wa-caption-overlay-bg); color: var(--wa-caption-overlay-text);"
                                      >
                                           <div v-if="isPrivateMessage(msg)" class="inline-flex items-center gap-1 text-xs font-semibold mb-1 opacity-90">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                     <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                                <span>Private</span>
                                           </div>
                                           <p v-if="mediaCaptionText(msg) && !shouldHideMessage(msg)" :class="[messageFontClass, 'mb-1 whitespace-pre-wrap break-words leading-snug']">{{ mediaCaptionText(msg) }}</p>
                                           <p v-else-if="mediaCaptionText(msg) && shouldHideMessage(msg)" class="text-xs italic mb-1 leading-snug opacity-80">🔒 Pesan ini bersifat private</p>
                                           <div class="flex justify-end items-center gap-1 text-[10px] opacity-70">
                                              <span v-if="providerTag(msg)" class="opacity-80">~{{ providerTag(msg) }}</span>
                                              <span v-if="msg.sender_code">~{{ msg.sender_code }}</span>
                                              <span>{{ msg.time }}</span>
                                           </div>
                                      </div>
                                  </div>

                                  <!-- Audio -->
                                  <div v-else-if="msg.type === 'audio' || msg.type === 'voice'" class="relative min-w-[200px] max-w-[280px]">
                                      <div class="flex items-center gap-3 p-2 rounded-lg bg-black/10 dark:bg-black/20">
                                          <button
                                            type="button"
                                            @click="toggleAudioPlay(msg.id)"
                                            class="flex-shrink-0 w-10 h-10 rounded-full bg-[var(--wa-accent-green)] text-white flex items-center justify-center hover:opacity-90 active:scale-95 transition-all"
                                            :title="playingAudioId === msg.id ? 'Pause' : 'Play'"
                                          >
                                            <svg v-if="playingAudioId !== msg.id" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z"/>
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                                            </svg>
                                          </button>
                                          <div class="flex-1 min-w-0">
                                              <audio
                                                :ref="el => setAudioRef(msg.id, el)"
                                                :src="msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`"
                                                @play="playingAudioId = msg.id"
                                                @pause="playingAudioId = playingAudioId === msg.id ? null : playingAudioId"
                                                @ended="playingAudioId = playingAudioId === msg.id ? null : playingAudioId"
                                                preload="metadata"
                                              ></audio>
                                              <p class="text-xs text-[var(--wa-text-secondary)]">Voice message</p>
                                              <div v-if="msg.time || msg.sender_code || providerTag(msg)" class="flex justify-end items-center gap-1 text-[10px] mt-0.5 text-[var(--wa-text-tertiary)]">
                                                  <span v-if="providerTag(msg)" class="opacity-80">~{{ providerTag(msg) }}</span>
                                                  <span v-if="msg.sender_code">~{{ msg.sender_code }}</span>
                                                  <span>{{ msg.time }}</span>
                                              </div>
                                          </div>
                                      </div>
                                  </div>

                                  <!-- Sticker -->
                                  <div v-else-if="msg.type === 'sticker'" class="relative">
                                      <div v-if="shouldHideMessage(msg)" class="relative">
                                           <img
                                             :src="msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`"
                                             class="w-36 h-36 object-contain blur-md"
                                             alt="Sticker"
                                           />
                                           <div class="absolute inset-0 flex items-center justify-center bg-black/30 rounded-lg">
                                                <div class="text-center text-white p-3">
                                                     <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                     </svg>
                                                     <p class="text-xs font-medium">Pesan Private</p>
                                                </div>
                                           </div>
                                      </div>
                                      <img
                                        v-else-if="msg.media_url || msg.media_id"
                                        :src="msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`"
                                        @click="openImageLightbox(msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`)"
                                        class="w-36 h-36 object-contain cursor-pointer"
                                        alt="Sticker"
                                      />
                                      <div v-else class="px-2.5 py-1 text-sm text-[var(--wa-text-tertiary)] italic">Sticker</div>
                                      <div
                                        v-if="msg.time || msg.sender_code || providerTag(msg)"
                                        class="flex justify-end items-center gap-1 text-[10px] mt-0.5 px-1 text-[var(--wa-text-tertiary)]"
                                      >
                                           <span v-if="providerTag(msg)" class="opacity-80">~{{ providerTag(msg) }}</span>
                                           <span v-if="msg.sender_code">~{{ msg.sender_code }}</span>
                                           <span>{{ msg.time }}</span>
                                      </div>
                                  </div>

                                  <!-- Location -->
                                  <div v-else-if="msg.type === 'location'" class="relative max-w-sm" :class="{ 'cursor-pointer': msg.media_url }" @click="msg.media_url && openLocation(msg.media_url)">
                                      <div class="bg-gradient-to-br from-green-50 to-blue-50 dark:from-green-900/20 dark:to-blue-900/20 border border-green-200 dark:border-green-800 rounded-lg p-1" :class="{ 'hover:bg-gradient-to-br hover:from-green-100 hover:to-blue-100 dark:hover:from-green-900/30 dark:hover:to-blue-900/30 transition-colors': msg.media_url }">
                                           <div class="flex items-start gap-3">
                                                <div class="flex-shrink-0">
                                                     <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                                          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                                     </svg>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                     <p v-if="msg.text" :class="[messageFontClass, 'text-[var(--wa-text-primary)] font-medium mb-0.5 leading-none']" style="line-height: 1.1;">{{ msg.text }}</p>
                                                     <p v-if="msg.media_caption" class="text-xs text-[var(--wa-text-secondary)] font-mono mb-1 leading-none" style="line-height: 1.1;">{{ msg.media_caption }}</p>
                                                     <div class="flex items-center gap-2 text-xs text-[var(--wa-text-tertiary)]">
                                                          <span class="text-blue-500 hover:text-blue-600 font-medium">Buka di Google Maps</span>
                                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                          </svg>
                                                     </div>
                                                </div>
                                           </div>
                                           <div v-if="msg.time || msg.sender_code" class="flex justify-end items-center gap-1 text-[10px] mt-1 pt-1 border-t border-green-200 dark:border-green-800">
                                                <span v-if="providerTag(msg)" class="text-[var(--wa-text-tertiary)] opacity-80">~{{ providerTag(msg) }}</span>
                                                <span v-if="msg.sender_code" class="text-[var(--wa-text-tertiary)]">~{{ msg.sender_code }}</span>
                                                <span class="text-[var(--wa-text-tertiary)]">{{ msg.time }}</span>
                                           </div>
                                      </div>
                                  </div>

                                  <!-- Text -->
                                  <div v-else :class="[messageFontClass, 'text-[var(--wa-text-primary)] overflow-hidden']">
                                       <div class="inline">
                                            <!-- Private message indicator - always show if private -->
                                            <span v-if="isPrivateMessage(msg)" class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 dark:text-amber-400 mb-0.5 mr-2 px-2 py-0.5 bg-amber-100 dark:bg-amber-900/30 rounded-full border border-amber-300 dark:border-amber-700">
                                                 <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                 </svg>
                                                 <span>Private</span>
                                            </span>
                                            <!-- Message content - hidden if private and not admin -->
                                            <span v-if="!shouldHideMessage(msg)" v-html="parseWhatsAppFormatting(msg.text)" class="whitespace-pre-wrap break-words leading-none" style="word-break: break-word; overflow-wrap: anywhere; line-height: 1.1;"></span>
                                            <span v-else class="text-[var(--wa-text-tertiary)] italic text-sm leading-none" style="line-height: 1.1;">
                                                 🔒 Pesan ini bersifat private
                                            </span>
                                            <span class="inline-flex items-center gap-1 ml-2 align-bottom select-none float-right mt-0.5" style="margin-left: 8px;">
                                                 <span v-if="providerTag(msg)" class="text-[10px] text-[var(--wa-bubble-out-meta)] opacity-85">~{{ providerTag(msg) }}</span>
                                                 <span v-if="msg.sender_code" class="text-[10px] text-[var(--wa-bubble-out-meta)] opacity-85">~{{ msg.sender_code }}</span>
                                                 <span class="text-[10px] text-[var(--wa-text-tertiary)]">{{ msg.time }}</span>
                                                 <!-- Status Icon for outgoing -->
                                                 <span v-if="msg.sender === 'me'" class="text-[var(--wa-bubble-out-meta)]">
                                                      <span v-if="msg.status === 'read'"><svg class="w-4 h-3 inline" viewBox="0 0 16 11" fill="none"><path d="M11.07 0.73L4.51 7.29L1.79 4.57L0.38 5.98L4.51 10.12L12.48 2.14L11.07 0.73Z" fill="#53bdeb"/><path d="M14.07 0.73L7.51 7.29L6.79 6.57L5.38 7.98L7.51 10.12L15.48 2.14L14.07 0.73Z" fill="#53bdeb"/></svg></span>
                                                      <span v-else-if="msg.status === 'delivered'"><svg class="w-4 h-3 inline text-[var(--wa-text-tertiary)]" viewBox="0 0 16 11" fill="none"><path d="M11.07 0.73L4.51 7.29L1.79 4.57L0.38 5.98L4.51 10.12L12.48 2.14L11.07 0.73Z" fill="currentColor"/><path d="M14.07 0.73L7.51 7.29L6.79 6.57L5.38 7.98L7.51 10.12L15.48 2.14L14.07 0.73Z" fill="currentColor"/></svg></span>
                                                      <span v-else-if="msg.status === 'sent'"><svg class="w-3 h-3 inline text-[var(--wa-text-tertiary)]" viewBox="0 0 12 11" fill="none"><path d="M10.07 0.73L3.51 7.29L0.79 4.57L0 5.36L3.51 8.87L10.86 1.52L10.07 0.73Z" fill="currentColor"/></svg></span>
                                                      <span v-else><svg class="w-3 h-3 inline text-[var(--wa-text-tertiary)]" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M6 3v3.5l2 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                                 </span>
                                            </span>
                                       </div>
                                  </div>
                             </div>

                             <!-- Reply Button - RIGHT side for INCOMING messages -->
                             <button 
                               v-if="isAdmin && msg.sender !== 'me'"
                               @click="setReplyTo(msg)" 
                               class="opacity-40 hover:opacity-100 active:opacity-100 transition-opacity p-1.5 rounded-full hover:bg-[var(--wa-hover)] active:bg-[var(--wa-hover)] text-[var(--wa-text-tertiary)] hover:text-[var(--wa-accent-green)] active:text-[var(--wa-accent-green)] flex-shrink-0"
                               title="Reply"
                             >
                               <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                               </svg>
                             </button>
                        </div>
                   </div>
              </div>
         </div>

         <!-- Input Area -->
         <div class="p-3 md:p-4 mt-2 bg-[var(--wa-header-bg)] border-t border-[var(--wa-border)] z-30">
             <!-- Preview/Reply Panels -->
             <div v-if="showImagePreview" class="bg-[var(--wa-bg-panel)] p-4 rounded-xl shadow-lg mb-2 relative border">
                  <img v-if="selectedMediaKind === 'image'" :src="imagePreview" class="h-48 object-cover rounded-lg mx-auto border" />
                  <video v-else :src="imagePreview" class="h-48 max-w-full object-contain rounded-lg mx-auto border bg-black" controls playsinline preload="metadata"></video>
                  <button @click="cancelImage" class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1">X</button>
                  <div class="mt-2 flex gap-2">
                      <input v-model="imageCaption" type="text" placeholder="Caption..." class="flex-1 bg-[var(--wa-bg-secondary)] border px-3 py-2 rounded-lg text-sm" />
                      <button @click="sendImage" :disabled="isUploadingImage" class="bg-[var(--wa-accent-green)] text-white px-4 rounded-lg text-sm font-bold">{{ isUploadingImage ? '...' : 'Send' }}</button>
                  </div>
             </div>

             <!-- Reply Preview -->
             <div v-if="replyToMessage" class="bg-[var(--wa-bg-panel)] p-2 rounded-lg border-l-4 border-[var(--wa-accent-green)] mb-2 flex justify-between items-center shadow-sm">
                  <div class="overflow-hidden">
                      <p class="text-xs font-bold text-[var(--wa-accent-green)]">{{ replyToMessage.sender === 'me' ? 'You' : activeConversation.name }}</p>
                      <p class="text-xs truncate text-[var(--wa-text-secondary)]">{{ getMessagePreview(replyToMessage) }}</p>
                  </div>
                  <button @click="cancelReply" class="text-[var(--wa-text-tertiary)] hover:text-red-500"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
             </div>

             <!-- Case 1: CSW Closed - Show Refresh Button -->
             <button
               v-if="!cswOpen"
               @click="emit('refresh-active-chat')"
               :disabled="isRefreshingChat"
               class="flex items-center justify-center gap-2 p-3 bg-[var(--wa-bg-tertiary)] rounded-lg border border-[var(--wa-border)] w-full min-h-[46px]"
               title="CSW Expired (>23 jam). Click to refresh chat data"
             >
                <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                  <div
                    v-if="isRefreshingChat"
                    class="w-4 h-4 border-2 border-[var(--wa-text-tertiary)] border-t-[var(--wa-accent-green)] rounded-full animate-spin"
                  ></div>
                  <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 text-[var(--wa-text-tertiary)]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                    />
                  </svg>
                </div>
                <span class="text-[var(--wa-text-secondary)] text-sm font-medium">
                  {{ isRefreshingChat ? 'Refreshing Chat...' : 'CSW Closed - Refresh' }}
                </span>
             </button>

             <!-- Case 2: CSW open tapi bukan admin -->
             <div
               v-else-if="!isAdmin"
               class="flex items-center justify-center gap-2 p-3 bg-[var(--wa-bg-tertiary)] rounded-lg border border-[var(--wa-border)] w-full min-h-[46px]"
             >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--wa-text-tertiary)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <span class="text-[var(--wa-text-secondary)] text-sm font-medium">Hanya admin yang dapat membalas chat</span>
             </div>

             <template v-else>
             <!-- Quick Replies Popup -->
             <div v-if="showQuickReplies" class="bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-xl shadow-2xl mb-2 max-h-60 overflow-y-auto">
                  <div class="sticky top-0 bg-[var(--wa-bg-panel)] border-b border-[var(--wa-border)] px-3 py-2">
                       <span class="text-xs text-[var(--wa-text-tertiary)]">💬 Quick Replies</span>
                  </div>
                  <div v-if="isLoadingQuickReplies" class="p-4 text-center text-[var(--wa-text-tertiary)]">
                       <div class="w-5 h-5 border-2 border-[var(--wa-text-tertiary)] border-t-[var(--wa-accent-green)] rounded-full animate-spin mx-auto"></div>
                  </div>
                  <div v-else-if="filteredQuickReplies.length === 0" class="p-4 text-center text-[var(--wa-text-tertiary)] text-sm">
                       No quick replies found
                  </div>
                  <div v-else>
                       <button
                         v-for="qr in filteredQuickReplies"
                         :key="qr.id"
                         @click="selectQuickReply(qr)"
                         class="w-full px-3 py-2.5 text-left hover:bg-[var(--wa-hover)] flex items-center gap-3 border-b border-[var(--wa-border)] last:border-b-0 transition-colors"
                       >
                            <span class="text-[var(--wa-accent-green)] font-mono text-xs shrink-0">/{{ (qr.shortcut || '').replace(/^\//, '') }}</span>
                            <span class="text-sm text-[var(--wa-text-primary)] truncate">{{ qr.title || qr.message }}</span>
                       </button>
                  </div>
             </div>

             <!-- Active Chat Input (CSW yCloud and/or Fonnte open) -->
             <div class="flex flex-col gap-2">
             <div v-if="bothChannelsOpen" class="flex items-center gap-2 text-xs">
               <span class="text-[var(--wa-text-tertiary)]">Kirim via:</span>
               <button
                 type="button"
                 @click="replyChannel = 'ycloud'"
                 class="px-2 py-1 rounded-md border transition-colors"
                 :class="replyChannel === 'ycloud' ? 'border-[var(--wa-accent-green)] text-[var(--wa-accent-green)] bg-[var(--wa-hover)]' : 'border-[var(--wa-border)] text-[var(--wa-text-secondary)]'"
               >~Y</button>
               <button
                 type="button"
                 @click="replyChannel = 'fonnte'"
                 class="px-2 py-1 rounded-md border transition-colors"
                 :class="replyChannel === 'fonnte' ? 'border-[var(--wa-accent-green)] text-[var(--wa-accent-green)] bg-[var(--wa-hover)]' : 'border-[var(--wa-border)] text-[var(--wa-text-secondary)]'"
               >~F</button>
             </div>
             <div v-else-if="activeConversation.fonnte_open && !activeConversation.ycloud_open" class="text-[10px] text-[var(--wa-text-tertiary)]">CSW Fonnte (~F)</div>
             <div v-else-if="activeConversation.ycloud_open && !activeConversation.fonnte_open" class="text-[10px] text-[var(--wa-text-tertiary)]">CSW yCloud (~Y)</div>
             <div class="flex gap-2 items-end">
                  <!-- Attachment buttons (left side) -->
                  <div class="flex items-center gap-2">
                       <input type="file" ref="fileInput" @change="selectImage" accept="image/*,video/mp4,video/3gpp,video/webm,video/quicktime" class="hidden" />
                       <!-- Emoji Button with Picker -->
                       <div class="relative">
                            <button @click.stop="showEmojiPicker = !showEmojiPicker" class="p-3 rounded-full bg-[var(--wa-bg-tertiary)] text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)] hover:bg-[var(--wa-hover)] transition-all">
                                 <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                 </svg>
                            </button>
                            <!-- Emoji Picker Component -->
                            <EmojiPicker
                              v-model="showEmojiPicker"
                              :recent-emojis="recentEmojis"
                              @select="handleEmojiSelect"
                            />
                       </div>
                       <!-- Camera/Image Button -->
                       <button @click="openImagePicker" class="p-3 rounded-full bg-[var(--wa-bg-tertiary)] text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)] hover:bg-[var(--wa-hover)] transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                       </button>
                  </div>
                  <!-- Text Input -->
                  <div class="flex-1 flex items-end bg-[var(--wa-input-bg)] rounded-3xl border border-[var(--wa-border)] overflow-hidden" :class="{ 'opacity-50': !isConnected }">
                       <textarea ref="messageTextarea" v-model="messageInput" @input="autoResizeTextarea" @keydown="handleMessageKeydown" :placeholder="isConnected ? 'Ketik pesan...' : 'Menghubungkan...'" :disabled="!isConnected" class="flex-1 bg-transparent py-3 px-4 text-sm focus:outline-none max-h-[150px] overflow-y-auto resize-none text-[var(--wa-text-primary)] disabled:cursor-not-allowed" rows="1"></textarea>
                  </div>
                  <!-- Send Button -->
                  <button @click="sendMessage" :disabled="!isConnected" class="p-3 bg-[var(--wa-accent-green)] rounded-full text-white shadow-lg hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed">
                       <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                       </svg>
                  </button>
             </div>
             </div>
             </template>
             
         </div>
      </div>
      
      <!-- Placeholder if no chat selected -->
      <div v-else class="w-full h-full flex items-center justify-center text-[var(--wa-text-tertiary)] flex-col">
           <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 opacity-20 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12z" /></svg>
           <p class="text-lg">Select a conversation to start chatting</p>
      </div>

     <!-- Quoted Message Detail Modal -->
    <Teleport to="body">
    <div v-if="showQuotedMessageModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[600] flex items-center justify-center p-4" @click="closeQuotedMessageDetail">
        <div v-if="quotedMessageToShow" class="bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-2xl shadow-2xl max-w-md w-full max-h-[80vh] overflow-hidden flex flex-col" @click.stop>
            <div class="flex justify-between items-center p-4 border-b border-[var(--wa-border)]">
                <h2 class="text-lg font-semibold text-[var(--wa-text-primary)]">Pesan yang di-reply</h2>
                <button @click="closeQuotedMessageDetail" class="p-1 rounded-full hover:bg-[var(--wa-hover)] text-[var(--wa-icon-default)]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="p-4 overflow-y-auto flex-1">
                <p class="text-xs font-bold text-[var(--wa-accent-green)] mb-1">{{ quotedMessageToShow.fromName }}</p>
                <div v-if="quotedMessageToShow.type === 'image' && (quotedMessageToShow.media_url || quotedMessageToShow.media_id)" class="space-y-2">
                    <img :src="quotedMessageToShow.media_url || `${API_BASE}/CRM/Chat/media?id=${quotedMessageToShow.media_id}`" class="max-w-full max-h-64 object-contain rounded-lg border border-[var(--wa-border)]" />
                    <p v-if="quotedMessageToShow.text" class="text-sm text-[var(--wa-text-primary)] whitespace-pre-wrap break-words" v-html="parseWhatsAppFormatting(quotedMessageToShow.text)"></p>
                </div>
                <div v-else-if="quotedMessageToShow.type === 'video' && (quotedMessageToShow.media_url || quotedMessageToShow.media_id)" class="space-y-2">
                    <video
                      :src="quotedMessageToShow.media_url || `${API_BASE}/CRM/Chat/media?id=${quotedMessageToShow.media_id}`"
                      class="max-w-full max-h-64 object-contain rounded-lg border border-[var(--wa-border)] bg-black"
                      controls
                      playsinline
                      preload="metadata"
                    ></video>
                    <p v-if="quotedMessageToShow.text" class="text-sm text-[var(--wa-text-primary)] whitespace-pre-wrap break-words" v-html="parseWhatsAppFormatting(quotedMessageToShow.text)"></p>
                </div>
                <div v-else-if="quotedMessageToShow.type === 'sticker' && (quotedMessageToShow.media_url || quotedMessageToShow.media_id)" class="space-y-2">
                    <img
                      :src="quotedMessageToShow.media_url || `${API_BASE}/CRM/Chat/media?id=${quotedMessageToShow.media_id}`"
                      class="w-36 h-36 object-contain"
                      alt="Sticker"
                    />
                </div>
                <p v-else class="text-sm text-[var(--wa-text-primary)] whitespace-pre-wrap break-words" v-html="parseWhatsAppFormatting(quotedMessageToShow.text)"></p>
            </div>
        </div>
    </div>
    </Teleport>
      </div>

    <CustomerPanel
      :conversation="activeConversation"
      :auth-id="authId"
      :api-base="API_BASE"
      :is-mobile="windowWidth < 768"
    />
    </main>
</template>

<style scoped>
@keyframes chat-poll-indeterminate {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(300%);
  }
}

.chat-poll-bar {
  animation: chat-poll-indeterminate 0.85s ease-in-out infinite;
}

@keyframes chat-poll-idle-pulse {
  0%, 100% {
    opacity: 0.55;
  }
  50% {
    opacity: 1;
  }
}

.chat-poll-idle-bar {
  animation: chat-poll-idle-pulse 1.6s ease-in-out infinite;
}
</style>
