import { defineStore } from 'pinia'

/**
 * PINIA NAVIGATION STORE (INTI SISTEM)
 * 
 * Pure WebView approach: Android can kill WebView, but cannot determine navigation.
 * This store is the SINGLE SOURCE OF TRUTH for navigation state.
 * 
 * - Persists to localStorage for anti-sleep
 * - Hydrated flag prevents infinite replace
 * - Used by Android back/resume handlers
 * 
 * For this CRM app:
 * - 'home' = conversation list view (showMobileChat = false)
 * - 'chat' = chat view (showMobileChat = true, activeChatId has value)
 */
export const useNavigationStore = defineStore('navigation', {
  state: () => ({
    current: 'home',     // Current view: 'home' or 'chat'
    activeChatId: null,  // Active chat ID when in chat view
    hydrated: false      // Prevents infinite replace on restore
  }),

  actions: {
    /**
     * Set current view and persist to localStorage
     */
    setView(view, chatId = null) {
      this.current = view
      this.activeChatId = chatId
      
      localStorage.setItem('NAV_CURRENT', view)
      if (chatId) {
        localStorage.setItem('NAV_CHAT_ID', String(chatId))
      } else {
        localStorage.removeItem('NAV_CHAT_ID')
      }
      
      console.log('📍 Navigation state saved:', view, chatId)
    },

    /**
     * Restore navigation state from localStorage
     * Called on app resume after sleep
     */
    restore() {
      if (this.hydrated) {
        console.log('⏭️ Already hydrated, skipping restore')
        return { current: this.current, chatId: this.activeChatId }
      }

      const savedView = localStorage.getItem('NAV_CURRENT')
      const savedChatId = localStorage.getItem('NAV_CHAT_ID')
      
      if (savedView) {
        this.current = savedView
        this.activeChatId = savedChatId ? parseInt(savedChatId) : null
        console.log('🔄 Navigation state restored:', savedView, savedChatId)
      }
      
      this.hydrated = true
      
      return { current: this.current, chatId: this.activeChatId }
    },

    /**
     * Reset navigation to home
     */
    reset() {
      this.setView('home', null)
      console.log('🏠 Navigation reset to home')
    }
  }
})
