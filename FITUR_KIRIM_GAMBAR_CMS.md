# 📸 Fitur Kirim Gambar di CMS Chat - Like WhatsApp

## ✨ Fitur yang Dibangun:

1. ✅ **Button Attach** yang berfungsi (paperclip icon)
2. ✅ **File picker** untuk pilih gambar
3. ✅ **Image preview** dengan:
   - Preview thumbnail besar
   - Button X untuk cancel
   - Input caption (optional)
   - Button "Send Image"
4. ✅ **Upload & Send** ke WhatsApp API
5. ✅ **Optimistic UI** - gambar langsung muncul di chat
6. ✅ **Real-time broadcast** via WebSocket
7. ✅ **Validation** - type & size (max 5MB)

---

## 📁 File yang Perlu Dimodifikasi:

### 1. **Frontend - `frontend/cms/src/App.vue`**

Ikuti instruksi di file: **`frontend/cms/IMAGE_UPLOAD_GUIDE.js`**

**Summary:**
- Add state variables (line ~26)
- Add functions (line ~297)
- Replace/update input area template (line ~992-1006)

### 2. **Backend - `api/app/Controllers/CMS/Chat.php`**

Copy method `sendImage()` dari file: **`api/CMS_CHAT_SENDIMAGE_ENDPOINT.php`**

Tambahkan method-method ini ke class Chat:
- `sendImage()` - main endpoint
- `uploadImage()` - private helper
- `broadcastToWebSocket()` - private helper

### 3. **Backend - `api/app/Helpers/WhatsAppService.php`**

Copy method `sendImage()` dari file: **`api/WHATSAPP_SENDIMAGE_METHOD.php`**

Tambahkan ke class WhatsAppService (setelah sendFreeText method).

---

## 🎨 UI Preview yang Akan Terlihat:

```
┌──────────────────────────────────────┐
│  [Preview Image]     [Caption Input] │
│    [x]               [Send Image]    │
└──────────────────────────────────────┘
┌──────────────────────────────────────┐
│ [📎] [Type message...        ] [➤]  │
└──────────────────────────────────────┘
```

**Features:**
- Click 📎 → File picker opens
- Select image → Preview shows
- Add caption (optional)
- Click "Send Image" → Uploads & sends

---

## 🔧 Step-by-Step Implementation:

### **STEP 1: Frontend - Add State Variables**

Buka `frontend/cms/src/App.vue`

Cari line ~26 (setelah `let lastBackPress = 0;`), tambahkan:

```javascript
// Image Upload State
const selectedImage = ref(null);
const imagePreview = ref('');
const showImagePreview = ref(false);
const isUploadingImage = ref(false);
const imageCaption = ref('');
const fileInput = ref(null);
```

### **STEP 2: Frontend - Add Functions**

Cari line ~297 (setelah function `sendMessage`), tambahkan:

```javascript
// Handle Image Selection
const selectImage = (event) => {
  const file = event.target.files[0];
  if (!file) return;
  
  if (!file.type.startsWith('image/')) {
    alert('Please select an image file');
    return;
  }
  
  if (file.size > 5 * 1024 * 1024) {
    alert('Image size must be less than 5MB');
    return;
  }
  
  selectedImage.value = file;
  
  const reader = new FileReader();
  reader.onload = (e) => {
    imagePreview.value = e.target.result;
    showImagePreview.value = true;
  };
  reader.readAsDataURL(file);
  
  event.target.value = '';
};

const cancelImage = () => {
  selectedImage.value = null;
  imagePreview.value = '';
  showImagePreview.value = false;
  imageCaption.value = '';
};

const sendImage = async (caption = '') => {
  if (!selectedImage.value || !activeConversation.value) return;
  
  isUploadingImage.value = true;
  
  try {
    const formData = new FormData();
    formData.append('image', selectedImage.value);
    formData.append('conversation_id', activeConversation.value.id);
    formData.append('user_id', authId.value);
    if (caption) formData.append('caption', caption);
    
    const tempId = Date.now();
    const newMsg = {
      id: tempId,
      text: caption || '[Image]',
      type: 'image',
      media_url: imagePreview.value,
      sender: 'me',
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
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
      alert('Failed to send image: ' + (res.message || 'Unknown error'));
    }
  } catch (e) {
    console.error('Send image error:', e);
    alert('Failed to send image');
  } finally {
    isUploadingImage.value = false;
  }
};
```

