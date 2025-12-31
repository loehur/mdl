
const fs = require('fs');
const path = String.raw`c:\xampp82\htdocs\mdl\frontend\beauty_salon\src\user_area\Order.vue`;

const content = fs.readFileSync(path, 'utf8');
const lines = content.split(/\r?\n/);

const newBlock = `                    v-for="prod in products" 
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
                  </div>`;

// Lines 1-185 -> Index 0-184
// Replace Index 185-197
// Index 198-End

const part1 = lines.slice(0, 185);
const part2 = lines.slice(198);

const newContent = part1.join('\n') + '\n' + newBlock + '\n' + part2.join('\n');

fs.writeFileSync(path, newContent, 'utf8');
console.log('Successfully patched Order.vue');
