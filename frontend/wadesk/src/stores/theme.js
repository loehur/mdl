import { defineStore } from "pinia";
import { computed, onScopeDispose, ref, watch } from "vue";

const STORAGE_KEY = "wadesk-theme";
/** Local hour: light 06:00–17:59, dark otherwise */
const DAY_START = 6;
const DAY_END = 18;

function timeBasedTheme(date = new Date()) {
  const h = date.getHours();
  return h >= DAY_START && h < DAY_END ? "light" : "dark";
}

function readStoredMode() {
  try {
    const v = localStorage.getItem(STORAGE_KEY);
    if (v === "auto" || v === "light" || v === "dark") return v;
  } catch (_) {
    /* ignore */
  }
  return "auto";
}

function resolveTheme(mode) {
  return mode === "auto" ? timeBasedTheme() : mode;
}

function applyDom(resolved) {
  const root = document.documentElement;
  root.setAttribute("data-theme", resolved);
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) {
    meta.setAttribute("content", resolved === "light" ? "#f1f5f9" : "#0b1220");
  }
}

/** Apply before Vue mount to avoid flash */
export function initTheme() {
  applyDom(resolveTheme(readStoredMode()));
}

export const useThemeStore = defineStore("theme", () => {
  const mode = ref(readStoredMode());
  const tick = ref(0); // bump periodically so auto mode re-evaluates

  const resolved = computed(() => {
    tick.value; // dependency
    return resolveTheme(mode.value);
  });

  function persistAndApply() {
    applyDom(resolved.value);
    try {
      localStorage.setItem(STORAGE_KEY, mode.value);
    } catch (_) {
      /* ignore */
    }
  }

  persistAndApply();

  watch([mode, resolved], persistAndApply);

  const timer = setInterval(() => {
    tick.value += 1;
  }, 60_000);

  onScopeDispose(() => clearInterval(timer));

  const isDark = computed(() => resolved.value === "dark");
  const isLight = computed(() => resolved.value === "light");
  const isAuto = computed(() => mode.value === "auto");

  /** Cycle: auto → light → dark → auto */
  function toggle() {
    if (mode.value === "auto") mode.value = "light";
    else if (mode.value === "light") mode.value = "dark";
    else mode.value = "auto";
  }

  function setMode(v) {
    if (v === "auto" || v === "light" || v === "dark") mode.value = v;
  }

  /** @deprecated use setMode */
  function setTheme(v) {
    setMode(v);
  }

  return {
    mode,
    theme: resolved,
    resolved,
    isDark,
    isLight,
    isAuto,
    toggle,
    setMode,
    setTheme,
  };
});
