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
  requestEmailChange(new_email) {
    return api.post('/auth/request-email-change', { new_email })
  },
  confirmEmailChange(token) {
    return api.get(`/auth/confirm-email-change?token=${token}`)
  }
}
