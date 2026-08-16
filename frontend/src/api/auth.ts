import client from './client'

export interface LoginCredentials {
  email: string
  password: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  roles: string[]
  permissions: string[]
  language: string
}

export const authApi = {
  login: (credentials: LoginCredentials) =>
    client.post<{ token: string; user: AuthUser }>('/login', credentials),

  logout: () => client.post('/logout'),

  me: () => client.get<AuthUser>('/user'),
}
