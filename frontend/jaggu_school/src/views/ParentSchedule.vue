<template>
  <div class="space-y-5">
    <section class="rounded-3xl bg-white border border-red-100 p-5 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wider text-jaggu-red">Jadwal</p>
      <h2 class="font-display text-2xl font-bold text-jaggu-crimson mt-1">Mapel Senin–Sabtu</h2>
      <p class="text-sm text-slate-500 mt-1">Urutan saja, tanpa jam. Berlaku terus sampai diubah.</p>
    </section>

    <div v-if="loading" class="text-sm text-slate-500">Memuat...</div>
    <div v-else-if="error" class="rounded-2xl bg-red-50 text-red-700 text-sm px-4 py-3">{{ error }}</div>

    <template v-else>
      <section
        v-for="day in days"
        :key="day.day_of_week"
        class="rounded-3xl bg-white border border-red-100 overflow-hidden shadow-sm"
      >
        <div class="px-4 py-3 bg-jaggu-soft border-b border-red-50 flex items-center justify-between">
          <h3 class="font-display font-bold text-jaggu-crimson">{{ day.day_name }}</h3>
          <button
            type="button"
            class="text-xs font-semibold text-jaggu-red"
            @click="addSubject(day)"
          >
            + Mapel
          </button>
        </div>
        <ul v-if="day.subjects.length" class="divide-y divide-slate-100">
          <li
            v-for="(s, idx) in day.subjects"
            :key="idx"
            class="px-4 py-2.5 flex items-center gap-2"
          >
            <span class="text-xs font-bold text-slate-400 w-5">{{ idx + 1 }}</span>
            <input
              v-model="s.subject_name"
              class="flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-jaggu-red/20"
              placeholder="Nama mapel"
            />
            <button
              type="button"
              class="text-xs text-slate-400 hover:text-red-600 px-1"
              title="Naik"
              :disabled="idx === 0"
              @click="move(day, idx, -1)"
            >↑</button>
            <button
              type="button"
              class="text-xs text-slate-400 hover:text-red-600 px-1"
              title="Turun"
              :disabled="idx === day.subjects.length - 1"
              @click="move(day, idx, 1)"
            >↓</button>
            <button
              type="button"
              class="text-xs text-red-500 px-1"
              @click="day.subjects.splice(idx, 1)"
            >✕</button>
          </li>
        </ul>
        <p v-else class="px-4 py-4 text-sm text-slate-400 text-center">Belum ada mapel</p>
      </section>

      <p v-if="saveMsg" class="text-sm text-center" :class="saveOk ? 'text-emerald-700' : 'text-red-600'">
        {{ saveMsg }}
      </p>

      <button
        type="button"
        :disabled="saving"
        class="w-full rounded-2xl bg-jaggu-red hover:bg-jaggu-crimson disabled:opacity-60 text-white font-semibold py-3 shadow-lg shadow-red-200"
        @click="save"
      >
        {{ saving ? "Menyimpan..." : "Simpan jadwal" }}
      </button>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";

const loading = ref(true);
const error = ref("");
const days = ref([]);
const saving = ref(false);
const saveMsg = ref("");
const saveOk = ref(false);

onMounted(load);

async function load() {
  loading.value = true;
  error.value = "";
  try {
    const res = await fetch("/api/Jaggu_School/Schedule/index");
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Gagal memuat");
    const list = data.data?.days || [];
    days.value = list.map((d) => ({
      ...d,
      subjects: (d.subjects || []).map((s) => ({
        subject_name: s.subject_name || "",
      })),
    }));
  } catch (e) {
    error.value = e.message || "Gagal memuat";
  } finally {
    loading.value = false;
  }
}

function addSubject(day) {
  day.subjects.push({ subject_name: "" });
}

function move(day, idx, dir) {
  const next = idx + dir;
  if (next < 0 || next >= day.subjects.length) return;
  const arr = day.subjects;
  const tmp = arr[idx];
  arr[idx] = arr[next];
  arr[next] = tmp;
}

async function save() {
  saving.value = true;
  saveMsg.value = "";
  try {
    const payload = { days: {} };
    for (const d of days.value) {
      payload.days[String(d.day_of_week)] = d.subjects
        .map((s) => (s.subject_name || "").trim())
        .filter(Boolean);
    }
    const res = await fetch("/api/Jaggu_School/Schedule/save", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Gagal menyimpan");
    saveOk.value = true;
    saveMsg.value = "Jadwal tersimpan.";
    await load();
  } catch (e) {
    saveOk.value = false;
    saveMsg.value = e.message || "Gagal menyimpan";
  } finally {
    saving.value = false;
  }
}
</script>
