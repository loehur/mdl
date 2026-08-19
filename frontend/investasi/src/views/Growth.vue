<template>
  <div class="space-y-4">
    <section class="glass-strong p-4">
      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="label-caps">Tahun</p>
          <p class="mt-1 font-display text-xl font-bold text-pearl">{{ year }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button class="btn-icon" type="button" title="Tahun sebelumnya" @click="changeYear(-1)">
            ‹
          </button>
          <button class="btn-icon" type="button" title="Tahun berikutnya" :disabled="year >= currentYear" @click="changeYear(1)">
            ›
          </button>
        </div>
      </div>
      <p class="mt-2 text-sm text-mist">Garis nilai portfolio akhir bulan (dalam juta)</p>
    </section>

    <section class="glass relative min-h-[320px] overflow-hidden p-3">
      <div
        v-if="loading"
        class="absolute inset-0 z-10 flex items-center justify-center bg-ink-50/90"
      >
        <PageLoader />
      </div>

      <EmptyState
        v-if="!loading && pointCount === 0"
        title="Belum ada data"
        :subtitle="`Tidak ada snapshot portfolio di tahun ${year}.`"
      />

      <div
        v-show="!loading && pointCount > 0"
        ref="chartContainer"
        class="growth-chart"
      />
    </section>

    <section v-if="!loading && monthSummary" class="glass-strong p-4">
      <p class="label-caps">{{ monthSummary.label }}</p>
      <p class="money-display-sm mt-2">{{ formatRupiah(monthSummary.value) }}</p>
      <p class="mt-1 text-sm font-semibold text-credit-dim">{{ formatChartMillion(monthSummary.value) }}</p>
      <p class="mt-3 text-xs text-mist">{{ monthSummary.snapshot_count }} snapshot di bulan ini</p>
    </section>

    <AlertBanner :message="error" type="error" />
  </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import {
  AreaSeries,
  LineType,
  createChart,
  createSeriesMarkers,
} from "lightweight-charts";
import { formatChartMillion, formatChartMillionAxis, formatRupiah } from "../utils/format";
import AlertBanner from "../components/AlertBanner.vue";
import EmptyState from "../components/EmptyState.vue";
import PageLoader from "../components/PageLoader.vue";

const MONTH_LABELS = [
  "Jan", "Feb", "Mar", "Apr", "Mei", "Jun",
  "Jul", "Agu", "Sep", "Okt", "Nov", "Des",
];

const currentYear = new Date().getFullYear();
const year = ref(currentYear);
const loading = ref(true);
const error = ref("");
const months = ref([]);
const pointCount = ref(0);
const monthSummary = ref(null);
const chartContainer = ref(null);

let chart = null;
let series = null;
let seriesMarkers = null;
let resizeObserver = null;
let renderRetryTimer = null;
let crosshairHandler = null;

function monthTime(yearValue, month) {
  return { year: yearValue, month, day: 1 };
}

function toNumber(value) {
  const num = Number(value);
  return Number.isFinite(num) ? num : null;
}

function buildPoints(items, yearValue) {
  return items
    .filter((item) => Number(item.snapshot_count) > 0)
    .map((item) => {
      const rawValue = toNumber(item.close);
      return {
        time: monthTime(yearValue, Number(item.month)),
        value: rawValue === null ? null : rawValue / 1_000_000,
        rawValue,
        month: Number(item.month),
        snapshot_count: Number(item.snapshot_count),
      };
    })
    .filter((item) => item.value !== null && item.rawValue !== null);
}

function setMonthSummary(point) {
  if (!point) {
    monthSummary.value = null;
    return;
  }

  monthSummary.value = {
    label: MONTH_LABELS[point.month - 1],
    value: point.rawValue,
    snapshot_count: point.snapshot_count,
  };
}

function sameTime(a, b) {
  return a.year === b.year && a.month === b.month && a.day === b.day;
}

function destroyChart() {
  if (renderRetryTimer) {
    clearTimeout(renderRetryTimer);
    renderRetryTimer = null;
  }
  if (chart && crosshairHandler) {
    chart.unsubscribeCrosshairMove(crosshairHandler);
  }
  crosshairHandler = null;
  seriesMarkers = null;
  resizeObserver?.disconnect();
  resizeObserver = null;
  chart?.remove();
  chart = null;
  series = null;
}

function renderChart(points) {
  destroyChart();

  if (!chartContainer.value || points.length === 0) return;

  const width = chartContainer.value.clientWidth;
  if (width <= 0) {
    renderRetryTimer = window.setTimeout(() => renderChart(points), 50);
    return;
  }

  try {
    chart = createChart(chartContainer.value, {
      width,
      height: 320,
      layout: {
        background: { color: "#ffffff" },
        textColor: "#64748b",
        fontFamily: '"Barlow", system-ui, sans-serif',
        fontSize: 12,
        attributionLogo: false,
      },
      grid: {
        vertLines: { color: "rgba(209, 219, 232, 0.55)" },
        horzLines: { color: "rgba(209, 219, 232, 0.55)" },
      },
      rightPriceScale: {
        borderColor: "rgba(209, 219, 232, 0.8)",
      },
      timeScale: {
        borderColor: "rgba(209, 219, 232, 0.8)",
        fixLeftEdge: true,
        fixRightEdge: true,
      },
      crosshair: {
        vertLine: { color: "rgba(74, 143, 212, 0.35)" },
        horzLine: { color: "rgba(74, 143, 212, 0.35)" },
      },
      localization: {
        priceFormatter: formatChartMillionAxis,
      },
    });

    series = chart.addSeries(AreaSeries, {
      lineType: LineType.Curved,
      lineColor: "#0d9488",
      topColor: "rgba(13, 148, 136, 0.32)",
      bottomColor: "rgba(13, 148, 136, 0.02)",
      lineWidth: 2,
      lastValueVisible: false,
      priceLineVisible: false,
      crosshairMarkerVisible: true,
    });

    const lineData = points.map((point) => ({
      time: point.time,
      value: point.value,
    }));

    series.setData(lineData);
    chart.timeScale().fitContent();

    seriesMarkers = createSeriesMarkers(
      series,
      points.map((point) => ({
        time: point.time,
        position: "aboveBar",
        shape: "circle",
        color: "#0d9488",
        text: formatChartMillion(point.rawValue),
        size: 0.5,
      })),
      { autoScale: true }
    );

    crosshairHandler = (param) => {
      if (!param.time || !param.seriesData.size) {
        setMonthSummary(points[points.length - 1] ?? null);
        return;
      }

      const hit = param.seriesData.get(series);
      if (!hit) return;

      const point = points.find((item) => sameTime(item.time, param.time));
      if (point) setMonthSummary(point);
    };

    chart.subscribeCrosshairMove(crosshairHandler);

    resizeObserver = new ResizeObserver(() => {
      if (!chartContainer.value || !chart) return;
      const nextWidth = chartContainer.value.clientWidth;
      if (nextWidth > 0) {
        chart.applyOptions({ width: nextWidth });
      }
    });
    resizeObserver.observe(chartContainer.value);
  } catch (err) {
    destroyChart();
    throw err;
  }
}

async function scheduleRender(points) {
  await nextTick();
  await new Promise((resolve) => requestAnimationFrame(resolve));
  try {
    renderChart(points);
  } catch (err) {
    error.value = err.message || "Gagal merender grafik";
    destroyChart();
  }
}

async function loadChart() {
  loading.value = true;
  error.value = "";
  monthSummary.value = null;
  pointCount.value = 0;
  destroyChart();

  try {
    const res = await fetch(`/api/Investasi/Portfolio/chart?year=${year.value}`);
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal memuat grafik");

    const availableYears = (data.data.years || []).map(Number);
    if (availableYears.length > 0 && !availableYears.includes(year.value)) {
      year.value = availableYears[0];
      return;
    }

    months.value = data.data.months || [];
    const points = buildPoints(months.value, year.value);
    pointCount.value = points.length;

    if (points.length > 0) {
      setMonthSummary(points[points.length - 1]);
      loading.value = false;
      await scheduleRender(points);
    } else {
      monthSummary.value = null;
      pointCount.value = 0;
    }
  } catch (err) {
    error.value = err.message || "Gagal memuat grafik";
    monthSummary.value = null;
    pointCount.value = 0;
    destroyChart();
  } finally {
    loading.value = false;
  }
}

function changeYear(delta) {
  const next = year.value + delta;
  if (next > currentYear) return;
  year.value = next;
}

watch(year, loadChart);

onMounted(loadChart);
onUnmounted(destroyChart);
</script>

<style scoped>
.growth-chart {
  width: 100%;
  height: 320px;
}
</style>
