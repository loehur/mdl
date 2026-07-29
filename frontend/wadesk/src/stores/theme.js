import { defineStore } from "pinia";
import { computed, ref, watch } from "vue";

const STORAGE_KEY = "wadesk-theme";

function readStored() {
  try {
    const v = localStorage.getItem(STORAGE_KEY);
    if (v === "light" || v === "dark") return v;
  } catch (_) {
    /* ignore */
  }
  return "dark";
}

function applyDom(theme) {
  const root = document.documentElement;
  root.setAttribute("data-theme", theme);
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) {
    meta.setAttribute("content", theme === "light" ? "#f1f5f9" : "#0b1220");
  }
}

/** Apply before Vue mount to avoid flash */
export function initTheme() {
  applyDom(readStored());
}

export const useThemeStore = defineStore("theme", () => {
  const theme = ref(readStored());

  applyDom(theme.value);

  watch(theme, (v) => {
    applyDom(v);
    try {
      localStorage.setItem(STORAGE_KEY, v);
    } catch (_) {
      /* ignore */
    }
  });

  const isDark = computed(() => theme.value === "dark");
  const isLight = computed(() => theme.value === "light");

  function toggle() {
    theme.value = theme.value === "dark" ? "light" : "dark";
  }

  function setTheme(v) {
    if (v === "light" || v === "dark") theme.value = v;
  }

  return { theme, isDark, isLight, toggle, setTheme };
});
