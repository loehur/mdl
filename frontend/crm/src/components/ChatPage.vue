<script setup>
import { ref, computed, nextTick, watch, onMounted, onUnmounted } from "vue";
import { Camera, CameraResultType, CameraSource } from "@capacitor/camera";
import EmojiPicker from "./EmojiPicker.vue";
import twemoji from 'twemoji';

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
  }
});

// Computed font size class based on prop
const messageFontClass = computed(() => {
  return props.fontSize === 'large' ? 'text-base' : 'text-sm';
});

const emit = defineEmits([
  "back-to-menu",
  "refresh-active-chat",
  "open-image-lightbox",
  "update:activeConversation", // For optimistic updates to bubble up if needed, though objects are ref passed
  "trigger-connect", // If we need to reconnect
  "load-more-messages" // For infinite scroll
]);

// --- LOCAL STATE ---
const messageInput = ref("");
const chatContainer = ref(null);
const fileInput = ref(null);
const messageTextarea = ref(null);
const replyToMessage = ref(null);

// Image Upload
const showImagePreview = ref(false);
const imagePreview = ref("");
const selectedImage = ref(null);
const isUploadingImage = ref(false);
const imageCaption = ref("");

// Customer Info Modal State (Local to ChatPage)
const showCustomerInfoModal = ref(false);
const copiedPhone = ref(false);

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
    (c) => (c.status || "open") !== "closed" && parseInt(c.case) > 0
  );
  // Role based filtering
  if (props.currentUserRole === "admin") return openCases;
  if (props.currentUserRole === "driver") return openCases.filter((c) => parseInt(c.case) === 2);
  if (props.currentUserRole === "crew") return openCases.filter((c) => parseInt(c.case) === 3);
  return [];
});

