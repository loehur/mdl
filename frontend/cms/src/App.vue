<script setup>
import { ref, computed, onMounted, nextTick, watch, onUnmounted } from 'vue';
import { App } from '@capacitor/app';

// Window resize listener helper
const windowWidth = ref(window.innerWidth);
const updateWidth = () => windowWidth.value = window.innerWidth;
window.addEventListener('resize', updateWidth);

// --- State ---
// --- State ---
const API_BASE = 'https://api.nalju.com';
const conversations = ref([]);

const activeChatId = ref(null);
const messageInput = ref('');
const chatContainer = ref(null);
const socket = ref(null);
const isConnected = ref(false);
const showMobileChat = ref(false);
const authId = ref('');
const authPassword = ref(''); // Added
const isConnecting = ref(false);
const connectionError = ref('');
const showExitToast = ref(false);
let lastBackPress = 0;

// Swipe Gesture State
const touchStartX = ref(0);
const touchStartY = ref(0);
const touchOffset = ref(0); // Current drag distance
const isDragging = ref(false);
const minSwipeDistance = 75; // px

// Login Delay State
const showLoginPrompt = ref(false);

// Title Blinking State
const originalTitle = 'MDL Chat';
const titleBlinkInterval = ref(null);
const isTitleRed = ref(false);

const fetchConversations = async () => {
    try {
        const userIdParam = authId.value ? `?user_id=${authId.value}` : '';
        const response = await fetch(`https://api.nalju.com/CMS/Chat/getConversations${userIdParam}`); 
        
        if (!response.ok) {
            const text = await response.text();
            console.error("API Error Response:", text); // Use console so user can copy
            return;
        }

        const result = await response.json();
        
        // Backend returns "status": true, not "success"
        if (result.status && Array.isArray(result.data)) {
            // Check if filtering was expected
            if (result.data.length === 0) {
                 console.log("API returned 0 conversations.");
            }
            conversations.value = result.data.map(c => ({
                id: c.id,
                name: c.contact_name || c.wa_number,
                kode_cabang: c.kode_cabang, // Add kode_cabang
                avatar: `https://api.dicebear.com/7.x/avataaars/svg?seed=${c.id}`,
                status: c.status,  
                lastMessage: c.last_message || c.last_message_text || 'No messages yet',
                lastTime: c.last_message_time ? new Date(c.last_message_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '',
                unread: parseInt(c.unread_count) || 0,
                messages: [] 
            }));
        } else {
            console.error("API format error:", result);
        }
    } catch (e) {
        console.error("Error fetching conversations:", e);
    }
};

const connect = () => {
    if(!authId.value || !authPassword.value) {
        connectionError.value = 'Please enter both ID and Password';
        return;
    }
    isConnecting.value = true;
    connectionError.value = '';
    connectWebSocket();
    fetchConversations();
}

// --- Computed ---
const activeConversation = computed(() => { 
  if (!activeChatId.value) return null;
  return conversations.value.find(c => c.id === activeChatId.value) || null;
});

// Total Unread Messages
const totalUnread = computed(() => {
  return conversations.value.reduce((total, chat) => total + (chat.unread || 0), 0);
});

// --- Methods ---

// --- Methods ---
const fetchMessages = async (conversationId) => {
    try {
        const response = await fetch(`${API_BASE}/CMS/Chat/getMessages?id=${conversationId}`);
        const result = await response.json();
        
        if (result.status && Array.isArray(result.data)) {
            return result.data.map(m => ({
                id: m.id,
                wamid: m.wamid,
                text: m.text || m.caption, // Use caption if text is empty
                type: m.type,
                media_id: m.media_id,
                media_url: m.media_url,
                sender: m.sender, // 'me' or 'customer'
                time: m.time ? new Date(m.time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '',
                status: m.status
            }));
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

const markMessagesRead = async (conversationId) => {
    try {
        await fetch(`${API_BASE}/CMS/Chat/markRead`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ conversation_id: conversationId })
        });
        
        // Update local state if needed
        const chat = conversations.value.find(c => c.id === conversationId);
        if(chat) chat.unread = 0;
    } catch (e) {
        console.error("Failed to mark read", e);
    }
};

const selectChat = async (id) => {
  activeChatId.value = id;
  showMobileChat.value = true;
  
  const chat = conversations.value.find(c => c.id === id);
  if (chat) {
      // Optimistic read status
      chat.unread = 0;
      
      // Load messages
      chat.messages = await fetchMessages(id);
      
      // Mark read in DB
      markMessagesRead(id);
  }
  
  scrollToBottom();
};

const backToMenu = () => {
    touchOffset.value = 0; // Reset
    showMobileChat.value = false;
    activeChatId.value = null; // Deselect chat so unread counts increment
};

const sendMessage = async () => {
  const text = messageInput.value.trim();
  if (!text) return;
  
  if (activeConversation.value) {
    const tempId = Date.now();
    const newMsg = {
      id: tempId,
      text: text,
      sender: 'me',
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      status: 'pending'
    };
    
    // Optimistic UI
    activeConversation.value.messages.push(newMsg);
    activeConversation.value.lastMessage = "You: " + text;
    activeConversation.value.lastTime = newMsg.time;
    
    messageInput.value = '';
    scrollToBottom();
    
    // API Call
    try {
        const response = await fetch(`${API_BASE}/CMS/Chat/reply`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                conversation_id: activeConversation.value.id,
                message: text
            })
        });
        const res = await response.json();
        
        if (res.status) {
            // Update status (not strictly needed if we reload, but good for UI)
            const sentMsg = activeConversation.value.messages.find(m => m.id === tempId);
            if(sentMsg) {
                sentMsg.status = 'sent';
                if (res.data && res.data.local_id) {
                    sentMsg.id = res.data.local_id; // Swap temp ID with real DB ID
                    if (res.data.wamid) sentMsg.wamid = res.data.wamid;
                    else if (res.data.id) sentMsg.wamid = res.data.id;
                }
            }
        } else {
             // Error state
            const sentMsg = activeConversation.value.messages.find(m => m.id === tempId);
            if(sentMsg) sentMsg.status = 'failed';
            alert("Failed to send: " + (res.message || 'Unknown error'));
        }
    } catch (e) {
        console.error("Reply error:", e);
        const sentMsg = activeConversation.value.messages.find(m => m.id === tempId);
        if(sentMsg) sentMsg.status = 'error';
    }
  }
};

