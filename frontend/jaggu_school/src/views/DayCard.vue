<template>
  <section class="rounded-3xl bg-white border border-red-100 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-red-50 flex items-center justify-between gap-2 min-h-[3.25rem]">
      <div class="min-w-0">
        <h3 class="font-display font-bold text-jaggu-crimson">{{ title }}</h3>
        <p class="text-xs text-slate-500">
          {{ day?.day_name }} · {{ formatDate(day?.date) }}
          <span v-if="day"> · {{ day.done }}/{{ day.total }}</span>
        </p>
      </div>
      <span
        class="text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full shrink-0 transition-opacity"
        :class="day?.complete
          ? 'bg-emerald-100 text-emerald-700 opacity-100'
          : 'bg-transparent text-transparent opacity-0 pointer-events-none'"
        aria-hidden="true"
      >Selesai</span>
    </div>

    <ul v-if="day?.items?.length" class="divide-y divide-slate-100">
      <li
        v-for="(item, idx) in day.items"
        :key="item.id"
        class="relative px-4 py-3 flex items-center gap-3 min-h-[3.25rem]"
      >
        <span class="text-xs font-bold text-slate-400 w-5 shrink-0">{{ idx + 1 }}</span>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-slate-800 truncate">{{ item.subject_name }}</p>
        </div>
        <button
          v-if="interactive && day.can_checklist"
          type="button"
          class="relative h-9 w-9 shrink-0 rounded-full border-2 flex items-center justify-center transition-transform active:scale-95 disabled:opacity-60"
          :class="item.checked
            ? 'bg-jaggu-red border-jaggu-red text-white'
            : 'border-slate-300 text-transparent hover:border-jaggu-red'"
          :disabled="busyId === item.id"
          @click="$emit('toggle', { item, day, checked: !item.checked })"
        >
          <span
            v-if="busyId === item.id"
            class="absolute inset-0 flex items-center justify-center"
          >
            <span class="h-4 w-4 rounded-full border-2 border-white/40 border-t-white animate-spin" />
          </span>
          <span :class="busyId === item.id ? 'opacity-0' : ''">✓</span>
        </button>
        <span
          v-else
          class="h-9 w-9 shrink-0 rounded-full flex items-center justify-center text-sm"
          :class="item.checked ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'"
        >
          {{ item.checked ? "✓" : "·" }}
        </span>
      </li>
    </ul>
    <p v-else class="px-4 py-6 text-sm text-slate-500 text-center">Tidak ada mapel.</p>
  </section>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  day: { type: Object, default: null },
  interactive: { type: Boolean, default: true },
  busyId: { type: [Number, String], default: null },
});

defineEmits(["toggle"]);

function formatDate(ymd) {
  if (!ymd) return "";
  const [y, m, d] = ymd.split("-");
  return `${d}/${m}/${y}`;
}
</script>
