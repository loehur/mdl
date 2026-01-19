import { defineStore } from 'pinia';

/**
 * Navigation Store - Anti-SLEEP
 * Manages app navigation state with Android back button integration
 * 
 * Features:
 * - Persistent state via localStorage
 * - Android back button handling
 * - Auto-restore after app sleep/reload
 */
export const useNavigationStore = defineStore('navigation', {
  state: () => ({
    // Current view state
    currentView: 'list', // 'list' | 'chat' | 'settings' | 'profile'
    isChildView: false,  // true if in chat/settings/etc
    parentView: 'list',  // where to go back to
    
    // Chat-specific state
    activeChatId: null,
    showMobileChat: false,
    
    // Navigation history
    history: [],
    canGoBack: false,
    
    // Timestamps for debugging
    lastUpdate: null,
    lastRestore: null,
  }),

  getters: {
    /**
     * Check if user can navigate back
     */
    canNavigateBack: (state) => {
      return state.isChildView || state.history.length > 0;
    },

    /**
     * Get serializable state for localStorage
     */
    persistentState: (state) => ({
      currentView: state.currentView,
      isChildView: state.isChildView,
      parentView: state.parentView,
      activeChatId: state.activeChatId,
      showMobileChat: state.showMobileChat,
      timestamp: Date.now(),
    }),
  },

  actions: {
    /**
     * Navigate to a child view (chat, settings, etc)
     */
    navigateToChild(viewName, options = {}) {
      console.log('📍 Navigate to child:', viewName, options);
      
      // Save previous state to history
      this.history.push({
        view: this.currentView,
        isChild: this.isChildView,
        chatId: this.activeChatId,
        timestamp: Date.now(),
      });

      // Update state
      this.currentView = viewName;
      this.isChildView = true;
      this.parentView = options.parentView || 'list';
      this.canGoBack = true;

      // Chat-specific state
      if (viewName === 'chat') {
        this.activeChatId = options.chatId || null;
        this.showMobileChat = true;
      }

      this.lastUpdate = Date.now();
      this.persist();
    },

    /**
     * Navigate back to parent view
     * Returns status string for Android
     */
    navigateBack() {
      console.log('🔙 Navigate back from:', this.currentView);

      // Pop from history if available
      if (this.history.length > 0) {
        const prevState = this.history.pop();
        console.log('📦 Restoring from history:', prevState);
        
        this.currentView = prevState.view;
        this.isChildView = prevState.isChild;
        this.activeChatId = prevState.chatId;
        this.showMobileChat = prevState.isChild && prevState.view === 'chat';
      } else {
        // Reset to parent view
        console.log('📦 Resetting to parent:', this.parentView);
        this.currentView = this.parentView;
        this.isChildView = false;
        this.activeChatId = null;
        this.showMobileChat = false;
      }

      this.canGoBack = this.history.length > 0;
      this.lastUpdate = Date.now();
      this.persist();

      // Return appropriate status
      if (this.currentView === 'chat') return 'chat_closed';
      if (this.currentView === 'settings') return 'settings_closed';
      return 'navigated_to_parent';
    },

    /**
     * Navigate to root (exit all child views)
     */
    navigateToRoot() {
      console.log('🏠 Navigate to root');
      
      this.currentView = 'list';
      this.isChildView = false;
      this.parentView = 'list';
      this.activeChatId = null;
      this.showMobileChat = false;
      this.history = [];
      this.canGoBack = false;
      this.lastUpdate = Date.now();
      
      this.persist();
      return 'navigated_to_parent';
    },

    /**
     * Persist state to localStorage
     */
    persist() {
      try {
        const state = this.persistentState;
        localStorage.setItem('nav_state', JSON.stringify(state));
        console.log('💾 State persisted:', state);
      } catch (e) {
        console.error('Failed to persist nav state:', e);
      }
    },

    /**
     * Restore state from localStorage
     * Called on app init or after sleep
     */
    restore() {
      try {
        const saved = localStorage.getItem('nav_state');
        if (!saved) {
          console.log('📦 No saved state, using defaults');
          return;
        }

        const state = JSON.parse(saved);
        console.log('📦 Restoring state:', state);

        // Restore state
        this.currentView = state.currentView || 'list';
        this.isChildView = state.isChildView || false;
        this.parentView = state.parentView || 'list';
        this.activeChatId = state.activeChatId || null;
        this.showMobileChat = state.showMobileChat || false;
        this.canGoBack = this.isChildView;
        this.lastRestore = Date.now();

        console.log('✅ State restored successfully');
      } catch (e) {
        console.error('Failed to restore nav state:', e);
        this.navigateToRoot(); // Reset on error
      }
    },

    /**
     * Clear all navigation state
     */
    clear() {
      console.log('🗑️ Clearing navigation state');
      this.navigateToRoot();
      localStorage.removeItem('nav_state');
    },
  },
});
