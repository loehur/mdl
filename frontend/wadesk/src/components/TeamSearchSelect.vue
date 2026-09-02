<template>
  <div ref="rootRef" class="relative w-full">
    <input
      :value="open ? query : displayValue"
      type="search"
      class="field w-full"
      :placeholder="open ? searchPlaceholder : placeholder"
      autocomplete="off"
      @focus="openPicker"
      @input="onSearchInput"
    />

    <div
      v-if="open"
      class="absolute z-30 mt-1 w-full rounded-xl border border-white/10 bg-ink-900 shadow-xl overflow-hidden"
    >
      <div class="max-h-44 overflow-y-auto divide-y divide-white/5">
        <button
          v-if="allowEmpty"
          type="button"
          class="w-full text-left px-3 py-2.5 text-sm hover:bg-white/5 transition"
          :class="!modelValue ? 'bg-accent/10 text-accent' : 'text-slate-300'"
          @mousedown.prevent="select('')"
        >
          {{ emptyLabel }}
        </button>

        <button
          v-for="t in rows"
          :key="t.id"
          type="button"
          class="w-full text-left px-3 py-2.5 text-sm hover:bg-white/5 transition truncate"
          :class="String(modelValue) === String(t.id) ? 'bg-accent/10 text-accent' : 'text-slate-300'"
          @mousedown.prevent="select(t.id)"
        >
          {{ t.name }}
        </button>

        <div ref="sentinelRef" class="h-1" aria-hidden="true" />

        <p v-if="loading && !rows.length" class="px-3 py-4 text-xs text-slate-500 text-center">Loading teams...</p>
        <p v-else-if="loading" class="px-3 py-2 text-[11px] text-slate-500 text-center">Loading more...</p>
        <p v-else-if="!rows.length" class="px-3 py-4 text-xs text-slate-500 text-center">
          {{ query.trim() ? "No teams found." : "No teams available." }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { api } from "../api";

const props = defineProps({
  modelValue: { type: [String, Number], default: "" },
  allowEmpty: { type: Boolean, default: false },
  emptyLabel: { type: String, default: "— Inactive —" },
  placeholder: { type: String, default: "Select a team..." },
  searchPlaceholder: { type: String, default: "Search teams..." },
  fallbackLabel: { type: String, default: "" },
  active: { type: Boolean, default: true },
});

const emit = defineEmits(["update:modelValue"]);

const LIMIT = 20;
const rootRef = ref(null);
const sentinelRef = ref(null);
const open = ref(false);
const query = ref("");
const rows = ref([]);
const page = ref(1);
const total = ref(0);
const loading = ref(false);
const hasMore = ref(true);
let observer = null;
let searchTimer = null;

const displayValue = computed(() => {
  if (props.modelValue === "" || props.modelValue === null || props.modelValue === undefined) {
    return props.allowEmpty ? props.emptyLabel : "";
  }
  const hit = rows.value.find((t) => String(t.id) === String(props.modelValue));
  return hit?.name || props.fallbackLabel || `#${props.modelValue}`;
});

function openPicker() {
  open.value = true;
  query.value = "";
  if (!rows.value.length) load(true);
}

function onSearchInput(e) {
  open.value = true;
  query.value = e.target.value;
}

function select(id) {
  emit("update:modelValue", id === "" || id === null ? "" : String(id));
  open.value = false;
  query.value = "";
}

async function load(reset = false) {
  if (loading.value) return;
  if (!reset && !hasMore.value) return;

  loading.value = true;
  try {
    if (reset) {
      page.value = 1;
      hasMore.value = true;
    }
    const reqPage = reset ? 1 : page.value;
    const q = query.value.trim();
    const res = await api(
      `/WaDesk/Teams/list?page=${reqPage}&limit=${LIMIT}&q=${encodeURIComponent(q)}&_=${Date.now()}`,
      { cache: "no-store" }
    );
    const batch = res.data?.teams || [];
    total.value = Number(res.data?.total ?? batch.length);
    if (reset) {
      rows.value = batch;
    } else {
      const seen = new Set(rows.value.map((t) => t.id));
      for (const row of batch) {
        if (!seen.has(row.id)) {
          rows.value.push(row);
          seen.add(row.id);
        }
      }
    }
    hasMore.value = rows.value.length < total.value;
    page.value = reqPage + 1;
  } catch {
    if (reset) rows.value = [];
  } finally {
    loading.value = false;
    await nextTick();
    setupObserver();
  }
}

function setupObserver() {
  observer?.disconnect();
  if (!open.value || !sentinelRef.value || !hasMore.value) return;
  observer = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) load(false);
    },
    { rootMargin: "80px" }
  );
  observer.observe(sentinelRef.value);
}

function onDocumentMouseDown(e) {
  if (!open.value) return;
  if (rootRef.value && !rootRef.value.contains(e.target)) {
    open.value = false;
    query.value = "";
  }
}

watch(query, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    if (open.value) load(true);
  }, 300);
});

watch(
  () => props.active,
  (v) => {
    if (v && props.modelValue) load(true);
  },
  { immediate: true }
);

watch(open, async (v) => {
  if (v) {
    await nextTick();
    setupObserver();
  } else {
    observer?.disconnect();
  }
});

onMounted(() => {
  document.addEventListener("mousedown", onDocumentMouseDown);
});

onUnmounted(() => {
  document.removeEventListener("mousedown", onDocumentMouseDown);
  observer?.disconnect();
  clearTimeout(searchTimer);
});
</script>

<style scoped>
.field {
  @apply w-full rounded-xl border border-white/10 bg-ink-950 px-3 py-2.5 text-sm text-slate-100 shadow-sm transition placeholder:text-slate-500 focus:outline-none focus:border-accent/60 focus:ring-2 focus:ring-accent/20;
}
</style>
