<template>
  <section class="rounded-3xl bg-white border border-red-100 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-red-50 flex items-center justify-between gap-2">
      <div>
        <h3 class="font-display font-bold text-jaggu-crimson">{{ title }}</h3>
        <p class="text-xs text-slate-500">
          {{ day?.day_name }} · {{ formatDate(day?.date) }}
          <span v-if="day"> · {{ day.done }}/{{ day.total }}</span>
        </p>
      </div>
      <span
        v-if="day?.complete"
        class="text-[10px] font-bold uppercase tracking-wide bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full"
      >Selesai</span>
    </div>

    <ul v-if="day?.items?.length" class="divide-y divide-slate-100">
      <li
        v-for="(item, idx) in day.items"
        :key="item.id"
        class="px-4 py-3 flex items-center gap-3"
      >
        <span class="text-xs font-bold text-slate-400 w-5">{{ idx + 1 }}</span>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-slate-800 truncate">{{ item.subject_name }}</p>
          <p v-if="item.checked_at" class="text-[11px] text-slate-400">{{ item.checked_at }}</p>
        </div>
        <button
          v-if="interactive && day.can_checklist"
          type="button"
          class="h-9 w-9 rounded-full border-2 flex items-center justify-center transition"
          :class="item.checked
            ? 'bg-jaggu-red border-jaggu-red text-white'
            : 'border-slate-300 text-transparent hover:border-jaggu-red'"
          @click="$emit('toggle', { item, day, checked: !item.checked })"
        >
          ✓
        </button>
        <span
          v-else
          class="h-8 w-8 rounded-full flex items-center justify-center text-sm"
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
});

defineEmits(["toggle"]);

function formatDate(ymd) {
  if (!ymd) return "";
  const [y, m, d] = ymd.split("-");
  return `${d}/${m}/${y}`;
}
</script>