const handleIncomingMessage = (payload) => {
  // Check if this is a status update
  // Check if this is a status update
  if (payload.type === 'status_update') {
      const { conversation_id, message } = payload;
      const conversation = conversations.value.find(c => c.id == conversation_id);
      
      if (conversation) {
          // Find message by ID (preferred) or WAMID
          const msgToUpdate = conversation.messages.find(m => m.id == message.id || m.wamid == message.wamid);
          
          if (msgToUpdate) {
              msgToUpdate.status = message.status;
          }
      }
      return;
  }

  // Normal Message Handling
  // Payload structure from webhook:
  // { conversation_id, customer_id, phone, contact_name, message: { id, text, type, time }, target_id }
  
  // Or fallback if direct
  const conversationId = payload.conversation_id || payload.conversationId;
  const messageData = payload.message || payload; // if message is nested or flat
  
  const text = messageData.text;
  const type = messageData.type || 'text';
  const sender = messageData.sender || 'customer';
  
  let displayText = text;
  if (!displayText && type !== 'text') {
      displayText = `[${type}]`;
      if (messageData.media_caption) displayText += ' ' + messageData.media_caption; 
  }
  const name = payload.contact_name || payload.name;
  
  // Find or create conversation
  let conversation = conversations.value.find(c => c.id == conversationId);
  
  if (!conversation) {
    // New conversation
    conversation = {
      id: conversationId,
      name: name || payload.phone || 'Unknown User',
      avatar: `https://api.dicebear.com/7.x/avataaars/svg?seed=${conversationId}`,
      status: 'online', // Assume online on new msg
      messages: [],
      unread: 0
    };
    conversations.value.unshift(conversation);
  }
  
  const newMsg = {
    id: messageData.id || Date.now(),
    text: displayText, // Use the safe display text
    type: type,
    media_id: messageData.media_id,
    media_url: messageData.media_url,
    sender: sender,
    time: messageData.time ? new Date(messageData.time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  };
  
  // Avoid duplicate messages if already present
  if (!conversation.messages.find(m => m.id === newMsg.id)) {
      conversation.messages.push(newMsg);
      conversation.lastMessage = displayText;
      conversation.lastTime = newMsg.time;
      
  // Check visibility: Active ID matches AND (Desktop OR Mobile Chat View Open)
  const isChatVisible = activeChatId.value == conversationId && (windowWidth.value >= 768 || showMobileChat.value);

  if (!isChatVisible) {
    conversation.unread++;
  } else {
    scrollToBottom();
    markMessagesRead(conversationId);
  }
      
      // Move conversation to top
      const idx = conversations.value.findIndex(c => c.id == conversation.id);
      if (idx > 0) {
        conversations.value.splice(idx, 1);
        conversations.value.unshift(conversation);
      }
  }
};



const connectWebSocket = () => {
  if (!authId.value) return;

  console.log("Connecting to WebSocket with ID:", authId.value);
  
  try {
     const wsUrl = `wss://waserver.nalju.com?id=${authId.value.trim()}&password=${authPassword.value.trim()}`;
     const ws = new WebSocket(wsUrl); 
     socket.value = ws;
     
     ws.onopen = () => {
       console.log('WebSocket connected');
       isConnected.value = true;
       isConnecting.value = false;
       connectionError.value = '';
       
       // Save session (3 days)
       const expiry = new Date().getTime() + (3 * 24 * 60 * 60 * 1000);
       localStorage.setItem('cms_chat_id', authId.value);
       localStorage.setItem('cms_chat_password', authPassword.value);
       localStorage.setItem('cms_chat_expiry', expiry.toString());
       
       showLoginPrompt.value = false;
     };
     
     ws.onmessage = (event) => {
       try {
         const payload = JSON.parse(event.data);
         
         // Handle different message types
         if (payload.type === 'connection' || payload.type === 'pong') {
             // System messages, ignore for chat ui
             return;
         }
         
         if (payload.type === 'status_update') {
             handleIncomingMessage(payload);
             return;
         }

         // Handle Read Receipt Sync
         if (payload.type === 'conversation_read') {
             const conv = conversations.value.find(c => c.id == payload.conversation_id);
             if (conv) {
                 conv.unread = 0;
             }
             return;
          }
          
          // Handle Agent Message Sent (from other devices)
          if (payload.type === 'agent_message_sent') {
              const conversationId = payload.conversation_id;
              const messageData = payload.message;
              
              const conversation = conversations.value.find(c => c.id == conversationId);
              if (conversation) {
                  // Check if message already exists (avoid duplicates)
                  const exists = conversation.messages.find(m => 
                      m.id == messageData.id || m.wamid == messageData.wamid
                  );
                  
                  if (!exists) {
                      // Add the message sent by another agent
                      const newMsg = {
                          id: messageData.id,
                          wamid: messageData.wamid,
                          text: messageData.text,
                          type: messageData.type || 'text',
                          sender: 'me',
                          time: new Date(messageData.time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                          status: messageData.status || 'sent'
                      };
                      
                      conversation.messages.push(newMsg);
                      conversation.lastMessage = "You: " + messageData.text;
                      conversation.lastTime = newMsg.time;
                      
                      // Auto-scroll if viewing this conversation
                      if (activeChatId.value == conversationId) {
                          scrollToBottom();
                      }
                  }
              }
              return;
          }
          
         if (payload.type === 'wa_masuk') {
             // Real incoming WA message
             handleIncomingMessage(payload.data);
         } else if (payload.conversationId) {
             // Fallback for direct legacy format (if any)
             handleIncomingMessage(payload);
         }
       } catch (e) {
         console.error('Error parsing WS message', e);
       }
     };
     
     ws.onclose = (event) => {
       if (isConnected.value) {
            console.log('WebSocket disconnected');
            isConnected.value = false;
       } else {
           // Connection failed during attempt
           isConnecting.value = false;
           // Clear invalid session if we failed to connect (e.g. ID revoked)
           // But be careful not to clear on transient network errors? 
           // Probably safe to let user try again or re-enter.
           // For now, let's not clear automatically unless it's strictly Auth error (1008)
           
           let msg = 'Connection failed.';
            if (event.code === 1008) {
                msg = 'Access Denied: Invalid ID.';
                localStorage.removeItem('chat_connection_id'); // Clear invalid ID
                localStorage.removeItem('chat_connection_expiry');
                showLoginPrompt.value = true; // Show login immediately on auth error
            } else if (event.code === 1006) {
                msg = 'Connection terminated abnormally.';
                // If pure network error, maybe wait or show modal? 
                // Let's show modal if we are truly disconnected for clarity
                showLoginPrompt.value = true;
            } else if (event.reason) {
                msg = `Error: ${event.reason}`;
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
              backToMenu();
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


onMounted(() => {
  scrollToBottom();
  
  // Initialize title blinking
  updateTitleBlinking();
  
  // Check Local Storage for Session
  const storedId = localStorage.getItem('cms_chat_id');
  const storedPass = localStorage.getItem('cms_chat_password');
  const storedExpiry = localStorage.getItem('cms_chat_expiry');
  const now = new Date().getTime();
  
  // Case 1: Complete valid session (ID + Password + Valid Expiry)
  if (storedId && storedPass && storedExpiry && now < parseInt(storedExpiry)) {
      console.log("Restoring session for ID:", storedId);
      authId.value = storedId;
      authPassword.value = storedPass;
      
      // Renew expiry for another 3 days
      const newExpiry = new Date().getTime() + (3 * 24 * 60 * 60 * 1000);
      localStorage.setItem('cms_chat_expiry', newExpiry.toString());
      
      connectWebSocket();
      fetchConversations();
  } 
  // Case 2: Has ID but missing password (legacy session) - Keep ID, prompt for password
  else if (storedId && !storedPass) {
      console.log("Legacy session detected. ID found but password missing. Please re-enter password.");
      authId.value = storedId; // Keep the ID
      connectionError.value = 'Session incomplete. Please enter your password to continue.';
      showLoginPrompt.value = true;
  }
  // Case 3: Expired or no session - Start fresh
  else {
      // Clean up any partial/expired data
      localStorage.removeItem('cms_chat_id');
      localStorage.removeItem('cms_chat_password');
      localStorage.removeItem('cms_chat_expiry');
      
      // Check URL param?
      const urlParams = new URLSearchParams(window.location.search);
      const idParam = urlParams.get('id');
      if (idParam) {
          authId.value = idParam;
          // Prompt for password
          showLoginPrompt.value = true;
          // Clean URL
          window.history.replaceState({}, document.title, window.location.pathname);
      } else {
        // Show Login Prompt
        setTimeout(() => { showLoginPrompt.value = true; }, 500);
      }
  }

  });

  // Handle Android Back Button
  App.addListener('backButton', () => {
    const timeNow = Date.now();
    if (timeNow - lastBackPress < 2000) {
        App.exitApp();
    } else {
        lastBackPress = timeNow;
        showExitToast.value = true;
        setTimeout(() => {
            showExitToast.value = false;
        }, 2000);
    }
  });

  // Login Modal Delay Logic
  setTimeout(() => {
      if (!isConnected.value && !isConnecting.value) {
          showLoginPrompt.value = true;
      }
  }, 1500); // Wait 1.5s before showing modal if not connected

const logout = () => {
    if (socket.value) {
        socket.value.close();
        socket.value = null;
    }
    isConnected.value = false;
    authId.value = '';
    authPassword.value = '';
    isConnecting.value = false;
    showLoginPrompt.value = true;
    
    // Clear Session
    localStorage.removeItem('cms_chat_id');
    localStorage.removeItem('cms_chat_password');
    localStorage.removeItem('cms_chat_expiry');
};

// Update Title Blinking
const updateTitleBlinking = () => {
  // Stop any existing interval
  if (titleBlinkInterval.value) {
    clearInterval(titleBlinkInterval.value);
    titleBlinkInterval.value = null;
    document.title = originalTitle;
  }
  
  // If there are unread messages, start blinking
  if (totalUnread.value > 0) {
    let showAlert = true;
    titleBlinkInterval.value = setInterval(() => {
      if (showAlert) {
        document.title = `🔴 (${totalUnread.value}) New Messages!`;
        isTitleRed.value = true;
      } else {
        document.title = originalTitle;
        isTitleRed.value = false;
      }
      showAlert = !showAlert;
    }, 1000); // Blink every 1 second
  }
};

watch(activeChatId, () => {
  scrollToBottom();
});

// Watch for unread count changes
watch(totalUnread, () => {
  updateTitleBlinking();
});

// Cleanup on unmount
onUnmounted(() => {
  if (titleBlinkInterval.value) {
    clearInterval(titleBlinkInterval.value);
    document.title = originalTitle;
  }
});

// Stop blinking when window is focused
window.addEventListener('focus', () => {
  if (totalUnread.value === 0 && titleBlinkInterval.value) {
    clearInterval(titleBlinkInterval.value);
    titleBlinkInterval.value = null;
    document.title = originalTitle;
  }
});

</script>

<template>
  <!-- Use fixed inset-0 to prevent body scroll issues on mobile -->
  <div class="fixed inset-0 w-full bg-[#0f172a] text-slate-200 overflow-hidden font-sans selection:bg-indigo-500 selection:text-white">
    
    <!-- Login Modal (Overlay) -->
     <div v-if="!isConnected && showLoginPrompt" class="fixed inset-0 z-[60] bg-[#0f172a] flex items-center justify-center p-4">
       <!-- Login Card -->
       <!-- ... existing login card ... -->
       <div class="bg-[#1e293b] border border-slate-700 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <!-- ... content ... -->
          <div class="p-6 bg-[#1e293b] border-b border-slate-700 text-center">
             <!-- ... header ... -->
             <div class="w-16 h-16 bg-slate-800 rounded-full mx-auto flex items-center justify-center mb-4 shadow-inner">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
             </div>
             <h2 class="text-xl font-bold text-white">Connect to Chat Console</h2>
             <p class="text-slate-400 text-sm mt-1">Enter your unique Connection ID to start.</p>
          </div>
          
          <!-- Form -->
          <div class="p-8">
             <div class="space-y-4">
                <div>
                   <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Connection ID</label>
                   <input 
                      v-model="authId" 
                      type="text" 
                      placeholder="e.g. 12345"
                      @keydown.enter="connect"
                      class="w-full bg-[#0f172a] border border-slate-700 rounded-lg px-4 py-3 text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                      :disabled="isConnecting"
                   >                 </div>
 
                 <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Password</label>
                    <input 
                       v-model="authPassword" 
                       type="password" 
                       placeholder="Enter password"
                       @keydown.enter="connect"
                       class="w-full bg-[#0f172a] border border-slate-700 rounded-lg px-4 py-3 text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                       :disabled="isConnecting"
                    >
                 </div>
 
                 <div v-if="connectionError" class="p-3 bg-red-500/10 border border-red-500/20 rounded-lg flex items-center gap-2 text-red-400 text-sm animate-pulse">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                   </svg>
                   {{ connectionError }}
                </div>
                
                <button 
                   @click="connect" 
                   class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-3 px-4 rounded-lg transition-all transform active:scale-[0.98] flex justify-center items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                   :disabled="isConnecting || !authId || !authPassword"
                >
                   <span v-if="isConnecting" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                   {{ isConnecting ? 'Connecting...' : 'Connect' }}
                </button>
             </div>
          </div>
       </div>
    </div>
    


    <!-- Sidebar -->
    <!-- On mobile, we keep it rendered but covered by chat when active. On desktop it's side-by-side. -->
    <aside v-if="isConnected" class="flex flex-col border-r border-slate-800 bg-[#1e293b] transition-all duration-300 absolute md:static z-0 h-full w-full md:w-80"
           :class="showMobileChat ? 'flex' : 'flex'">
      <!-- Header -->
      <div class="p-4 border-b border-slate-700 flex justify-between items-center bg-[#1e293b]/50 backdrop-blur-md">
        <h1 class="text-xl font-bold bg-gradient-to-r from-indigo-400 to-cyan-400 bg-clip-text text-transparent">
          MDL WhatsApp
        </h1>
        <div class="relative">
             <div class="w-3 h-3 rounded-full" :class="isConnected ? 'bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.5)]' : 'bg-red-500'"></div>
        </div>
      </div>
      
      <!-- Conversation List -->
      <div class="flex-1 overflow-y-auto custom-scrollbar">
        <div 
          v-for="chat in conversations" 
          :key="chat.id"
          @click="selectChat(chat.id)"
          class="p-4 flex items-center gap-3 cursor-pointer transition-colors duration-200 border-b border-slate-800 hover:bg-slate-800/50"
          :class="{'bg-[#334155]/60 border-l-4 border-l-indigo-500': activeChatId === chat.id, 'border-l-4 border-l-transparent': activeChatId !== chat.id}"
        >
          <div class="relative">
            <img :src="chat.avatar" class="w-12 h-12 rounded-full bg-slate-700 object-cover border border-slate-600">
            <span v-if="chat.status === 'online'" class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-[#1e293b] rounded-full"></span>
          </div>
           <div class="flex-1 min-w-0">
             <div class="flex justify-between items-baseline mb-0.5 gap-2">
               <h3 class="font-semibold text-sm truncate text-slate-100 uppercase max-w-[180px]" :title="chat.name">
                 <span v-if="chat.kode_cabang" class="font-mono text-xs mr-1" :class="chat.kode_cabang === '00' ? 'text-pink-500' : 'text-indigo-400'">[{{ chat.kode_cabang }}]</span>
                 {{ chat.name }}
               </h3>
               <span class="text-xs text-slate-500 flex-shrink-0">{{ chat.lastTime }}</span>
             </div>
            <div class="flex justify-between items-center">
               <p class="text-xs text-slate-400 truncate w-32" :class="{'font-medium text-slate-200': chat.unread > 0}">{{ chat.lastMessage }}</p>
               <span v-if="chat.unread > 0" class="bg-indigo-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow-sm">
                 {{ chat.unread }}
               </span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- User Profile (Self) -->
      <div class="p-4 border-t border-slate-700 bg-[#1e293b]/80 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Admin" class="w-10 h-10 rounded-full bg-indigo-900 border border-slate-600">
            <div>
               <div class="text-sm font-medium text-slate-200">Support Agent</div>
               <div class="text-xs text-green-400 flex items-center gap-1">
                 <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span> Online
               </div>
            </div>
        </div>
        
        <button 
           @click="logout" 
           title="Logout"
           class="p-2 text-slate-400 hover:text-red-400 hover:bg-slate-700/50 rounded-lg transition-colors"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
        </button>
      </div>
    </aside>
    
    <!-- Main Chat Area -->
    <!-- Mobile: Fixed on top (z-50) if active. Desktop: static flex-1. -->
    <main 
        class="flex flex-col bg-[#0f172a] h-full overflow-x-hidden"
        :class="{
            'fixed inset-0 z-50 w-full': showMobileChat,
            'hidden': !showMobileChat && windowWidth < 768,
            'fixed top-0 right-0 bottom-0 md:left-80 z-0 !w-auto': windowWidth >= 768
        }"
        :style="{ 
            transform: showMobileChat && windowWidth < 768 ? `translateX(${touchOffset}px)` : '',
            transition: isDragging ? 'none' : 'transform 0.3s ease-out'
        }"
        @touchstart="handleTouchStart"
        @touchmove="handleTouchMove"
        @touchend="handleTouchEnd"
    >

       <!-- Background Pattern -->
       <div class="absolute inset-0 opacity-5 pointer-events-none" 
            style="background-image: radial-gradient(#6366f1 1px, transparent 1px); background-size: 32px 32px;">
       </div>

      <div v-if="activeConversation" class="flex flex-col w-full h-full relative z-10">
        <!-- Chat Header -->
        <header class="h-16 border-b border-slate-800 bg-[#0f172a]/90 backdrop-blur-sm flex items-center justify-between px-4 md:px-6 z-10 sticky top-0">
          <div class="flex items-center gap-3">
             <!-- Back Button (Mobile Only) -->
             <button @click="backToMenu" class="md:hidden p-1 -ml-2 text-slate-400 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
             </button>
                          <img :src="activeConversation.avatar" class="w-10 h-10 rounded-full border border-slate-700">
              <div class="min-w-0 flex-1">
                <h2 class="font-bold text-slate-100 text-base md:text-lg uppercase truncate max-w-[200px] md:max-w-[300px]" :title="activeConversation.name">{{ activeConversation.name }}</h2>
                <p v-if="activeConversation.kode_cabang" class="text-xs font-mono" :class="activeConversation.kode_cabang === '00' ? 'text-pink-500' : 'text-indigo-400'">{{ activeConversation.kode_cabang }}</p>
              </div>
          </div>
          <div class="flex items-center gap-4 text-slate-400">
             <button class="hover:text-indigo-400 transition-colors p-2 rounded-full hover:bg-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
             </button>
             <button class="hover:text-indigo-400 transition-colors p-2 rounded-full hover:bg-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
             </button>
          </div>
        </header>
        
        <!-- Messages -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar" ref="chatContainer">
          <div v-for="(msg, index) in activeConversation.messages" :key="msg.id" class="flex flex-col">
            
             <!-- Date Separator (Optional Logic could go here) -->
             
             <!-- Customer Message -->
             <div v-if="msg.sender !== 'me'" class="flex gap-3 max-w-[75%] items-end">
                <img v-if="index === 0 || activeConversation.messages[index-1]?.sender === 'me'" :src="activeConversation.avatar" class="w-8 h-8 rounded-full mb-1">
                <div v-else class="w-8"></div> <!-- Spacer -->
                
                <div class="bg-slate-800 text-slate-200 px-4 py-2.5 rounded-2xl rounded-bl-sm border border-slate-700/50 shadow-sm max-w-full">
                   <div v-if="msg.type === 'image'" class="mb-2">
                        <img v-if="msg.media_url" :src="msg.media_url" class="rounded-lg max-w-[200px] cursor-pointer" onclick="window.open(this.src)">
                        <img v-else-if="msg.media_id" :src="`${API_BASE}/CMS/Chat/media?id=${msg.media_id}`" class="rounded-lg max-w-[200px] cursor-pointer" onclick="window.open(this.src)">
                        <div v-else class="p-2 bg-slate-900 rounded border border-slate-700/50 flex flex-col items-center justify-center w-[200px] h-[150px]">
                           <span class="text-[10px] text-slate-500">Image (Protected)</span>
                        </div>
                   </div>
                   <p v-if="msg.text" class="leading-relaxed text-sm break-words whitespace-pre-wrap">{{ msg.text }}</p>
                   <span class="text-[10px] text-slate-500 block mt-1 text-right">{{ msg.time }}</span>
                </div>
             </div>
             
             <!-- My Message -->
             <div v-else class="flex gap-3 max-w-[75%] self-end items-end justify-end">
                <div class="bg-indigo-600 text-white px-4 py-2.5 rounded-2xl rounded-br-sm shadow-md shadow-indigo-900/20 max-w-full">
                   <div v-if="msg.type === 'image'" class="mb-2">
                        <img v-if="msg.media_url" :src="msg.media_url" class="rounded-lg max-w-[200px] bg-slate-800">
                        <img v-else-if="msg.media_id" :src="`${API_BASE}/CMS/Chat/media?id=${msg.media_id}`" class="rounded-lg max-w-[200px] bg-slate-800" onclick="window.open(this.src)">
                   </div>
                   <p v-if="msg.text" class="leading-relaxed text-sm break-words whitespace-pre-wrap">{{ msg.text }}</p>
                     <div class="flex items-center justify-end gap-1 mt-1">
                        <span class="text-[10px] text-indigo-200">{{ msg.time }}</span>
                        <!-- Status Indicators -->
                        <span v-if="msg.status === 'pending'" class="text-indigo-300">
                           <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                        <span v-else-if="msg.status === 'sent'" class="text-indigo-300">
                           <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                        </span>
                        <span v-else-if="msg.status === 'delivered'" class="text-indigo-300">
                           <div class="flex -space-x-1">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                           </div>
                        </span>
                        <span v-else-if="msg.status === 'read'" class="text-blue-300">
                            <div class="flex -space-x-1">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                           </div>
                        </span>
                         <span v-else-if="msg.status === 'failed'" class="text-red-300">
                           <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                     </div>
                </div>
             </div>
             
          </div>
        </div>
        
        <!-- Input Area -->
        <div class="p-4 bg-[#0f172a] border-t border-slate-800 relative z-20">
           <div class="flex gap-3 items-end bg-[#1e293b] p-2 rounded-xl border border-slate-700 focus-within:ring-2 focus-within:ring-indigo-500/50 focus-within:border-indigo-500 transition-all">
              <button class="p-2 text-slate-400 hover:text-indigo-400 transition-colors">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
              </button>
              <textarea 
                v-model="messageInput"
                @keydown.enter.prevent="sendMessage"
                placeholder="Type your message..." 
                class="flex-1 bg-transparent text-slate-200 placeholder:text-slate-500 focus:outline-none resize-none py-2 max-h-32 text-sm"
                rows="1"
              ></textarea>
              <button @click="sendMessage" class="p-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors shadow-lg shadow-indigo-600/30">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
              </button>
           </div>
           <div class="text-center mt-2">
              <span class="text-[10px] text-slate-500">Press Enter to send</span>
           </div>
        </div>
        
      </div>
      
      <div v-else class="w-full h-full relative z-10 flex flex-col">
         <div class="flex-1 flex flex-col items-center justify-center text-slate-500 w-full h-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mb-4 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <p class="text-lg">Select a conversation to start chatting</p>
         </div>
      </div>
      
    </main>
    <!-- Exit Toast -->
    <div v-if="showExitToast" class="fixed bottom-8 left-1/2 transform -translate-x-1/2 bg-slate-800/90 backdrop-blur text-white px-6 py-3 rounded-full shadow-xl border border-slate-700/50 z-[100] transition-opacity duration-300 pointer-events-none">
        <span class="text-sm font-medium">Press back again to exit</span>
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
  background: #334155;
  border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #475569;
}
</style>
