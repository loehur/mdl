
import os

path = r'c:\xampp82\htdocs\mdl\frontend\beauty_salon\src\user_area\Order.vue'
with open(path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

new_block = """                    v-for="prod in products" 
                    :key="prod.id"
                    class="border rounded-xl p-3 relative overflow-hidden group transition-all"
                    :class="getQuantity(prod.id) > 0 ? 'border-pink-500 bg-pink-50' : 'border-gray-200 hover:border-pink-300'"
                  >
                     <div class="flex justify-between items-start mb-2" @click="getQuantity(prod.id) === 0 ? addProduct(prod) : null">
                        <div class="w-full cursor-pointer">
                            <div class="font-medium text-gray-800">{{ prod.name }}</div>
                            <div class="text-sm font-bold text-pink-600">Rp {{ formatNumber(prod.price) }}</div>
                        </div>
                     </div>
                     
                     <div v-if="getQuantity(prod.id) > 0" class="flex items-center justify-end gap-2 mt-2 z-10 relative">
                        <button @click.stop="removeProduct(prod)" class="w-8 h-8 rounded-full bg-white border border-pink-200 text-pink-600 hover:bg-pink-100 flex items-center justify-center font-bold shadow-sm transition-colors">-</button>
                        <span class="font-bold text-gray-800 w-6 text-center">{{ getQuantity(prod.id) }}</span>
                        <button @click.stop="addProduct(prod)" class="w-8 h-8 rounded-full bg-pink-600 text-white hover:bg-pink-700 flex items-center justify-center font-bold shadow-sm transition-colors">+</button>
                     </div>
                     <div v-else class="absolute inset-0 cursor-pointer" @click="addProduct(prod)"></div>
                  </div>
"""

# Indicies 0-184 are Lines 1-185.
# Indicies 185-197 are Lines 186-198 (To be replaced).
# Indicies 198-End are Lines 199-End.

out = lines[:185] + [new_block] + lines[198:]

with open(path, 'w', encoding='utf-8') as f:
    f.write(''.join(out))

print("Successfully patched Order.vue")