const filteredQuickReplies = computed(() => {
  if (!quickReplySearchQuery.value) return quickReplies.value;
  const q = quickReplySearchQuery.value.toLowerCase();
  return quickReplies.value.filter(
    (qr) => (qr.shortcut || "").replace(/^\//, "").toLowerCase().includes(q) || (qr.title || "").toLowerCase().includes(q)
  );
});

// --- METHODS: UTILS ---
const scrollToBottom = () => {
  nextTick(() => {
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
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

const formatPhoneTo08 = (phone) => {
  if (!phone) return "";
  let p = phone.toString().replace(/\D/g, "");
  if (p.startsWith("62")) p = "0" + p.substring(2);
  return p;
};

const copyPhoneNumber = () => {
    if(!props.activeConversation?.wa_number) return;
    const phone = formatPhoneTo08(props.activeConversation.wa_number);
    navigator.clipboard.writeText(phone).then(() => {
        copiedPhone.value = true;
        setTimeout(() => (copiedPhone.value = false), 2000);
    });
};

// --- HANDLERS ---
const showCustomerInfo = () => {
    showCustomerInfoModal.value = true;
};

const backToMenu = () => {
    emit('back-to-menu');
};

const openImageLightbox = (url) => {
    emit('open-image-lightbox', url);
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
const sendMessage = async () => {
  const text = messageInput.value.trim();
  if (!text) return;
  if (props.activeConversation) {
    const tempId = Date.now();
    const replyingTo = replyToMessage.value;
    const newMsg = {
      id: tempId, text: text, sender: "me",
      time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", hour12: false }),
      rawTime: new Date().toISOString(), timestamp: Date.now(), status: "pending",
      quoted_message_id: replyingTo?.wamid || null,
      quoted_message_body: replyingTo?.text || replyingTo?.caption || null,
      sender_code: props.senderCode || localStorage.getItem("cms_chat_sender_code") || "",
    };

    props.activeConversation.messages.push(newMsg);
    props.activeConversation.lastMessage = "You: " + text;
    props.activeConversation.lastTime = newMsg.time;

    messageInput.value = "";
    replyToMessage.value = null;
    scrollToBottom();
    resetTextareaHeight();

    try {
      const res = await fetch(`${props.API_BASE}/CRM/Chat/reply`, {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          phone: props.activeConversation.wa_number, message: text, user_id: props.authId,
          sender_code: props.senderCode, reply_to: replyingTo?.wamid || null,
        }),
      }).then(r => r.json());

      const sentMsg = props.activeConversation.messages.find(m => m.id === tempId);
      if(sentMsg) {
          if (res.status) {
            sentMsg.status = "sent";
            if(res.data?.local_id) sentMsg.id = res.data.local_id;
            if(res.data?.wamid || res.data?.id) sentMsg.wamid = res.data.wamid || res.data.id;
          } else {
             sentMsg.status = "failed";
          }
      }
    } catch(e) {
         const sentMsg = props.activeConversation.messages.find(m => m.id === tempId);
         if(sentMsg) sentMsg.status = "error";
    }
  }
};

// ... Image Handling ...
// Minimal version for length, assume compressImage similar to before
const sendImage = async () => {
    if(isUploadingImage.value || !selectedImage.value || !props.activeConversation) return;
    isUploadingImage.value = true;
    const caption = imageCaption.value.trim();
    showImagePreview.value = false;

    const tempId = Date.now();
    const newMsg = {
      id: tempId, text: caption || "", type: "image", media_url: imagePreview.value,
      sender: "me", time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", hour12: false }),
      sender_code: props.senderCode, rawTime: new Date().toISOString(), status: "pending",
    };
    props.activeConversation.messages.push(newMsg);
    props.activeConversation.lastMessage = "You: 📷 Image";
    scrollToBottom();

    // FormData upload
    try {
        const formData = new FormData();
        formData.append("image", selectedImage.value);
        formData.append("phone", props.activeConversation.wa_number);
        formData.append("user_id", props.authId);
        formData.append("sender_code", props.senderCode);
        if(caption) formData.append("caption", caption);

        const res = await fetch(`${props.API_BASE}/CRM/Chat/sendImage`, { method: "POST", body: formData }).then(r => r.json());
        const sentMsg = props.activeConversation.messages.find(m => m.id === tempId);
        if(sentMsg) {
            if(res.status) {
                sentMsg.status = "sent";
                if(res.data?.local_id) sentMsg.id = res.data.local_id;
                if(res.data?.media_url) sentMsg.media_url = res.data.media_url;
            } else sentMsg.status = "failed";
        }
    } catch(e) { console.error(e); }
    finally {
        isUploadingImage.value = false;
        selectedImage.value = null; imagePreview.value = "";
    }
};

// ... Open Image Picker implementation ...
const openImagePicker = async () => {
    if(fileInput.value) fileInput.value.click();
}
const selectImage = async (event) => {
    const file = event.target.files[0];
    if (file) {
        // Simple preview without compression for brevity (can add back if needed)
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

const cancelImage = () => { selectedImage.value = null; imagePreview.value = ""; showImagePreview.value = false; imageCaption.value = ""; };

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
    if(m.type === 'image') return "📷 Image";
    return (m.text || m.caption || "").substring(0, 60);
};
const findQuotedMessage = (id) => props.activeConversation?.messages?.find(m => m.wamid === id || m.id === id);
const scrollToMessage = (id) => {
    const el = document.getElementById("msg-" + id); // assumes ID
    if(el) el.scrollIntoView({behavior: "smooth", block: "center"});
};
const setReplyTo = (m) => { replyToMessage.value = m; nextTick(() => messageTextarea.value?.focus()); };
const cancelReply = () => replyToMessage.value = null;

// Watchers
// Watch for deep changes to scroll to bottom (new messages, etc)
watch(() => props.activeConversation, () => {
    scrollToBottom();
}, { deep: true });

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

// Fetch quick replies from API
const fetchQuickReplies = async () => {
  if (isLoadingQuickReplies.value) return;
  isLoadingQuickReplies.value = true;
  try {
    const res = await fetch(`${props.API_BASE}/CRM/QuickReply/getAll`).then(r => r.json());
    if (res.status && res.data) {
      quickReplies.value = res.data;
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

// Watch for message count change to restore scroll position
watch(() => props.activeConversation?.messages?.length, (newCount, oldCount) => {
  if (shouldRestoreScroll.value && newCount > oldCount) {
    nextTick(() => {
      if (chatContainer.value) {
        const newScrollHeight = chatContainer.value.scrollHeight;
        const scrollDiff = newScrollHeight - savedScrollHeight.value;
        chatContainer.value.scrollTop = savedScrollTop.value + scrollDiff;
        shouldRestoreScroll.value = false;
      }
    });
  }
});

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
    scrollToBottom();
    
    // Load recent emojis from localStorage
    const savedEmojis = localStorage.getItem("recent_emojis");
    if (savedEmojis) {
      try {
        recentEmojis.value = JSON.parse(savedEmojis);
      } catch (e) {
        recentEmojis.value = [];
      }
    }
    
    // Load Quick Replies - simple fetch
    if(props.API_BASE) {
        fetch(`${props.API_BASE}/CRM/QuickReply/getAll`).then(r=>r.json()).then(res => {
            if(res.status) quickReplies.value = res.data;
        }).catch(e=>{});
    }
    
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
      class="flex flex-col bg-[var(--wa-bg-chat)] h-full overflow-x-hidden"
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
      <!-- Background Pattern -->
      <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9IndhLWJnIiB4PSIwIiB5PSIwIiB3aWR0aD0iODAiIGhlaWdodD0iODAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiPjxwYXRoIGQ9Ik0wIDIwIEwgMjAgMCBMIDQwIDIwIEwgNjAgMCBMIDgwIDIwIiBzdHJva2U9IiNhZWJhYzEiIHN0cm9rZS13aWR0aD0iMC41IiBmaWxsPSJub25lIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI3dhLWJnKSIvPjwvc3ZnPg=='); background-size: 80px 80px;"></div>

      <div v-if="activeConversation" class="w-full h-full relative z-10 flex flex-col">
         <!-- Chat Header -->
          <header class="h-16 border-b flex items-center justify-between px-4 md:px-6 z-30 border-[var(--wa-border)] bg-[var(--wa-bg-panel)] flex-shrink-0">
               <div class="flex items-center gap-3 flex-1 min-w-0">
                  <button @click="backToMenu" class="md:hidden p-1 -ml-2 text-[var(--wa-icon-default)]"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg></button>
                  <div @click="showCustomerInfo" class="min-w-0 flex-1 cursor-pointer">
                      <h2 class="font-medium text-[var(--wa-text-primary)] text-base md:text-lg truncate uppercase">{{ activeConversation.name }}</h2>
                      <div class="flex items-center gap-2">
                        <span v-if="activeConversation.kode_cabang" class="text-xs font-mono text-[var(--wa-text-secondary)]">{{ activeConversation.kode_cabang }}</span>
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
          </header>

         <!-- Messages -->
         <div ref="chatContainer" class="flex-1 overflow-y-auto overflow-x-hidden custom-scrollbar pt-4 pb-2 relative">
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
                               v-if="msg.sender === 'me'"
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
                               'rounded-lg shadow-sm px-3 py-1.5 relative overflow-hidden',
                               msg.type === 'image' ? 'p-0 bg-[var(--wa-bubble-incoming)]' : '',
                               msg.sender === 'me' ? 'bg-[var(--wa-bubble-outgoing)] rounded-tr-none' : 'bg-[var(--wa-bubble-incoming)] rounded-tl-none'
                             ]">
                                 <!-- Reply Quote -->
                                 <div v-if="msg.quoted_message_id" class="bg-black/10 rounded px-2 py-1 mb-1 border-l-2 border-[var(--wa-accent-green)]" :class="{ 'cursor-pointer': findQuotedMessage(msg.quoted_message_id) }" @click="findQuotedMessage(msg.quoted_message_id) && scrollToMessage(msg.quoted_message_id)">
                                      <span class="text-[10px] font-bold text-[var(--wa-accent-green)] block">
                                        {{ findQuotedMessage(msg.quoted_message_id)?.sender === 'me' ? 'You' : (msg.quoted_message_from ? 'Customer' : activeConversation.name) }}
                                      </span>
                                      <span class="text-xs truncate block text-[var(--wa-text-secondary)]">
                                        {{ findQuotedMessage(msg.quoted_message_id) ? getMessagePreview(findQuotedMessage(msg.quoted_message_id)) : (msg.quoted_message_body || 'Message not found') }}
                                      </span>
                                 </div>

                                  <!-- Image -->
                                  <div v-if="msg.type === 'image'" class="relative max-w-sm">
                                      <img :src="msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`" @click="openImageLightbox(msg.media_url || `${API_BASE}/CRM/Chat/media?id=${msg.media_id}`)" class="max-h-80 object-cover cursor-pointer" />
                                      <div v-if="msg.text || msg.time" class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/60 to-transparent p-2 text-white">
                                           <p v-if="msg.text" :class="[messageFontClass, 'mb-1']">{{ msg.text }}</p>
                                           <div class="flex justify-end items-center gap-1 text-[10px]">
                                              <span v-if="msg.sender_code">~{{ msg.sender_code }}</span>
                                              <span>{{ msg.time }}</span>
                                           </div>
                                      </div>
                                  </div>

                                  <!-- Text -->
                                  <div v-else :class="[messageFontClass, 'text-[var(--wa-text-primary)] overflow-hidden']">
                                       <div class="inline">
                                            <span v-html="parseWhatsAppFormatting(msg.text)" class="whitespace-pre-wrap break-words" style="word-break: break-word; overflow-wrap: anywhere;"></span>
                                            <span class="inline-flex items-center gap-1 ml-2 align-bottom select-none float-right mt-1" style="margin-left: 8px;">
                                                 <span v-if="msg.sender_code" class="text-[10px] text-[var(--wa-bubble-out-meta)] opacity-70">~{{ msg.sender_code }}</span>
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
                               v-if="msg.sender !== 'me'"
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
                  <img :src="imagePreview" class="h-48 object-cover rounded-lg mx-auto border" />
                  <button @click="cancelImage" class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1">X</button>
                  <div class="mt-2 flex gap-2">
                      <input v-model="imageCaption" type="text" placeholder="Caption..." class="flex-1 bg-[var(--wa-bg-secondary)] border px-3 py-2 rounded-lg text-sm" />
                      <button @click="sendImage" :disabled="isUploadingImage" class="bg-[var(--wa-accent-green)] text-black px-4 rounded-lg text-sm font-bold">{{ isUploadingImage ? '...' : 'Send' }}</button>
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
               v-if="activeConversation.status === 'closed'"
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

             <!-- Case 2: Active Chat Input (only when conversation is NOT closed) -->
             <div v-if="activeConversation.status !== 'closed'" class="flex gap-2 items-end">
                  <!-- Attachment buttons (left side) -->
                  <div class="flex items-center gap-2">
                       <input type="file" ref="fileInput" @change="selectImage" accept="image/*" class="hidden" />
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
                       <textarea ref="messageTextarea" v-model="messageInput" @input="autoResizeTextarea" @keydown.ctrl.enter.prevent="isConnected && sendMessage()" :placeholder="isConnected ? 'Ketik pesan...' : 'Menghubungkan...'" :disabled="!isConnected" class="flex-1 bg-transparent py-3 px-4 text-sm focus:outline-none max-h-[150px] overflow-y-auto resize-none text-[var(--wa-text-primary)] disabled:cursor-not-allowed" rows="1"></textarea>
                  </div>
                  <!-- Send Button -->
                  <button @click="sendMessage" :disabled="!isConnected" class="p-3 bg-[var(--wa-accent-green)] rounded-full text-black shadow-lg hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed">
                       <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                       </svg>
                  </button>
             </div>
             
         </div>
      </div>
      
      <!-- Placeholder if no chat selected -->
      <div v-else class="w-full h-full flex items-center justify-center text-[var(--wa-text-tertiary)] flex-col">
           <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 opacity-20 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12z" /></svg>
           <p class="text-lg">Select a conversation to start chatting</p>
      </div>

     <!-- Customer Info Modal -->
    <div v-if="showCustomerInfoModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[600] flex items-center justify-center p-4" @click="showCustomerInfoModal = false">
        <div class="bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-2xl shadow-2xl max-w-sm w-full p-6" @click.stop>
            <div class="flex justify-between mb-6">
                <h2 class="text-xl font-semibold text-[var(--wa-text-primary)]">Info Customer</h2>
                <button @click="showCustomerInfoModal = false" class="text-[var(--wa-icon-default)]">X</button>
            </div>
            <div class="space-y-4">
                 <div class="bg-[var(--wa-bg-secondary)] rounded-xl p-4 border border-[var(--wa-border)]">
                      <label class="text-xs text-[var(--wa-text-tertiary)]">Nama</label>
                      <p class="text-base font-medium text-[var(--wa-text-primary)] uppercase">{{ activeConversation?.name }}</p>
                 </div>
                 <div class="bg-[var(--wa-bg-secondary)] rounded-xl p-4 border border-[var(--wa-border)]">
                      <label class="text-xs text-[var(--wa-text-tertiary)]">WA</label>
                      <div class="flex justify-between">
                          <p class="text-base font-mono text-[var(--wa-text-primary)]">{{ formatPhoneTo08(activeConversation?.wa_number) }}</p>
                          <button @click="copyPhoneNumber" class="text-[var(--wa-accent-green)] text-sm font-bold">{{ copiedPhone ? 'Copied!' : 'Copy' }}</button>
                      </div>
                 </div>
            </div>
        </div>
    </div>
    </main>
</template>
