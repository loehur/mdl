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

    <section class="glass relative min-h-[320px] overflow-hidden p-3">
      <div
        v-if="loading"
        class="absolute inset-0 z-10 flex items-center justify-center bg-ink-50/90"
      >
        <PageLoader />
      </div>

      <EmptyState
        v-if="!loading && candleCount === 0"
        title="Belum ada data"
        :subtitle="`Tidak ada snapshot portfolio di tahun ${year}.`"
      />

      <div
        v-show="!loading && candleCount > 0"
        ref="chartContainer"
        class="growth-chart"
      />
    </section>

    <section v-if="!loading && monthSummary" class="glass-strong p-4">
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
let pendingCandles = null;
let renderRetryTimer = null;

function monthTime(yearValue, month) {
  return {
    year: yearValue,
    month,
    day: 1,
  };
}

function toNumber(value) {
  const num = Number(value);
  return Number.isFinite(num) ? num : null;
}

function buildCandles(items, yearValue) {
  return items
    .filter((item) => Number(item.snapshot_count) > 0)
    .map((item) => ({
      time: monthTime(yearValue, Number(item.month)),
      open: toNumber(item.open),
      high: toNumber(item.high),
      low: toNumber(item.low),
      close: toNumber(item.close),
      month: Number(item.month),
      snapshot_count: Number(item.snapshot_count),
    }))
    .filter((item) => item.open !== null && item.high !== null && item.low !== null && item.close !== null);
}

function setMonthSummary(candle) {
  if (!candle) {
    monthSummary.value = null;
    return;
  }

  monthSummary.value = {
    label: MONTH_LABELS[candle.month - 1],
    open: candle.open,
    high: candle.high,
    low: candle.low,
    close: candle.close,
    snapshot_count: candle.snapshot_count,
  };
}

function destroyChart() {
  if (renderRetryTimer) {
    clearTimeout(renderRetryTimer);
    renderRetryTimer = null;
  }
  resizeObserver?.disconnect();
  resizeObserver = null;
  chart?.remove();
  chart = null;
  series = null;
  pendingCandles = null;
}

function renderChart(candles) {
  destroyChart();
  pendingCandles = candles;

  if (!chartContainer.value || candles.length === 0) return;

  const width = chartContainer.value.clientWidth;
  if (width <= 0) {
    renderRetryTimer = window.setTimeout(() => renderChart(candles), 50);
    return;
  }

  chart = createChart(chartContainer.value, {
    width,
    height: 320,
    layout: {
      background: { color: "#ffffff" },
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
      barSpacing: 18,
      minBarSpacing: 8,
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
      setMonthSummary(candles[candles.length - 1] ?? null);
      return;
    }

    const point = param.seriesData.get(series);
    if (!point) return;

    const candle = candles.find((item) => {
      const t = param.time;
      return item.time.year === t.year && item.time.month === t.month && item.time.day === t.day;
    });
    if (candle) setMonthSummary(candle);
  });

  resizeObserver = new ResizeObserver(() => {
    if (!chartContainer.value || !chart) return;
    const nextWidth = chartContainer.value.clientWidth;
    if (nextWidth > 0) {
      chart.applyOptions({ width: nextWidth });
    }
  });
  resizeObserver.observe(chartContainer.value);
}

async function scheduleRender(candles) {
  await nextTick();
  await new Promise((resolve) => requestAnimationFrame(resolve));
  renderChart(candles);
}

async function loadChart() {
  loading.value = true;
  error.value = "";
  monthSummary.value = null;
  candleCount.value = 0;
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
    const candles = buildCandles(months.value, year.value);
    candleCount.value = candles.length;

    if (candles.length > 0) {
      setMonthSummary(candles[candles.length - 1]);
      loading.value = false;
      await scheduleRender(candles);
    } else {
      monthSummary.value = null;
      candleCount.value = 0;
    }
  } catch (err) {
    error.value = err.message || "Gagal memuat grafik";
    monthSummary.value = null;
    candleCount.value = 0;
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
