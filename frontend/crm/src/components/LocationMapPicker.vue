<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from "vue";
import { Loader } from "@googlemaps/js-api-loader";

const lat = defineModel("lat", { type: Number, default: null });
const lng = defineModel("lng", { type: Number, default: null });

const DEFAULT_CENTER = { lat: -6.2088, lng: 106.8456 };
const DEFAULT_ZOOM = 15;
const SELECT_ZOOM = 17;

const mapEl = ref(null);
const autocompleteHost = ref(null);
const loading = ref(true);
const error = ref("");

let mapInstance = null;
let idleListener = null;
let autocompleteEl = null;
let suppressIdle = false;
let lastEmitted = { lat: null, lng: null };
let destroyed = false;

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

const initMap = async () => {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
  if (!apiKey) {
    error.value = "API key Google Maps belum dikonfigurasi (VITE_GOOGLE_MAPS_API_KEY).";
    loading.value = false;
    return;
  }

  if (!mapEl.value || !autocompleteHost.value) return;

  try {
    const loader = new Loader({
      apiKey,
      version: "weekly",
    });
    const { Map } = await loader.importLibrary("maps");
    const { PlaceAutocompleteElement } = await loader.importLibrary("places");
    if (destroyed) return;

    const startLat = lat.value != null ? lat.value : DEFAULT_CENTER.lat;
    const startLng = lng.value != null ? lng.value : DEFAULT_CENTER.lng;
    const hasCoords = lat.value != null && lng.value != null;

    mapInstance = new Map(mapEl.value, {
      center: { lat: startLat, lng: startLng },
      zoom: hasCoords ? SELECT_ZOOM : DEFAULT_ZOOM,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false,
    });

    autocompleteEl = new PlaceAutocompleteElement({
      includedRegionCodes: ["id"],
    });
    autocompleteEl.placeholder = "Cari alamat…";
    autocompleteHost.value.appendChild(autocompleteEl);

    autocompleteEl.addEventListener("gmp-placeselect", async ({ place }) => {
      if (!place || !mapInstance) return;
      try {
        await place.fetchFields({ fields: ["location", "formattedAddress"] });
        if (place.location) {
          panToCoords(place.location.lat(), place.location.lng(), SELECT_ZOOM);
        }
      } catch (_) {
        error.value = "Gagal memuat detail lokasi dari pencarian.";
      }
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
  await nextTick();
  await initMap();
});

onUnmounted(() => {
  destroyed = true;
  if (idleListener && window.google?.maps?.event) {
    google.maps.event.removeListener(idleListener);
    idleListener = null;
  }
  if (autocompleteEl?.remove) {
    autocompleteEl.remove();
    autocompleteEl = null;
  }
  mapInstance = null;
});
</script>

<template>
  <div class="space-y-2">
    <label class="text-xs text-[var(--wa-text-tertiary)]">Titik lokasi di peta</label>
    <div
      ref="autocompleteHost"
      class="location-autocomplete-host w-full rounded-lg border border-[var(--wa-border)] bg-[var(--wa-bg-secondary)] overflow-hidden"
    ></div>

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
      Cari alamat atau geser peta agar pin berada di titik yang tepat.
    </p>
  </div>
</template>

<style scoped>
.location-autocomplete-host :deep(gmp-place-autocomplete) {
  width: 100%;
  color-scheme: dark;
}

.location-autocomplete-host :deep(input) {
  width: 100%;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  background: transparent;
  color: var(--wa-text-primary);
  border: none;
  outline: none;
}

.location-autocomplete-host :deep(input::placeholder) {
  color: var(--wa-text-tertiary);
}
</style>
