<script setup>
import { ref, computed, onMounted, nextTick, watch, onUnmounted } from 'vue';
import { App } from '@capacitor/app';

// Window resize listener helper
const windowWidth = ref(window.innerWidth);
const updateWidth = () => windowWidth.value = window.innerWidth;
window.addEventListener('resize', updateWidth);

const getAvatarColor = (seed) => {
  const colors = [
    '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#ef4444', 
    '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#64748b'
  ];
  if (!seed) return colors[0];
  const num = typeof seed === 'string' ? seed.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0) : seed;
  return colors[num % colors.length];
};

// Case Color Helper
const getCaseColor = (caseId) => {
    // 1=Payment, 2=Pickup, 3=Request, 4=Reopen/Urgent
    switch(parseInt(caseId)) {
        case 1: return 'bg-green-500';
        case 2: return 'bg-yellow-500';
        case 3: return 'bg-red-500';
        case 4: return 'bg-purple-500';
        case 0: return 'bg-slate-500'; 
        default: return 'hidden'; 
    }
};

const getCaseLabel = (caseId) => {
    switch(parseInt(caseId)) {
        case 1: return 'Check Payment';
        case 2: return 'Pickup/Delivery';
        case 3: return 'Request';
        case 4: return 'Follow Up';
        default: return 'Case ' + caseId;
    }
};

// Check if a case is currently open
const isCaseOpen = (caseId) => {
    if (!activeConversation.value || !activeConversation.value.cases) return false;
    return activeConversation.value.cases.some(c => 
        parseInt(c.case) === parseInt(caseId) && (c.status || 'open') !== 'closed'
    );
};

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
const isLoadingConversations = ref(false); // For skeleton loading
const showExitToast = ref(false);
const refreshInterval = ref(null); // Fix: Add missing ref
const currentUserRole = ref('crew'); // Default to crew (safest)
let lastBackPress = 0;

// Image Upload State
const selectedImage = ref(null);
const imagePreview = ref('');
const showImagePreview = ref(false);
const isUploadingImage = ref(false);
const imageCaption = ref('');
const fileInput = ref(null);

// Swipe Gesture State
const touchStartX = ref(0);
const touchStartY = ref(0);
const touchOffset = ref(0); // Current drag distance
const isDragging = ref(false);
const minSwipeDistance = 75; // px

// Login Delay State
const showLoginPrompt = ref(false);
const showDuplicateConnectionModal = ref(false);

// Title Blinking State
const originalTitle = 'MDL Chat';
const titleBlinkInterval = ref(null);
const isTitleRed = ref(false);

// Chat Menu State
const showChatMenu = ref(false);
const isMarkingAsDone = ref(false);
const isCheckingPayment = ref(false);
const isPickupDelivery = ref(false);
const isRequest = ref(false);
const isReopeningConversation = ref(false);
const showResolveMenu = ref(false); // New state

// Auto-Open Chat on Incoming Message
const autoOpenChatOnIncoming = ref(false); // Set false if you want manual open only


// SSE (Server-Sent Events) for real-time updates
const eventSource = ref(null);

const searchQuery = ref('');

// Conversation Filter State
const conversationFilter = ref('all'); // 'all' or 'unread'

// Settings State
const showSettingsModal = ref(false);
const fontSize = ref('medium'); // 'medium', 'large'

// Load font size from localStorage on mount
const loadFontSize = () => {
  const saved = localStorage.getItem('cms_font_size');
  if (saved && ['medium', 'large'].includes(saved)) {
    fontSize.value = saved;
  }
};

// Save font size to localStorage
const setFontSize = (size) => {
  fontSize.value = size;
  localStorage.setItem('cms_font_size', size);
  showSettingsModal.value = false;
};

// Computed font sizes for messages
const messageFontSize = computed(() => {
  const sizes = {
    medium: '14.2px',
    large: '16px'
  };
  return sizes[fontSize.value] || sizes.medium;
});


const filteredConversations = computed(() => {
  let list = conversations.value;

  // **ROLE BASED FILTERING**
  const role = currentUserRole.value;
  const myId = authId.value;

  if (role === 'driver') {
      // Driver only sees Priority 2 (Pickup/Delivery)
      list = list.filter(c => c.cases && c.cases.some(x => x.case === 2));
  } else if (role === 'crew') {
      // Crew only sees conversations assigned to them
      if (myId) {
          list = list.filter(c => String(c.assignment_user_id) === String(myId));
      }
  }
  // Admin sees everything (no filter added)

  // Apply conversation filter (All/Unread)
  if (conversationFilter.value === 'unread') {
    list = list.filter(c => c.unread > 0);
  }
  
  // Apply search filter if query exists
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    list = list.filter(c => 
      (c.name && c.name.toLowerCase().includes(query)) ||
      (c.wa_number && c.wa_number.includes(query)) ||
      (c.lastMessage && c.lastMessage.toLowerCase().includes(query))
    );
  }
  
  // **NEW SORTING LOGIC**:
  // Sort by updated_at (most recent first) - Case/Priority doesn't force top anymore per request?
  // User said: "lingkaran2 berwarna sesuai beberapa case".
  // Let's keep sorting by existence of ANY active case > 0 first, then time.
  return list.sort((a, b) => {
    // Check if has any active case (>0)
    const aHasCase = a.cases && a.cases.some(c => c.case > 0);
    const bHasCase = b.cases && b.cases.some(c => c.case > 0);
    
    if (aHasCase && !bHasCase) return -1;
    if (!aHasCase && bHasCase) return 1;
    
    // Fallback to API order (time)
    return 0;
  });
});

// Computed: Cases available to resolve based on Role
const resolveableCases = computed(() => {
    if (!activeConversation.value || !activeConversation.value.cases) return [];
    
    const role = currentUserRole.value;
    // Filter open cases (include all cases including Case 4)
    const openCases = activeConversation.value.cases.filter(c => (c.status || 'open') !== 'closed' && c.case > 0);
    
    if (role === 'admin') return openCases;
    if (role === 'driver') return openCases.filter(c => c.case === 2);
    if (role === 'crew') return openCases.filter(c => c.case === 3);
    
    return []; // default no access
});

// Total unread messages count
const totalUnreadCount = computed(() => {
  return conversations.value.reduce((sum, conv) => sum + (conv.unread || 0), 0);
});

// Title blinking is now handled by shouldBlinkTitle watch below (line 314)
// to avoid conflicts between totalUnreadCount and priority-based blinking


const fetchConversations = async () => {
    try {
        isLoadingConversations.value = true; // Start loading
        
        const userIdParam = authId.value ? `user_id=${authId.value}` : '';
        const separator = userIdParam ? '&' : '?';
        const query = userIdParam ? `?${userIdParam}${separator}_t=${Date.now()}` : `?_t=${Date.now()}`;
        
        const response = await fetch(`${API_BASE}/CMS/Chat/getConversations${query}`); 
        
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
            const existingMap = new Map(conversations.value.map(c => [c.id, c]));
            const newOrder = [];

            result.data.forEach(c => {
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
                        if (typeof rawCase === 'string' && (rawCase.startsWith('[') || rawCase.startsWith('{'))) {
                            try {
                                const parsed = JSON.parse(rawCase);
                                if (Array.isArray(parsed)) cases = parsed;
                                else if (parsed.case) cases = [parsed];
                            } catch(e) {}
                        }
                        
                        // 3. Fallback: Legacy Priority/Case Val (Only if still empty)
                        if (cases.length === 0 && (c.priority > 0 || c.case_val > 0)) {
                             cases = [{ case: parseInt(c.priority || c.case_val || 0) }];
                        }
                    }
                    
                    // Filter out 0 case if there are others, or just keep distinct
                    return cases;
                };

                let convo = existingMap.get(c.id);
                
                if (convo) {
                    // Update existing
                    convo.wa_number = c.wa_number;
                    convo.name = c.contact_name || c.wa_number;
                    convo.kode_cabang = c.kode_cabang;
                    // convo.priority = parseInt(c.priority) || 0; // Legacy ignored
                    convo.cases = parseCases(c); // New Array
                    
                    convo.initials = (c.contact_name || c.wa_number || '?').substring(0, 1).toUpperCase();
                    convo.color = getAvatarColor(c.id);
                    convo.status = c.status;
                    convo.lastMessage = c.last_message || c.last_message_text || 'No messages yet';
                    convo.lastTime = formatLastTime(c.last_message_time);
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
                        
                        initials: (c.contact_name || c.wa_number || '?').substring(0, 1).toUpperCase(),
                        color: getAvatarColor(c.id),
                        status: c.status,  
                        lastMessage: c.last_message || c.last_message_text || 'No messages yet',
                        lastTime: formatLastTime(c.last_message_time),
                        unread: parseInt(c.unread_count) || 0,
                        assignment_user_id: c.assigned_user_id, // Fix: map from backend assigned_user_id
                        messages: []
                    };
                }
                newOrder.push(convo);
            });
            
            // Re-assign to update list order/membership
            conversations.value = newOrder;
            
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
        const res = await fetch(`${API_BASE}/CMS/Roles`);
        const result = await res.json();
        if (result.status && result.data) {
            const roles = result.data;
            const myId = authId.value;
            let role = 'crew'; // Default
            
            // Loose comparison in case ID is string vs int
            const includesId = (arr, id) => arr.some(x => String(x) === String(id));
            
            if (roles.admin && includesId(roles.admin, myId)) role = 'admin';
            else if (roles.driver && includesId(roles.driver, myId)) role = 'driver';
            else if (roles.crew && includesId(roles.crew, myId)) role = 'crew';
            
            currentUserRole.value = role;
            localStorage.setItem('cms_chat_role', role);
            console.log('User Role Detected:', role);
        }
    } catch (e) {
        console.error("Failed to fetch roles:", e);
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
    fetchUserRole(); // Determine role
    fetchConversations();
    
    // ✅ FINAL RELIABLE SOLUTION: 3-Second Polling
    // WebSocket broadcast is blocked by server. SSE is buffered.
    // Short polling is the ONLY robust way to sync agents without Node.js access.
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
    }
    
    refreshInterval.value = setInterval(() => {
        // ALWAYS poll even if hidden, to ensure multi-monitor setups see updates instanty
        if (isConnected.value) {
            fetchConversations();
            // console.log('⚡ Polling (3s)...'); 
        }
    }, 3000); // 3 Seconds = Fast enough for UX, light server load.
}

