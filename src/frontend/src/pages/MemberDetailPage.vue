<template>
  <div class="max-w-4xl space-y-6">
    <!-- Lade-Zustand -->
    <div v-if="store.loading" class="flex justify-center py-12">
      <div class="animate-spin w-8 h-8 border-2 border-primary-600 border-t-transparent rounded-full" />
    </div>

    <template v-else-if="member">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <RouterLink to="/mitglieder" class="text-gray-400 hover:text-gray-600">
            <ChevronLeftIcon class="w-5 h-5" />
          </RouterLink>
          <h2 class="text-xl font-bold text-gray-900">
            {{ member.first_name }} {{ member.last_name }}
          </h2>
          <StatusBadge :status="member.status" />
        </div>
        <RouterLink :to="`/mitglieder/${member.id}/bearbeiten`" class="btn-primary">
          {{ $t('common.edit') }}
        </RouterLink>
      </div>

      <!-- Stammdaten + Kontakt -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="card space-y-4">
          <h3 class="font-semibold text-gray-900">Stammdaten</h3>
          <dl class="space-y-2">
            <div class="flex justify-between text-sm">
              <dt class="text-gray-500">Mitgliedsnummer</dt>
              <dd class="font-mono font-medium">{{ member.member_number ?? '—' }}</dd>
            </div>
            <div class="flex justify-between text-sm">
              <dt class="text-gray-500">Geburtsdatum</dt>
              <dd>{{ formatDate(member.birth_date) }}</dd>
            </div>
            <div class="flex justify-between text-sm">
              <dt class="text-gray-500">Kategorie</dt>
              <dd>{{ $t(`members.categories.${member.category}`) }}</dd>
            </div>
            <div class="flex justify-between text-sm">
              <dt class="text-gray-500">Mitglied seit</dt>
              <dd>{{ formatDate(member.membership_start) }}</dd>
            </div>
            <div class="flex justify-between text-sm">
              <dt class="text-gray-500">Beitrag</dt>
              <dd>{{ member.membership_fee }} € / Monat</dd>
            </div>
          </dl>
        </div>

        <div class="card space-y-4">
          <h3 class="font-semibold text-gray-900">Kontakt</h3>
          <dl class="space-y-2">
            <div class="flex justify-between text-sm">
              <dt class="text-gray-500">E-Mail</dt>
              <dd>{{ member.email ?? '—' }}</dd>
            </div>
            <div class="flex justify-between text-sm">
              <dt class="text-gray-500">Telefon</dt>
              <dd>{{ member.phone ?? '—' }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { ChevronLeftIcon } from '@heroicons/vue/24/outline'
import { useMembersStore } from '@/stores/members'
import StatusBadge from '@/components/members/StatusBadge.vue'

const route = useRoute()
const store = useMembersStore()
const member = computed(() => store.currentMember)

function formatDate(date: string | null) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('de-DE')
}

onMounted(() => {
  store.fetchMember(Number(route.params.id))
})
</script>
