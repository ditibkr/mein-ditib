import client from './client'

export interface Member {
  id: number
  member_number: string | null
  first_name: string
  last_name: string
  email: string | null
  phone: string | null
  birth_date: string | null
  status: string
  category: string
  city: string | null
  membership_start: string | null
  membership_fee: number
  language_preference: string
  gdpr_consent: boolean
  created_at: string
}

export interface MemberListParams {
  page?: number
  per_page?: number
  search?: string
  status?: string
  category?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export const membersApi = {
  list: (params?: MemberListParams) =>
    client.get<PaginatedResponse<Member>>('/members', { params }),

  get: (id: number) =>
    client.get<Member>(`/members/${id}`),

  create: (data: Partial<Member>) =>
    client.post<Member>('/members', data),

  update: (id: number, data: Partial<Member>) =>
    client.put<Member>(`/members/${id}`, data),

  delete: (id: number) =>
    client.delete(`/members/${id}`),

  statistics: () =>
    client.get('/members/statistics'),
}
