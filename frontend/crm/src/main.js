import { createApp } from 'vue'
import './style.css'
import App from './App.vue'

const app = createApp(App)
app.mount('#app')

// Cancel loading timeout after Vue app is mounted
if (typeof window.__cancelAppLoadingTimeout === 'function') {
    window.__cancelAppLoadingTimeout()
}
