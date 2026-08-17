<template>
  <span class="badge" :class="badgeClass">{{ label }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{ status: string }>()
const { t } = useI18n()

const badgeClass = computed(() => ({
  aktiv: 'badge-green',
  ruhend: 'badge-yellow',
  ausgetreten: 'badge-gray',
  ausgeschlossen: 'badge-red',
}[props.status] ?? 'badge-gray'))

const label = computed(() => t(`members.statuses.${props.status}`, props.status))
</script>
