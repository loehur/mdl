<template>
  <div ref="rootEl" class="relative">
    <button
      type="button"
      class="field-input flex w-full items-center justify-between gap-2 text-left"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="toggle"
    >
      <span class="min-w-0 truncate" :class="selectedLabel ? 'text-pearl' : 'text-mist/50'">
        {{ selectedLabel || placeholder }}
      </span>
      <svg
        class="h-4 w-4 shrink-0 text-mist transition"
        :class="open ? 'rotate-180' : ''"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
      >
        <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <div
      v-if="open"
      class="absolute left-0 right-0 z-30 mt-2 overflow-hidden rounded-2xl border border-ink-200 bg-ink-50 shadow-panel"
    >
      <div class="border-b border-ink-200 p-2">
        <input
          ref="searchEl"
          v-model="query"
          type="search"
          class="field-input py-2.5 text-sm"
          placeholder="Cari nama / HP / email..."
          autocomplete="off"
          @keydown.esc.prevent="close"
          @keydown.down.prevent="move(1)"
          @keydown.up.prevent="move(-1)"
          @keydown.enter.prevent="pickHighlighted"
        />
      </div>

      <ul
        role="listbox"
        class="max-h-56 overflow-y-auto py-1"
      >
        <li v-if="!filtered.length" class="px-4 py-3 text-sm text-mist">
          Tidak ditemukan
        </li>
        <li
          v-for="(opt, idx) in filtered"
          :key="opt.id"
          role="option"
          class="cursor-pointer px-4 py-3 transition"
          :class="idx === highlightIndex ? 'bg-ledger/10' : 'hover:bg-ink-100'"
          :aria-selected="String(opt.id) === modelValue"
          @mousedown.prevent="select(opt)"
          @mouseenter="highlightIndex = idx"
        >
          <p class="truncate text-sm font-semibold text-pearl">{{ opt.name }}</p>
          <p class="truncate text-xs text-mist">
            {{ opt.phone }}<template v-if="opt.email"> · {{ opt.email }}</template>
          </p>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: String, default: "" },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: "Pilih pelanggan..." },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const open = ref(false);
const query = ref("");
const highlightIndex = ref(0);
const rootEl = ref(null);
const searchEl = ref(null);

const selected = computed(() => {
  if (!props.modelValue) return null;
  return props.options.find((o) => String(o.id) === String(props.modelValue)) || null;
});

const selectedLabel = computed(() => {
  if (!selected.value) return "";
  return `${selected.value.name} — ${selected.value.phone}`;
});

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return props.options;
  return props.options.filter((o) => {
    const hay = `${o.name || ""} ${o.phone || ""} ${o.email || ""}`.toLowerCase();
    return hay.includes(q);
  });
});

watch(filtered, () => {
  highlightIndex.value = 0;
});

watch(
  () => props.modelValue,
  () => {
    if (!open.value) query.value = "";
  }
);

async function toggle() {
  if (props.disabled) return;
  if (open.value) {
    close();
    return;
  }
  open.value = true;
  query.value = "";
  highlightIndex.value = Math.max(
    0,
    filtered.value.findIndex((o) => String(o.id) === String(props.modelValue))
  );
  await nextTick();
  searchEl.value?.focus();
}

function close() {
  open.value = false;
  query.value = "";
}

function select(opt) {
  emit("update:modelValue", String(opt.id));
  close();
}

function move(delta) {
  if (!filtered.value.length) return;
  const len = filtered.value.length;
  highlightIndex.value = (highlightIndex.value + delta + len) % len;
}

function pickHighlighted() {
  const opt = filtered.value[highlightIndex.value];
  if (opt) select(opt);
}

function onDocPointerDown(e) {
  if (!open.value || !rootEl.value) return;
  if (!rootEl.value.contains(e.target)) close();
}

onMounted(() => {
  document.addEventListener("pointerdown", onDocPointerDown);
});

onUnmounted(() => {
  document.removeEventListener("pointerdown", onDocPointerDown);
});
</script>
