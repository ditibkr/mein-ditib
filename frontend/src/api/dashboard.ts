import client from './client'

export interface DashboardStats {
  members: {
    total: number
    active: number
    newThisMonth: number
    newThisYear: number
    byCategory: Record<string, number>
    byStatus: Record<string, number>
    growthData: Array<{ month: string; label: string; count: number }>
  }
  users: {
    total: number
    active: number
  }
  communications: {
    newsletters_sent: number
    newsletters_draft: number
  }
}

export const dashboardApi = {
  stats: () => client.get<DashboardStats>('/dashboard/stats'),
}
