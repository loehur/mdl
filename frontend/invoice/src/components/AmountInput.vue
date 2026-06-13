<template>
  <input
    :value="displayValue"
    class="field-input-lg"
    type="text"
    inputmode="numeric"
    autocomplete="off"
    :placeholder="placeholder"
    :required="required"
    @input="onInput"
  />
</template>

<script setup>
import { computed } from "vue";
import { formatAmountInput, parseAmountInput } from "../utils/format";

const model = defineModel({ type: String, default: "" });

defineProps({
  placeholder: { type: String, default: "0" },
  required: { type: Boolean, default: false },
});

const displayValue = computed(() => formatAmountInput(model.value));

function onInput(event) {
  model.value = parseAmountInput(event.target.value);
}
</script>
