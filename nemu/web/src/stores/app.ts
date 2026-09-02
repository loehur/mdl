import { defineStore } from 'pinia';

export const useAppStore = defineStore('app', {
  state: () => ({ toast: '' }),
  actions: { showToast(message: string) { this.toast = message; window.setTimeout(() => { this.toast = ''; }, 3200); } }
});
