<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex-1 max-w-md">
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            v-model="search"
            @input="debouncedSearch"
            type="text"
            class="form-input pl-9"
            :placeholder="$t('members.search')"
          />
        </div>
      </div>

      <div class="flex gap-2">
        <select v-model="statusFilter" @change="loadMembers" class="form-input w-auto">
          <option value="">Alle Status</option>
          <option v-for="(label, val) in statusOptions" :key="val" :value="val">{{ label }}</option>
        </select>

        <RouterLink to="/mitglieder/neu" class="btn-primary">
          <PlusIcon class="w-4 h-4" />
          {{ $t('members.addMember') }}
        </RouterLink>
      </div>
    </div>

    <!-- Tabelle -->
    <div class="card p-0 overflow-hidden">
      <div v-if="store.loading" class="flex items-center justify-center h-48">
        <div class="animate-spin w-8 h-8 border-2 border-primary-600 border-t-transparent rounded-full" />
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-600">{{ $t('members.memberNumber') }}</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">{{ $t('members.lastName') }}, {{ $t('members.firstName') }}</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">{{ $t('members.email') }}</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">{{ $t('members.city') }}</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">{{ $t('members.status') }}</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">{{ $t('members.category') }}</th>
            <th class="px-4 py-3" />
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-100">
          <tr
            v-for="member in store.members"
            :key="member.id"
            class="hover:bg-gray-50 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs text-gray-500">
              {{ member.member_number ?? '—' }}
            </td>
            <td class="px-4 py-3 font-medium text-gray-900">
              <RouterLink :to="`/mitglieder/${member.id}`" class="hover:text-primary-600">
                {{ member.last_name }}, {{ member.first_name }}
              </RouterLink>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ member.email ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-600">{{ member.city ?? '—' }}</td>
            <td class="px-4 py-3">
              <StatusBadge :status="member.status" />
            </td>
            <td class="px-4 py-3">
              <span class="badge badge-gray">
                {{ $t(`members.categories.${member.category}`) }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <RouterLink :to="`/mitglieder/${member.id}`" class="text-gray-400 hover:text-primary-600">
                <ChevronRightIcon class="w-5 h-5" />
              </RouterLink>
            </td>
          </tr>

          <tr v-if="!store.loading && store.members.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-gray-400">
              {{ $t('members.noResults') }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div
        v-if="store.pagination.lastPage > 1"
        class="flex items-center justify-between px-4 py-3 border-t border-gray-200 bg-gray-50"
      >
        <span class="text-sm text-gray-600">
          {{ store.pagination.total }} {{ $t('common.total') }}
        </span>
        <div class="flex gap-1">
          <button
            v-for="page in pages"
            :key="page"
            @click="goToPage(page)"
            class="w-8 h-8 text-sm rounded transition-colors"
            :class="page === currentPage ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
          >
            {{ page }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { MagnifyingGlassIcon, PlusIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import { useMembersStore } from '@/stores/members'
import StatusBadge from '@/components/members/StatusBadge.vue'

const store = useMembersStore()
const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)

const statusOptions = {
  aktiv: 'Aktiv',
  ruhend: 'Ruhend',
  ausgetreten: 'Ausgetreten',
}

let searchTimer: ReturnType<typeof setTimeout>

function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadMembers(), 400)
}

async function loadMembers() {
  await store.fetchMembers({
    page: currentPage.value,
    search: search.value || undefined,
    status: statusFilter.value || undefined,
  })
}

async function goToPage(page: number) {
  currentPage.value = page
  await loadMembers()
}

const pages = computed(() => {
  const last = store.pagination.lastPage
  return Array.from({ length: Math.min(last, 7) }, (_, i) => i + 1)
})

onMounted(loadMembers)
</script>
