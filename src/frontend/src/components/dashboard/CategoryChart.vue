<template>
  <div class="flex flex-col items-center">
    <div class="w-full max-w-[200px]">
      <Doughnut :data="chartData" :options="chartOptions" />
    </div>
    <!-- Legende -->
    <ul class="mt-4 space-y-1 w-full">
      <li
        v-for="(item, i) in legendItems"
        :key="item.label"
        class="flex items-center justify-between text-sm"
      >
        <span class="flex items-center gap-2">
          <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: colors[i] }" />
          {{ item.label }}
        </span>
        <span class="font-medium">{{ item.value }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps<{
  data: Record<string, number>
}>()

const colors = ['#16a34a', '#3b82f6', '#f59e0b', '#6366f1']

const labels: Record<string, string> = {
  vollmitglied: 'Vollmitglied',
  foerdermitglied: 'Fördermitglied',
  ehrenmitglied: 'Ehrenmitglied',
  jugend: 'Jugend',
}

const legendItems = computed(() =>
  Object.entries(props.data).map(([key, value]) => ({
    label: labels[key] ?? key,
    value,
  }))
)

const chartData = computed(() => ({
  labels: Object.keys(props.data).map((k) => labels[k] ?? k),
  datasets: [
    {
      data: Object.values(props.data),
      backgroundColor: colors,
      borderWidth: 2,
      borderColor: '#fff',
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend: { display: false },
  },
  cutout: '65%',
}
</script>
