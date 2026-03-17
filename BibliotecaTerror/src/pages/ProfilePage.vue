<script setup>
import { ref, reactive } from 'vue'
import { useAuthStore } from '../stores/auth'
import authApi from '../api/auth'

const authStore = useAuthStore()

const handleLogout = async () => {
  await authStore.logout()
  window.location.href = '/'
}

// Form States
const usernameData = reactive({
  username: authStore.user?.username || ''
})

const phoneData = reactive({
  phone: authStore.user?.phone || ''
})

const passwordData = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})

const emailData = reactive({
  new_email: ''
})

// UI States
const isLoading = ref(false)
const successMsg = ref('')
const errorMsg = ref('')

// Helpers
const clearMessages = () => {
  successMsg.value = ''
  errorMsg.value = ''
}

const showSuccess = (msg) => {
  successMsg.value = msg
  setTimeout(() => clearMessages(), 5000)
}

const showError = (msg) => {
  errorMsg.value = msg
  setTimeout(() => clearMessages(), 5000)
}

// Handlers
const handleUpdateUsername = async () => {
  if (!usernameData.username.trim()) {
    showError("El nombre de usuario es obligatorio.")
    return
  }
  
  if (usernameData.username === authStore.user?.username) {
    showSuccess("El nombre de usuario ya es ese.")
    return
  }

  isLoading.value = true
  clearMessages()

  try {
    const { data } = await authApi.updateUsername(usernameData.username)
    if (data.token) authStore.setAuth(data.token, data.user)
    showSuccess("Nombre de usuario actualizado con éxito.")
  } catch (error) {
    showError(error.response?.data?.error || "Error al actualizar el nombre de usuario.")
  } finally {
    isLoading.value = false
  }
}

const handleUpdatePhone = async () => {
  if (phoneData.phone === authStore.user?.phone) {
    showSuccess("El teléfono ya es ese.")
    return
  }

  isLoading.value = true
  clearMessages()

  try {
    const { data } = await authApi.updatePhone(phoneData.phone)
    if (data.token) authStore.setAuth(data.token, data.user)
    showSuccess("Teléfono actualizado con éxito.")
  } catch (error) {
    showError(error.response?.data?.error || "Error al actualizar el teléfono.")
  } finally {
    isLoading.value = false
  }
}

const handleChangePassword = async () => {
  if (!passwordData.current_password || !passwordData.new_password || !passwordData.new_password_confirmation) {
    showError("Rellena todos los campos de contraseña.")
    return
  }

  if (passwordData.new_password !== passwordData.new_password_confirmation) {
    showError("Las contraseñas nuevas no coinciden.")
    return
  }

  isLoading.value = true
  clearMessages()

  try {
    const { data } = await authApi.changePassword(
      passwordData.current_password, 
      passwordData.new_password, 
      passwordData.new_password_confirmation
    )
    if (data.token) authStore.setAuth(data.token, data.user)
    
    showSuccess("Contraseña actualizada con éxito.")
    passwordData.current_password = ''
    passwordData.new_password = ''
    passwordData.new_password_confirmation = ''
  } catch (error) {
    showError(error.response?.data?.error || "Error al cambiar la contraseña.")
  } finally {
    isLoading.value = false
  }
}

const handleRequestEmailChange = async () => {
  if (!emailData.new_email.trim()) {
    showError("Introduce el nuevo correo electrónico.")
    return
  }

  isLoading.value = true
  clearMessages()

  try {
    await authApi.requestEmailChange(emailData.new_email)
    showSuccess(`Se ha enviado un enlace de confirmación a: ${emailData.new_email}. Revisa tu bandeja de entrada.`)
    emailData.new_email = ''
  } catch (error) {
    showError(error.response?.data?.error || "Error al solicitar el cambio de correo.")
  } finally {
    isLoading.value = false
  }
}

</script>

