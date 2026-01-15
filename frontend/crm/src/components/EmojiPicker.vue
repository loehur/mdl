<script setup>
import { ref, computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  recentEmojis: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue", "select"]);

// Active category tab
const activeCategory = ref("smileys");

// Emoji Categories with full sets
const emojiCategories = {
  recent: { label: "🕒", title: "Recently Used", emojis: [] },
  smileys: { 
    label: "😊", 
    title: "Smileys & Emotions", 
    emojis: ["😀","😃","😄","😁","😆","😅","🤣","😂","🙂","🙃","😉","😊","😇","🥰","😍","🤩","😘","😗","😚","😙","🥲","😋","😛","😜","🤪","😝","🤑","🤗","🤭","🤫","🤔","🤐","🤨","😐","😑","😶","😏","😒","🙄","😬","🤥","😌","😔","😪","🤤","😴","😷","🤒","🤕","🤢","🤮","🤧","🥵","🥶","🥴","😵","🤯","🤠","🥳","🥸","😎","🤓","🧐","😕","😟","🙁","☹️","😮","😯","😲","😳","🥺","😦","😧","😨","😰","😥","😢","😭","😱","😖","😣","😞","😓","😩","😫","🥱","😤","😡","😠","🤬","😈","👿","💀","☠️","💩","🤡","👹","👺","👻","👽","👾","🤖"] 
  },
  gestures: { 
    label: "👋", 
    title: "Gestures & People", 
    emojis: ["👋","🤚","🖐️","✋","🖖","👌","🤌","🤏","✌️","🤞","🤟","🤘","🤙","👈","👉","👆","🖕","👇","☝️","👍","👎","✊","👊","🤛","🤜","👏","🙌","👐","🤲","🤝","🙏","💪","🦾","🧠","👀","👁️","👅","👄","💋","🦷","🦴","👶","🧒","👦","👧","🧑","👱","👨","🧔","👩","🧓","👴","👵"] 
  },
  animals: { 
    label: "🐱", 
    title: "Animals & Nature", 
    emojis: ["🐶","🐱","🐭","🐹","🐰","🦊","🐻","🐼","🐨","🐯","🦁","🐮","🐷","🐸","🐵","🙈","🙉","🙊","🐒","🐔","🐧","🐦","🐤","🦆","🦅","🦉","🦇","🐺","🐗","🐴","🦄","🐝","🐛","🦋","🐌","🐞","🐜","🦟","🦗","🕷️","🌸","💐","🌹","🌺","🌻","🌼","🌷","🌱","🌲","🌳","🌴","🌵","🌾","🌿","☘️","🍀","🍁","🍂","🍃"] 
  },
  food: { 
    label: "🍔", 
    title: "Food & Drink", 
    emojis: ["🍇","🍈","🍉","🍊","🍋","🍌","🍍","🥭","🍎","🍏","🍐","🍑","🍒","🍓","🫐","🥝","🍅","🫒","🥥","🥑","🍆","🥔","🥕","🌽","🌶️","🫑","🥒","🥬","🥦","🧄","🧅","🍄","🥜","🌰","🍞","🥐","🥖","🫓","🥨","🥯","🥞","🧇","🧀","🍖","🍗","🥩","🥓","🍔","🍟","🍕","🌭","🥪","🌮","🌯","🫔","🥙","🧆","🥚","🍳","🥘","🍲","🫕","🥣","🥗","🍿","🧈","🧂","🥫","☕","🍵","🧃","🥤","🧋","🍶","🍺","🍻","🥂","🍷","🥃","🍸","🍹","🧉","🍾","🧊"] 
  },
  symbols: { 
    label: "❤️", 
    title: "Symbols", 
    emojis: ["❤️","🧡","💛","💚","💙","💜","🖤","🤍","🤎","💔","❣️","💕","💞","💓","💗","💖","💘","💝","💟","☮️","✝️","☪️","🕉️","☸️","✡️","🔯","🕎","☯️","☦️","🛐","⛎","♈","♉","♊","♋","♌","♍","♎","♏","♐","♑","♒","♓","🆔","⚛️","✅","❌","⭕","❗","❓","‼️","⁉️","💯","🔥","✨","⭐","🌟","💫","💥","💢","💦","💨","🕳️","💤"] 
  },
  objects: { 
    label: "💡", 
    title: "Objects", 
    emojis: ["⌚","📱","💻","⌨️","🖥️","🖨️","🖱️","🖲️","💽","💾","💿","📀","🧮","🎥","📷","📸","📹","📼","🔍","🔎","💡","🔦","🏮","📔","📕","📖","📗","📘","📙","📚","📓","📒","📃","📜","📄","📰","🗞️","📑","🔖","💰","💴","💵","💶","💷","💸","💳","🧾","✉️","📧","📨","📩","📤","📥","📦","📫","📪","📬","📭","📮","🗳️","✏️","✒️","🖋️","🖊️","🖌️","🖍️","📝","📁","📂","🗂️","📅","📆","🗒️","🗓️","📇","📈","📉","📊","📋","📌","📍","📎","🖇️","📏","📐","✂️","🗃️","🗄️","🗑️","🔒","🔓","🔏","🔐","🔑","🗝️"] 
  },
};

// Current emojis to display based on category
const currentEmojis = computed(() => {
  if (activeCategory.value === "recent") {
    return props.recentEmojis.length > 0 ? props.recentEmojis : emojiCategories.smileys.emojis.slice(0, 20);
  }
  return emojiCategories[activeCategory.value]?.emojis || [];
});

// Select emoji
const selectEmoji = (emoji) => {
  emit("select", emoji);
  emit("update:modelValue", false);
};

// Close picker
const close = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <div 
    v-if="modelValue"
    class="absolute bottom-full left-0 mb-2 bg-[var(--wa-bg-panel)] border border-[var(--wa-border)] rounded-2xl shadow-2xl w-80 overflow-hidden z-50"
  >
    <!-- Header with Categories -->
    <div class="flex border-b border-[var(--wa-border)] bg-[var(--wa-bg-secondary)]">
      <button
        v-for="(cat, key) in emojiCategories"
        :key="key"
        @click="activeCategory = key"
        class="flex-1 py-2 text-xl hover:bg-[var(--wa-hover)] transition-colors"
        :class="activeCategory === key ? 'bg-[var(--wa-hover)] border-b-2 border-[var(--wa-accent-green)]' : ''"
        :title="cat.title"
      >
        {{ cat.label }}
      </button>
    </div>

    <!-- Emoji Grid -->
    <div class="h-64 overflow-y-auto custom-scrollbar p-2">
      <div class="grid grid-cols-8 gap-1">
        <button
          v-for="emoji in currentEmojis"
          :key="emoji"
          @click="selectEmoji(emoji)"
          class="text-2xl p-1 hover:bg-[var(--wa-hover)] rounded-lg transition-colors"
        >
          {{ emoji }}
        </button>
      </div>
      
      <!-- Empty state for recent -->
      <div v-if="activeCategory === 'recent' && recentEmojis.length === 0" class="text-center py-8 text-[var(--wa-text-tertiary)]">
        <p class="text-sm">No recent emojis</p>
        <p class="text-xs mt-1">Your recently used emojis will appear here</p>
      </div>
    </div>

    <!-- Close Button -->
    <div class="border-t border-[var(--wa-border)] p-2 bg-[var(--wa-bg-secondary)]">
      <button @click="close" class="w-full py-1.5 text-sm text-[var(--wa-text-secondary)] hover:text-[var(--wa-text-primary)] hover:bg-[var(--wa-hover)] rounded-lg transition-colors">
        Close
      </button>
    </div>
  </div>
</template>
