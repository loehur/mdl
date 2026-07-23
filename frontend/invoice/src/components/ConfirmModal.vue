<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center"
        @keydown.esc.prevent="onCancel"
      >
        <div class="absolute inset-0 bg-pearl/50 backdrop-blur-[2px]" @click="onCancel" />

        <div
          class="confirm-sheet relative w-full max-w-sm overflow-hidden rounded-[1.75rem] border border-ink-200 bg-ink-50 shadow-panel"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
        >
          <div class="h-1.5 w-full" :class="accentBarClass" />

          <div class="px-5 pb-5 pt-6 text-center">
            <div
              class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl"
              :class="iconWrapClass"
            >
              <!-- success / paid -->
              <svg v-if="variant === 'success'" class="h-7 w-7" :class="iconClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <!-- danger -->
              <svg v-else-if="variant === 'danger'" class="h-7 w-7" :class="iconClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
              </svg>
              <!-- default / info -->
              <svg v-else class="h-7 w-7" :class="iconClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>

            <h3 :id="titleId" class="font-display text-xl font-bold tracking-tight text-pearl">
              {{ title }}
            </h3>
            <p class="mt-2 text-sm leading-relaxed text-mist">
              {{ message }}
            </p>

            <div v-if="detail" class="mt-4 rounded-2xl border border-ink-200 bg-ink-100/80 px-4 py-3 text-left">
              <p class="label-caps mb-1">Detail</p>
              <p class="text-sm font-semibold text-pearl">{{ detail }}</p>
            </div>

            <div class="mt-6 flex gap-3">
              <button
                type="button"
                class="btn-ghost flex-1"
                :disabled="loading"
                @click="onCancel"
              >
                {{ cancelLabel }}
              </button>
              <button
                type="button"
                class="flex-1"
                :class="confirmBtnClass"
                :disabled="loading"
                @click="onConfirm"
              >
                {{ loading ? loadingLabel : confirmLabel }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: "Konfirmasi" },
  message: { type: String, default: "" },
  detail: { type: String, default: "" },
  confirmLabel: { type: String, default: "Ya" },
  cancelLabel: { type: String, default: "Batal" },
  loadingLabel: { type: String, default: "Memproses..." },
  loading: { type: Boolean, default: false },
  /** 'success' | 'danger' | 'info' */
  variant: { type: String, default: "info" },
});

const emit = defineEmits(["confirm", "cancel"]);

const titleId = `confirm-title-${Math.random().toString(36).slice(2, 8)}`;

const accentBarClass = computed(() => {
  if (props.variant === "success") return "bg-gradient-to-r from-credit-dim via-credit to-credit-dim";
  if (props.variant === "danger") return "bg-gradient-to-r from-debit-dim via-debit to-debit-dim";
  return "bg-gradient-to-r from-ledger-dim via-ledger to-ledger-glow";
});

const iconWrapClass = computed(() => {
  if (props.variant === "success") return "bg-credit-light text-credit-dim";
  if (props.variant === "danger") return "bg-debit-light text-debit-dim";
  return "bg-ledger/10 text-ledger-dim";
});

const iconClass = computed(() => {
  if (props.variant === "success") return "text-credit-dim";
  if (props.variant === "danger") return "text-debit-dim";
  return "text-ledger-dim";
});

const confirmBtnClass = computed(() => {
  if (props.variant === "danger") return "btn-debit";
  return "btn-primary";
});

function onConfirm() {
  if (props.loading) return;
  emit("confirm");
}

function onCancel() {
  if (props.loading) return;
  emit("cancel");
}
</script>

<style scoped>
.confirm-fade-enter-active,
.confirm-fade-leave-active {
  transition: opacity 0.2s ease;
}
.confirm-fade-enter-active .confirm-sheet,
.confirm-fade-leave-active .confirm-sheet {
  transition: transform 0.22s ease, opacity 0.22s ease;
}
.confirm-fade-enter-from,
.confirm-fade-leave-to {
  opacity: 0;
}
.confirm-fade-enter-from .confirm-sheet {
  opacity: 0;
  transform: translateY(16px) scale(0.98);
}
.confirm-fade-leave-to .confirm-sheet {
  opacity: 0;
  transform: translateY(8px) scale(0.98);
}
</style>
