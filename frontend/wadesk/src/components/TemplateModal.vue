<template>
  <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60">
    <div class="w-full sm:max-w-2xl md:max-w-3xl lg:max-w-4xl rounded-t-2xl sm:rounded-2xl bg-ink-900 border border-white/10 shadow-2xl max-h-[90vh] overflow-y-auto relative">
      <div class="p-4 border-b border-white/10 flex items-center justify-between sticky top-0 bg-ink-900 z-10">
        <h2 class="font-display font-semibold text-lg text-slate-100">Send template</h2>
        <button
          type="button"
          class="text-slate-400 hover:text-slate-100 disabled:opacity-40 disabled:pointer-events-none"
          :disabled="busy || checking"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <form class="p-4 md:p-6 space-y-4 md:space-y-6" @submit.prevent="submit">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 md:items-start">
          <!-- Kolom kiri: channel, nomor, template -->
          <div class="space-y-4">
            <div v-if="!fixedKeyId">
              <label class="label">Channel / nomor WA</label>
              <select v-model="form.channel_id" required class="field" :disabled="busy || checking" @change="onKeyChange">
                <option disabled value="">Select a channel</option>
                <option v-for="k in keys" :key="k.id" :value="k.id">
                  {{ k.label }} ({{ k.phone_number }})
                </option>
              </select>
            </div>

            <div v-if="!fixedPhone">
              <label class="label">Recipient number</label>
              <input v-model="form.phone" required class="field" placeholder="62812..." :disabled="busy || checking" />
            </div>

            <div>
              <label class="label">Template</label>
              <select
                v-model="form.template_id"
                required
                class="field"
                :disabled="busy || checking || !hasChannel"
                @change="onTplChange"
              >
                <option disabled value="">Select a template</option>
                <option v-for="t in filteredTemplates" :key="t.id" :value="t.id">
                  {{ t.template_name }} ({{ t.language }})
                </option>
              </select>
              <p v-if="hasChannel && !filteredTemplates.length" class="mt-1 text-xs text-amber-300/90">
                No templates are available for this WhatsApp number. Sync again in Admin → Templates.
              </p>
              <p v-if="livePreview" class="mt-2 text-xs text-slate-300 whitespace-pre-wrap rounded-lg bg-ink-950/60 p-3 border border-white/5 min-h-[5rem]">
                {{ livePreview }}
              </p>
            </div>
          </div>

          <!-- Kolom kanan: parameter template -->
          <div class="space-y-4">
            <p v-if="(selectedTpl?.params || []).length" class="text-xs font-medium uppercase tracking-wide text-slate-500">
              Parameter template
            </p>
            <p v-else class="text-sm text-slate-500 rounded-lg border border-dashed border-white/10 p-4 text-center">
              Pilih template untuk mengisi parameter.
            </p>

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
                :maxlength="paramMaxlength(p)"
                :disabled="busy || checking"
                @input="clearAiWarning"
              />
              <p class="text-[10px] text-slate-500">Max. {{ paramMaxlength(p) }} characters</p>
            </div>
          </div>
        </div>

        <div
          v-if="aiWarning"
          class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-100"
          role="alert"
        >
          <p class="font-medium text-amber-200 mb-1">Template cannot be sent</p>
          <p class="text-xs text-amber-100/90 whitespace-pre-wrap">{{ aiWarning }}</p>
        </div>

        <p
          v-if="error && !aiWarning"
          class="rounded-xl border border-rose-500/25 bg-rose-500/10 p-3 text-sm leading-6 text-rose-300 whitespace-normal break-words"
        >
          {{ error }}
        </p>

        <button
          type="submit"
          class="w-full py-3 rounded-xl bg-accent font-semibold disabled:opacity-50 inline-flex items-center justify-center gap-2"
          :disabled="busy || checking"
        >
          <svg
            v-if="busy || checking"
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
          {{ checking ? "Checking with AI..." : busy ? "Sending..." : "Send template" }}
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
          <p class="text-sm text-slate-200">Sending template...</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { api } from "../api";
import {
  buildFilledPreview,
  buildPreviewMapsFromValues,
  paramKey,
} from "../utils/templatePreview";

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
  channel_id: props.fixedKeyId || "",
  phone: props.fixedPhone || "",
  template_id: "",
});
const paramValues = reactive({});
const checking = ref(false);
const aiWarning = ref("");

const filteredTemplates = computed(() => props.templates);

const hasChannel = computed(() => Boolean(form.channel_id || props.fixedKeyId));

const selectedTpl = computed(() =>
  filteredTemplates.value.find((t) => Number(t.id) === Number(form.template_id))
);

function paramMaxlength(p) {
  const m = Number(p?.maxlength ?? 20);
  return m > 0 ? m : 20;
}

const livePreview = computed(() => {
  const tpl = selectedTpl.value;
  if (!tpl) return "";

  const { named, indexed } = buildPreviewMapsFromValues(tpl.params, paramValues);
  return buildFilledPreview(tpl.body_preview, tpl.params, named, indexed);
});

function clearAiWarning() {
  aiWarning.value = "";
}

function requestTemplateReload() {
  const cid = form.channel_id || props.fixedKeyId;
  if (cid) {
    emit("load-templates", cid);
  }
}

onMounted(() => {
  requestTemplateReload();
});

watch(
  () => props.fixedKeyId,
  (v) => {
    if (v) {
      form.channel_id = v;
      requestTemplateReload();
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

watch(
  () => props.error,
  () => {
    if (props.error) aiWarning.value = "";
  }
);

function onKeyChange() {
  form.template_id = "";
  clearAiWarning();
  requestTemplateReload();
}

function onTplChange() {
  Object.keys(paramValues).forEach((k) => delete paramValues[k]);
  for (const p of selectedTpl.value?.params || []) {
    paramValues[paramKey(p)] = "";
  }
  clearAiWarning();
}

function buildPayload() {
  const tpl = selectedTpl.value;
  const template_params = {};
  for (const p of tpl?.params || []) {
    const key = paramKey(p);
    template_params[key] = paramValues[key] ?? "";
  }

  return {
    channel_id: Number(form.channel_id || props.fixedKeyId),
    phone: form.phone || props.fixedPhone,
    template_id: Number(form.template_id),
    template_name: tpl?.template_name,
    language: tpl?.language || "id",
    template_params,
    message: livePreview.value || tpl?.body_preview || "",
  };
}

async function submit() {
  if (props.busy || checking.value) return;
  const tpl = selectedTpl.value;
  if (!tpl) return;

  checking.value = true;
  aiWarning.value = "";

  const payload = buildPayload();

  try {
    const mod = await api("/WaDesk/Chat/moderateTemplateParams", {
      method: "POST",
      body: {
        template_id: payload.template_id,
        template_params: payload.template_params,
      },
    });

    if (!mod.data?.safe) {
      aiWarning.value =
        mod.data?.reason ||
        mod.message ||
        "Konten parameter ditolak moderasi AI.";
      return;
    }

    emit("submit", payload);
  } catch (e) {
    aiWarning.value = e.message || "Failed to check parameters with AI";
  } finally {
    checking.value = false;
  }
}
</script>

<style scoped>
.label {
  @apply block text-xs text-slate-400 mb-1;
}
.field {
  @apply w-full rounded-xl bg-ink-950 border border-white/10 px-3 py-2.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-accent/40 disabled:opacity-50;
}
</style>
