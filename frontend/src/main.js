import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'

import App from './App.vue'
import router from './router'
import './assets/main.css'

import Dialog from 'primevue/dialog'
import Toast from 'primevue/toast'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      darkModeSelector: '.dark-mode',
    },
  },
})
app.use(ToastService)
app.use(ConfirmationService)
app.directive('tooltip', Tooltip)

// PrimeVue's public component names intentionally match its documentation.
// eslint-disable-next-line vue/multi-word-component-names, vue/no-reserved-component-names
app.component('Dialog', Dialog)
// eslint-disable-next-line vue/multi-word-component-names
app.component('Toast', Toast)

app.mount('#app')
