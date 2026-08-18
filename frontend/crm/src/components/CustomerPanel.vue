<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { showCustomerPanel } from "../stores/chatStore.js";

const props = defineProps({
  conversation: { type: Object, default: null },
  authId: { type: String, default: "" },
  apiBase: { type: String, default: "https://api.nalju.com" },
});

const copiedPhone = ref(false);
const isUpdatingPartner = ref(false);

const isPartnerActive = computed(() => {
  const p = props.conversation?.partner;
  return p === 1 || p === "1";
});

const formatPhoneTo08 = (phone) => {
  if (!phone) return "";
  let p = phone.toString().replace(/\D/g, "");
  if (p.startsWith("62")) p = "0" + p.substring(2);
  return p;
};

const closePanel = () => {
  showCustomerPanel.value = false;
};

const copyPhoneNumber = async () => {
  if (!props.conversation?.wa_number) return;
  const phone = formatPhoneTo08(props.conversation.wa_number);
  try {
    await navigator.clipboard.writeText(phone);
  } catch (_) {
    const textArea = document.createElement("textarea");
    textArea.value = phone;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand("copy");
    document.body.removeChild(textArea);
  }
  copiedPhone.value = true;
  setTimeout(() => {
    copiedPhone.value = false;
  }, 2000);
};

const onPartnerToggle = async (e) => {
  if (!props.conversation?.wa_number || isUpdatingPartner.value) return;
  const wantOn = e.target.checked;
  const prevPartner = props.conversation.partner;
  props.conversation.partner = wantOn ? 1 : null;
  isUpdatingPartner.value = true;
  try {
    const res = await fetch(`${props.apiBase}/CRM/Chat/setPartner`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        phone: props.conversation.wa_number,
        partner: wantOn,
        user_id: props.authId,
      }),
    }).then((r) => r.json());
    if (!res.status) {
      props.conversation.partner = prevPartner;
      e.target.checked = prevPartner === 1 || prevPartner === "1";
    }
  } catch (_) {
    props.conversation.partner = prevPartner;
    e.target.checked = prevPartner === 1 || prevPartner === "1";
  } finally {
    isUpdatingPartner.value = false;
  }
};

const onKeydown = (e) => {
  if (e.key === "Escape" && showCustomerPanel.value) {
    closePanel();
  }
};

watch(
  () => props.conversation?.id,
  () => {
    copiedPhone.value = false;
  }
);

onMounted(() => {
  window.addEventListener("keydown", onKeydown);
});

onUnmounted(() => {
  window.removeEventListener("keydown", onKeydown);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="customer-panel">
      <div
        v-if="showCustomerPanel && conversation"
        class="fixed inset-0 z-[600]"
        @click="closePanel"
      >
        <div class="absolute inset-0 bg-black/50 customer-panel-backdrop"></div>
        <aside
          class="customer-panel-drawer absolute right-0 top-0 bottom-0 w-full max-w-md bg-[var(--wa-bg-panel)] border-l border-[var(--wa-border)] shadow-2xl flex flex-col"
          @click.stop
        >
          <header class="h-16 flex items-center justify-between px-4 border-b border-[var(--wa-border)] flex-shrink-0">
            <div class="min-w-0">
              <p class="text-xs text-[var(--wa-text-tertiary)]">Customer Panel</p>
              <h2 class="text-base font-semibold text-[var(--wa-text-primary)] truncate uppercase">
                {{ conversation.name }}
              </h2>
            </div>
            <button
              type="button"
              class="p-2 text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)] hover:bg-[var(--wa-hover)] rounded-lg transition-colors"
              title="Close"
              @click="closePanel"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </header>

          <div class="flex-1 overflow-y-auto p-4 space-y-4">
            <section class="space-y-3">
              <div class="flex items-center gap-3">
                <div
                  class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg flex-shrink-0"
                  :style="{ backgroundColor: conversation.color || '#25d366' }"
                >
                  {{ conversation.initials || (conversation.name || "?").charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-[var(--wa-text-primary)] uppercase truncate">
                    {{ conversation.name }}
                  </p>
                  <div class="flex items-center gap-2 flex-wrap">
                    <span v-if="conversation.kode_cabang" class="text-xs font-bold text-[var(--wa-accent-green)]">
                      {{ conversation.kode_cabang }}
                    </span>
                    <span v-if="conversation.cust_id" class="text-xs text-[var(--wa-text-tertiary)]">
                      #{{ conversation.cust_id }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="bg-[var(--wa-bg-secondary)] rounded-xl p-4 border border-[var(--wa-border)]">
                <label class="text-xs text-[var(--wa-text-tertiary)]">WA</label>
                <div class="flex justify-between items-center gap-3">
                  <p class="text-base font-mono text-[var(--wa-text-primary)]">
                    {{ formatPhoneTo08(conversation.wa_number) }}
                  </p>
                  <button
                    type="button"
                    class="text-[var(--wa-accent-green)] text-sm font-bold"
                    @click="copyPhoneNumber"
                  >
                    {{ copiedPhone ? "Copied!" : "Copy" }}
                  </button>
                </div>
              </div>

              <div class="flex items-center justify-between gap-3 bg-[var(--wa-bg-secondary)] rounded-xl p-4 border border-[var(--wa-border)]">
                <span class="text-sm font-medium text-[var(--wa-text-primary)]">Partner</span>
                <label
                  class="relative inline-flex cursor-pointer items-center"
                  :class="{ 'pointer-events-none opacity-50': isUpdatingPartner }"
                >
                  <input
                    type="checkbox"
                    class="peer sr-only"
                    :checked="isPartnerActive"
                    :disabled="isUpdatingPartner"
                    @change="onPartnerToggle"
                  />
                  <div class="relative peer h-6 w-11 shrink-0 rounded-full bg-[var(--wa-bg-tertiary)] after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-[var(--wa-border)] after:bg-white after:transition-all peer-checked:bg-[var(--wa-accent-green)] peer-checked:after:translate-x-full peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[var(--wa-accent-green)] peer-focus:ring-offset-2 peer-focus:ring-offset-[var(--wa-bg-panel)]"></div>
                </label>
              </div>
            </section>
          </div>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.customer-panel-enter-active,
.customer-panel-leave-active {
  transition: opacity 0.2s ease;
}
.customer-panel-enter-active .customer-panel-drawer,
.customer-panel-leave-active .customer-panel-drawer {
  transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}
.customer-panel-enter-from,
.customer-panel-leave-to {
  opacity: 0;
}
.customer-panel-enter-from .customer-panel-drawer,
.customer-panel-leave-to .customer-panel-drawer {
  transform: translateX(100%);
}
</style>
