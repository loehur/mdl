<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from "vue";
import { showCustomerPanel, showAddLokasiModal, showDeleteLokasiModal, showDeliveryRequestModal } from "../stores/chatStore.js";

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
const deletingLokasi = ref(false);
const resolvingMaps = ref(false);
const formIdLokasi = ref(0);
const formNama = ref("");
const formDetail = ref("");
const formGmaps = ref("");
const formLatt = ref(null);
const formLongt = ref(null);
const formMsg = ref("");
const deleteTarget = ref(null);
const deleteMsg = ref("");
const deliveryJenis = ref("");
const deliveryLokasiId = ref(0);
const deliveryCatatan = ref("");
const deliveryFormMsg = ref("");
const deliveryResultMsg = ref("");
const deliveryResultOk = ref(false);
const submittingDelivery = ref(false);
const deliveryAktifItems = ref([]);
const deliveryAktifLoading = ref(false);

const deliveryJenisOptions = [
  { id: "jemput", label: "Jemput" },
  { id: "antar", label: "Antar" },
  { id: "antar_jemput", label: "Antar & Jemput" },
];

const isEditLokasi = computed(() => formIdLokasi.value > 0);
const canSaveLokasi = computed(() => {
  if (savingLokasi.value) return false;
  if (!formNama.value.trim() || !formDetail.value.trim()) return false;
  if (isEditLokasi.value) return formLatt.value != null && formLongt.value != null;
  return !!(formGmaps.value.trim() || (formLatt.value != null && formLongt.value != null));
});

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
  formIdLokasi.value = 0;
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

const openEditLokasi = (loc) => {
  if (!custId.value || !loc?.id_lokasi) return;
  resetAddLokasiForm();
  formIdLokasi.value = Number(loc.id_lokasi) || 0;
  formNama.value = loc.nama || "";
  formDetail.value = loc.detail || "";
  formLatt.value = loc.latt != null ? loc.latt : null;
  formLongt.value = loc.longt != null ? loc.longt : null;
  showAddLokasiModal.value = true;
};

const closeDeleteLokasi = () => {
  showDeleteLokasiModal.value = false;
  deleteTarget.value = null;
  deleteMsg.value = "";
};

const openDeleteLokasi = (loc) => {
  if (!custId.value || !loc?.id_lokasi) return;
  deleteMsg.value = "";
  deleteTarget.value = loc;
  showDeleteLokasiModal.value = true;
};

const resetDeliveryForm = () => {
  deliveryJenis.value = "";
  deliveryLokasiId.value = 0;
  deliveryCatatan.value = "";
  deliveryFormMsg.value = "";
};

const closeDeliveryRequest = () => {
  showDeliveryRequestModal.value = false;
  resetDeliveryForm();
};

const openDeliveryRequest = () => {
  if (!custId.value) return;
  resetDeliveryForm();
  deliveryResultMsg.value = "";
  deliveryResultOk.value = false;
  if (custId.value && !lokasiItems.value.length && !lokasiLoading.value) {
    loadLokasi();
  }
  showDeliveryRequestModal.value = true;
};

const submitDeliveryRequest = async () => {
  if (!custId.value || !deliveryJenis.value || !deliveryLokasiId.value || submittingDelivery.value) return;
  submittingDelivery.value = true;
  deliveryFormMsg.value = "";
  try {
    const res = await fetch(`${props.apiBase}/Laundry/DeliveryRequest/create`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        cust_id: custId.value,
        jenis: deliveryJenis.value,
        id_lokasi: deliveryLokasiId.value,
        wa_number: props.conversation?.wa_number || "",
        catatan: deliveryCatatan.value.trim(),
      }),
    }).then((r) => r.json());
    if (!res?.ok && !res?.status) {
      deliveryFormMsg.value = res?.message || "Gagal membuat permintaan";
      return;
    }
    deliveryResultOk.value = true;
    deliveryResultMsg.value = res?.message || "Permintaan terkirim.";
    if (Array.isArray(res.items)) {
      deliveryAktifItems.value = res.items;
    } else {
      loadDeliveryAktif();
    }
    closeDeliveryRequest();
  } catch (_) {
    deliveryFormMsg.value = "Gagal membuat permintaan";
  } finally {
    submittingDelivery.value = false;
  }
};

