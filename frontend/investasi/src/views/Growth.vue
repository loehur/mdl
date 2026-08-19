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
      <p class="mt-2 text-sm text-mist">Satu candle = agregat snapshot per bulan (OHLC)</p>
    </section>

    <PageLoader v-if="loading" />

    <EmptyState
      v-else-if="candleCount === 0"
      title="Belum ada data"
      :subtitle="`Tidak ada snapshot portfolio di tahun ${year}.`"
    />

    <template v-else>
      <section class="glass overflow-hidden p-3">
        <div ref="chartContainer" class="growth-chart" />
      </section>

      <section v-if="monthSummary" class="glass-strong p-4">
        <p class="label-caps">{{ monthSummary.label }}</p>
        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
          <div>
            <p class="text-mist">Open</p>
            <p class="font-semibold text-pearl">{{ formatRupiah(monthSummary.open) }}</p>
          </div>
          <div>
            <p class="text-mist">Close</p>
            <p class="font-semibold text-pearl">{{ formatRupiah(monthSummary.close) }}</p>
          </div>
          <div>
            <p class="text-mist">High</p>
            <p class="font-semibold text-credit-dim">{{ formatRupiah(monthSummary.high) }}</p>
          </div>
          <div>
            <p class="text-mist">Low</p>
            <p class="font-semibold text-debit-dim">{{ formatRupiah(monthSummary.low) }}</p>
          </div>
        </div>
        <p class="mt-3 text-xs text-mist">{{ monthSummary.snapshot_count }} snapshot di bulan ini</p>
      </section>
    </template>

    <AlertBanner :message="error" type="error" />
  </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { createChart, CandlestickSeries } from "lightweight-charts";
import { formatRupiah } from "../utils/format";
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
const candleCount = ref(0);
const monthSummary = ref(null);
const chartContainer = ref(null);

let chart = null;
let series = null;
let resizeObserver = null;

function monthTime(yearValue, month) {
  return `${yearValue}-${String(month).padStart(2, "0")}-01`;
}

function buildCandles(items, yearValue) {
  return items
    .filter((item) => item.snapshot_count > 0)
    .map((item) => ({
      time: monthTime(yearValue, item.month),
      open: item.open,
      high: item.high,
      low: item.low,
      close: item.close,
      month: item.month,
      snapshot_count: item.snapshot_count,
    }));
}

function destroyChart() {
  resizeObserver?.disconnect();
  resizeObserver = null;
  chart?.remove();
  chart = null;
  series = null;
}

function renderChart(candles) {
  destroyChart();
  if (!chartContainer.value || candles.length === 0) return;

  chart = createChart(chartContainer.value, {
    width: chartContainer.value.clientWidth,
    height: 320,
    layout: {
      background: { color: "transparent" },
      textColor: "#64748b",
      fontFamily: '"Barlow", system-ui, sans-serif',
      fontSize: 12,
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
  });

  series = chart.addSeries(CandlestickSeries, {
    upColor: "#0d9488",
    downColor: "#dc2626",
    borderUpColor: "#0f766e",
    borderDownColor: "#b91c1c",
    wickUpColor: "#0f766e",
    wickDownColor: "#b91c1c",
  });

  series.setData(candles);

  chart.timeScale().fitContent();

  series.subscribeCrosshairMove((param) => {
    if (!param.time || !param.seriesData.size) {
      monthSummary.value = candles[candles.length - 1]
        ? {
            label: MONTH_LABELS[candles[candles.length - 1].month - 1],
            open: candles[candles.length - 1].open,
            high: candles[candles.length - 1].high,
            low: candles[candles.length - 1].low,
            close: candles[candles.length - 1].close,
            snapshot_count: candles[candles.length - 1].snapshot_count,
          }
        : null;
      return;
    }

    const point = param.seriesData.get(series);
    if (!point) return;

    const candle = candles.find((item) => item.time === param.time);
    if (!candle) return;

    monthSummary.value = {
      label: MONTH_LABELS[candle.month - 1],
      open: candle.open,
      high: candle.high,
      low: candle.low,
      close: candle.close,
      snapshot_count: candle.snapshot_count,
    };
  });

  resizeObserver = new ResizeObserver(() => {
    if (!chartContainer.value || !chart) return;
    chart.applyOptions({ width: chartContainer.value.clientWidth });
  });
  resizeObserver.observe(chartContainer.value);
}

async function loadChart() {
  loading.value = true;
  error.value = "";
  monthSummary.value = null;

  try {
    const res = await fetch(`/api/Investasi/Portfolio/chart?year=${year.value}`);
    const data = await res.json();
    if (!res.ok || !data.status) throw new Error(data.message || "Gagal memuat grafik");

    months.value = data.data.months || [];
    candleCount.value = months.value.filter((item) => item.snapshot_count > 0).length;

    const availableYears = data.data.years || [];
    if (availableYears.length > 0 && !availableYears.includes(year.value)) {
      year.value = availableYears[0];
      return;
    }

    await nextTick();

    const candles = buildCandles(months.value, year.value);
    if (candles.length > 0) {
      const last = candles[candles.length - 1];
      monthSummary.value = {
        label: MONTH_LABELS[last.month - 1],
        open: last.open,
        high: last.high,
        low: last.low,
        close: last.close,
        snapshot_count: last.snapshot_count,
      };
      renderChart(candles);
    } else {
      destroyChart();
    }
  } catch (err) {
    error.value = err.message || "Gagal memuat grafik";
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
