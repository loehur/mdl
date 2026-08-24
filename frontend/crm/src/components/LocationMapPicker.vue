<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from "vue";
import { Loader } from "@googlemaps/js-api-loader";

const lat = defineModel("lat", { type: Number, default: null });
const lng = defineModel("lng", { type: Number, default: null });

const props = defineProps({
  apiBase: { type: String, required: true },
  mapHeightClass: { type: String, default: "h-[280px]" },
  layout: { type: String, default: "stacked" },
  custId: { type: Number, default: 0 },
});

const DEFAULT_CENTER = { lat: -6.2088, lng: 106.8456 };
const DEFAULT_ZOOM = 15;
const SELECT_ZOOM = 17;
const KOTA_SEARCH_RADIUS_KM = 50;

const mapEl = ref(null);
const searchWrap = ref(null);
const searchQuery = ref("");
const suggestions = ref([]);
const showSuggestions = ref(false);
const searching = ref(false);
const selectingPlace = ref(false);
const loading = ref(true);
const error = ref("");
const geoHint = ref("");
const searchCenter = ref({ lat: null, lng: null, label: "" });

let mapInstance = null;
let idleListener = null;
let suppressIdle = false;
let lastEmitted = { lat: null, lng: null };
let destroyed = false;
let searchTimer = null;
let searchSeq = 0;

const roundCoord = (value) => Math.round(Number(value) * 1e7) / 1e7;

const emitCoords = (nextLat, nextLng) => {
  const roundedLat = roundCoord(nextLat);
  const roundedLng = roundCoord(nextLng);
  lastEmitted = { lat: roundedLat, lng: roundedLng };
  lat.value = roundedLat;
  lng.value = roundedLng;
};

const readCenterCoords = () => {
  if (!mapInstance) return;
  const center = mapInstance.getCenter();
  if (!center) return;
  emitCoords(center.lat(), center.lng());
};

const panToCoords = (nextLat, nextLng, zoom = null) => {
  if (!mapInstance || nextLat == null || nextLng == null) return;
  suppressIdle = true;
  mapInstance.panTo({ lat: nextLat, lng: nextLng });
  if (zoom != null) {
    mapInstance.setZoom(zoom);
  }
  window.setTimeout(() => {
    suppressIdle = false;
    readCenterCoords();
  }, 350);
};

const fetchKotaFallback = async () => {
  if (props.custId <= 0) return null;
  try {
    const res = await fetch(
      `${props.apiBase}/Laundry/PelangganLokasi/defaultMap?cust_id=${props.custId}`
    ).then((r) => r.json());
    if (!res?.ok && !res?.status) return null;
    const nextLat = res.latt != null ? Number(res.latt) : null;
    const nextLng = res.longt != null ? Number(res.longt) : null;
    if (nextLat == null || nextLng == null || Number.isNaN(nextLat) || Number.isNaN(nextLng)) {
      return null;
    }
    return {
      lat: nextLat,
      lng: nextLng,
      label: String(res.nama_kota || "").trim(),
    };
  } catch (_) {
    return null;
  }
};

const loadSearchCenter = async () => {
  const kota = await fetchKotaFallback();
  if (kota) {
    searchCenter.value = {
      lat: kota.lat,
      lng: kota.lng,
      label: kota.label || "kota cabang",
    };
    return searchCenter.value;
  }
  searchCenter.value = {
    lat: DEFAULT_CENTER.lat,
    lng: DEFAULT_CENTER.lng,
    label: "Jakarta",
  };
  return searchCenter.value;
};

const resolveStartCenter = async (hasCoords) => {
  if (hasCoords) {
    return { lat: lat.value, lng: lng.value };
  }
  const center = await loadSearchCenter();
  geoHint.value = center.label
    ? `Peta dimulai dari ${center.label}. Pencarian maks. ${KOTA_SEARCH_RADIUS_KM} km dari pusat kota.`
    : `Pencarian maks. ${KOTA_SEARCH_RADIUS_KM} km dari pusat kota.`;
  return { lat: center.lat, lng: center.lng };
};

const getSearchBiasCoords = () => {
  const c = searchCenter.value;
  if (c.lat != null && c.lng != null && !Number.isNaN(c.lat) && !Number.isNaN(c.lng)) {
    return { lat: c.lat, lng: c.lng };
  }
  return null;
};

