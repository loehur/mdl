<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from "vue";
import { showCustomerPanel, showAddLokasiModal } from "../stores/chatStore.js";

const props = defineProps({
  conversation: { type: Object, default: null },
  authId: { type: String, default: "" },
  apiBase: { type: String, default: "https://api.nalju.com" },
  isMobile: { type: Boolean, default: false },
});

const copiedPhone = ref(false);
const isUpdatingPartner = ref(false);

const lokasiItems = ref([]);
const lokasiLoading = ref(false);
const lokasiError = ref("");
const savingLokasi = ref(false);
const resolvingMaps = ref(false);
const formNama = ref("");
const formDetail = ref("");
const formGmaps = ref("");
const formLatt = ref(null);
const formLongt = ref(null);
const formMsg = ref("");

const custId = computed(() => Number(props.conversation?.cust_id) || 0);

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

const isOpen = computed(() => !!(showCustomerPanel.value && props.conversation));
const isVisible = ref(false);
const isEntering = ref(false);
const isExiting = ref(false);
let enterTimer = null;
let exitTimer = null;

const clearPanelTimers = () => {
  if (enterTimer) {
    clearTimeout(enterTimer);
    enterTimer = null;
  }
  if (exitTimer) {
    clearTimeout(exitTimer);
    exitTimer = null;
  }
};

watch(
  [isOpen, () => props.isMobile],
  async ([open, isMobile]) => {
    if (!isMobile) {
      clearPanelTimers();
      isVisible.value = false;
      isEntering.value = false;
      isExiting.value = false;
      return;
    }

    if (open) {
      clearPanelTimers();
      isExiting.value = false;
      isEntering.value = true;
      isVisible.value = true;
      await nextTick();
      enterTimer = setTimeout(() => {
        isEntering.value = false;
        enterTimer = null;
      }, 30);
      return;
    }

    if (!isVisible.value || isExiting.value) return;

    isEntering.value = false;
    isExiting.value = true;
    exitTimer = setTimeout(() => {
      isVisible.value = false;
      isExiting.value = false;
      exitTimer = null;
    }, 300);
  }
);

const panelClass = computed(() => {
  if (props.isMobile) {
    return [
      "customer-panel-mobile",
      isEntering.value ? "is-entering" : "",
      isExiting.value ? "is-exiting" : "",
    ];
  }
  return ["customer-panel-desktop", isOpen.value ? "is-open" : ""];
});

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

const resetAddLokasiForm = () => {
  formNama.value = "";
  formDetail.value = "";
  formGmaps.value = "";
  formLatt.value = null;
  formLongt.value = null;
  formMsg.value = "";
};

const closeAddLokasi = () => {
  showAddLokasiModal.value = false;
  resetAddLokasiForm();
};

const openAddLokasi = () => {
  if (!custId.value) return;
  resetAddLokasiForm();
  showAddLokasiModal.value = true;
};

const loadLokasi = async () => {
  if (!custId.value) {
    lokasiItems.value = [];
    lokasiError.value = "";
    return;
  }
  lokasiLoading.value = true;
  lokasiError.value = "";
  try {
    const res = await fetch(
      `${props.apiBase}/Laundry/PelangganLokasi/listLokasi?cust_id=${custId.value}`
    ).then((r) => r.json());
    if (!res?.ok && !res?.status) {
      lokasiError.value = res?.message || "Gagal memuat lokasi";
      lokasiItems.value = [];
      return;
    }
    lokasiItems.value = Array.isArray(res.items) ? res.items : [];
  } catch (_) {
    lokasiError.value = "Gagal memuat lokasi";
    lokasiItems.value = [];
  } finally {
    lokasiLoading.value = false;
  }
};

const resolveGmaps = async () => {
  const url = formGmaps.value.trim();
  if (!url) {
    formLatt.value = null;
    formLongt.value = null;
    return;
  }
  resolvingMaps.value = true;
  formMsg.value = "";
  try {
    const res = await fetch(`${props.apiBase}/Laundry/PelangganLokasi/resolveMaps`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ url }),
    }).then((r) => r.json());
    if (!res?.ok && !res?.status) {
      formLatt.value = null;
      formLongt.value = null;
      formMsg.value = res?.message || "Gagal membaca koordinat dari URL";
      return;
    }
    formLatt.value = res.latt;
    formLongt.value = res.longt;
  } catch (_) {
    formMsg.value = "Gagal membaca koordinat dari URL";
  } finally {
    resolvingMaps.value = false;
  }
};