const loadDeliveryAktif = async () => {
  if (!custId.value) {
    deliveryAktifItems.value = [];
    return;
  }
  deliveryAktifLoading.value = true;
  try {
    const res = await fetch(
      `${props.apiBase}/Laundry/DeliveryRequest/listAktif?cust_id=${custId.value}`
    ).then((r) => r.json());
    if (!res?.ok && !res?.status) {
      deliveryAktifItems.value = [];
      return;
    }
    deliveryAktifItems.value = Array.isArray(res.items) ? res.items : [];
  } catch (_) {
    deliveryAktifItems.value = [];
  } finally {
    deliveryAktifLoading.value = false;
  }
};

const deliveryJenisLabel = (req) => req?.jenis_label || (req?.sekalian_jemput ? "Antar & Jemput" : (req?.jenis === "antar" ? "Antar" : "Jemput"));

const formatRequestTime = (raw) => {
  if (!raw) return "";
  const d = new Date(String(raw).replace(" ", "T"));
  if (Number.isNaN(d.getTime())) return String(raw);
  const pad = (n) => String(n).padStart(2, "0");
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
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
    if (!isEditLokasi.value) {
      formLatt.value = null;
      formLongt.value = null;
    }
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
      if (!isEditLokasi.value) {
        formLatt.value = null;
        formLongt.value = null;
      }
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

const applyLokasiItems = (res) => {
  if (Array.isArray(res?.items)) {
    lokasiItems.value = res.items;
    return;
  }
  return loadLokasi();
};

const saveLokasi = async () => {
  if (!custId.value || !canSaveLokasi.value) return;
  savingLokasi.value = true;
  formMsg.value = "";
  try {
    const isEdit = isEditLokasi.value;
    const payload = {
      cust_id: custId.value,
      nama: formNama.value.trim(),
      detail: formDetail.value.trim(),
    };
    const gmaps = formGmaps.value.trim();
    if (gmaps) payload.gmaps_url = gmaps;
    if (formLatt.value != null && formLongt.value != null) {
      payload.latt = formLatt.value;
      payload.longt = formLongt.value;
    }
    if (isEdit) payload.id_lokasi = formIdLokasi.value;

    const res = await fetch(
      `${props.apiBase}/Laundry/PelangganLokasi/${isEdit ? "update" : "add"}`,
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      }
    ).then((r) => r.json());
    if (!res?.ok && !res?.status) {
      formMsg.value = res?.message || "Gagal menyimpan lokasi";
      return;
    }
    await applyLokasiItems(res);
    closeAddLokasi();
  } catch (_) {
    formMsg.value = "Gagal menyimpan lokasi";
  } finally {
    savingLokasi.value = false;
  }
};

const confirmDeleteLokasi = async () => {
  const loc = deleteTarget.value;
  if (!custId.value || !loc?.id_lokasi || deletingLokasi.value) return;
  deletingLokasi.value = true;
  deleteMsg.value = "";
  try {
    const res = await fetch(`${props.apiBase}/Laundry/PelangganLokasi/delete`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        cust_id: custId.value,
        id_lokasi: loc.id_lokasi,
      }),
    }).then((r) => r.json());
    if (!res?.ok && !res?.status) {
      deleteMsg.value = res?.message || "Gagal menghapus lokasi";
      return;
    }
    await applyLokasiItems(res);
    closeDeleteLokasi();
  } catch (_) {
    deleteMsg.value = "Gagal menghapus lokasi";
  } finally {
    deletingLokasi.value = false;
  }
};

const onKeydown = (e) => {
  if (e.key !== "Escape") return;
  if (showDeleteLokasiModal.value) {
    closeDeleteLokasi();
    e.stopImmediatePropagation();
    return;
  }
  if (showAddLokasiModal.value) {
    closeAddLokasi();
    e.stopImmediatePropagation();
    return;
  }
  if (showDeliveryRequestModal.value) {
    closeDeliveryRequest();
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
    closeDeleteLokasi();
    closeDeliveryRequest();
    deliveryResultMsg.value = "";
    deliveryResultOk.value = false;
  }
);

watch(showAddLokasiModal, (open) => {
  if (!open) resetAddLokasiForm();
});

watch(showDeleteLokasiModal, (open) => {
  if (!open) {
    deleteTarget.value = null;
    deleteMsg.value = "";
  }
});

watch(showDeliveryRequestModal, (open) => {
  if (!open) resetDeliveryForm();
});

