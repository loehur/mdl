<template>
  <div
    class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60"
    @click.self="onCancel"
  >
    <div class="w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl bg-ink-900 border border-white/10 shadow-2xl">
      <div class="p-4 border-b border-white/10 flex items-center justify-between">
        <h2 class="font-display font-semibold text-lg text-slate-100">{{ title }}</h2>
        <button type="button" class="text-slate-400 hover:text-slate-100" @click="onCancel">✕</button>
      </div>
      <div class="p-4 space-y-4">
        <p class="text-sm text-slate-300 whitespace-pre-wrap">{{ message }}</p>
        <div class="flex gap-2 justify-end">
          <button
            v-if="mode === 'confirm'"
            type="button"
            class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-200 text-sm"
            @click="onCancel"
          >
            {{ cancelLabel }}
          </button>
          <button
            type="button"
            class="px-4 py-2.5 rounded-xl text-sm font-medium"
            :class="danger ? 'bg-rose-500/90 hover:bg-rose-500 text-white' : 'bg-accent hover:bg-accent/90 text-white'"
            @click="onConfirm"
          >
            {{ confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: "Konfirmasi" },
  message: { type: String, required: true },
  mode: { type: String, default: "confirm" }, // confirm | alert
  confirmLabel: { type: String, default: "Ya" },
  cancelLabel: { type: String, default: "Batal" },
  danger: { type: Boolean, default: false },
});

const emit = defineEmits(["confirm", "cancel", "close"]);

function onConfirm() {
  emit("confirm");
  emit("close");
}

function onCancel() {
  emit("cancel");
  emit("close");
}
</script>
