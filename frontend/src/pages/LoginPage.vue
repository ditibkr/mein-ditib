<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 p-4">
    <div class="w-full max-w-md">
      <!-- Logo & Titel -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-600 mb-4">
          <span class="text-white text-2xl font-bold">D</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $t('app.name') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('app.tagline') }}</p>
      </div>

      <!-- Login-Formular -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-6">{{ $t('auth.login') }}</h2>

        <form @submit.prevent="handleLogin" class="space-y-4">
          <div>
            <label class="form-label">{{ $t('auth.email') }}</label>
            <input
              v-model="form.email"
              type="email"
              required
              autocomplete="email"
              class="form-input"
              :placeholder="$t('auth.email')"
            />
          </div>

          <div>
            <label class="form-label">{{ $t('auth.password') }}</label>
            <input
              v-model="form.password"
              type="password"
              required
              autocomplete="current-password"
              class="form-input"
              :placeholder="$t('auth.password')"
            />
          </div>

          <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 p-3">
            <p class="text-sm text-red-700">{{ error }}</p>
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="btn-primary w-full justify-center py-2.5"
          >
            <span v-if="loading">{{ $t('common.loading') }}</span>
            <span v-else>{{ $t('auth.loginButton') }}</span>
          </button>
        </form>

        <!-- Sprachumschalter -->
        <div class="mt-4 flex justify-center gap-3">
          <button
            v-for="lang in ['de', 'tr']"
            :key="lang"
            @click="switchLanguage(lang)"
            class="text-xs px-2 py-1 rounded border transition-colors"
            :class="locale === lang ? 'border-primary-500 text-primary-700' : 'border-gray-200 text-gray-400 hover:border-gray-300'"
          >
            {{ lang === 'de' ? 'Deutsch' : 'Türkçe' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const { t, locale } = useI18n()

const form = reactive({ email: '', password: '' })
const loading = ref(false)
const error = ref<string | null>(null)

async function handleLogin() {
  loading.value = true
  error.value = null

  try {
    await auth.login(form)
    const redirect = (route.query.redirect as string) || '/'
    router.push(redirect)
  } catch (e: any) {
    error.value = e.response?.data?.message ?? t('auth.loginError')
  } finally {
    loading.value = false
  }
}

function switchLanguage(lang: string) {
  locale.value = lang
  localStorage.setItem('lang', lang)
}
</script>