const saveLokasi = async () => {
  if (!custId.value || savingLokasi.value) return;
  savingLokasi.value = true;
  formMsg.value = "";
  try {
    const res = await fetch(`${props.apiBase}/Laundry/PelangganLokasi/add`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        cust_id: custId.value,
        nama: formNama.value.trim(),
        detail: formDetail.value.trim(),
        gmaps_url: formGmaps.value.trim(),
        latt: formLatt.value,
        longt: formLongt.value,
      }),
    }).then((r) => r.json());
    if (!res?.ok && !res?.status) {
      formMsg.value = res?.message || "Gagal menyimpan lokasi";
      return;
    }
    if (Array.isArray(res.items)) {
      lokasiItems.value = res.items;
    } else {
      await loadLokasi();
    }
    closeAddLokasi();
  } catch (_) {
    formMsg.value = "Gagal menyimpan lokasi";
  } finally {
    savingLokasi.value = false;
  }
};

const onKeydown = (e) => {
  if (e.key !== "Escape") return;
  if (showAddLokasiModal.value) {
    closeAddLokasi();
    e.stopImmediatePropagation();
    return;
  }
  if (showCustomerPanel.value) {
    closePanel();
  }
};

watch(
  () => props.conversation?.id,
  () => {
    copiedPhone.value = false;
    closeAddLokasi();
  }
);

watch(showAddLokasiModal, (open) => {
  if (!open) resetAddLokasiForm();
});

watch(
  [() => showCustomerPanel.value, custId],
  ([open, id]) => {
    if (!open) {
      closeAddLokasi();
      lokasiItems.value = [];
      lokasiError.value = "";
      return;
    }
    if (id) {
      loadLokasi();
    } else {
      lokasiItems.value = [];
      lokasiError.value = "";
    }
  }
);

onMounted(() => {
  window.addEventListener("keydown", onKeydown);
});

onUnmounted(() => {
  window.removeEventListener("keydown", onKeydown);
  clearPanelTimers();
});
</script>

