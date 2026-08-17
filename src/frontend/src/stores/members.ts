import { defineStore } from 'pinia'
import { ref } from 'vue'
import { membersApi, type Member, type MemberListParams } from '@/api/members'

export const useMembersStore = defineStore('members', () => {
  const members = ref<Member[]>([])
  const currentMember = ref<Member | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 25,
    total: 0,
  })

  async function fetchMembers(params?: MemberListParams) {
    loading.value = true
    error.value = null

    try {
      const { data } = await membersApi.list(params)
      members.value = data.data
      pagination.value = {
        currentPage: data.meta.current_page,
        lastPage: data.meta.last_page,
        perPage: data.meta.per_page,
        total: data.meta.total,
      }
    } catch (e: any) {
      error.value = e.response?.data?.message ?? 'Fehler beim Laden der Mitglieder'
    } finally {
      loading.value = false
    }
  }

  async function fetchMember(id: number) {
    loading.value = true
    try {
      const { data } = await membersApi.get(id)
      currentMember.value = data
    } finally {
      loading.value = false
    }
  }

  async function createMember(data: Partial<Member>) {
    const { data: created } = await membersApi.create(data)
    members.value.unshift(created)
    return created
  }

  async function updateMember(id: number, data: Partial<Member>) {
    const { data: updated } = await membersApi.update(id, data)
    const idx = members.value.findIndex((m) => m.id === id)
    if (idx !== -1) members.value[idx] = updated
    if (currentMember.value?.id === id) currentMember.value = updated
    return updated
  }

  async function deleteMember(id: number) {
    await membersApi.delete(id)
    members.value = members.value.filter((m) => m.id !== id)
  }

  return {
    members,
    currentMember,
    loading,
    error,
    pagination,
    fetchMembers,
    fetchMember,
    createMember,
    updateMember,
    deleteMember,
  }
})
