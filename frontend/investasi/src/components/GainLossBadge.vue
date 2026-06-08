<template>
  <div
    v-if="gainLoss !== null && gainLoss !== undefined"
    class="rounded-2xl border px-4 py-3 text-center"
    :class="boxClass"
  >
    <p class="money-display-sm" :class="amountClass">
      {{ formatGainLoss(gainLoss) }}
    </p>
    <p v-if="pct !== null && pct !== undefined" class="mt-1 text-sm" :class="labelClass">
      {{ pct > 0 ? '+' : '' }}{{ pct }}% dari modal
    </p>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { formatGainLoss } from "../utils/format";

const props = defineProps({
  gainLoss: { type: Number, default: null },
  gainLossPct: { type: Number, default: null },
  status: { type: String, default: null },
});

const pct = computed(() => props.gainLossPct);

const boxClass = computed(() => {
  if (props.status === "profit") return "border-credit-dim/25 bg-credit-light";
  if (props.status === "loss") return "border-debit-dim/25 bg-debit-light";
  return "border-ink-200 bg-ink-100";
});

const labelClass = computed(() => {
  if (props.status === "profit") return "text-credit-dim";
  if (props.status === "loss") return "text-debit-dim";
  return "text-mist";
});

const amountClass = computed(() => {
  if (props.status === "profit") return "text-credit-dim";
  if (props.status === "loss") return "text-debit-dim";
  return "text-pearl";
});
</script>