// --- Computed ---

// --- Computed ---
const activeConversation = computed(() => { 
  if (!activeChatId.value) return null;
  return conversations.value.find(c => c.id === activeChatId.value) || null;
});

// Total Unread Messages
const totalUnread = computed(() => {
  return conversations.value.reduce((total, chat) => total + (chat.unread || 0), 0);
});

// Total Priority Conversations
const totalPriority = computed(() => {
  return conversations.value.filter(chat => chat.priority && chat.priority > 0).length;
});

// Should Title Blink (any conversation with unread messages)
const shouldBlinkTitle = computed(() => {
  const anyUnread = conversations.value.filter(chat => chat.unread > 0);
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
        document.title = isTitleRed.value ? `🔴 (${count}) New Messages` : originalTitle;
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

// Handle Duplicate Connection - Clear session and reload
const handleDuplicateConnection = () => {
  localStorage.removeItem('cms_chat_id');
  localStorage.removeItem('cms_chat_password');
  localStorage.removeItem('cms_chat_expiry');
  window.location.reload();
};

// Parse WhatsApp Formatting to HTML
const parseWhatsAppFormatting = (text) => {
  if (!text) return '';
  
  let formatted = text;
  
  // Escape HTML first to prevent XSS
  formatted = formatted
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
  
  // Convert URLs to clickable links BEFORE other formatting
  // This prevents URLs from being broken by formatting tags
  // Pattern matches: http://, https://, www., or domain.com patterns
  const urlPattern = /(https?:\/\/[^\s]+)|(www\.[^\s]+)|([a-zA-Z0-9][-a-zA-Z0-9]{0,62}\.(?:com|net|org|id|co\.id|ac\.id|io|dev|app|ai|me|info|biz|edu|gov|mil|xyz|online|store|tech|site|web|cloud|link|blog)[^\s]*)/gi;
  
  formatted = formatted.replace(urlPattern, (match) => {
    let href = match;
    
    // Add protocol if missing
    if (!href.match(/^https?:\/\//i)) {
      href = 'http://' + href;
    }
    
    // Truncate display text if too long (keep first 40 chars + ...)
    const displayText = match.length > 50 ? match.substring(0, 47) + '...' : match;
    
    return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="text-blue-400 hover:text-blue-300 underline">${displayText}</a>`;
  });
  
  // Parse WhatsApp formatting patterns
  // Bold: *text* → <strong>text</strong>
  formatted = formatted.replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
  
  // Italic: _text_ → <em>text</em>
  formatted = formatted.replace(/_([^_]+)_/g, '<em>$1</em>');
  
  // Strikethrough: ~text~ → <del>text</del>
  formatted = formatted.replace(/~([^~]+)~/g, '<del>$1</del>');
  
  // Monospace/Code: ```text``` → <code>text</code>
  formatted = formatted.replace(/```([^`]+)```/g, '<code class="bg-slate-900/50 px-1.5 py-0.5 rounded text-xs font-mono">$1</code>');
  
  return formatted;
};

// Format Reaction Text (safely handle null)
const formatReactionText = (text) => {
  if (!text) return '👍'; // Default emoji if text is null/undefined
  return text.replace('Reacted: ', '').replace('Removed reaction', '👎');
};

// Format Last Time for Conversation List (WhatsApp Style)
const formatLastTime = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  const now = new Date();
  
  // Reset time part for accurate date comparison
  const d = new Date(date); d.setHours(0,0,0,0);
  const n = new Date(now); n.setHours(0,0,0,0);
  const y = new Date(n); y.setDate(y.getDate() - 1);
  
  if (d.getTime() === n.getTime()) {
    return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
  } else if (d.getTime() === y.getTime()) {
    return 'Yesterday';
  } else {
    // DD/MM/YY
    return date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: '2-digit' });
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
    return 'Today';
  } else if (msgDate.getTime() === yesterday.getTime()) {
    return 'Yesterday';
  } else {
    return new Date(dateString).toLocaleDateString('id-ID', { 
      day: 'numeric', 
      month: 'long', 
      year: 'numeric' 
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
// --- Helper: Centralized Message Sanitizer ---
// Aggressively cleans duplicates based on ID, WAMID, and Fuzzy Content/Time
const sanitizeMessages = (messages) => {
    if (!Array.isArray(messages)) return [];
    
    // 1. Sort by Time (Robust)
    messages.sort((a, b) => {
        let ta = new Date(a.rawTime || a.time).getTime();
        let tb = new Date(b.rawTime || b.time).getTime();
        
        // Fallback for "10:30 PM" format if Date parse fails
        if (isNaN(ta) && a.time) ta = new Date('1970/01/01 ' + a.time).getTime();
        if (isNaN(tb) && b.time) tb = new Date('1970/01/01 ' + b.time).getTime();
        
        // Final fallback: keep original order (0)
        return (isNaN(ta) ? 0 : ta) - (isNaN(tb) ? 0 : tb);
    });
    
    const uniqueMap = new Map();
    const result = [];
    
    messages.forEach(msg => {
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
             const normalize = (str) => String(str || '').replace(/\s+/g, ' ').trim();
             const msgTime = new Date(msg.rawTime || msg.time).getTime();
             const msgText = normalize(msg.text);
             
             // Look backwards for a fuzzy match (optimisation: only check last 10 messages)
             // We iterate result array which contains 'kept' messages
             for (let i = result.length - 1; i >= 0 && i >= result.length - 10; i--) {
                 const cand = result[i];
                 if (cand.sender === msg.sender && normalize(cand.text) === msgText) {
                     const candTime = new Date(cand.rawTime || cand.time).getTime();
                     if (Math.abs(candTime - msgTime) < 5000) { // 5s window
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
             
             if (msg.status && msg.status !== 'read' && existing.status !== msg.status) {
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
        const response = await fetch(`${API_BASE}/CMS/Chat/getMessages?phone=${phone}&_t=${Date.now()}`);
        const result = await response.json();
        
        if (result.status && Array.isArray(result.data)) {
            const mappedMessages = result.data.map(m => ({
                id: m.id,
                wamid: m.wamid,
                text: m.text || m.caption, 
                type: m.type,
                media_id: m.media_id,
                media_url: m.media_url,
                sender: m.sender, 
                time: m.time ? new Date(m.time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '',
                rawTime: m.time, 
                status: m.status
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
        await fetch(`${API_BASE}/CMS/Chat/markRead`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                phone: phone, // Send phone
                user_id: authId.value // Add sender ID
            })
        });
        
        // Update local state if needed
        const chat = conversations.value.find(c => c.wa_number === phone);
        if(chat) chat.unread = 0;
    } catch (e) {
        console.error("Failed to mark read", e);
    }
};

const markAsDone = async () => {
    if (!activeConversation.value || isMarkingAsDone.value) return;
    
    try {
        isMarkingAsDone.value = true;
        showChatMenu.value = false; // Close menu
        
        const response = await fetch(`${API_BASE}/CMS/Chat/markAsDone`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                phone: activeConversation.value.wa_number,
                user_id: authId.value
            })
        });
        
        const res = await response.json();
        
        if (res.status) {
            // Update local cases
            activeConversation.value.cases = [{case: 0}]; 
            console.log('✓ Conversation marked as done');
            
            // ℹ️ SSE will broadcast update to all other clients automatically!
            // No manual refresh needed - real-time magic! ✨
        } else {
            console.error('Failed to mark as done:', res.message);
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
        
        const response = await fetch(`${API_BASE}/CMS/Chat/updateCase`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                phone: activeConversation.value.wa_number,
                case: 1,
                user_id: authId.value
            })
        });
        
        const res = await response.json();
        
        if (res.status) {
            // Update local cases
            // Update local cases (Append for multi-case)
            if (!activeConversation.value.cases) activeConversation.value.cases = [];
            activeConversation.value.cases = activeConversation.value.cases.filter(c => c.case !== 0); // Remove 'normal'
            if (!activeConversation.value.cases.some(c => c.case === 1)) {
                 activeConversation.value.cases.push({case: 1});
            }
            console.log('✓ Conversation marked for payment check');
        } else {
            console.error('Failed to mark for payment check:', res.message);
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
        
        const response = await fetch(`${API_BASE}/CMS/Chat/updateCase`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                phone: activeConversation.value.wa_number,
                case: 2,
                user_id: authId.value
            })
        });
        
        const res = await response.json();
        
        if (res.status) {
            // Update local cases
            // Update local cases (Append for multi-case)
            if (!activeConversation.value.cases) activeConversation.value.cases = [];
            activeConversation.value.cases = activeConversation.value.cases.filter(c => c.case !== 0);
            if (!activeConversation.value.cases.some(c => c.case === 2)) {
                 activeConversation.value.cases.push({case: 2});
            }
            console.log('✓ Conversation marked for pickup/delivery');
        } else {
            console.error('Failed to mark for pickup/delivery:', res.message);
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
        
        const response = await fetch(`${API_BASE}/CMS/Chat/updateCase`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                phone: activeConversation.value.wa_number,
                case: 3,
                user_id: authId.value
            })
        });
        
        const res = await response.json();
        
        if (res.status) {
            // Update local cases
            // Update local cases (Append for multi-case)
            if (!activeConversation.value.cases) activeConversation.value.cases = [];
            activeConversation.value.cases = activeConversation.value.cases.filter(c => c.case !== 0);
            if (!activeConversation.value.cases.some(c => c.case === 3)) {
                 activeConversation.value.cases.push({case: 3});
            }
            console.log('✓ Conversation marked as request');
        } else {
            console.error('Failed to mark as request:', res.message);
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

const reopenConversation = async () => {
    if (!activeConversation.value || isReopeningConversation.value) return;
    
    try {
        isReopeningConversation.value = true;
        showChatMenu.value = false; // Close menu
        
        const response = await fetch(`${API_BASE}/CMS/Chat/reopenConversation`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                phone: activeConversation.value.wa_number,
                user_id: authId.value
            })
        });
        
        const res = await response.json();
        
        if (res.status) {
            // Update local cases
            activeConversation.value.cases = [{case: 4}];
            console.log('✓ Conversation reopened - needs attention');
        } else {
            console.error('Failed to reopen conversation:', res.message);
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
        const response = await fetch(`${API_BASE}/CMS/Chat/resolveCase`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                phone: activeConversation.value.wa_number,
                case: caseId,
                user_id: authId.value
            })
        });
        const res = await response.json();
        if (res.status) {
             // Optimistic Update: Remove from local list
             if (activeConversation.value.cases) {
                 activeConversation.value.cases = activeConversation.value.cases.filter(x => x.case !== caseId);
             }
             showResolveMenu.value = false;
             console.log('Case resolved:', caseId);
        }
    } catch (e) {
        console.error("Error resolving case:", e);
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
      // If we have cached messages, show them immediately and fetch in background
      if (chat.messages && chat.messages.length > 0) {
          scrollToBottom(); // Show cache immediately
          // Background fetch to sync and merge
          // Background fetch to sync and merge
          fetchMessages(chat.wa_number).then(msgs => {
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
      rawTime: new Date().toISOString(), // Fixed: Add rawTime for proper sorting
      timestamp: Date.now(), // Add timestamp for duplicate detection
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
                phone: activeConversation.value.wa_number, // Use wa_number
                message: text,
                user_id: authId.value // Add sender ID
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

// Handle Image Selection
const selectImage = async (event) => {
  const file = event.target.files[0];
  if (!file) return;
  
  if (!file.type.startsWith('image/')) {
    alert('Please select an image file');
    return;
  }
  
  if (file.size > 10 * 1024 * 1024) {
    alert('Image size must be less than 10MB');
    return;
  }
  
  try {
    // Compress image to ~500KB
    const compressedBlob = await compressImage(file, 500 * 1024); // 500KB target
    
    // Create new File from compressed blob
    const compressedFile = new File([compressedBlob], file.name, {
      type: file.type,
      lastModified: Date.now()
    });
    
    selectedImage.value = compressedFile;
    
    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      imagePreview.value = e.target.result;
      showImagePreview.value = true;
    };
    reader.readAsDataURL(compressedFile);
    
  } catch (err) {
    console.error('Compression error:', err);
    alert('Failed to process image');
  }
  
  event.target.value = '';
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
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
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
          canvas.toBlob((blob) => {
            if (blob.size <= targetSizeBytes || q <= 0.1) {
              resolve(blob);
            } else {
              // Reduce quality and try again
              tryCompress(q - 0.1);
            }
          }, file.type, q);
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
  imagePreview.value = '';
  showImagePreview.value = false;
  imageCaption.value = '';
};

// Handle Paste Event (Windows Screenshot / Clipboard)
const handlePaste = async (event) => {
  // Only allow paste if a chat is active
  if (!activeChatId.value && !showMobileChat.value) return;

  const items = (event.clipboardData || event.originalEvent.clipboardData).items;
  let file = null;

  for (const item of items) {
    if (item.type.indexOf('image') !== -1) {
      file = item.getAsFile();
      break;
    }
  }

  if (!file) return;

  // Prevent default paste behavior
  event.preventDefault();

  if (file.size > 10 * 1024 * 1024) {
    alert('Image size must be less than 10MB');
    return;
  }

  try {
    // Compress image to ~500KB using existing compressImage function
    const compressedBlob = await compressImage(file, 500 * 1024);
    
    // Create new File from compressed blob
    const compressedFile = new File([compressedBlob], "pasted_image_" + Date.now() + ".jpg", {
      type: file.type || 'image/jpeg',
      lastModified: Date.now()
    });
    
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
    console.error('Paste processing error:', err);
    alert('Failed to process pasted image');
  }
};



// Close menu when clicking outside
const handleClickOutside = (event) => {
  if (showChatMenu.value) showChatMenu.value = false;
  if (showResolveMenu.value) showResolveMenu.value = false;
};

onUnmounted(() => {
    window.removeEventListener('paste', handlePaste);
    window.removeEventListener('click', handleClickOutside);
    
    // Clear polling interval
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
    }
});

const sendImage = async () => {
  // ❌ GUARD: Prevent multiple simultaneous sends - CHECK FIRST!
  if (isUploadingImage.value) {
    console.warn('Image upload already in progress, ignoring duplicate call');
    return;
  }
  
  // Validate required data
  if (!selectedImage.value) {
    console.error('No image selected');
    return;
  }
  
  if (!activeConversation.value) {
    console.error('No active conversation');
    return;
  }
  
  // Set guard IMMEDIATELY before any async operations
  isUploadingImage.value = true;
  
  const caption = imageCaption.value.trim();
  
  // ✨ Hide modal immediately for snappy UX
  showImagePreview.value = false;
  
  try {
    const formData = new FormData();
    formData.append('image', selectedImage.value);
    formData.append('phone', activeConversation.value.wa_number); // Use wa_number
    formData.append('user_id', authId.value);
    if (caption) formData.append('caption', caption);
    

    const tempId = Date.now();
    const newMsg = {
      id: tempId,
      text: caption || '', // ✅ Empty string if no caption (not "[Image]")
      type: 'image',
      media_url: imagePreview.value,
      sender: 'me',
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      rawTime: new Date().toISOString(), // FIXED: Add rawTime for proper sorting and date separator
      status: 'pending'
    };
    
    activeConversation.value.messages.push(newMsg);
    activeConversation.value.lastMessage = "You: 📷 Image";
    activeConversation.value.lastTime = newMsg.time;
    
    scrollToBottom();
    
    const response = await fetch(`${API_BASE}/CMS/Chat/sendImage`, {
      method: 'POST',
      body: formData
    });
    
    const res = await response.json();
    
    if (res.status) {
      const sentMsg = activeConversation.value.messages.find(m => m.id === tempId);
      if (sentMsg) {
        sentMsg.status = 'sent';
        if (res.data && res.data.local_id) {
          sentMsg.id = res.data.local_id;
          if (res.data.media_url) sentMsg.media_url = res.data.media_url;
        }
      }
      cancelImage();
    } else {
      const sentMsg = activeConversation.value.messages.find(m => m.id === tempId);
      if (sentMsg) sentMsg.status = 'failed';
      alert('Failed to send image: ' + (res.message || res.error || 'Unknown error'));
    }
  } catch (e) {
    console.error('Send image error:', e);
    const sentMsg = activeConversation.value.messages.find(m => m.id === tempId);
    if (sentMsg) sentMsg.status = 'failed';
    alert('Error: ' + e.message);
  } finally {
    isUploadingImage.value = false;
  }
};

const handleIncomingMessage = (payload) => {
  // DEBUG: Log ALL incoming messages to see what we really get
  console.log('📡 WS Payload:', payload);

  // Check if this is a status update
  if (payload.type === 'status_update') {
      // ... existing status logic ...
      const { conversation_id, message, phone } = payload;
      const conversation = conversations.value.find(c => (conversation_id && c.id == conversation_id) || (phone && c.wa_number == phone));
      
      if (conversation) {
          const msgToUpdate = conversation.messages.find(m => m.id == message.id || m.wamid == message.wamid);
          if (msgToUpdate) {
              msgToUpdate.status = message.status;
          }
      }
      return;
  }

  // Handle priority update (Standard)
  if (payload.type === 'priority_updated') {
      const { phone, priority } = payload;
      console.log('[WebSocket] Received priority_updated:', { phone, priority });
      
      const conversation = conversations.value.find(c => c.wa_number === phone);
      
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
  const type = messageData.type || 'text';
  const sender = messageData.sender || 'customer';
  
  let displayText = text;
  if (!displayText && type !== 'text') {
      displayText = `[${type}]`;
      if (messageData.media_caption) displayText += ' ' + messageData.media_caption; 
  }
  const name = payload.contact_name || payload.name;
  
  // Find or create conversation
  let conversation = conversations.value.find(c => (conversationId && c.id == conversationId) || (phone && c.wa_number == phone));
  
  if (!conversation) {
    // New conversation
    conversation = {
      id: conversationId,
      wa_number: phone, // ✅ Add wa_number
      name: name || payload.phone || 'Unknown User',
      kode_cabang: payload.kode_cabang || '00', // Set from payload
      // priority: parseInt(payload.priority) || 0, // Legacy
      cases: [{case: parseInt(payload.case || payload.priority || 0)}], // Initialize cases
      initials: (name || payload.phone || '?').substring(0, 1).toUpperCase(),
      color: getAvatarColor(conversationId),
      status: 'online', // Assume online on new msg
      messages: [],
      unread: 0
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
      // Update case if provided (IGNORE NULL - Null means "no change", 0 means clear/normal)
      if (payload.case !== undefined && payload.case !== null) {
           conversation.cases = [{case: parseInt(payload.case)}];
      } else if (payload.priority !== undefined && payload.priority !== null) {
           // Fallback payload.priority
           conversation.cases = [{case: parseInt(payload.priority)}];
      }
  }
  
  const newMsg = {
    id: messageData.id || Date.now(),
    text: displayText, // Use the safe display text
    type: type,
    media_id: messageData.media_id,
    media_url: messageData.media_url,
    sender: sender,
    time: messageData.time ? new Date(messageData.time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    rawTime: messageData.time || new Date().toISOString() // Keep raw timestamp for date separator
  };
  
  // DEBUG: Log every incoming message attempt
  console.log('[handleIncomingMessage] Processing:', {
      conversation: conversationId || phone,
      sender: sender,
      text: displayText,
      id: newMsg.id,
      source: 'handleIncomingMessage'
  });
  
  // Avoid duplicate messages if already present
  // Enhanced check: ID match OR (same sender + same text + within 2 seconds)
  const isDuplicate = conversation.messages.find(m => {
      // Exact ID match (string comparison for safety)
      if (String(m.id) === String(newMsg.id)) return true;
      
      // Wamid match
      if (m.wamid && newMsg.wamid && String(m.wamid) === String(newMsg.wamid)) return true;
      
      // Fuzzy match: same sender + same NORMALIZED text + close timestamp
      const normalize = (str) => String(str || '').replace(/\s+/g, ' ').trim();
      
      if (m.sender === newMsg.sender && normalize(m.text) === normalize(newMsg.text)) {
          // Check if timestamps are within 5 seconds of each other
          const time1 = new Date(m.rawTime || m.time).getTime();
          const time2 = new Date(newMsg.rawTime || newMsg.time).getTime();
          
          if (!isNaN(time1) && !isNaN(time2) && Math.abs(time1 - time2) < 5000) {
              console.log('⚠️ Duplicate detected (fuzzy match):', newMsg.id, 'matches existing:', m.id);
              return true;
          }
      }
      
      return false;
  });
  
  if (!isDuplicate) {
      console.log('✓ Adding message to conversation:', newMsg.id);
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
  const isChatVisible = activeChatId.value == conversationId && (windowWidth.value >= 768 || showMobileChat.value);

  if (!isChatVisible) {
    conversation.unread++;
    
    // 🆕 AUTO-OPEN FEATURE: Open conversation automatically for incoming customer messages
    if (autoOpenChatOnIncoming.value && sender === 'customer' && windowWidth.value >= 768) {
      console.log('🔔 Auto-opening conversation:', conversation.name);
      selectChat(conversation.id);
    }
  } else {
    scrollToBottom();
    markMessagesRead(conversation.wa_number); // Use phone number, not conversation ID
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
     // Always connect to Production Server (as per user workflow)
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
         
         // Handle Connection Welcome
         if (payload.type === 'connection') {
             if (payload.role) {
                 currentUserRole.value = payload.role;
                 localStorage.setItem('cms_chat_role', payload.role);
                 console.log('User Role Set:', payload.role);
             }
             return;
         }
         
         if (payload.type === 'status_update') {
             handleIncomingMessage(payload);
             return;
         }

         // Handle Read Receipt Sync
         if (payload.type === 'conversation_read') {
             const conv = conversations.value.find(c => (payload.conversation_id && c.id == payload.conversation_id) || (payload.phone && c.wa_number == payload.phone));
             if (conv) {
                 conv.unread = 0;
             }
             return;
          }

           // Handle Case Update (Replaces Priority Update)
           if (payload.type === 'case_updated') {
               console.log('[WS] case_updated received:', payload);
               
               // Normalize phone number for matching (remove +, spaces, etc)
               const normalizePhone = (phone) => {
                 if (!phone) return '';
                 return phone.toString().replace(/\D/g, ''); // Remove all non-digits
               };
               
               const targetPhone = normalizePhone(payload.phone);
               const conv = conversations.value.find(c => normalizePhone(c.wa_number) === targetPhone);
               
               if (conv) {
                    const newC = parseInt(payload.case);
                    console.log('✓ Updating case for conversation:', conv.name, newC);
                    
                    if (newC === 0) {
                        // Reset if 0 (Done)
                        conv.cases = [{case: 0}];
                    } else {
                        // Append logic
                        if (!conv.cases) conv.cases = [];
                        conv.cases = conv.cases.filter(c => c.case !== 0); // Remove dummy 0
                        if (!conv.cases.some(c => c.case === newC)) {
                             conv.cases.push({case: newC}); 
                        }
                    }
                   // Force re-sort?? Automatically handled by computed filteredConversations
               } else {
                   console.warn('⚠️ Conversation not found for case update:', payload.phone);
               }
               return;
           }

          
           if (payload.type === 'case_resolved') {
               console.log('[WS] case_resolved received:', payload);
               const normalizePhone = (phone) => {
                  if (!phone) return '';
                  return phone.toString().replace(/\D/g, ''); 
               };
               const targetPhone = normalizePhone(payload.phone);
               const conv = conversations.value.find(c => normalizePhone(c.wa_number) === targetPhone);
               
               if (conv && conv.cases) {
                   // Remove resolved case from view
                   const resolvedCase = parseInt(payload.case);
                   conv.cases = conv.cases.filter(x => x.case !== resolvedCase);
               }
               return;
           }

          // Handle Agent Message Sent (from other devices)
          if (payload.type === 'agent_message_sent') {
              const conversationId = payload.conversation_id;
              const messageData = payload.message;
              const senderId = payload.sender_id;
               
              // DEBUG: Log all agent messages for troubleshooting
              console.log('[WS] agent_message_sent:', {
                  conversation: conversationId,
                  sender: senderId,
                  myId: authId.value,
                  text: messageData.text,
                  match: senderId == authId.value
              });
               
              // Skip if this message was sent by current user (use == for type safety)
              // This prevents the duplicate when server echoes our own message back
              if (senderId == authId.value) {
                  console.log('✓ Ignoring self-broadcast (already in optimistic UI)');
                  return;
              }
               
               
              const conversation = conversations.value.find(c => (conversationId && c.id == conversationId) || (payload.phone && c.wa_number == payload.phone));
              if (conversation) {
                   // Enhanced duplicate check: ID, wamid, OR media_url for images
                   const existingMessage = conversation.messages.find(m => 
                      m.id == messageData.id || 
                      (m.wamid && messageData.wamid && m.wamid == messageData.wamid) ||
                      (messageData.type === 'image' && m.media_url && messageData.media_url && m.media_url == messageData.media_url)
                   );
                   
                   if (existingMessage) {
                       // Update existing message (from optimistic UI after API response)
                       existingMessage.id = messageData.id;
                       existingMessage.wamid = messageData.wamid;
                       existingMessage.status = messageData.status || 'sent';
                       if (messageData.media_url) existingMessage.media_url = messageData.media_url;
                       console.log('Updated existing message:', existingMessage.id);
                       // Don't add as new - already exists
                   } else {
                       // NEW DEFENSE: Robust Fuzzy Match
                       // Search backwards for the most recent message from 'me' with same text
                       // This handles race conditions where the order might be slightly off or not the very last item
                       let pendingMatch = null;
                       const cleanIncomingText = (messageData.text || '').trim();
                       
                       // Scan last 5 messages
                       for (let i = conversation.messages.length - 1; i >= 0; i--) {
                           if (conversation.messages.length - i > 5) break; 
                           
                           const m = conversation.messages[i];
                           const cleanLocalText = (m.text || '').trim();
                           
                           // Check match: Sender is me AND text matches
                           if (m.sender === 'me' && cleanLocalText === cleanIncomingText) {
                                // If it's already "read", we probably shouldn't merge (it's old)
                                // But if it's pending, sent, or delivered, it's a candidate
                                if (m.status !== 'read') {
                                    pendingMatch = m;
                                    break;
                                }
                           }
                       }
                       
                       if (pendingMatch) {
                           console.log('Matched duplicate (Fuzzy Refined):', pendingMatch.id);
                           
                           // Update IDs to server values
                           pendingMatch.id = messageData.id; 
                           if (messageData.wamid) pendingMatch.wamid = messageData.wamid;
                           pendingMatch.status = messageData.status || 'sent';
                           if (messageData.media_url) pendingMatch.media_url = messageData.media_url;
                           return; // Stop, don't add new
                       }

                       // Add new message (from another agent/device)
                       const newMsg = {
                           id: messageData.id,
                           wamid: messageData.wamid,
                           text: messageData.text,
                           type: messageData.type || 'text',
                           media_url: messageData.media_url,
                           sender: 'me',
                           time: new Date(messageData.time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                           rawTime: messageData.time,
                           status: messageData.status || 'sent'
                       };
                       
                       conversation.messages.push(newMsg);
                       
                       // Sort messages by rawTime to ensure chronological order
                       conversation.messages.sort((a, b) => {
                           if (!a.rawTime || !b.rawTime) return 0;
                           return new Date(a.rawTime) - new Date(b.rawTime);
                       });
                       
                       conversation.lastMessage = messageData.type === 'image' ? "You: 📷 Image" : "You: " + messageData.text;
                       conversation.lastTime = newMsg.time;
                       
                       console.log('Added new message from other device:', newMsg.id);
                       
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
             console.log('WebSocket disconnected, code:', event.code, 'reason:', event.reason);
             isConnected.value = false;
             
             // Check if disconnected due to connection limit (code 1008)
             if (event.code === 1008) {
                 connectionError.value = '⚠️ Koneksi ditutup: ID ini sudah terkoneksi di tab/device lain';
                 console.warn('Connection closed: Another connection with same ID exists');
                 // Show blocking modal
                 showDuplicateConnectionModal.value = true;
             } else {
                 connectionError.value = '⚠️ WebSocket terputus. Polling backup aktif (update setiap 3 detik)';
             }
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





// --- Persistence DISABLED ---
// localStorage cache removed to ensure 100% accurate data from server
// Data will always be fresh from API

onMounted(() => {
  // Add Paste Listener
  window.addEventListener('paste', handlePaste);
  
  scrollToBottom();
  
  // --- NO CACHE LOADING ---
  // Always fetch fresh data from server for 100% accuracy
  
  // --- VISIBILITY CHANGE HANDLER ---
  // Fix for blank screen/disconnect after long backgrounding
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      console.log('App resumed, checking connection...');
      
      // Check if socket is dead
      if (!socket.value || socket.value.readyState !== WebSocket.OPEN) {
          console.log('Socket disconnected, reconnecting...');
          connectWebSocket();
      }
      
      // Refresh data to ensure sync
      fetchConversations();
      
      // Hard refresh if really stale (optional, but requested solution for "blank")
      // We rely on the view reactivation. 
      // If the WebView completely killed the renderer but kept the process, a reload might be needed.
      // But usually "blank" means the Vue app crashed or memory loss. 
      // We can try to force update a key ref to trigger re-render if needed.
    }
  });
  
  // --- CLICK OUTSIDE HANDLER ---
  // Close menu when clicking anywhere
  window.addEventListener('click', handleClickOutside);
  
  // --- PASTE HANDLER ---
  // Handle pasted images
  window.addEventListener('paste', handlePaste);
  
  // Load font size preference
  loadFontSize();
  
  const storedId = localStorage.getItem('cms_chat_id');
  const storedPass = localStorage.getItem('cms_chat_password');
  const storedExpiry = localStorage.getItem('cms_chat_expiry');
  const storedRole = localStorage.getItem('cms_chat_role');
  const now = new Date().getTime();
  
  if (storedRole) currentUserRole.value = storedRole;
  
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
    authId.value = '';
    authPassword.value = '';
    isConnecting.value = false;
    showLoginPrompt.value = true;
    
    // Clear Session
    localStorage.removeItem('cms_chat_id');
    localStorage.removeItem('cms_chat_password');
    localStorage.removeItem('cms_chat_expiry');
    // Note: conversations cache removed - data always from server
};


// Cleanup on unmount
onUnmounted(() => {
  if (titleBlinkInterval.value) {
    clearInterval(titleBlinkInterval.value);
    document.title = originalTitle;
  }
});

// Stop blinking when window is focused
window.addEventListener('focus', () => {
  if (titleBlinkInterval.value) {
    clearInterval(titleBlinkInterval.value);
    titleBlinkInterval.value = null;
    document.title = originalTitle;
    isTitleRed.value = false;
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
    <aside v-if="authId" class="flex flex-col border-r border-[var(--wa-border)] bg-[var(--wa-bg-panel)] transition-all duration-300 absolute md:static z-0 h-full w-full md:w-96"
           :class="showMobileChat ? 'flex' : 'flex'">
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
                      v-model="searchQuery"
                      type="text" 
                      placeholder="Search or start new chat" 
                      class="block w-full pl-10 pr-10 py-2.5 border border-[var(--wa-border)] rounded-lg leading-5 bg-[var(--wa-bg-secondary)] text-[var(--wa-text-primary)] placeholder-[var(--wa-text-tertiary)] focus:outline-none focus:bg-[var(--wa-bg-tertiary)] focus:border-[var(--wa-accent-green)] focus:ring-0 sm:text-sm transition-all"
                  >
                  <div v-if="searchQuery" class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" @click="searchQuery = ''">
                       <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 hover:text-slate-300 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                       </svg>
                  </div>
              </div>
              
              <!-- Settings Button -->
              <button 
                  @click="showSettingsModal = !showSettingsModal"
                  class="p-2.5 text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)] hover:bg-[var(--wa-hover)] rounded-lg transition-colors"
                  title="Settings"
              >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
              </button>
          </div>
       </div>
       
       <!-- Filter Tabs (WhatsApp Style Pills) -->
       <div class="px-4 py-3 bg-[var(--wa-bg-panel)]">
          <div class="flex gap-2">
             <!-- All Tab -->
             <button 
                @click="conversationFilter = 'all'"
                class="px-3 py-1.5 text-sm font-medium rounded-full transition-all"
                :class="conversationFilter === 'all' 
                   ? 'bg-[var(--wa-accent-green)] text-black' 
                   : 'bg-[var(--wa-bg-tertiary)] text-[var(--wa-text-secondary)] hover:bg-[var(--wa-hover)]'"
             >
                Semua
             </button>
             
             <!-- Unread Tab -->
             <button 
                @click="conversationFilter = 'unread'"
                class="px-3 py-1.5 text-sm font-medium rounded-full transition-all flex items-center gap-1.5"
                :class="conversationFilter === 'unread' 
                   ? 'bg-[var(--wa-accent-green)] text-black' 
                   : 'bg-[var(--wa-bg-tertiary)] text-[var(--wa-text-secondary)] hover:bg-[var(--wa-hover)]'"
             >
                <span>Belum dibaca</span>
                <span 
                   v-if="totalUnreadCount > 0" 
                   class="text-xs font-bold min-w-[20px] h-5 px-1.5 rounded-full flex items-center justify-center"
                   :class="conversationFilter === 'unread' 
                      ? 'bg-black/20 text-black' 
                      : 'bg-[var(--wa-accent-green)] text-black'"
                >
                   {{ totalUnreadCount }}
                </span>
             </button>
          </div>
       </div>
       
      <!-- Conversation List (Pure CSS Shadows) -->
      <div 
         ref="conversationListRef" 
         class="flex-1 overflow-y-auto custom-scrollbar"
      >
        <!-- Skeleton Loading -->
        <div v-if="isLoadingConversations && conversations.length === 0" class="space-y-0">
          <div v-for="n in 8" :key="'skeleton-' + n" class="p-3 flex items-center gap-3 border-b border-[var(--wa-divider)] animate-pulse">
            <!-- Avatar Skeleton -->
            <div class="w-12 h-12 rounded-full bg-[var(--wa-bg-tertiary)]"></div>
            
            <!-- Content Skeleton -->
            <div class="flex-1 space-y-2">
              <div class="flex justify-between items-center">
                <div class="h-4 bg-[var(--wa-bg-tertiary)] rounded w-32"></div>
                <div class="h-3 bg-[var(--wa-bg-tertiary)] rounded w-12"></div>
              </div>
              <div class="h-3 bg-[var(--wa-bg-tertiary)] rounded w-48"></div>
            </div>
          </div>
        </div>
        
        <!-- Actual Conversations -->
        <div 
          v-for="chat in filteredConversations"  
          :key="chat.id"
          @click="selectChat(chat.id)"
          class="p-3 flex items-center gap-3 cursor-pointer transition-colors duration-150 border-b border-[var(--wa-divider)] hover:bg-[var(--wa-hover)]"
          :class="{
            'bg-[var(--wa-active)]': activeChatId === chat.id
          }"
        >
           <div class="relative">
             <div 
               class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold border-0"
               :style="{ backgroundColor: chat.color }"
             >
               {{ chat.initials }}
             </div>
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
              <div v-if="chat.cases && chat.cases.some(c => c.case > 0 && (c.status || 'open') !== 'closed')" class="flex flex-wrap gap-1.5 mb-1.5">
                  <template v-for="(cse, idx) in chat.cases" :key="idx">
                      <div 
                        v-if="cse.case > 0 && (cse.status || 'open') !== 'closed'"
                        class="w-2.5 h-2.5 rounded-full ring-1 ring-black/20"
                        :class="getCaseColor(cse.case)"
                        :title="'Case: ' + cse.case"
                      ></div>
                  </template>
              </div>
             <div class="flex justify-between items-center">
                <p class="text-sm text-[var(--wa-text-secondary)] truncate w-64" :class="{'font-normal text-[var(--wa-text-primary)]': chat.unread > 0}">{{ chat.lastMessage }}</p>
                <span v-if="chat.unread > 0" class="bg-[var(--wa-accent-green)] text-black text-[11px] font-semibold px-2 py-0.5 rounded-full min-w-[20px] text-center">
                  {{ chat.unread }}
                </span>
             </div>
           </div>
        </div>
      </div>
      
      <!-- User Profile (Self) -->
      <div class="p-4 border-t border-[var(--wa-border)] bg-[var(--wa-bg-panel)] flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-[var(--wa-accent-green)] flex items-center justify-center text-black font-bold border-0">
               A
            </div>
            <div>
               <div class="text-sm font-medium text-[var(--wa-text-primary)]">MDL Agent <span class="text-[var(--wa-accent-blue)] font-mono">#{{ authId }}</span></div>
               <div class="text-xs text-[var(--wa-accent-green)] flex items-center gap-1">
                 <span class="w-1.5 h-1.5 rounded-full bg-[var(--wa-accent-green)]"></span> Online
               </div>
            </div>
        </div>
        
        <button 
           @click="logout" 
           title="Logout"
           class="p-2 text-[var(--wa-icon-default)] hover:text-red-400 hover:bg-[var(--wa-hover)] rounded-lg transition-colors"
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
        class="flex flex-col bg-[var(--wa-bg-chat)] h-full overflow-x-hidden"
        :class="{
            'fixed inset-0 z-50 w-full': showMobileChat,
            'hidden': !showMobileChat && windowWidth < 768,
            'fixed top-0 right-0 bottom-0 md:left-96 z-0 !w-auto': windowWidth >= 768
        }"
        :style="{ 
            transform: showMobileChat && windowWidth < 768 ? `translateX(${touchOffset}px)` : '',
            transition: isDragging ? 'none' : 'transform 0.3s ease-out'
        }"
        @touchstart="handleTouchStart"
        @touchmove="handleTouchMove"
        @touchend="handleTouchEnd"
    >

       <!-- Background Pattern (WhatsApp style) -->
       <div class="absolute inset-0 opacity-[0.03] pointer-events-none" 
            style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9IndhLWJnIiB4PSIwIiB5PSIwIiB3aWR0aD0iODAiIGhlaWdodD0iODAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiPjxwYXRoIGQ9Ik0wIDIwIEwgMjAgMCBMIDQwIDIwIEwgNjAgMCBMIDgwIDIwIiBzdHJva2U9IiNhZWJhYzEiIHN0cm9rZS13aWR0aD0iMC41IiBmaWxsPSJub25lIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI3dhLWJnKSIvPjwvc3ZnPg=='); background-size: 80px 80px;">
       </div>

      <div v-if="activeConversation" class="w-full h-full relative z-10">
        <!-- Chat Header - ABSOLUTE TOP -->
        <header 
          class="absolute top-0 left-0 right-0 h-16 border-b flex items-center justify-between px-4 md:px-6 z-30 border-[var(--wa-border)] bg-[var(--wa-bg-panel)]"
        >
          <div class="flex items-center gap-3 flex-1 min-w-0">
             <!-- Back Button (Mobile Only) -->
             <button @click="backToMenu" class="md:hidden p-1 -ml-2 text-[var(--wa-icon-default)] hover:text-[var(--wa-text-primary)] flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
             </button>
             
             <div 
               class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold border-0 flex-shrink-0"
               :style="{ backgroundColor: activeConversation.color }"
             >
               {{ activeConversation.initials }}
             </div>
             
             <div class="min-w-0 flex-1">
               <h2 class="font-medium text-[var(--wa-text-primary)] text-base md:text-lg truncate uppercase" :title="activeConversation.name">{{ activeConversation.name }}</h2>
               <div class="flex items-center gap-2">
                 <p v-if="activeConversation.kode_cabang" class="text-xs font-mono text-[var(--wa-text-secondary)]">{{ activeConversation.kode_cabang }}</p>
                 <!-- Header Case Badges -->
                 <div v-if="activeConversation.cases && activeConversation.cases.some(c => c.case > 0 && (c.status || 'open') !== 'closed')" class="flex gap-1">
                    <template v-for="(cse, idx) in activeConversation.cases" :key="idx">
                        <div 
                            v-if="cse.case > 0 && (cse.status || 'open') !== 'closed'"
                            class="w-3 h-3 rounded-full ring-1 ring-black/20"
                            :class="getCaseColor(cse.case)"
                            :title="'Case: ' + cse.case"
                        ></div>
                    </template>
                 </div>
               </div>
             </div>
          </div>
          
          
          <div class="flex items-center gap-2 text-[var(--wa-icon-default)] flex-shrink-0 relative">
             <!-- Resolve Case Button -->
             <div class="relative"> 
                 <button 
                    v-if="resolveableCases.length > 0"
                    @click.stop="showResolveMenu = !showResolveMenu; showChatMenu = false"
                    class="hover:text-[var(--wa-text-primary)] transition-colors p-2 rounded-full hover:bg-[var(--wa-hover)] text-green-500"
                    title="Resolve Cases"
                 >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                 </button>
                 
                 <!-- Resolve Menu Dropdown -->
                 <div v-if="showResolveMenu" class="absolute right-0 top-full mt-2 w-56 bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-lg shadow-xl overflow-hidden z-20">
                    <button 
                        v-for="c in resolveableCases" 
                        :key="c.case"
                        @click="resolveCase(c.case)"
                        class="w-full px-4 py-3 text-left hover:bg-[var(--wa-hover)] transition-colors flex items-center gap-3 text-sm text-[var(--wa-text-primary)] border-b border-[var(--wa-divider)] last:border-0"
                        :class="{
                            'hover:text-blue-400': c.case === 1,
                            'hover:text-yellow-400': c.case === 2,
                            'hover:text-red-400': c.case === 3,
                            'hover:text-purple-400': c.case === 4
                        }"
                    >
                        <!-- Generic Checklist Icon (Inherits Hover Color) -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        
                        <span>
                            {{ getCaseLabel(c.case) }}
                        </span>
                    </button>
                 </div>
             </div>
              <!-- Three Dots Menu Button - ACTIVE -->
              <div class="relative">
                <button 
                  @click.stop="showChatMenu = !showChatMenu; showResolveMenu = false"
                  class="hover:text-[var(--wa-text-primary)] transition-colors p-2 rounded-full hover:bg-[var(--wa-hover)]"
                  :class="{'bg-[var(--wa-hover)] text-[var(--wa-text-primary)]': showChatMenu}"
                  title="Manage Cases"
                >
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                   </svg>
               </button>
               
               <!-- Dropdown Menu -->
               <div 
                 v-if="showChatMenu" 
                 @click.stop
                 class="absolute right-0 top-full mt-2 w-48 bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-lg shadow-xl overflow-hidden z-50"
               >
                 <!-- Selesai Option -->
                 <!-- Check Payment Option -->
                 <button 
                   v-if="!isCaseOpen(1)"
                   @click="checkPayment"
                   :disabled="isCheckingPayment"
                   class="w-full px-4 py-3 text-left hover:bg-[var(--wa-hover)] transition-colors flex items-center gap-3 text-sm text-[var(--wa-text-primary)] hover:text-blue-400 disabled:opacity-50 disabled:cursor-not-allowed border-b border-[var(--wa-divider)]"
                 >
                   <svg v-if="!isCheckingPayment" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                   </svg>
                   <span v-if="isCheckingPayment" class="w-5 h-5 border-2 border-slate-400 border-t-white rounded-full animate-spin"></span>
                   <span>{{ isCheckingPayment ? 'Memproses...' : 'Check Payment' }}</span>
                  </button>
                  <!-- Pickup/Delivery Option -->
                  <button 
                    v-if="!isCaseOpen(2)"
                    @click="pickupDelivery"
                    :disabled="isPickupDelivery"
                    class="w-full px-4 py-3 text-left hover:bg-[var(--wa-hover)] transition-colors flex items-center gap-3 text-sm text-[var(--wa-text-primary)] hover:text-yellow-400 disabled:opacity-50 disabled:cursor-not-allowed border-b border-[var(--wa-divider)]"
                  >
                    <svg v-if="!isPickupDelivery" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    <span v-if="isPickupDelivery" class="w-5 h-5 border-2 border-slate-400 border-t-white rounded-full animate-spin"></span>
                    <span>{{ isPickupDelivery ? 'Memproses...' : 'Pickup/Delivery' }}</span>
                  </button>
                  
                  <!-- Request Option -->
                  <button 
                    v-if="!isCaseOpen(3)"
                    @click="requestPriority"
                    :disabled="isRequest"
                    class="w-full px-4 py-3 text-left hover:bg-[var(--wa-hover)] transition-colors flex items-center gap-3 text-sm text-[var(--wa-text-primary)] hover:text-red-400 disabled:opacity-50 disabled:cursor-not-allowed border-b border-[var(--wa-divider)]"
                  >
                    <svg v-if="!isRequest" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <span v-if="isRequest" class="w-5 h-5 border-2 border-slate-400 border-t-white rounded-full animate-spin"></span>
                    <span>{{ isRequest ? 'Memproses...' : 'Request' }}</span>
                  </button>
                  
                  <!-- Selesai Option -->
                 <button id="btn-selesai" 
                   v-if="activeConversation.priority > 0"
                   @click="markAsDone"
                   :disabled="isMarkingAsDone"
                   class="w-full px-4 py-3 text-left hover:bg-[var(--wa-hover)] transition-colors flex items-center gap-3 text-sm text-[var(--wa-text-primary)] hover:text-green-400 disabled:opacity-50 disabled:cursor-not-allowed"
                 >
                   <svg v-if="!isMarkingAsDone" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                   </svg>
                   <span v-if="isMarkingAsDone" class="w-5 h-5 border-2 border-slate-400 border-t-white rounded-full animate-spin"></span>
                   <span>{{ isMarkingAsDone ? 'Memproses...' : 'Selesai' }}</span>
                 </button>
                 
                  <!-- Reopen Option (Review Removed) -->
               </div>
             </div>
          </div>
        </header>


        
        <!-- Messages - Scrollable Area with top and bottom padding -->
        <div 
          ref="chatContainer"
          class="absolute inset-0 pt-16 pb-[88px] overflow-y-auto custom-scrollbar"
        >
          <div class="p-4 space-y-2">
            <div v-for="(msg, index) in activeConversation.messages" :key="msg.id" :id="'msg-' + msg.id" class="flex flex-col group relative">
            
               <!-- Date Separator -->
               <div v-if="index === 0 || needsDateSeparator(msg, activeConversation.messages[index - 1])" class="flex items-center justify-center my-4">
                  <div class="bg-[var(--wa-bg-panel)] text-[var(--wa-text-secondary)] text-xs font-normal px-3 py-1 rounded-lg shadow-sm">
                     {{ formatDateSeparator(msg.rawTime) }}
                  </div>
               </div>
               
               <!-- Customer Message -->
               <div v-if="msg.sender !== 'me'" class="flex gap-3 max-w-[75%] items-end">
                  <div 
                    v-if="index === 0 || activeConversation.messages[index-1]?.sender === 'me'" 
                    class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] text-white font-bold mb-1 flex-shrink-0"
                    :style="{ backgroundColor: activeConversation.color }"
                  >
                    {{ activeConversation.initials }}
                  </div>
                  <div v-else class="w-8"></div> <!-- Spacer -->
                  
                  <!-- Image Message: Transparent style -->
                  <div v-if="msg.type === 'image'" class="rounded-lg overflow-hidden shadow-md max-w-[240px] bg-[var(--wa-bubble-incoming)]/50">
                     <div class="relative">
                        <img v-if="msg.media_url" :src="msg.media_url" class="w-full cursor-pointer" onclick="window.open(this.src)">
                        <img v-else-if="msg.media_id" :src="`${API_BASE}/CMS/Chat/media?id=${msg.media_id}`" class="w-full cursor-pointer" onclick="window.open(this.src)">
                        <div v-else class="bg-slate-900/50 flex flex-col items-center justify-center w-full h-[150px]">
                           <span class="text-[10px] text-slate-400">Image (Protected)</span>
                        </div>
                        <!-- Caption & Time Overlay -->
                        <div v-if="msg.text || msg.time" class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-2">
                           <p v-if="msg.text" class="text-white text-[13px] leading-tight mb-1" v-html="parseWhatsAppFormatting(msg.text)"></p>
                           <span class="text-[10px] text-white/80 block text-right">{{ msg.time }}</span>
                        </div>
                     </div>
                  </div>
                  
                  <!-- Sticker Message -->
                  <div v-else-if="msg.type === 'sticker'" class="rounded-lg overflow-hidden max-w-[150px]">
                     <img v-if="msg.media_url" :src="msg.media_url" class="w-full cursor-pointer" onclick="window.open(this.src)" style="background: transparent;">
                     <img v-else-if="msg.media_id" :src="`${API_BASE}/CMS/Chat/media?id=${msg.media_id}`" class="w-full cursor-pointer" onclick="window.open(this.src)" style="background: transparent;">
                     <div v-else class="bg-[var(--wa-bubble-incoming)] px-3 py-2 rounded-lg">
                        <span class="text-2xl">🎨</span>
                        <span class="text-[11px] text-[var(--wa-text-tertiary)] block mt-1">{{ msg.time }}</span>
                     </div>
                  </div>
                  
                  <!-- Reaction Message -->
                  <div v-else-if="msg.type === 'reaction'" class="bg-[var(--wa-bubble-incoming)]/50 px-3 py-2 rounded-full shadow-sm flex items-center gap-2">
                     <span class="text-xl">{{ formatReactionText(msg.text) }}</span>
                     <span class="text-[10px] text-[var(--wa-text-tertiary)]">{{ msg.time }}</span>
                  </div>
                  
                  <!-- Text Message: Normal style -->
                  <div v-else class="bg-[var(--wa-bubble-incoming)] text-[var(--wa-text-primary)] px-3 py-2 rounded-lg rounded-tl-none shadow-sm max-w-full">
                     <p v-if="msg.text" class="leading-relaxed break-words whitespace-pre-wrap" v-html="parseWhatsAppFormatting(msg.text)" :style="{ fontSize: messageFontSize }"></p>
                     <span class="text-[11px] text-[var(--wa-text-tertiary)] block mt-1 text-right">{{ msg.time }}</span>
                  </div>
               </div>
               
               <!-- My Message -->
               <div v-else class="flex gap-3 max-w-[75%] self-end items-end justify-end">
                  <!-- Image Message: Transparent style -->
                  <div v-if="msg.type === 'image'" class="rounded-lg overflow-hidden shadow-md max-w-[240px] bg-[var(--wa-bubble-outgoing)]/50">
                     <div class="relative">
                        <img v-if="msg.media_url" :src="msg.media_url" class="w-full cursor-pointer" onclick="window.open(this.src)">
                        <img v-else-if="msg.media_id" :src="`${API_BASE}/CMS/Chat/media?id=${msg.media_id}`" class="w-full cursor-pointer" onclick="window.open(this.src)">
                        <!-- Caption, Time & Status Overlay -->
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-2">
                           <p v-if="msg.text" class="text-white text-[13px] leading-tight mb-1" v-html="parseWhatsAppFormatting(msg.text)"></p>
                           <div class="flex items-center justify-end gap-1">
                              <span class="text-[10px] text-white/90">{{ msg.time }}</span>
                              <!-- Status Icons -->
                              <span v-if="msg.status === 'pending'" class="text-white/70">
                                 <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                              </span>
                              <span v-else-if="msg.status === 'sent'" class="text-white/80">
                                 <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                              </span>
                              <span v-else-if="msg.status === 'delivered'" class="text-white/80">
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
                  
                  <!-- Sticker Message -->
                  <div v-else-if="msg.type === 'sticker'" class="rounded-lg overflow-hidden max-w-[150px]">
                     <img v-if="msg.media_url" :src="msg.media_url" class="w-full cursor-pointer" onclick="window.open(this.src)" style="background: transparent;">
                     <img v-else-if="msg.media_id" :src="`${API_BASE}/CMS/Chat/media?id=${msg.media_id}`" class="w-full cursor-pointer" onclick="window.open(this.src)" style="background: transparent;">
                     <div v-else class="bg-[var(--wa-bubble-outgoing)] px-3 py-2 rounded-lg">
                        <span class="text-2xl">🎨</span>
                        <span class="text-[11px] text-white/70 block mt-1">{{ msg.time }}</span>
                     </div>
                  </div>
                  
                  <!-- Reaction Message (rarely sent by agents, but supported) -->
                  <div v-else-if="msg.type === 'reaction'" class="bg-[var(--wa-bubble-outgoing)]/50 px-3 py-2 rounded-full shadow-sm flex items-center gap-2">
                     <span class="text-xl">{{ formatReactionText(msg.text) }}</span>
                     <span class="text-[10px] text-white/70">{{ msg.time }}</span>
                  </div>
                  
                  <!-- Text Message: Normal style -->
                  <div v-else class="bg-[var(--wa-bubble-outgoing)] text-white px-3 py-2 rounded-lg rounded-tr-none shadow-sm max-w-full">
                     <p v-if="msg.text" class="leading-relaxed break-words whitespace-pre-wrap" v-html="parseWhatsAppFormatting(msg.text)" :style="{ fontSize: messageFontSize }"></p>
                       <div class="flex items-center justify-end gap-1 mt-1">
                          <span class="text-[11px] text-white/70">{{ msg.time }}</span>
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
        </div>
        
        <!-- Input Area - ABSOLUTE BOTTOM -->
        <div class="absolute bottom-0 left-0 right-0 p-4 bg-[var(--wa-bg-panel)] border-t border-[var(--wa-border)] z-30">
           
           <!-- Case 1: Conversation Closed -->
           <div v-if="activeConversation.status === 'closed'" class="flex items-center justify-center gap-2 p-2 bg-[var(--wa-bg-tertiary)] rounded-lg border border-[var(--wa-border)]">
               <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--wa-text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
               </svg>
               <span class="text-[var(--wa-text-secondary)] text-xs font-medium">CSW Closed</span>
           </div>

           <!-- Case 2: Active Chat Input -->
           <div v-else>
               <!-- Image Preview Modal -->
               <div v-if="showImagePreview" class="absolute bottom-full left-4 right-4 mb-2 bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-xl p-4 shadow-2xl">
                  <div class="flex flex-col gap-3">
                     <!-- Image with close button -->
                     <div class="relative mx-auto">
                        <img :src="imagePreview" alt="Preview" class="w-48 h-48 object-cover rounded-lg border border-slate-600">
                        <button @click="cancelImage" class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1.5 shadow-lg">
                           <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                           </svg>
                        </button>
                     </div>
                     
                     <!-- Caption and Send Button Below -->
                     <div class="flex flex-col gap-2">
                        <input 
                           v-model="imageCaption" 
                           type="text" 
                           placeholder="Add a caption (optional)..."
                           class="bg-[var(--wa-bg-secondary)] border border-[var(--wa-border)] rounded-lg px-3 py-2 text-sm text-[var(--wa-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--wa-accent-green)]"
                        >
                        <button 
                           @click="sendImage" 
                           :disabled="isUploadingImage"
                           class="bg-[var(--wa-accent-green)] hover:bg-[#00a884]/90 text-black px-4 py-2.5 rounded-lg text-sm font-medium flex items-center justify-center gap-2 disabled:opacity-50"
                        >
                           <span v-if="isUploadingImage" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                           {{ isUploadingImage ? 'Sending...' : 'Send Image' }}
                        </button>
                     </div>
                  </div>
               </div>
               
               <div class="flex gap-3 items-end bg-[var(--wa-bg-secondary)] p-2 rounded-lg border border-[var(--wa-border)] focus-within:ring-0 focus-within:border-[var(--wa-accent-green)] transition-all">
                  <input 
                     type="file" 
                     ref="fileInput" 
                     @change="selectImage" 
                     accept="image/*" 
                     class="hidden"
                  >
                  
                  <button @click="fileInput.click()" class="p-2 text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)] transition-colors">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                  </button>
                  
                  <textarea 
                    v-model="messageInput"
                    @keydown.enter.prevent="sendMessage"
                    placeholder="Type a message" 
                    class="flex-1 bg-transparent text-[var(--wa-text-primary)] placeholder:text-[var(--wa-text-tertiary)] focus:outline-none resize-none py-2 max-h-32 text-sm"
                    rows="1"
                  ></textarea>
                  
                  <button @click="sendMessage" class="p-2 bg-[var(--wa-accent-green)] hover:bg-[#00a884]/90 text-black rounded-full transition-colors">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                  </button>
               </div>
           </div>
        </div>
        
      </div>
      
      <div v-else class="w-full h-full relative z-10 flex flex-col">
         <div class="flex-1 flex flex-col items-center justify-center text-[var(--wa-text-tertiary)] w-full h-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mb-4 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <p class="text-lg text-[var(--wa-text-secondary)]">Select a conversation to start chatting</p>
         </div>
      </div>
      
      
    </main>
    <!-- Exit Toast -->
    <div v-if="showExitToast" class="fixed bottom-8 left-1/2 transform -translate-x-1/2 bg-slate-800/90 backdrop-blur text-white px-6 py-3 rounded-full shadow-xl border border-slate-700/50 z-[100] transition-opacity duration-300 pointer-events-none">
        <span class="text-sm font-medium">Press back again to exit</span>
    </div>

    <!-- Settings Modal -->
    <div v-if="showSettingsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[500] flex items-center justify-center p-4" @click="showSettingsModal = false">
      <div class="bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-2xl shadow-2xl max-w-md w-full p-6" @click.stop>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-[var(--wa-text-primary)]">Settings</h2>
          <button @click="showSettingsModal = false" class="p-2 text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)] hover:bg-[var(--wa-hover)] rounded-lg transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <!-- Font Size Setting -->
        <div class="space-y-4">
          <div>
            <h3 class="text-sm font-medium text-[var(--wa-text-secondary)] mb-3">Font Size</h3>
            <div class="space-y-2">
              <!-- Medium Option (Default) -->
              <button 
                @click="setFontSize('medium')"
                class="w-full flex items-center justify-between p-3 rounded-lg border transition-all"
                :class="fontSize === 'medium' ? 'border-[var(--wa-accent-green)] bg-[var(--wa-accent-green)]/10' : 'border-[var(--wa-border)] hover:bg-[var(--wa-hover)]'"
              >
                <span class="text-[var(--wa-text-primary)]" style="font-size: 14.2px;">Medium (Default)</span>
                <svg v-if="fontSize === 'medium'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--wa-accent-green)]" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </button>
              
              <!-- Large Option -->
              <button 
                @click="setFontSize('large')"
                class="w-full flex items-center justify-between p-3 rounded-lg border transition-all"
                :class="fontSize === 'large' ? 'border-[var(--wa-accent-green)] bg-[var(--wa-accent-green)]/10' : 'border-[var(--wa-border)] hover:bg-[var(--wa-hover)]'"
              >
                <span class="text-[var(--wa-text-primary)]" style="font-size: 16px;">Large</span>
                <svg v-if="fontSize === 'large'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--wa-accent-green)]" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Duplicate Connection Modal (Blocking) -->
    <div v-if="showDuplicateConnectionModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[999] flex items-center justify-center p-4">
      <div class="bg-gradient-to-br from-red-950/90 to-red-900/80 border-2 border-red-600 rounded-2xl shadow-2xl max-w-md w-full p-8 text-center animate-bounce-in">
        <!-- Alert Icon -->
        <div class="mx-auto w-20 h-20 bg-red-500/20 rounded-full flex items-center justify-center mb-6 animate-pulse">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        
        <!-- Title -->
        <h2 class="text-2xl font-bold text-white mb-3">Koneksi Duplikat Terdeteksi</h2>
        
        <!-- Message -->
        <p class="text-red-200 mb-2 leading-relaxed">
          ID Anda (<strong>{{ authId }}</strong>) sudah terkoneksi di tab atau device lain.
        </p>
        <p class="text-red-300/80 text-sm mb-6">
          Hanya 1 koneksi aktif per ID yang diizinkan. Silakan logout dari device lain atau reload tab ini.
        </p>
        
        <!-- Action -->
        <button 
          @click="handleDuplicateConnection" 
          class="w-full bg-red-600 hover:bg-red-500 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105"
        >
          Logout & Reload
        </button>
      </div>
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
  font-family: 'Courier New', monospace;
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
</style>
