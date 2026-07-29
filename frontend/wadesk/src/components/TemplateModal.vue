<template>
  <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60">
    <div class="w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl bg-ink-900 border border-white/10 shadow-2xl max-h-[90vh] overflow-y-auto relative">
      <div class="p-4 border-b border-white/10 flex items-center justify-between sticky top-0 bg-ink-900 z-10">
        <h2 class="font-display font-semibold text-lg">Kirim template</h2>
        <button
          type="button"
          class="text-slate-400 hover:text-white disabled:opacity-40 disabled:pointer-events-none"
          :disabled="busy"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <form class="p-4 space-y-4" @submit.prevent="submit">
        <div v-if="!fixedKeyId">
          <label class="label">API key / nomor WA</label>
          <select v-model="form.ycloud_key_id" required class="field" :disabled="busy" @change="onKeyChange">
            <option disabled value="">Pilih key</option>
            <option v-for="k in keys" :key="k.id" :value="k.id">
              {{ k.label }} ({{ k.phone_number }}) — {{ k.team_name }}
            </option>
          </select>
        </div>

        <div v-if="!fixedPhone">
          <label class="label">Nomor tujuan</label>
          <input v-model="form.phone" required class="field" placeholder="62812..." :disabled="busy" />
        </div>

        <div>
          <label class="label">Template</label>
          <select v-model="form.template_id" required class="field" :disabled="busy" @change="onTplChange">
            <option disabled value="">Pilih template</option>
            <option v-for="t in filteredTemplates" :key="t.id" :value="t.id">
              {{ t.template_name }} ({{ t.language }})
            </option>
          </select>
          <p v-if="livePreview" class="mt-2 text-xs text-slate-300 whitespace-pre-wrap rounded-lg bg-ink-950/60 p-2 border border-white/5">
            {{ livePreview }}
          </p>
        </div>

        <div v-for="p in selectedTpl?.params || []" :key="paramKey(p)" class="space-y-1">
          <label class="label">
            {{ p.label }}
            <span class="text-slate-500">
              ({{ '{' + '{' + (p.param_name || p.param_index) + '}' + '}' }})
            </span>
          </label>
          <input
            v-model="paramValues[paramKey(p)]"
            class="field"
            :placeholder="p.example_value || ''"
            :required="Number(p.is_required) === 1"
            :disabled="busy"
          />
        </div>

        <p v-if="error" class="text-sm text-rose-400">{{ error }}</p>

        <button
          type="submit"
          class="w-full py-3 rounded-xl bg-accent font-semibold disabled:opacity-50 inline-flex items-center justify-center gap-2"
          :disabled="busy"
        >
          <svg
            v-if="busy"
            class="h-4 w-4 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
          {{ busy ? "Mengirim..." : "Kirim template" }}
        </button>
      </form>

      <div
        v-if="busy"
        class="absolute inset-0 z-20 rounded-t-2xl sm:rounded-2xl bg-ink-950/50 flex items-center justify-center pointer-events-none"
        aria-live="polite"
        aria-busy="true"
      >
        <div class="flex flex-col items-center gap-2 rounded-xl bg-ink-900/95 border border-white/10 px-5 py-4 shadow-lg">
          <svg
            class="h-6 w-6 animate-spin text-accent"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
          <p class="text-sm text-slate-200">Mengirim template...</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from "vue";

const props = defineProps({
  keys: { type: Array, default: () => [] },
  templates: { type: Array, default: () => [] },
  fixedKeyId: { type: [Number, String, null], default: null },
  fixedPhone: { type: String, default: "" },
  busy: { type: Boolean, default: false },
  error: { type: String, default: "" },
});

const emit = defineEmits(["close", "submit", "load-templates"]);

const form = reactive({
  ycloud_key_id: props.fixedKeyId || "",
  phone: props.fixedPhone || "",
  template_id: "",
});
const paramValues = reactive({});

const filteredTemplates = computed(() => {
  const kid = Number(form.ycloud_key_id || props.fixedKeyId);
  if (!kid) return props.templates;
  return props.templates.filter((t) => Number(t.ycloud_key_id) === kid);
});

const selectedTpl = computed(() =>
  filteredTemplates.value.find((t) => Number(t.id) === Number(form.template_id))
);

function paramKey(p) {
  if (p.param_name) return String(p.param_name);
  const component = String(p.component || "body").toLowerCase();
  return `${component}_${p.param_index}`;
}

function escapeRegExp(s) {
  return String(s).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

/**
 * Replace {{name}} / {{1}} in preview text.
 * Header vars (e.g. {{customer}}) are often missing from older body_preview
 * rows that only stored BODY — prepend missing placeholders so live fill works
 * before the next YCloud sync.
 */
const livePreview = computed(() => {
  const tpl = selectedTpl.value;
  if (!tpl) return "";

  const values = { ...paramValues };
  let out = String(tpl.body_preview || "");

  const textParams = (tpl.params || []).filter((p) => {
    const c = String(p.component || "body").toLowerCase();
    return c === "body" || c === "header";
  });

  const missingTokens = [];
  for (const p of textParams) {
    const token = p.param_name ? String(p.param_name) : String(p.param_index ?? "");
    if (!token) continue;
    const re = new RegExp(`\\{\\{\\s*${escapeRegExp(token)}\\s*\\}\\}`);
    if (!re.test(out)) {
      missingTokens.push(token);
    }
  }
  if (missingTokens.length) {
    const synthetic = missingTokens.map((t) => `{{${t}}}`).join(" ");
    out = out ? `${synthetic}\n\n${out}` : synthetic;
  }

  for (const p of textParams) {
    const key = paramKey(p);
    const val = values[key] ?? "";
    const token = p.param_name ? String(p.param_name) : String(p.param_index ?? "");
    if (!token) continue;
    out = out.replace(new RegExp(`\\{\\{\\s*${escapeRegExp(token)}\\s*\\}\\}`, "g"), val);
  }

  return out;
});

watch(
  () => props.fixedKeyId,
  (v) => {
    if (v) {
      form.ycloud_key_id = v;
      emit("load-templates", v);
    }
  },
  { immediate: true }
);

watch(
  () => props.fixedPhone,
  (v) => {
    if (v) form.phone = v;
  },
  { immediate: true }
);

function onKeyChange() {
  form.template_id = "";
  emit("load-templates", form.ycloud_key_id);
}

function onTplChange() {
  Object.keys(paramValues).forEach((k) => delete paramValues[k]);
  for (const p of selectedTpl.value?.params || []) {
    paramValues[paramKey(p)] = "";
  }
}

function submit() {
  if (props.busy) return;
  const tpl = selectedTpl.value;
  const template_params = {};
  for (const p of tpl?.params || []) {
    const key = paramKey(p);
    const val = paramValues[key] ?? "";
    // Prefer named; otherwise use component_index (matches Blast CSV + resolveTemplateParams)
    template_params[key] = val;
  }

  emit("submit", {
    ycloud_key_id: Number(form.ycloud_key_id || props.fixedKeyId),
    phone: form.phone || props.fixedPhone,
    template_id: Number(form.template_id),
    template_name: tpl?.template_name,
    language: tpl?.language || "id",
    template_params,
    message: livePreview.value || tpl?.body_preview || "",
  });
}
</script>

<style scoped>
.label {
  @apply block text-xs text-slate-400 mb-1;
}
.field {
  @apply w-full rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-accent/40 disabled:opacity-50;
}
</style>
