<template>
  <div class="grid gap-6">
    <section class="p-5 bg-white rounded-xl shadow">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">Settings</h3>
        <button class="px-3 py-2 text-sm rounded bg-gray-100 hover:bg-gray-200" @click="fetchSettings">Refresh</button>
      </div>
      <p class="mt-2 text-sm" :class="isError ? 'text-red-700' : 'text-gray-600'">{{ message || 'Kelola pengaturan sistem' }}</p>
      <div class="mt-4 grid gap-3">
        <div class="grid gap-2 md:grid-cols-3">
          <input v-model="newEnum" class="border rounded-lg px-3 py-2" placeholder="Key (enum)" />
          <input v-model="newValue" class="border rounded-lg px-3 py-2 md:col-span-2" placeholder="Value" />
        </div>
        <div class="flex items-center gap-2">
          <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700" :disabled="isSaving" @click="onAdd">Simpan</button>
          <button class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300" @click="onReset">Reset</button>
        </div>
      </div>
    </section>

    <section class="p-5 bg-white rounded-xl shadow">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">Daftar Settings</h3>
        <span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">{{ settings.length }} item</span>
      </div>
      <div class="mt-4">
        <div v-if="!settings.length" class="text-sm text-gray-600">Belum ada data</div>
        <ul v-else class="divide-y">
          <li v-for="(s, i) in settings" :key="s.enum" class="py-3 grid md:grid-cols-12 md:items-center gap-2">
            <div class="md:col-span-3">
              <div class="text-xs text-gray-600">Key</div>
              <div class="font-medium break-all">{{ s.enum }}</div>
            </div>
            <div class="md:col-span-7">
              <div class="text-xs text-gray-600">Value</div>
              <input v-model="s.value" class="border rounded-lg px-3 py-2 w-full" />
            </div>
            <div class="md:col-span-2 flex items-center gap-2">
              <button class="px-3 py-2 text-sm rounded bg-blue-600 text-white hover:bg-blue-700" @click="onUpdate(s)">Update</button>
              <button class="px-3 py-2 text-sm rounded bg-red-600 text-white hover:bg-red-700" @click="onDelete(s)">Hapus</button>
            </div>
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { apiUrl } from '../api.js'

const settings = ref([])
const message = ref('')
const isError = ref(false)
const isSaving = ref(false)
const newEnum = ref('')
const newValue = ref('')

async function fetchSettings() {
  message.value = ''
  isError.value = false
  try {
    const res = await fetch(apiUrl('/api/mdl/settings'))
    const data = await res.json().catch(() => ({ success: false, settings: [] }))
    if (!res.ok || !data.success) {
      isError.value = true
      message.value = data.message || 'Gagal memuat settings'
      settings.value = []
      return
    }
    settings.value = Array.isArray(data.settings) ? data.settings : []
  } catch (e) {
    isError.value = true
    message.value = 'Network error'
  }
}

async function onAdd() {
  if (!newEnum.value) {
    isError.value = true
    message.value = 'Key wajib diisi'
    return
  }
  isSaving.value = true
  try {
    const res = await fetch(apiUrl('/api/mdl/settings'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ enum: newEnum.value, value: newValue.value })
    })
    const data = await res.json().catch(() => ({ success: false }))
    if (!res.ok || !data.success) {
      isError.value = true
      message.value = data.message || 'Gagal menyimpan'
      isSaving.value = false
      return
    }
    message.value = 'Berhasil menyimpan'
    isError.value = false
    await fetchSettings()
    onReset()
  } catch (e) {
    isError.value = true
    message.value = 'Network error'
  } finally {
    isSaving.value = false
  }
}

function onReset() {
  newEnum.value = ''
  newValue.value = ''
}

async function onUpdate(s) {
  try {
    const res = await fetch(apiUrl('/api/mdl/settings'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ enum: s.enum, value: s.value })
    })
    const data = await res.json().catch(() => ({ success: false }))
    if (!res.ok || !data.success) {
      isError.value = true
      message.value = data.message || 'Gagal update'
      return
    }
    isError.value = false
    message.value = 'Berhasil update'
  } catch (e) {
    isError.value = true
    message.value = 'Network error'
  }
}

async function onDelete(s) {
  try {
    const res = await fetch(apiUrl(`/api/mdl/settings/${encodeURIComponent(s.enum)}`), { method: 'DELETE' })
    const data = await res.json().catch(() => ({ success: false }))
    if (!res.ok || !data.success) {
      isError.value = true
      message.value = data.message || 'Gagal menghapus'
      return
    }
    await fetchSettings()
    isError.value = false
    message.value = 'Berhasil menghapus'
  } catch (e) {
    isError.value = true
    message.value = 'Network error'
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<style></style>