const fetchSuggestions = async (query) => {
  const q = query.trim();
  if (q.length < 2) {
    suggestions.value = [];
    searching.value = false;
    return;
  }

  const seq = ++searchSeq;
  searching.value = true;
  geoHint.value = "";

  const payload = { input: q };
  if (props.custId > 0) {
    payload.cust_id = props.custId;
  }
  const bias = getSearchBiasCoords();
  if (bias) {
    payload.lat = bias.lat;
    payload.lng = bias.lng;
  }

  try {
    const res = await fetch(`${props.apiBase}/Laundry/MapsConfig/autocomplete`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    }).then((r) => r.json());

    if (seq !== searchSeq || destroyed || selectingPlace.value) return;

    if (!res?.ok && !res?.status) {
      suggestions.value = [];
      geoHint.value = res?.message || "Gagal memuat saran alamat.";
      return;
    }

    suggestions.value = Array.isArray(res.items) ? res.items : [];
    if (selectingPlace.value) return;
    showSuggestions.value = suggestions.value.length > 0;
    if (!suggestions.value.length) {
      geoHint.value = `Tidak ada hasil dalam radius ${KOTA_SEARCH_RADIUS_KM} km dari pusat kota.`;
    }
  } catch (_) {
    if (seq !== searchSeq) return;
    suggestions.value = [];
    geoHint.value = "Gagal memuat saran alamat.";
  } finally {
    if (seq === searchSeq) {
      searching.value = false;
    }
  }
};

const onSearchInput = () => {
  if (searchTimer) {
    clearTimeout(searchTimer);
  }
  const q = searchQuery.value.trim();
  if (q.length < 2) {
    suggestions.value = [];
    showSuggestions.value = false;
    searching.value = false;
    return;
  }
  showSuggestions.value = true;
  searchTimer = window.setTimeout(() => {
    fetchSuggestions(q);
  }, 280);
};

const closeSuggestions = () => {
  showSuggestions.value = false;
  suggestions.value = [];
};

const dismissSuggestions = () => {
  if (searchTimer) {
    clearTimeout(searchTimer);
    searchTimer = null;
  }
  searchSeq++;
  searching.value = false;
  closeSuggestions();
};

const selectSuggestion = async (item) => {
  if (!item?.place_id || selectingPlace.value) return;
  selectingPlace.value = true;
  searchQuery.value = item.label || "";
  dismissSuggestions();
  geoHint.value = "";

  try {
    const detailsPayload = { place_id: item.place_id };
    if (props.custId > 0) {
      detailsPayload.cust_id = props.custId;
    }
    const res = await fetch(`${props.apiBase}/Laundry/MapsConfig/placeDetails`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(detailsPayload),
    }).then((r) => r.json());

    if (!res?.ok && !res?.status) {
      geoHint.value = res?.message || "Gagal memuat detail lokasi.";
      return;
    }

    if (res.lat != null && res.lng != null) {
      panToCoords(res.lat, res.lng, SELECT_ZOOM);
    } else {
      geoHint.value = "Koordinat lokasi tidak ditemukan.";
    }
  } catch (_) {
    geoHint.value = "Gagal memuat detail lokasi.";
  } finally {
    selectingPlace.value = false;
  }
};

const onDocumentClick = (event) => {
  const wrap = searchWrap.value;
  if (!wrap || wrap.contains(event.target)) return;
  closeSuggestions();
};

