<template>
  <div class="space-y-6">
    <!-- Stat-Karten -->
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div v-for="i in 3" :key="i" class="card animate-pulse h-28" />
    </div>

    <div v-else-if="stats" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <StatCard
        :label="$t('dashboard.totalMembers')"
        :value="stats.members.total"
        color="primary"
        icon="users"
      />
      <StatCard
        :label="$t('dashboard.activeMembers')"
        :value="stats.members.active"
        color="green"
        icon="check"
      />
      <StatCard
        :label="$t('dashboard.newThisMonth')"
        :value="stats.members.newThisMonth"
        color="yellow"
        icon="user-plus"
      />
    </div>

    <!-- Charts -->
    <div v-if="stats" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="card lg:col-span-2">
        <h3 class="text-base font-semibold text-gray-900 mb-4">
          {{ $t('dashboard.memberGrowth') }}
        </h3>
        <GrowthChart :data="stats.members.growthData" />
      </div>

      <div class="card">
        <h3 class="text-base font-semibold text-gray-900 mb-4">
          {{ $t('dashboard.membersByCategory') }}
        </h3>
        <CategoryChart :data="stats.members.byCategory" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { dashboardApi, type DashboardStats } from '@/api/dashboard'
import StatCard from '@/components/dashboard/StatCard.vue'
import GrowthChart from '@/components/dashboard/GrowthChart.vue'
import CategoryChart from '@/components/dashboard/CategoryChart.vue'

const stats = ref<DashboardStats | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await dashboardApi.stats()
    stats.value = data
  } finally {
    loading.value = false
  }
})
</script>
