import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8000',
  headers: {
    'Content-Type': 'application/json'
  }
})

// Request interceptor to add token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor: si el backend invalida la sesión (login desde otro
// dispositivo, ban, admin force-logout, expiración por cambio de password…)
// limpia el token local para que el estado reactivo desloguee al usuario.
// No redirigimos desde aquí; dejamos que el router/estado haga su trabajo.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && localStorage.getItem('auth_token')) {
      localStorage.removeItem('auth_token')
    }
    return Promise.reject(error)
  }
)

export default {
  register(data) {
    return api.post('/auth/register', data)
  },
  login(credentials) {
    return api.post('/auth/login', credentials)
  },
  verifyEmail(token) {
    return api.get(`/auth/verify-email?token=${token}`)
  },
  resendVerification(email) {
    return api.post('/auth/resend-verification', { email })
  },
  requestPasswordReset(email) {
    return api.post('/auth/request-password-reset', { email })
  },
  resetPassword(token, password, password_confirmation) {
    return api.post('/auth/reset-password', {
      token,
      new_password: password,
      new_password_confirmation: password_confirmation
    })
  },
  getMe() {
    return api.get('/auth/me')
  },
  logout() {
    return api.post('/auth/logout')
  },
  
  // Admin Endpoints
  adminGetUsers() {
    return api.get('/auth/admin/users')
  },
  adminUpdateRole(userId, role) {
    return api.post('/auth/admin/update-role', { user_id: userId, role })
  },
  adminRegister(data) {
    return api.post('/auth/admin/register', data)
  },
  adminDeleteUser(userId) {
    return api.post('/auth/admin/delete-user', { user_id: userId })
  },
  adminForceLogout(userId, current_password) {
    return api.post('/auth/admin/force-logout', { user_id: userId, current_password })
  },
  adminSetBan(userId, banned, current_password) {
    return api.post('/auth/admin/set-ban', { user_id: userId, banned, current_password })
  },
  confirmLoginLocation(token, decision) {
    return api.post('/auth/confirm-login-location', { token, decision })
  },
  deleteMe() {
    return api.post('/auth/delete-me')
  },

  // Profile Settings Endpoints
  updateName(firstName, lastName) {
    return api.post('/auth/update-name', { first_name: firstName, last_name: lastName })
  },
  updateUsername(username) {
    return api.post('/auth/update-username', { username })
  },
  updatePhone(phone) {
    return api.post('/auth/update-phone', { phone })
  },
  changePassword(current_password, new_password, new_password_confirmation) {
    return api.post('/auth/change-password', { 
      current_password, 
      new_password, 
      new_password_confirmation 
    })
  },
  requestEmailChange(new_email, current_password) {
    return api.post('/auth/request-email-change', { new_email, current_password })
  },
  confirmEmailChange(token) {
    return api.get(`/auth/confirm-email-change?token=${token}`)
  }
}
