import axios from 'axios'

// Traducciones de códigos del backend (en inglés) a mensajes en español
// user-friendly. El backend devuelve estos identificadores como contrato
// estable de la API; el frontend los traduce antes de mostrarlos.
// Lookup case-insensitive. Si un código no está en el mapa, se muestra
// el texto original (que puede ser ya una frase en español válida).
const AUTH_MESSAGES = {
  // ── Login / sesión ──
  'invalid credentials': 'Correo o contraseña incorrectos.',
  'email and password are required': 'Introduce correo y contraseña.',
  'email not verified': 'Debes confirmar tu correo antes de iniciar sesión.',
  'account banned': 'Esta cuenta está suspendida. Contacta con el administrador.',
  'account temporarily locked, try again later': 'Demasiados intentos fallidos. Inténtalo de nuevo en 30 minutos.',
  'too many requests, try again later': 'Has realizado demasiadas peticiones. Inténtalo más tarde.',
  'password reset required': 'Por seguridad, necesitas cambiar tu contraseña. Te hemos enviado un email con las instrucciones.',
  'session expired': 'Tu sesión ha caducado. Vuelve a iniciar sesión.',
  'invalid token': 'Tu sesión no es válida. Vuelve a iniciar sesión.',
  'token revoked': 'Tu sesión ya no es válida. Vuelve a iniciar sesión.',
  'unauthorized': 'Debes iniciar sesión para continuar.',
  'forbidden': 'No tienes permisos para realizar esta acción.',

  // ── Registro ──
  'username, first_name, last_name, dni, email, password and password_confirmation are required': 'Rellena todos los campos obligatorios.',
  'invalid username format': 'El nombre de usuario no es válido (3-30 caracteres: letras, números, punto, guion o guion bajo).',
  'invalid email': 'El correo no es válido.',
  'invalid spanish phone number format': 'El teléfono no es un número español válido.',
  'passwords do not match': 'Las contraseñas no coinciden.',
  'password must be at least 6 characters': 'La contraseña debe tener al menos 6 caracteres.',
  'email or username already exists': 'Ese correo o nombre de usuario ya está en uso.',
  'there is already a pending registration for this email or username': 'Ya hay un registro pendiente con ese correo o usuario. Revisa tu bandeja de entrada.',
  'could not create user': 'No se ha podido crear el usuario.',
  'verification email could not be sent': 'No se ha podido enviar el correo de verificación.',

  // ── Verificación / email ──
  'verification token is required': 'Falta el token de verificación.',
  'invalid or expired verification token': 'El enlace de verificación no es válido o ha caducado.',
  'could not verify email': 'No se ha podido verificar el correo.',
  'verification email sent': 'Te hemos enviado el correo de verificación.',
  'email is already verified': 'El correo ya está verificado.',
  'valid email is required': 'Introduce un correo válido.',
  'could not send verification email': 'No se ha podido enviar el correo de verificación.',

  // ── Reset / cambio de contraseña ──
  'token, new_password and new_password_confirmation are required': 'Rellena todos los campos.',
  'new passwords do not match': 'Las contraseñas nuevas no coinciden.',
  'new password must be at least 6 characters': 'La contraseña nueva debe tener al menos 6 caracteres.',
  'invalid or expired reset token': 'El enlace de recuperación no es válido o ha caducado.',
  'new password must be different from current password': 'La contraseña nueva debe ser distinta a la actual.',
  'current password is incorrect': 'La contraseña actual no es correcta.',
  'could not reset password': 'No se ha podido restablecer la contraseña.',
  'could not update password': 'No se ha podido actualizar la contraseña.',
  'could not send password reset email': 'No se ha podido enviar el correo de recuperación.',
  'password updated': 'Contraseña actualizada correctamente.',

  // ── Cambio de email ──
  'current_password is required': 'Confirma tu contraseña actual.',
  'new email must be different from current email': 'El correo nuevo debe ser distinto al actual.',
  'email already exists': 'Ese correo ya está en uso.',
  'confirmation token is required': 'Falta el token de confirmación.',
  'invalid or expired confirmation token': 'El enlace de confirmación no es válido o ha caducado.',
  'could not request email change': 'No se ha podido solicitar el cambio de correo.',
  'could not send email change confirmation': 'No se ha podido enviar la confirmación.',
  'could not confirm email change': 'No se ha podido confirmar el cambio de correo.',
  'email updated': 'Correo actualizado.',
  'email change confirmation sent': 'Te hemos enviado un correo para confirmar el cambio.',

  // ── Perfil ──
  'first_name and last_name are required': 'Introduce nombre y apellidos.',
  'invalid phone': 'El teléfono no es válido.',
  'username already exists': 'Ese nombre de usuario ya está en uso.',
  'could not update username': 'No se ha podido actualizar el nombre de usuario.',
  'could not update name': 'No se han podido actualizar los datos.',
  'could not update phone': 'No se ha podido actualizar el teléfono.',
  'user not found': 'No se ha encontrado el usuario.',

  // ── Admin ──
  'user_id and role are required': 'Faltan parámetros (usuario y rol).',
  'invalid role': 'Rol no válido.',
  'could not update role': 'No se ha podido actualizar el rol.',
  'role updated': 'Rol actualizado.',
  'name, username, email, dni and password are required': 'Rellena todos los campos obligatorios.',
  'DNI already exists': 'Ese DNI ya está registrado.',
  'user_id is required': 'Falta el identificador del usuario.',
  'banned flag is required': 'Falta indicar si hay que banear o desbanear.',
  'cannot target self': 'No puedes aplicar esta acción sobre tu propia cuenta.',
  'could not force logout': 'No se ha podido cerrar la sesión del usuario.',
  'could not update ban state': 'No se ha podido actualizar el estado del baneo.',
  'sessions invalidated': 'Sesiones del usuario cerradas.',
  'user banned': 'Usuario baneado.',
  'user unbanned': 'Usuario desbaneado.',
  'account deleted': 'Cuenta eliminada.',
  'user deleted': 'Usuario eliminado.',
  'could not delete user': 'No se ha podido eliminar al usuario.',
  'could not delete account': 'No se ha podido eliminar la cuenta.',
  'logged out': 'Sesión cerrada.',
}

function translateAuthMessage(code) {
  if (typeof code !== 'string') return code
  const key = code.toLowerCase()
  return AUTH_MESSAGES[key] || code
}

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

// Response interceptor:
//   (a) Si el backend invalida la sesión (login desde otro dispositivo, ban,
//       admin force-logout, expiración…) limpiamos el token local.
//   (b) Traducimos los códigos del backend (inglés) a mensajes en español
//       mutando response.data.error / response.data.message / err.response.data.*
//       ANTES de que el componente los lea. Así cualquier componente existente
//       que haga `err.response?.data?.error` recibe ya el texto traducido.
api.interceptors.response.use(
  (response) => {
    if (response?.data) {
      if (response.data.error) {
        response.data.error = translateAuthMessage(response.data.error)
      }
      if (response.data.message) {
        response.data.message = translateAuthMessage(response.data.message)
      }
    }
    return response
  },
  (error) => {
    if (error.response?.status === 401 && localStorage.getItem('auth_token')) {
      localStorage.removeItem('auth_token')
    }
    if (error.response?.data) {
      if (error.response.data.error) {
        error.response.data.error = translateAuthMessage(error.response.data.error)
      }
      if (error.response.data.message) {
        error.response.data.message = translateAuthMessage(error.response.data.message)
      }
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
  },

  // Helper para traducir códigos del backend en componentes que los
  // reciban por otra vía (p.ej. query strings de páginas de confirmación).
  translateAuthMessage
}
