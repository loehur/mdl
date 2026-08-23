<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from "vue";
import { Loader } from "@googlemaps/js-api-loader";

const lat = defineModel("lat", { type: Number, default: null });
const lng = defineModel("lng", { type: Number, default: null });

const props = defineProps({
  apiBase: { type: String, required: true },
});

const DEFAULT_CENTER = { lat: -6.2088, lng: 106.8456 };
const DEFAULT_ZOOM = 15;
const SELECT_ZOOM = 17;

const mapEl = ref(null);
const searchWrap = ref(null);
const searchQuery = ref("");
const suggestions = ref([]);
const showSuggestions = ref(false);
const searching = ref(false);
const selectingPlace = ref(false);
const loading = ref(true);
const locatingUser = ref(false);
const error = ref("");
const geoHint = ref("");

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

const getUserLocation = () =>
  new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error("unsupported"));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) =>
        resolve({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
        }),
      (err) => reject(err),
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
    );
  });

const goToMyLocation = async () => {
  if (!mapInstance || locatingUser.value) return;
  locatingUser.value = true;
  geoHint.value = "";
  try {
    const pos = await getUserLocation();
    panToCoords(pos.lat, pos.lng, SELECT_ZOOM);
  } catch (_) {
    geoHint.value = "Tidak bisa mengakses lokasi perangkat. Izinkan akses lokasi/GPS di browser.";
  } finally {
    locatingUser.value = false;
  }
};

const resolveStartCenter = async (hasCoords) => {
  if (hasCoords) {
    return { lat: lat.value, lng: lng.value, fromDevice: false };
  }
  try {
    const pos = await getUserLocation();
    return { ...pos, fromDevice: true };
  } catch (_) {
    geoHint.value = "Lokasi perangkat tidak tersedia. Peta dimulai dari Jakarta — gunakan tombol lokasi atau cari alamat.";
    return { ...DEFAULT_CENTER, fromDevice: false };
  }
};

const getMapBiasCoords = () => {
  if (!mapInstance) {
    return { lat: lat.value, lng: lng.value };
  }
  const center = mapInstance.getCenter();
  if (!center) {
    return { lat: lat.value, lng: lng.value };
  }
  return { lat: center.lat(), lng: center.lng() };
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
  const bias = getMapBiasCoords();
  const payload = { input: q };
  if (bias.lat != null && bias.lng != null) {
    payload.lat = bias.lat;
    payload.lng = bias.lng;
  }

  try {
    const res = await fetch(`${props.apiBase}/Laundry/MapsConfig/autocomplete`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    }).then((r) => r.json());

    if (seq !== searchSeq || destroyed) return;

    if (!res?.ok && !res?.status) {
      suggestions.value = [];
      geoHint.value = res?.message || "Gagal memuat saran alamat.";
      return;
    }

    geoHint.value = "";
    suggestions.value = Array.isArray(res.items) ? res.items : [];
    showSuggestions.value = suggestions.value.length > 0;
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
};

const selectSuggestion = async (item) => {
  if (!item?.place_id || selectingPlace.value) return;
  selectingPlace.value = true;
  searchQuery.value = item.label || "";
  closeSuggestions();
  geoHint.value = "";

  try {
    const res = await fetch(`${props.apiBase}/Laundry/MapsConfig/placeDetails`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ place_id: item.place_id }),
    }).then((r) => r.json());

    if (!res?.ok && !res?.status) {
      geoHint.value = res?.message || "Gagal memuat detail lokasi.";
      return;
    }

    if (res.lat != null && res.lng != null) {
      panToCoords(res.lat, res.lng, SELECT_ZOOM);
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
      zoom: hasCoords || start.fromDevice ? SELECT_ZOOM : DEFAULT_ZOOM,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false,
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
    error.value = err?.message || "Gagal memuat Google Maps.";
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
  <div class="space-y-2">
    <label class="text-xs text-[var(--wa-text-tertiary)]">Cari alamat</label>
    <div ref="searchWrap" class="relative z-[800]">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Ketik nama jalan, tempat, atau alamat…"
        class="w-full rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-secondary)] px-3 py-2 text-sm text-[var(--wa-text-primary)] placeholder-[var(--wa-text-tertiary)] focus:border-[var(--wa-accent-green)] focus:outline-none"
        autocomplete="off"
        @input="onSearchInput"
        @focus="onSearchInput"
      />
      <p v-if="searching" class="mt-1 text-[11px] text-[var(--wa-text-tertiary)]">Mencari…</p>
      <ul
        v-if="showSuggestions && suggestions.length"
        class="absolute left-0 right-0 top-full z-[900] mt-1 max-h-56 overflow-y-auto rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-panel)] shadow-2xl"
      >
        <li v-for="item in suggestions" :key="item.place_id">
          <button
            type="button"
            class="w-full px-3 py-2.5 text-left text-sm text-[var(--wa-text-primary)] hover:bg-[var(--wa-bg-secondary)]"
            @click="selectSuggestion(item)"
          >
            {{ item.label }}
          </button>
        </li>
      </ul>
    </div>

    <label class="text-xs text-[var(--wa-text-tertiary)]">Titik lokasi di peta</label>
    <div class="relative rounded-lg overflow-hidden border border-[var(--wa-border)]">
      <div ref="mapEl" class="h-[280px] w-full bg-[var(--wa-bg-secondary)]"></div>
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
      <button
        type="button"
        class="absolute right-3 bottom-3 z-20 flex h-10 w-10 items-center justify-center rounded-full border border-[var(--wa-border)] bg-[var(--wa-bg-panel)] text-[var(--wa-text-primary)] shadow-lg transition hover:border-[var(--wa-accent-green)] hover:text-[var(--wa-accent-green)] disabled:opacity-50"
        :disabled="loading || locatingUser"
        title="Ke lokasi saya"
        aria-label="Ke lokasi saya"
        @click="goToMyLocation"
      >
        <svg
          v-if="!locatingUser"
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M12 8a3 3 0 100 6 3 3 0 000-6zm8.94 3a8.94 8.94 0 01-1.88 2.83l1.42 1.42a.75.75 0 11-1.06 1.06l-1.42-1.42A8.94 8.94 0 0112 20.94V22a.75.75 0 01-1.5 0v-1.06A8.94 8.94 0 014.06 15.3l-1.42 1.42a.75.75 0 11-1.06-1.06l1.42-1.42A8.94 8.94 0 013.06 12H2a.75.75 0 010-1.5h1.06A8.94 8.94 0 014.94 6.7L3.52 5.28a.75.75 0 111.06-1.06l1.42 1.42A8.94 8.94 0 0112 3.06V2a.75.75 0 011.5 0v1.06A8.94 8.94 0 0119.94 8.7l1.42-1.42a.75.75 0 111.06 1.06l-1.42 1.42A8.94 8.94 0 0120.94 12H22a.75.75 0 010 1.5h-1.06z"
          />
        </svg>
        <svg
          v-else
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5 animate-spin"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
      </button>
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