<template>
  <aside
    v-if="!isMobile || isVisible"
    class="customer-panel bg-[var(--wa-bg-panel)] flex flex-col h-full overflow-hidden"
    :class="panelClass"
  >
    <div class="customer-panel-inner w-full md:w-96 md:min-w-96 h-full flex flex-col">
      <header class="h-16 flex items-center justify-between px-4 border-b border-[var(--wa-border)] flex-shrink-0 gap-3">
        <div class="min-w-0">
          <p class="text-xs text-[var(--wa-text-tertiary)] flex items-center gap-2">
            <span>Customer Panel</span>
            <span v-if="conversation?.kode_cabang">{{ conversation.kode_cabang }}</span>
            <span v-if="conversation?.cust_id">#{{ conversation.cust_id }}</span>
          </p>
          <h2 class="text-base font-semibold text-[var(--wa-text-primary)] truncate uppercase">
            {{ conversation?.name }}
          </h2>
        </div>
        <button
          type="button"
          class="p-2 text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)] hover:bg-[var(--wa-hover)] rounded-lg transition-colors flex-shrink-0"
          title="Close"
          @click="closePanel"
        >
          <svg v-if="isMobile" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </header>

      <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <section class="grid grid-cols-2 gap-3">
          <div class="bg-[var(--wa-bg-secondary)] rounded-xl p-3 border border-[var(--wa-border)] min-w-0">
            <label class="text-xs text-[var(--wa-text-tertiary)]">WA</label>
            <div class="flex items-center justify-between gap-2 mt-0.5">
              <p class="text-sm font-mono text-[var(--wa-text-primary)] truncate">
                {{ formatPhoneTo08(conversation?.wa_number) }}
              </p>
              <button
                type="button"
                class="text-[var(--wa-accent-green)] text-xs font-bold flex-shrink-0"
                @click="copyPhoneNumber"
              >
                {{ copiedPhone ? "Copied!" : "Copy" }}
              </button>
            </div>
          </div>

          <div class="bg-[var(--wa-bg-secondary)] rounded-xl p-3 border border-[var(--wa-border)] flex items-center justify-between gap-2">
            <span class="text-sm font-medium text-[var(--wa-text-primary)]">Partner</span>
            <label
              class="relative inline-flex cursor-pointer items-center flex-shrink-0"
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

        <section>
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--wa-text-tertiary)]">Lokasi</h3>
            <button
              type="button"
              class="text-xs font-bold text-[var(--wa-accent-green)] disabled:opacity-40 disabled:cursor-not-allowed"
              :disabled="!custId"
              @click="openAddLokasi"
            >
              + Tambah
            </button>
          </div>

          <p v-if="!custId" class="text-xs text-[var(--wa-text-tertiary)]">
            Customer belum terhubung ke data laundry, lokasi tidak bisa ditambah.
          </p>
          <p v-else-if="lokasiLoading" class="text-xs text-[var(--wa-text-tertiary)]">Memuat lokasi…</p>
          <p v-else-if="lokasiError" class="text-xs text-red-400">{{ lokasiError }}</p>
          <p v-else-if="!lokasiItems.length" class="text-xs text-[var(--wa-text-tertiary)]">
            Belum ada lokasi.
          </p>
          <div v-else class="space-y-2">
            <div
              v-for="loc in lokasiItems"
              :key="loc.id_lokasi"
              class="bg-[var(--wa-bg-secondary)] rounded-xl p-3 border border-[var(--wa-border)]"
            >
              <div class="flex items-start justify-between gap-2">
                <p class="text-sm font-medium text-[var(--wa-text-primary)] truncate">{{ loc.nama }}</p>
                <a
                  v-if="loc.maps_url"
                  :href="loc.maps_url"
                  target="_blank"
                  rel="noopener"
                  class="text-[11px] font-bold text-[var(--wa-accent-green)] flex-shrink-0"
                >
                  Maps
                </a>
              </div>
              <p class="text-xs text-[var(--wa-text-tertiary)] mt-0.5 break-words">{{ loc.detail }}</p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </aside>

  <Teleport to="body">
    <div
      v-if="showAddLokasiModal"
      class="fixed inset-0 z-[700] flex items-center justify-center p-4"
      @click="closeAddLokasi"
    >
      <div class="absolute inset-0 bg-black/50"></div>
      <div
        class="relative w-full max-w-sm bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-2xl shadow-2xl p-5"
        @click.stop
      >
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base font-semibold text-[var(--wa-text-primary)]">Tambah Lokasi</h3>
          <button
            type="button"
            class="p-1 text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)]"
            @click="closeAddLokasi"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="space-y-3">
          <div>
            <label class="text-xs text-[var(--wa-text-tertiary)]">Nama lokasi</label>
            <input
              v-model="formNama"
              type="text"
              maxlength="50"
              placeholder="Rumah / Kos / Kantor"
              class="mt-1 w-full px-3 py-2 rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-secondary)] text-sm text-[var(--wa-text-primary)] placeholder-[var(--wa-text-tertiary)] focus:outline-none focus:border-[var(--wa-accent-green)]"
            />
          </div>
          <div>
            <label class="text-xs text-[var(--wa-text-tertiary)]">Detail alamat</label>
            <textarea
              v-model="formDetail"
              rows="3"
              maxlength="255"
              placeholder="Ciri / patokan / nomor rumah"
              class="mt-1 w-full px-3 py-2 rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-secondary)] text-sm text-[var(--wa-text-primary)] placeholder-[var(--wa-text-tertiary)] focus:outline-none focus:border-[var(--wa-accent-green)] resize-none"
            ></textarea>
          </div>
          <div>
            <label class="text-xs text-[var(--wa-text-tertiary)]">URL Google Maps</label>
            <input
              v-model="formGmaps"
              type="url"
              placeholder="https://maps.app.goo.gl/…"
              class="mt-1 w-full px-3 py-2 rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-secondary)] text-sm text-[var(--wa-text-primary)] placeholder-[var(--wa-text-tertiary)] focus:outline-none focus:border-[var(--wa-accent-green)]"
              @blur="resolveGmaps"
            />
            <p v-if="resolvingMaps" class="text-[11px] text-[var(--wa-text-tertiary)] mt-1">Membaca koordinat…</p>
            <p v-else-if="formLatt != null && formLongt != null" class="text-[11px] text-[var(--wa-accent-green)] mt-1 font-mono">
              {{ formLatt }}, {{ formLongt }}
            </p>
          </div>
          <p v-if="formMsg" class="text-xs text-red-400">{{ formMsg }}</p>
          <div class="flex gap-2 pt-1">
            <button
              type="button"
              class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-[var(--wa-bg-secondary)] text-[var(--wa-text-primary)]"
              @click="closeAddLokasi"
            >
              Batal
            </button>
            <button
              type="button"
              class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-[var(--wa-accent-green)] text-black disabled:opacity-50"
              :disabled="savingLokasi || !formNama.trim() || !formDetail.trim() || (!formGmaps.trim() && formLatt == null)"
              @click="saveLokasi"
            >
              {{ savingLokasi ? "Menyimpan…" : "Simpan" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.customer-panel-desktop {
  width: 0;
  min-width: 0;
  flex-shrink: 0;
  border-left-width: 0;
  transition: width 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}
.customer-panel-desktop.is-open {
  width: 24rem;
  border-left-width: 1px;
  border-left-color: var(--wa-border);
}
.customer-panel-mobile {
  position: fixed;
  inset: 0;
  z-index: 80;
  width: 100%;
  transform: translateX(0);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.customer-panel-mobile.is-entering,
.customer-panel-mobile.is-exiting {
  transform: translateX(100%);
}
</style>
