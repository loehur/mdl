import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { writeFileSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))

/** Stamp version.json on every build so Android WebView can detect updates. */
function emitVersionPlugin() {
  const stamp = () => {
    const version = new Date()
      .toISOString()
      .replace(/[-:TZ.]/g, '')
      .slice(0, 14) // YYYYMMDDHHmmss
    const payload = JSON.stringify({ version }, null, 2) + '\n'
    writeFileSync(resolve(__dirname, 'public/version.json'), payload)
    return version
  }

  return {
    name: 'emit-version',
    buildStart() {
      const version = stamp()
      console.log(`[emit-version] ${version}`)
    },
  }
}

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), emitVersionPlugin()],
  base: './',
  build: {
    outDir: '../../public/crm',
    emptyOutDir: true,
  }
})
