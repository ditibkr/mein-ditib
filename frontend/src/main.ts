import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import App from './App.vue'
import router from './router'
import de from './i18n/de'
import tr from './i18n/tr'
import './assets/main.css'

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('lang') || 'de',
  fallbackLocale: 'de',
  messages: { de, tr },
})

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(i18n)

app.mount('#app')

// Service Worker registrieren
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {})
}
