<template>
  <div class="max-w-lg">
    <div class="card space-y-4">
      <h2 class="font-semibold text-gray-900">{{ $t('nav.settings') }}</h2>

      <div>
        <label class="form-label">Sprache / Dil</label>
        <div class="flex gap-2 mt-1">
          <button
            v-for="lang in [{ code: 'de', label: 'Deutsch' }, { code: 'tr', label: 'Türkçe' }]"
            :key="lang.code"
            @click="switchLanguage(lang.code)"
            class="flex-1 py-2 rounded-lg border text-sm font-medium transition-colors"
            :class="locale === lang.code
              ? 'border-primary-500 bg-primary-50 text-primary-700'
              : 'border-gray-200 text-gray-600 hover:border-gray-300'"
          >
            {{ lang.label }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()

function switchLanguage(lang: string) {
  locale.value = lang
  localStorage.setItem('lang', lang)
}
</script>
