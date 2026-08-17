<template>
  <div class="flex h-screen bg-ditib-light overflow-hidden">

    <!-- Mobile Menu Overlay -->
    <div
      v-if="menuOpen"
      class="fixed inset-0 z-40 bg-black/50 md:hidden"
      @click="menuOpen = false"
    />

    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-50 w-sidebar bg-ditib-dark text-white flex flex-col transform transition-transform duration-200
             md:relative md:translate-x-0 md:flex"
      :class="menuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
    >
      <!-- Logo -->
      <div class="h-16 flex items-center px-6 border-b border-white/10 shrink-0">
        <span class="text-lg font-bold text-white tracking-wide">
          {{ $t('app.name') }}
        </span>
        <!-- Mobile Close -->
        <button
          class="ml-auto md:hidden text-white/70 hover:text-white"
          @click="menuOpen = false"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 py-4 space-y-1 px-3 overflow-y-auto">
        <RouterLink
          v-for="item in navigation"
          :key="item.name"
          :to="item.to"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="[
            $route.name === item.name
              ? 'bg-ditib-red text-white'
              : 'text-white/70 hover:bg-white/10 hover:text-white'
          ]"
          @click="menuOpen = false"
        >
          <component :is="item.icon" class="w-5 h-5 shrink-0" />
          {{ item.label }}
        </RouterLink>
      </nav>

      <!-- Benutzer + Abmelden -->
      <div class="border-t border-white/10 p-4 shrink-0">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-8 h-8 rounded-full bg-ditib-red flex items-center justify-center text-white font-semibold text-sm shrink-0">
            {{ userInitials }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white truncate">{{ user?.name }}</p>
            <p class="text-xs text-white/50 truncate">{{ user?.email }}</p>
          </div>
        </div>

        <!-- Sprachumschalter -->
        <div class="flex gap-2 mb-2">
          <button
            v-for="lang in ['de', 'tr']"
            :key="lang"
            @click="switchLanguage(lang)"
            class="flex-1 py-1 text-xs rounded border transition-colors"
            :class="locale === lang
              ? 'border-ditib-red bg-ditib-red/20 text-white'
              : 'border-white/20 text-white/50 hover:border-white/40'"
          >
            {{ lang.toUpperCase() }}
          </button>
        </div>

        <button
          @click="handleLogout"
          class="w-full flex items-center gap-2 px-3 py-2 text-sm text-white/60 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
        >
          <ArrowRightOnRectangleIcon class="w-4 h-4" />
          {{ $t('nav.logout') }}
        </button>
      </div>
    </aside>

    <!-- Hauptbereich -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Header -->
      <header class="h-16 bg-white border-b border-gray-200 flex items-center px-4 md:px-6 shrink-0 gap-3">
        <!-- Hamburger (nur Mobile) -->
        <button
          class="md:hidden text-gray-500 hover:text-gray-700 -ml-1"
          @click="menuOpen = true"
        >
          <Bars3Icon class="w-6 h-6" />
        </button>

        <h1 class="text-lg md:text-xl font-semibold text-gray-900 truncate">
          <slot name="title">{{ pageTitle }}</slot>
        </h1>
      </header>

      <!-- Inhalt -->
      <main class="flex-1 overflow-y-auto p-4 md:p-6">
        <RouterView />
      </main>

      <!-- Bottom Navigation (nur Mobile) -->
      <nav class="md:hidden flex justify-around bg-white border-t border-gray-200 shrink-0">
        <RouterLink
          v-for="item in navigation"
          :key="item.name"
          :to="item.to"
          class="flex flex-col items-center py-2 px-4 text-xs transition-colors"
          :class="$route.name === item.name
            ? 'text-ditib-red'
            : 'text-gray-500 hover:text-gray-700'"
        >
          <component :is="item.icon" class="w-5 h-5 mb-0.5" />
          {{ item.label }}
        </RouterLink>
      </nav>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  HomeIcon,
  UsersIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
  Bars3Icon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const { t, locale } = useI18n()

const menuOpen = ref(false)

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