### **STEP 3: Frontend - Update Template**

Cari bagian Input Area (line ~990-1006), REPLACE dengan:

```vue
<!-- Input Area - ABSOLUTE BOTTOM -->
<div class="absolute bottom-0 left-0 right-0 p-4 bg-[#0f172a] border-t border-slate-800 z-30">
   <!-- Image Preview Modal -->
   <div v-if="showImagePreview" class="absolute bottom-full left-0 right-0 mb-2 bg-[#1e293b] border border-slate-700 rounded-xl p-4 shadow-2xl">
      <div class="flex items-start gap-3">
         <div class="relative flex-shrink-0">
            <img :src="imagePreview" alt="Preview" class="w-32 h-32 object-cover rounded-lg border border-slate-600">
            <button @click="cancelImage" class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1 shadow-lg">
               <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
               </svg>
            </button>
         </div>
         
         <div class="flex-1 flex flex-col gap-2">
            <input 
               v-model="imageCaption" 
               type="text" 
               placeholder="Add a caption (optional)..."
               class="bg-[#0f172a] border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
            <button 
               @click="sendImage(imageCaption || '')" 
               :disabled="isUploadingImage"
               class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center justify-center gap-2 disabled:opacity-50"
            >
               <span v-if="isUploadingImage" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
               {{ isUploadingImage ? 'Sending...' : 'Send Image' }}
            </button>
         </div>
      </div>
   </div>
   
   <div class="flex gap-3 items-end bg-[#1e293b] p-2 rounded-xl border border-slate-700 focus-within:ring-2 focus-within:ring-indigo-500/50 focus-within:border-indigo-500 transition-all">
      <input 
         type="file" 
         ref="fileInput" 
         @change="selectImage" 
         accept="image/*" 
         class="hidden"
      >
      
      <button @click="$refs.fileInput.click()" class="p-2 text-slate-400 hover:text-indigo-400 transition-colors">
         <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
         </svg>
      </button>
      
      <textarea 
        v-model="messageInput"
        @keydown.enter.prevent="sendMessage"
        placeholder="Type your message..." 
        class="flex-1 bg-transparent text-slate-200 placeholder:text-slate-500 focus:outline-none resize-none py-2 max-h-32 text-sm"
        rows="1"
      ></textarea>
      
      <button @click="sendMessage" class="p-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors shadow-lg shadow-indigo-600/30">
         <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
         </svg>
      </button>
   </div>
</div>
```

### **STEP 4: Backend - Chat.php**

Buka `api/app/Controllers/CMS/Chat.php`

Tambahkan 3 method di akhir class (copy dari `api/CMS_CHAT_SENDIMAGE_ENDPOINT.php`):
1. `public function sendImage()`
2. `private function uploadImage($file)`
3. `private function broadcastToWebSocket($data)`

### **STEP 5: Backend - WhatsAppService.php**

Buka `api/app/Helpers/WhatsAppService.php`

Tambahkan method `sendImage()` (copy dari `api/WHATSAPP_SENDIMAGE_METHOD.php`)

### **STEP 6: Create Upload Directory**

```bash
mkdir -p api/uploads/wa_media
chmod 755 api/uploads/wa_media
```

---

## 🧪 Testing:

1. **Open CMS Chat**
2. **Click paperclip icon** (📎)
3. **Select an image** (JPG/PNG, max 5MB)
4. **See preview** appear above input
5. **Add caption** (optional)
6. **Click "Send Image"**
7. **Image appears** in chat immediately (optimistic UI)
8. **Status changes** to ✓ when sent
9. **Customer receives** image on WhatsApp

---

## 🎉 Features:

✅ **Upload validation** - type & size
✅ **Beautiful preview** - like WhatsApp
✅ **Caption support** - optional text
✅ **Optimistic UI** - instant feedback
✅ **Error handling** - failed state
✅ **Real-time sync** - WebSocket broadcast
✅ **Mobile responsive** - works on all devices

---

## 📂 Files Created:

1. `frontend/cms/IMAGE_UPLOAD_GUIDE.js` - Step-by-step frontend guide
2. `api/CMS_CHAT_SENDIMAGE_ENDPOINT.php` - Backend endpoint code
3. `api/WHATSAPP_SENDIMAGE_METHOD.php` - WhatsApp API method

---

**Happy Coding! 🚀**
