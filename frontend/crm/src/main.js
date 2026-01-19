import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.mount('#app')

// Cancel loading timeout after Vue app is mounted
if (typeof window.__cancelAppLoadingTimeout === 'function') {
    window.__cancelAppLoadingTimeout()
}
