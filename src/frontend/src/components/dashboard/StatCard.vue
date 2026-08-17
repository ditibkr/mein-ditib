<template>
  <div class="card">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">{{ label }}</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">
          {{ value.toLocaleString('de-DE') }}
        </p>
      </div>
      <div
        class="w-12 h-12 rounded-xl flex items-center justify-center"
        :class="iconBg"
      >
        <UsersIcon v-if="icon === 'users'" class="w-6 h-6" :class="iconColor" />
        <CheckCircleIcon v-else-if="icon === 'check'" class="w-6 h-6" :class="iconColor" />
        <UserPlusIcon v-else-if="icon === 'user-plus'" class="w-6 h-6" :class="iconColor" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { UsersIcon, CheckCircleIcon, UserPlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps<{
  label: string
  value: number
  color: 'primary' | 'green' | 'yellow'
  icon: string
}>()

const iconBg = computed(() => ({
  primary: 'bg-primary-50',
  green: 'bg-green-50',
  yellow: 'bg-yellow-50',
}[props.color]))

const iconColor = computed(() => ({
  primary: 'text-primary-600',
  green: 'text-green-600',
  yellow: 'text-yellow-600',
}[props.color]))
</script>