<template>
  <section class="profile-page">
    <div class="profile-container">
      
      <!-- HEADER -->
      <div class="profile-header">
        <h2>Ajustes de Perfil</h2>
        <div v-if="authStore.user" class="role-badge">
          Rol: <span>{{ authStore.user.role }}</span>
        </div>
      </div>

      <!-- ALERTS -->
      <div v-if="successMsg" class="alert success">{{ successMsg }}</div>
      <div v-if="errorMsg" class="alert error">{{ errorMsg }}</div>

      <!-- SETTINGS GRID -->
      <div class="settings-grid">
        
        <!-- COLUMN 1: Username & Phone -->
        <div class="settings-col">
          
          <!-- CHANGE USERNAME -->
          <div class="settings-card">
            <h3>Nombre de Usuario</h3>
            <form @submit.prevent="handleUpdateUsername" class="settings-form">
              <div class="form-group">
                <label>Nombre de Usuario</label>
                <input v-model="usernameData.username" type="text" :disabled="isLoading" />
              </div>
              <button type="submit" class="submit-btn" :disabled="isLoading">
                Actualizar Usuario
              </button>
            </form>
          </div>

          <!-- CHANGE PHONE -->
          <div class="settings-card">
            <h3>Teléfono</h3>
            <form @submit.prevent="handleUpdatePhone" class="settings-form">
              <div class="form-group">
                <label>Teléfono (Opcional)</label>
                <input v-model="phoneData.phone" type="text" :disabled="isLoading" />
              </div>
              <button type="submit" class="submit-btn" :disabled="isLoading">
                Actualizar Teléfono
              </button>
            </form>
          </div>

        </div>

        <!-- COLUMN 2: Security & Email -->
        <div class="settings-col">
          
          <!-- CHANGE EMAIL -->
          <div class="settings-card email-card">
            <h3>Correo Electrónico</h3>
            <p class="current-email">
              Actual: <strong>{{ authStore.user?.email }}</strong>
            </p>
            <p class="email-notice">
              Cambiar tu correo requiere verificación vía enlace.
            </p>
            <form @submit.prevent="handleRequestEmailChange" class="settings-form">
              <div class="form-group">
                <label>Nuevo Correo</label>
                <input v-model="emailData.new_email" type="email" placeholder="nuevo@correo.com" :disabled="isLoading" />
              </div>
              <button type="submit" class="submit-btn" :disabled="isLoading">
                Solicitar Cambio
              </button>
            </form>
          </div>

          <!-- CHANGE PASSWORD -->
          <div class="settings-card">
            <h3>Cambiar Contraseña</h3>
            <form @submit.prevent="handleChangePassword" class="settings-form">
              <div class="form-group">
                <label>Contraseña Actual</label>
                <input v-model="passwordData.current_password" type="password" :disabled="isLoading" />
              </div>
              <div class="form-group">
                <label>Nueva Contraseña</label>
                <input v-model="passwordData.new_password" type="password" :disabled="isLoading" />
              </div>
              <div class="form-group">
                <label>Confirmar Nueva Contraseña</label>
                <input v-model="passwordData.new_password_confirmation" type="password" :disabled="isLoading" />
              </div>
              <button type="submit" class="submit-btn" :disabled="isLoading">
                Cambiar Contraseña
              </button>
            </form>
          </div>

        </div>

      </div>
      
      <!-- LOGOUT -->
      <div class="profile-footer">
        <button class="logout-btn" @click="handleLogout" :disabled="isLoading">Cerrar Sesión</button>
      </div>

    </div>
  </section>
</template>

<style scoped>
.profile-page {
  display: flex;
  justify-content: center;
  padding: 3rem 1rem;
  min-height: calc(100vh - var(--header-height));
}

.profile-container {
  width: 100%;
  max-width: 900px;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.profile-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid rgba(237, 77, 77, 0.2);
  padding-bottom: 1rem;
}

.profile-header h2 {
  color: #f5f5f4;
  margin: 0;
  font-size: 2rem;
}

.role-badge {
  background: rgba(237, 77, 77, 0.15);
  padding: 0.5rem 1rem;
  border-radius: 999px;
  border: 1px solid rgba(237, 77, 77, 0.3);
  color: #c6cbdb;
  font-size: 0.9rem;
}

.role-badge span {
  color: #ff8a8a;
  font-weight: bold;
  text-transform: capitalize;
}

/* Alerts */
.alert {
  padding: 1rem;
  border-radius: 8px;
  text-align: center;
  font-weight: 500;
  animation: fadeIn 0.3s ease;
}

.alert.success {
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.4);
  color: #4ade80;
}

.alert.error {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.4);
  color: #f87171;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Grid */
.settings-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2rem;
}

@media (min-width: 768px) {
  .settings-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.settings-col {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

/* Cards */
.settings-card {
  background: rgba(15, 17, 26, 0.85);
  border: 1px solid rgba(237, 77, 77, 0.15);
  border-radius: 12px;
  padding: 2rem;
  color: #c6cbdb;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
}

.settings-card h3 {
  margin-top: 0;
  margin-bottom: 1.5rem;
  color: #ff8a8a;
  font-size: 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  padding-bottom: 0.75rem;
}

/* Forms */
.settings-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-size: 0.9rem;
  color: #94a3b8;
}

.form-group input {
  padding: 0.8rem 1rem;
  background: rgba(8, 9, 14, 0.8);
  border: 1px solid #2d3348;
  border-radius: 8px;
  color: #f5f5f4;
  font-size: 1rem;
  transition: all 0.2s;
}

.form-group input:focus {
  outline: none;
  border-color: #ff4d4d;
  box-shadow: 0 0 0 2px rgba(237, 77, 77, 0.2);
}

.form-group input:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.submit-btn {
  margin-top: 0.5rem;
  padding: 0.85rem;
  background: #ff4d4d;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.submit-btn:hover:not(:disabled) {
  background: #ff3333;
  transform: translateY(-1px);
}

.submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Email Card Specifics */
.email-card .current-email {
  margin-top: 0;
  margin-bottom: 0.5rem;
}

.email-card .current-email strong {
  color: #f5f5f4;
}

.email-card .email-notice {
  font-size: 0.85rem;
  color: #94a3b8;
  margin-bottom: 1.5rem;
  line-height: 1.4;
}

/* Footer / Logout */
.profile-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.logout-btn {
  padding: 0.8rem 2rem;
  background: transparent;
  border: 1px solid #ff8a8a;
  color: #ff8a8a;
  border-radius: 8px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.2s;
}

.logout-btn:hover:not(:disabled) {
  background: rgba(237, 77, 77, 0.1);
}

.logout-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