const initMap = async () => {
  if (!mapEl.value) return;

  window.gm_authFailure = () => {
    error.value =
      "Google Maps menolak API key browser. Aktifkan Maps JavaScript API + Map Tiles API di project key browser, dan tambahkan referrer https://api.nalju.com/*";
  };

  let apiKey = "";
  try {
    const res = await fetch(`${props.apiBase}/Laundry/MapsConfig/get`).then((r) => r.json());
    if (!res?.ok && !res?.status) {
      error.value = res?.message || "Gagal memuat konfigurasi Google Maps dari server.";
      loading.value = false;
      return;
    }
    apiKey = String(res.api_key || "").trim();
  } catch (_) {
    error.value = "Gagal memuat konfigurasi Google Maps dari server.";
    loading.value = false;
    return;
  }

  if (!apiKey) {
    error.value = "Google Maps API key belum dikonfigurasi di server.";
    loading.value = false;
    return;
  }

  try {
    const loader = new Loader({
      apiKey,
      version: "weekly",
      language: "id",
      region: "ID",
      authReferrerPolicy: "origin",
    });
    const { Map } = await loader.importLibrary("maps");
    if (destroyed) return;

    const hasCoords = lat.value != null && lng.value != null;
    const start = await resolveStartCenter(hasCoords);
    if (destroyed) return;
    const startLat = start.lat;
    const startLng = start.lng;

    mapInstance = new Map(mapEl.value, {
      center: { lat: startLat, lng: startLng },
      zoom: hasCoords ? SELECT_ZOOM : DEFAULT_ZOOM,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false,
      cameraControl: false,
      zoomControl: false,
    });

    idleListener = mapInstance.addListener("idle", () => {
      if (suppressIdle) return;
      readCenterCoords();
    });

    if (hasCoords) {
      emitCoords(startLat, startLng);
    } else {
      readCenterCoords();
    }
  } catch (err) {
    error.value =
      (err?.message || "Gagal memuat Google Maps.") +
      " Cek: " +
      props.apiBase +
      "/Laundry/MapsConfig/diagnose";
  } finally {
    loading.value = false;
  }
};

watch(
  () => [lat.value, lng.value],
  ([nextLat, nextLng]) => {
    if (nextLat == null || nextLng == null || !mapInstance) return;
    if (nextLat === lastEmitted.lat && nextLng === lastEmitted.lng) return;
    panToCoords(nextLat, nextLng, mapInstance.getZoom());
  }
);

onMounted(async () => {
  document.addEventListener("click", onDocumentClick);
  await nextTick();
  await initMap();
});

onUnmounted(() => {
  destroyed = true;
  if (window.gm_authFailure) {
    delete window.gm_authFailure;
  }
  document.removeEventListener("click", onDocumentClick);
  if (searchTimer) {
    clearTimeout(searchTimer);
    searchTimer = null;
  }
  if (idleListener && window.google?.maps?.event) {
    google.maps.event.removeListener(idleListener);
    idleListener = null;
  }
  mapInstance = null;
});
</script>