watch(
  [() => showCustomerPanel.value, custId],
  ([open, id]) => {
    if (!open) {
      closeAddLokasi();
      closeDeleteLokasi();
      closeDeliveryRequest();
      lokasiItems.value = [];
      lokasiError.value = "";
      deliveryAktifItems.value = [];
      return;
    }
    if (id) {
      loadLokasi();
      loadDeliveryAktif();
    } else {
      lokasiItems.value = [];
      lokasiError.value = "";
      deliveryAktifItems.value = [];
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
          <button
            type="button"
            class="w-full py-2.5 rounded-xl text-sm font-bold bg-[var(--wa-accent-green)] text-white disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="!custId"
            @click="openDeliveryRequest"
          >
            Delivery Request
          </button>
          <p v-if="!custId" class="text-xs text-[var(--wa-text-tertiary)] mt-2">
            Customer belum terhubung ke data laundry.
          </p>
          <p
            v-else-if="deliveryResultMsg"
            class="text-xs mt-2"
            :class="deliveryResultOk ? 'text-[var(--wa-accent-green)]' : 'text-red-400'"
          >
            {{ deliveryResultMsg }}
          </p>
          <p v-if="custId && deliveryAktifLoading && !deliveryAktifItems.length" class="text-xs text-[var(--wa-text-tertiary)] mt-3">
            Memuat request…
          </p>
          <div v-else-if="deliveryAktifItems.length" class="space-y-2 mt-3">
            <div
              v-for="req in deliveryAktifItems"
              :key="req.id_request"
              class="bg-[var(--wa-bg-secondary)] rounded-xl p-3 border border-[var(--wa-border)]"
            >
              <div class="flex items-start justify-between gap-2">
                <p class="text-sm font-medium text-[var(--wa-text-primary)]">{{ deliveryJenisLabel(req) }}</p>
                <span class="text-[11px] font-bold text-[var(--wa-accent-green)] flex-shrink-0">
                  {{ req.status_label || "Berjalan" }}
                </span>
              </div>
              <p class="text-xs text-[var(--wa-text-tertiary)] mt-0.5">
                {{ req.lokasi_nama || "Lokasi" }}
                <span v-if="req.lokasi_detail"> · {{ req.lokasi_detail }}</span>
              </p>
              <p v-if="req.catatan_kurir" class="text-xs text-[var(--wa-text-secondary)] mt-1 break-words">
                {{ req.catatan_kurir }}
              </p>
              <p class="text-[11px] text-[var(--wa-text-tertiary)] mt-1">
                #{{ req.id_request }}
                <span v-if="req.layanan && req.layanan !== 'sameday'"> · {{ req.layanan }}</span>
                <span v-if="req.insertTime"> · {{ formatRequestTime(req.insertTime) }}</span>
              </p>
            </div>
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
              <div class="flex items-center gap-3 mt-2">
                <button
                  type="button"
                  class="text-[11px] font-bold text-[var(--wa-accent-green)]"
                  @click="openEditLokasi(loc)"
                >
                  Edit
                </button>
                <button
                  type="button"
                  class="text-[11px] font-bold text-red-400"
                  @click="openDeleteLokasi(loc)"
                >
                  Hapus
                </button>
              </div>
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
          <h3 class="text-base font-semibold text-[var(--wa-text-primary)]">
            {{ isEditLokasi ? "Edit Lokasi" : "Tambah Lokasi" }}
          </h3>
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
            <p v-else class="text-[11px] text-[var(--wa-text-tertiary)] mt-1">
              {{ isEditLokasi ? "Isi ulang URL hanya jika ingin mengubah titik." : "Wajib. Koordinat diambil dari URL." }}
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
              class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-[var(--wa-accent-green)] text-white disabled:opacity-50"
              :disabled="!canSaveLokasi"
              @click="saveLokasi"
            >
              {{ savingLokasi ? "Menyimpan…" : "Simpan" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div
      v-if="showDeleteLokasiModal"
      class="fixed inset-0 z-[710] flex items-center justify-center p-4"
      @click="closeDeleteLokasi"
    >
      <div class="absolute inset-0 bg-black/50"></div>
      <div
        class="relative w-full max-w-sm bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-2xl shadow-2xl p-5"
        @click.stop
      >
        <h3 class="text-base font-semibold text-[var(--wa-text-primary)]">Hapus Lokasi</h3>
        <p class="text-sm text-[var(--wa-text-tertiary)] mt-2">
          Hapus lokasi
          <span class="text-[var(--wa-text-primary)] font-medium">{{ deleteTarget?.nama || "" }}</span>?
        </p>
        <p v-if="deleteMsg" class="text-xs text-red-400 mt-2">{{ deleteMsg }}</p>
        <div class="flex gap-2 pt-4">
          <button
            type="button"
            class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-[var(--wa-bg-secondary)] text-[var(--wa-text-primary)]"
            @click="closeDeleteLokasi"
          >
            Batal
          </button>
          <button
            type="button"
            class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-red-500 text-white disabled:opacity-50"
            :disabled="deletingLokasi"
            @click="confirmDeleteLokasi"
          >
            {{ deletingLokasi ? "Menghapus…" : "Hapus" }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div
      v-if="showDeliveryRequestModal"
      class="fixed inset-0 z-[700] flex items-center justify-center p-4"
      @click="closeDeliveryRequest"
    >
      <div class="absolute inset-0 bg-black/50"></div>
      <div
        class="relative w-full max-w-sm bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-2xl shadow-2xl p-5 max-h-[90vh] overflow-y-auto"
        @click.stop
      >
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base font-semibold text-[var(--wa-text-primary)]">Delivery Request</h3>
          <button
            type="button"
            class="p-1 text-[var(--wa-icon-default)] hover:text-[var(--wa-accent-green)]"
            @click="closeDeliveryRequest"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--wa-text-tertiary)] mb-2">Jenis</p>
        <div class="grid grid-cols-1 gap-2 mb-4">
          <button
            v-for="opt in deliveryJenisOptions"
            :key="opt.id"
            type="button"
            class="w-full py-2.5 rounded-xl text-sm font-bold border"
            :class="deliveryJenis === opt.id
              ? 'bg-[var(--wa-accent-green)] text-white border-transparent'
              : 'bg-[var(--wa-bg-secondary)] text-[var(--wa-text-primary)] border-[var(--wa-border)]'"
            @click="deliveryJenis = opt.id"
          >
            {{ opt.label }}
          </button>
        </div>

        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--wa-text-tertiary)] mb-2">Lokasi</p>
        <p v-if="lokasiLoading" class="text-xs text-[var(--wa-text-tertiary)] mb-3">Memuat lokasi…</p>
        <p v-else-if="!lokasiItems.length" class="text-xs text-[var(--wa-text-tertiary)] mb-3">
          Belum ada lokasi. Tambah lokasi dulu di Customer Panel.
        </p>
        <div v-else class="space-y-2 mb-3 max-h-48 overflow-y-auto">
          <button
            v-for="loc in lokasiItems"
            :key="'dr-' + loc.id_lokasi"
            type="button"
            class="w-full text-left rounded-xl p-3 border"
            :class="deliveryLokasiId === loc.id_lokasi
              ? 'border-[var(--wa-accent-green)] bg-[var(--wa-bg-secondary)]'
              : 'border-[var(--wa-border)] bg-[var(--wa-bg-secondary)]'"
            @click="deliveryLokasiId = loc.id_lokasi"
          >
            <p class="text-sm font-medium text-[var(--wa-text-primary)] truncate">{{ loc.nama }}</p>
            <p class="text-xs text-[var(--wa-text-tertiary)] mt-0.5 break-words">{{ loc.detail }}</p>
          </button>
        </div>

        <div class="mb-3">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--wa-text-tertiary)]">Catatan untuk Kurir</label>
          <textarea
            v-model="deliveryCatatan"
            rows="2"
            maxlength="150"
            placeholder="Opsional — patokan / instruksi untuk driver"
            class="mt-1 w-full px-3 py-2 rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-secondary)] text-sm text-[var(--wa-text-primary)] placeholder-[var(--wa-text-tertiary)] focus:outline-none focus:border-[var(--wa-accent-green)] resize-none"
          ></textarea>
          <p class="text-[11px] text-[var(--wa-text-tertiary)] mt-1">Maks. 150 karakter. Kosongkan jika tidak perlu.</p>
        </div>

        <p v-if="deliveryFormMsg" class="text-xs text-red-400 mb-3">{{ deliveryFormMsg }}</p>
        <div class="flex gap-2">
          <button
            type="button"
            class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-[var(--wa-bg-secondary)] text-[var(--wa-text-primary)]"
            @click="closeDeliveryRequest"
          >
            Batal
          </button>
          <button
            type="button"
            class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-[var(--wa-accent-green)] text-white disabled:opacity-50"
            :disabled="submittingDelivery || !deliveryJenis || !deliveryLokasiId"
            @click="submitDeliveryRequest"
          >
            {{ submittingDelivery ? "Mengirim…" : "Kirim" }}
          </button>
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
