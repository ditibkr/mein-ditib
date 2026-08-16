<template>
  <div class="min-h-screen flex bg-gray-50">
    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
      <!-- Logo -->
      <div class="h-16 flex items-center px-6 border-b border-gray-200">
        <span class="text-lg font-bold text-primary-700">
          {{ $t('app.name') }}
        </span>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 py-4 space-y-1 px-3">
        <RouterLink
          v-for="item in navigation"
          :key="item.name"
          :to="item.to"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
          :class="[
            $route.name === item.name
              ? 'bg-primary-50 text-primary-700'
              : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
          ]"
        >
          <component :is="item.icon" class="w-5 h-5 shrink-0" />
          {{ item.label }}
        </RouterLink>
      </nav>

      <!-- User + Logout -->
      <div class="border-t border-gray-200 p-4">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-sm">
            {{ userInitials }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ user?.name }}</p>
            <p class="text-xs text-gray-500 truncate">{{ user?.email }}</p>
          </div>
        </div>

        <!-- Sprachumschalter -->
        <div class="flex gap-2 mb-2">
          <button
            v-for="lang in ['de', 'tr']"
            :key="lang"
            @click="switchLanguage(lang)"
            class="flex-1 py-1 text-xs rounded border transition-colors"
            :class="locale === lang ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-200 text-gray-500 hover:border-gray-300'"
          >
            {{ lang.toUpperCase() }}
          </button>
        </div>

        <button
          @click="handleLogout"
          class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
        >
          <ArrowRightOnRectangleIcon class="w-4 h-4" />
          {{ $t('nav.logout') }}
        </button>
      </div>
    </aside>

    <!-- Hauptinhalt -->
    <main class="flex-1 flex flex-col min-w-0">
      <header class="h-16 bg-white border-b border-gray-200 flex items-center px-6">
        <h1 class="text-xl font-semibold text-gray-900">
          <slot name="title">{{ pageTitle }}</slot>
        </h1>
      </header>

      <div class="flex-1 p-6 overflow-auto">
        <RouterView />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  HomeIcon,
  UsersIcon,
  EnvelopeIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const { t, locale } = useI18n()

const user = computed(() => auth.user)
const userInitials = computed(() => {
  const name = user.value?.name ?? ''
  return name.split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase()
})

const navigation = computed(() => [
  { name: 'dashboard', to: '/', label: t('nav.dashboard'), icon: HomeIcon },
  { name: 'members', to: '/mitglieder', label: t('nav.members'), icon: UsersIcon },
  { name: 'settings', to: '/einstellungen', label: t('nav.settings'), icon: Cog6ToothIcon },
])

const pageTitle = computed(() => {
  const found = navigation.value.find((n) => n.name === route.name)
  return found?.label ?? ''
})

function switchLanguage(lang: string) {
  locale.value = lang
  localStorage.setItem('lang', lang)
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>
