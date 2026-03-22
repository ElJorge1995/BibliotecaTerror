import { defineStore } from 'pinia'
import authApi from '../api/auth'

/**
 * Librum Tenebris - Almacén Central de Autenticación (Pinia)
 * 
 * Gestiona el ciclo de vida del usuario de cara al Frontend. 
 * Contiene el Token JWT en memoria y en localStorage para persistencia,
 * y facilita endpoints rápidos de login, logout e hidratación de perfil.
 */

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('auth_token') || null,
    loading: false,
    error: null
  }),

  // Add getters that depend on state
  getters: {
    isAuthenticated: (state) => !!state.token && !!state.user,
    isAdmin: (state) => state.user?.role === 'admin'
  },

  actions: {
    setAuth(token, user) {
      this.token = token
      this.user = user
      localStorage.setItem('auth_token', token)
    },

    clearAuth() {
      this.token = null
      this.user = null
      localStorage.removeItem('auth_token')
    },

    async fetchMe() {
      if (!this.token) return

      this.loading = true
      try {
        const { data } = await authApi.getMe()
        this.user = data.user
      } catch (e) {
        console.error('Failed to fetch user', e)
        this.clearAuth()
      } finally {
        this.loading = false
      }
    },

    /**
     * Autentica al usuario contra la API y clona su token JWT si las credenciales son válidas.
     * @param {Object} credentials Contiene user/email y password.
     */
    async login(credentials) {
      this.loading = true
      this.error = null
      try {
        const { data } = await authApi.login(credentials)
        this.setAuth(data.token, data.user)
        return true
      } catch (e) {
        this.error = e.response?.data?.error || 'Error al iniciar sesión'
        throw e
      } finally {
        this.loading = false
      }
    },

    async register(userData) {
      this.loading = true
      this.error = null
      try {
        await authApi.register(userData)
        return true
      } catch (e) {
        this.error = e.response?.data?.error || 'Error en el registro'
        throw e
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        await authApi.logout()
      } catch (e) {
        // Even if API call fails, clear local state
        console.error('Logout API failed', e)
      } finally {
        this.clearAuth()
      }
    }
  }
})
