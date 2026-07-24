<template>
  <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60">
    <div class="w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl bg-ink-900 border border-white/10 shadow-2xl max-h-[90vh] overflow-y-auto">
      <div class="p-4 border-b border-white/10 flex items-center justify-between sticky top-0 bg-ink-900">
        <h2 class="font-display font-semibold text-lg">Kirim template</h2>
        <button class="text-slate-400 hover:text-white" @click="$emit('close')">✕</button>
      </div>

      <form class="p-4 space-y-4" @submit.prevent="submit">
        <div v-if="!fixedKeyId">
          <label class="label">API key / nomor WA</label>
          <select v-model="form.ycloud_key_id" required class="field" @change="onKeyChange">
            <option disabled value="">Pilih key</option>
            <option v-for="k in keys" :key="k.id" :value="k.id">
              {{ k.label }} ({{ k.phone_number }}) — {{ k.team_name }}
            </option>
          </select>
        </div>

        <div v-if="!fixedPhone">
          <label class="label">Nomor tujuan</label>
          <input v-model="form.phone" required class="field" placeholder="62812..." />
        </div>

        <div>
          <label class="label">Template</label>
          <select v-model="form.template_id" required class="field" @change="onTplChange">
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
              ({{ p.component }} · {{ '{' + '{' + (p.param_name || p.param_index) + '}' + '}' }})
            </span>
          </label>
          <input
            v-model="paramValues[paramKey(p)]"
            class="field"
            :placeholder="p.example_value || ''"
            :required="Number(p.is_required) === 1"
          />
        </div>

        <p v-if="error" class="text-sm text-rose-400">{{ error }}</p>

        <button type="submit" class="w-full py-3 rounded-xl bg-accent font-semibold disabled:opacity-50" :disabled="busy">
          {{ busy ? "Mengirim..." : "Kirim template" }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";

const props = defineProps({
  keys: { type: Array, default: () => [] },
  templates: { type: Array, default: () => [] },
  fixedKeyId: { type: [Number, String, null], default: null },
  fixedPhone: { type: String, default: "" },
});

const emit = defineEmits(["close", "submit", "load-templates"]);

const form = reactive({
  ycloud_key_id: props.fixedKeyId || "",
  phone: props.fixedPhone || "",
  template_id: "",
});
const paramValues = reactive({});
const busy = ref(false);
const error = ref("");

const filteredTemplates = computed(() => {
  const kid = Number(form.ycloud_key_id || props.fixedKeyId);
  if (!kid) return props.templates;
  return props.templates.filter((t) => Number(t.ycloud_key_id) === kid);
});

const selectedTpl = computed(() =>
  filteredTemplates.value.find((t) => Number(t.id) === Number(form.template_id))
);

function paramKey(p) {
  return p.param_name ? String(p.param_name) : `idx_${p.param_index}`;
}

function renderPreview(text, valuesByName, valuesByIndex) {
  let out = String(text || "");
  for (const [name, val] of Object.entries(valuesByName)) {
    out = out.split(`{{${name}}}`).join(val ?? "");
  }
  for (const [idx, val] of Object.entries(valuesByIndex)) {
    out = out.split(`{{${idx}}}`).join(val ?? "");
  }
  return out;
}

const livePreview = computed(() => {
  const tpl = selectedTpl.value;
  if (!tpl?.body_preview) return "";
  const named = {};
  const indexed = {};
  for (const p of tpl.params || []) {
    const key = paramKey(p);
    const val = paramValues[key] ?? "";
    if (p.param_name) named[p.param_name] = val;
    if (p.param_index) indexed[p.param_index] = val;
  }
  return renderPreview(tpl.body_preview, named, indexed);
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

async function submit() {
  error.value = "";
  busy.value = true;
  try {
    const tpl = selectedTpl.value;
    // Laundry-style named object: { customer: "Rangga" }
    const template_params = {};
    for (const p of tpl?.params || []) {
      const key = paramKey(p);
      const val = paramValues[key] ?? "";
      if (p.param_name) {
        template_params[p.param_name] = val;
      } else {
        template_params[String(p.param_index)] = val;
      }
    }

    emit("submit", {
      ycloud_key_id: Number(form.ycloud_key_id || props.fixedKeyId),
      phone: form.phone || props.fixedPhone,
      template_id: Number(form.template_id),
      template_name: tpl?.template_name,
      language: tpl?.language || "id",
      template_params,
      message: tpl?.body_preview || "",
    });
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}
</script>

<style scoped>
.label {
  @apply block text-xs text-slate-400 mb-1;
}
.field {
  @apply w-full rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-accent/40;
}
</style>