<template>
  <div v-if="layout === 'modal'" class="grid grid-cols-1 lg:grid-cols-2 gap-5 lg:gap-6">
    <div class="space-y-3">
      <slot name="form" />
      <slot name="form-after-search" />
      <div class="space-y-2">
        <label class="text-xs text-[var(--wa-text-tertiary)]">Cari alamat</label>
        <div ref="searchWrap" class="relative z-[800]">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Ketik nama jalan, tempat, atau alamat…"
            class="w-full rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-secondary)] px-3 py-2 pr-10 text-sm text-[var(--wa-text-primary)] placeholder-[var(--wa-text-tertiary)] focus:border-[var(--wa-accent-green)] focus:outline-none"
            autocomplete="off"
            :aria-busy="searching || selectingPlace"
            @input="onSearchInput"
            @focus="onSearchInput"
            @keydown.escape="dismissSuggestions"
          />
          <div
            v-if="searching || selectingPlace"
            class="pointer-events-none absolute right-3 top-1/2 z-[10] -translate-y-1/2"
            aria-hidden="true"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4 animate-spin text-[var(--wa-accent-green)]"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
          </div>
          <ul
            v-if="showSuggestions && suggestions.length"
            class="absolute left-0 right-0 top-full z-[900] mt-1 max-h-40 overflow-y-auto rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-panel)] shadow-2xl"
          >
            <li v-for="item in suggestions" :key="item.place_id">
              <button
                type="button"
                class="w-full px-3 py-2.5 text-left text-sm text-[var(--wa-text-primary)] hover:bg-[var(--wa-bg-secondary)]"
                @mousedown.prevent
                @click="selectSuggestion(item)"
              >
                {{ item.label }}
              </button>
            </li>
          </ul>
        </div>
        <p v-if="geoHint && !error" class="text-[11px] text-amber-400/90">{{ geoHint }}</p>
      </div>
    </div>

    <div class="min-h-0 space-y-2">
      <label class="text-xs text-[var(--wa-text-tertiary)]">Titik lokasi di peta</label>
      <div class="relative rounded-lg overflow-hidden border border-[var(--wa-border)]">
        <div ref="mapEl" class="w-full bg-[var(--wa-bg-secondary)]" :class="mapHeightClass"></div>
        <div
          class="pointer-events-none absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-full"
          aria-hidden="true"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 36" class="h-9 w-6 drop-shadow-md">
            <path
              fill="#ef4444"
              stroke="#fff"
              stroke-width="1.5"
              d="M12 0C7.03 0 3 4.03 3 9c0 6.75 9 15 9 15s9-8.25 9-15c0-4.97-4.03-9-9-9zm0 12.5a3.5 3.5 0 110-7 3.5 3.5 0 010 7z"
            />
          </svg>
        </div>
        <div
          v-if="loading"
          class="absolute inset-0 flex items-center justify-center bg-black/20 text-xs text-[var(--wa-text-primary)]"
        >
          Memuat peta…
        </div>
      </div>

      <p v-if="error" class="text-[11px] text-red-400">{{ error }}</p>
      <p v-else-if="lat != null && lng != null" class="text-[11px] text-[var(--wa-accent-green)] font-mono">
        {{ lat }}, {{ lng }}
      </p>
      <p v-else class="text-[11px] text-[var(--wa-text-tertiary)]">
        Geser peta agar pin berada di titik yang tepat.
      </p>
    </div>
  </div>

  <div v-else class="space-y-2">
    <label class="text-xs text-[var(--wa-text-tertiary)]">Cari alamat</label>
    <div ref="searchWrap" class="relative z-[800]">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Ketik nama jalan, tempat, atau alamat…"
        class="w-full rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-secondary)] px-3 py-2 pr-10 text-sm text-[var(--wa-text-primary)] placeholder-[var(--wa-text-tertiary)] focus:border-[var(--wa-accent-green)] focus:outline-none"
        autocomplete="off"
        :aria-busy="searching || selectingPlace"
        @input="onSearchInput"
        @focus="onSearchInput"
        @keydown.escape="dismissSuggestions"
      />
      <div
        v-if="searching || selectingPlace"
        class="pointer-events-none absolute right-3 top-1/2 z-[10] -translate-y-1/2"
        aria-hidden="true"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-4 w-4 animate-spin text-[var(--wa-accent-green)]"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
      </div>
      <ul
        v-if="showSuggestions && suggestions.length"
        class="absolute left-0 right-0 top-full z-[900] mt-1 max-h-40 overflow-y-auto rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-panel)] shadow-2xl"
      >
        <li v-for="item in suggestions" :key="item.place_id">
          <button
            type="button"
            class="w-full px-3 py-2.5 text-left text-sm text-[var(--wa-text-primary)] hover:bg-[var(--wa-bg-secondary)]"
            @mousedown.prevent
            @click="selectSuggestion(item)"
          >
            {{ item.label }}
          </button>
        </li>
      </ul>
    </div>

    <label class="text-xs text-[var(--wa-text-tertiary)]">Titik lokasi di peta</label>
    <div class="relative rounded-lg overflow-hidden border border-[var(--wa-border)]">
      <div ref="mapEl" class="w-full bg-[var(--wa-bg-secondary)]" :class="mapHeightClass"></div>
      <div
        class="pointer-events-none absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-full"
        aria-hidden="true"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 36" class="h-9 w-6 drop-shadow-md">
          <path
            fill="#ef4444"
            stroke="#fff"
            stroke-width="1.5"
            d="M12 0C7.03 0 3 4.03 3 9c0 6.75 9 15 9 15s9-8.25 9-15c0-4.97-4.03-9-9-9zm0 12.5a3.5 3.5 0 110-7 3.5 3.5 0 010 7z"
          />
        </svg>
      </div>
      <div
        v-if="loading"
        class="absolute inset-0 flex items-center justify-center bg-black/20 text-xs text-[var(--wa-text-primary)]"
      >
        Memuat peta…
      </div>
    </div>

    <p v-if="error" class="text-[11px] text-red-400">{{ error }}</p>
    <p v-else-if="geoHint" class="text-[11px] text-amber-400/90">{{ geoHint }}</p>
    <p v-else-if="lat != null && lng != null" class="text-[11px] text-[var(--wa-accent-green)] font-mono">
      {{ lat }}, {{ lng }}
    </p>
    <p v-else class="text-[11px] text-[var(--wa-text-tertiary)]">
      Cari alamat atau geser peta agar pin berada di titik yang tepat.
    </p>
  </div>
</template>
