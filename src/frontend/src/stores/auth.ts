import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi, type AuthUser, type LoginCredentials } from '@/api/auth'
import { useI18n } from 'vue-i18n'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(null)

  const isAuthenticated = computed(() => !!token.value)
  const hasRole = (role: string) => user.value?.roles.includes(role) ?? false
  const hasPermission = (perm: string) => user.value?.permissions.includes(perm) ?? false

  function initFromStorage() {
    const storedToken = localStorage.getItem('auth_token')
    const storedUser = localStorage.getItem('auth_user')

    if (storedToken && storedUser) {
      token.value = storedToken
      user.value = JSON.parse(storedUser)
    }
  }

  async function login(credentials: LoginCredentials) {
    const { data } = await authApi.login(credentials)

    token.value = data.token
    user.value = data.user

    localStorage.setItem('auth_token', data.token)
    localStorage.setItem('auth_user', JSON.stringify(data.user))

    // Sprache des Benutzers setzen
    if (data.user.language) {
      localStorage.setItem('lang', data.user.language)
    }

    return data
  }

  async function logout() {
    try {
      await authApi.logout()
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    hasRole,
    hasPermission,
    initFromStorage,
    login,
    logout,
  }
})
