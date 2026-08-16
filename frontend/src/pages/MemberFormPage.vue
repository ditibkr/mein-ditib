<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <RouterLink to="/mitglieder" class="text-gray-400 hover:text-gray-600">
        <ChevronLeftIcon class="w-5 h-5" />
      </RouterLink>
      <h2 class="text-xl font-bold text-gray-900">Neues Mitglied</h2>
    </div>

    <div class="card">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">{{ $t('members.firstName') }} *</label>
            <input v-model="form.first_name" required class="form-input" />
          </div>
          <div>
            <label class="form-label">{{ $t('members.lastName') }} *</label>
            <input v-model="form.last_name" required class="form-input" />
          </div>
          <div>
            <label class="form-label">{{ $t('members.email') }}</label>
            <input v-model="form.email" type="email" class="form-input" />
          </div>
          <div>
            <label class="form-label">{{ $t('members.phone') }}</label>
            <input v-model="form.phone" type="tel" class="form-input" />
          </div>
          <div>
            <label class="form-label">{{ $t('members.status') }} *</label>
            <select v-model="form.status" required class="form-input">
              <option v-for="(label, val) in statusOptions" :key="val" :value="val">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">{{ $t('members.category') }} *</label>
            <select v-model="form.category" required class="form-input">
              <option v-for="(label, val) in categoryOptions" :key="val" :value="val">{{ label }}</option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input v-model="form.gdpr_consent" type="checkbox" id="gdpr" class="rounded border-gray-300" />
          <label for="gdpr" class="text-sm text-gray-700">
            DSGVO-Einwilligung erteilt
          </label>
        </div>

        <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
          {{ error }}
        </div>

        <div class="flex justify-end gap-3">
          <RouterLink to="/mitglieder" class="btn-secondary">
            {{ $t('common.cancel') }}
          </RouterLink>
          <button type="submit" :disabled="saving" class="btn-primary">
            {{ saving ? $t('common.loading') : $t('common.save') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ChevronLeftIcon } from '@heroicons/vue/24/outline'
import { useMembersStore } from '@/stores/members'

const router = useRouter()
const store = useMembersStore()
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  status: 'aktiv',
  category: 'vollmitglied',
  gdpr_consent: false,
})

const statusOptions = {
  aktiv: 'Aktiv',
  ruhend: 'Ruhend',
  ausgetreten: 'Ausgetreten',
}

const categoryOptions = {
  vollmitglied: 'Vollmitglied',
  foerdermitglied: 'Fördermitglied',
  ehrenmitglied: 'Ehrenmitglied',
  jugend: 'Jugend',
}

async function handleSubmit() {
  saving.value = true
  error.value = null

  try {
    const created = await store.createMember(form)
    router.push(`/mitglieder/${created.id}`)
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Speichern fehlgeschlagen'
  } finally {
    saving.value = false
  }
}
</script>
