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
  new_email: '',
  current_password: ''
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

const scrollToAlerts = () => {
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const showSuccess = (msg) => {
  successMsg.value = msg
  scrollToAlerts()
  setTimeout(() => clearMessages(), 5000)
}

const showError = (msg) => {
  errorMsg.value = msg
  scrollToAlerts()
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
  if (!emailData.current_password) {
    showError("Confirma tu contraseña actual para cambiar el correo.")
    return
  }

  isLoading.value = true
  clearMessages()

  try {
    await authApi.requestEmailChange(emailData.new_email, emailData.current_password)
    showSuccess(`Se ha enviado un enlace de confirmación a: ${emailData.new_email}. Revisa tu bandeja de entrada.`)
    emailData.new_email = ''
    emailData.current_password = ''
  } catch (error) {
    showError(error.response?.data?.error || "Error al solicitar el cambio de correo.")
  } finally {
    isLoading.value = false
  }
}

// ACCOUNT DELETION
const showDeleteModal = ref(false)
const deleteLoading = ref(false)

const handleDeleteAccount = async () => {
  deleteLoading.value = true
  try {
    await authApi.deleteMe()
    authStore.clearAuth()
    window.location.href = '/'
  } catch (error) {
    showDeleteModal.value = false
    showError(error.response?.data?.error || "No se ha podido cerrar la cuenta. Comprueba que no tengas préstamos activos.")
  } finally {
    deleteLoading.value = false
  }
}

</script>

<template>
  <section class="profile-page">
    <div class="profile-container">
      
      <!-- HEADER -->
      <header class="profile-main-header">
        <div class="header-content">
          <div class="user-avatar-large">
            <span>{{ authStore.user?.username?.charAt(0).toUpperCase() }}</span>
          </div>
          <div class="header-text">
            <h2>{{ authStore.user?.name }}</h2>
            <p class="user-handle">@{{ authStore.user?.username }}</p>
            <div v-if="authStore.user" class="role-pill">
              <svg viewBox="0 0 24 24" class="icon-xs" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
              </svg>
              {{ authStore.user.role }}
            </div>
          </div>
        </div>
      </header>

      <!-- ALERTS -->
      <transition name="fade">
        <div v-if="successMsg" class="alert success">
          <svg viewBox="0 0 24 24" class="icon-sm" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          {{ successMsg }}
        </div>
      </transition>
      <transition name="fade">
        <div v-if="errorMsg" class="alert error">
          <svg viewBox="0 0 24 24" class="icon-sm" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          {{ errorMsg }}
        </div>
      </transition>

      <div class="settings-sections">
        <!-- SECTION 1: MI CUENTA -->
        <section class="settings-group">
          <div class="group-header">
            <svg viewBox="0 0 24 24" class="icon-md" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" /><circle cx="12" cy="7" r="4" />
            </svg>
            <h3>Mi Cuenta</h3>
          </div>
          <div class="settings-card">
            <form @submit.prevent="handleUpdateUsername" class="settings-form-row">
              <div class="form-item flex-1">
                <label>Nombre de Usuario</label>
                <input v-model="usernameData.username" type="text" :disabled="isLoading" />
              </div>
              <button type="submit" class="action-btn" :disabled="isLoading">Guardar</button>
            </form>
            <div class="divider"></div>
            <form @submit.prevent="handleUpdatePhone" class="settings-form-row">
              <div class="form-item flex-1">
                <label>Teléfono de Contacto</label>
                <input v-model="phoneData.phone" type="text" placeholder="Ej: 600000000" :disabled="isLoading" />
              </div>
              <button type="submit" class="action-btn" :disabled="isLoading">Actualizar</button>
            </form>
            <div class="divider"></div>
            <div class="settings-form-row">
              <div class="form-item flex-1">
                <label>DNI (No editable)</label>
                <input :value="authStore.user?.dni" type="text" disabled class="disabled-input" />
              </div>
              <div class="info-tag">Verificado</div>
            </div>
          </div>
        </section>

        <!-- SECTION 2: SEGURIDAD -->
        <section class="settings-group">
          <div class="group-header">
            <svg viewBox="0 0 24 24" class="icon-md" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0110 0v4" />
            </svg>
            <h3>Seguridad</h3>
          </div>
          <div class="settings-card">
            <div class="email-section">
              <div class="email-info">
                <label>Correo Electrónico</label>
                <p>{{ authStore.user?.email }}</p>
              </div>
              <form @submit.prevent="handleRequestEmailChange" class="settings-form-column">
                <div class="form-item">
                  <input v-model="emailData.new_email" type="email" placeholder="Nuevo correo electrónico" :disabled="isLoading" />
                </div>
                <div class="form-item">
                  <input v-model="emailData.current_password" type="password" placeholder="Contraseña actual" :disabled="isLoading" />
                </div>
                <button type="submit" class="action-btn secure" :disabled="isLoading">Cambiar Email</button>
              </form>
              <p class="note">Recibirás un enlace de confirmación en el nuevo correo.</p>
            </div>
            
            <div class="divider"></div>

            <div class="password-section">
              <label>Cambiar Contraseña</label>
              <form @submit.prevent="handleChangePassword" class="settings-form-column">
                <div class="form-item">
                  <input v-model="passwordData.current_password" type="password" placeholder="Contraseña actual" :disabled="isLoading" />
                </div>
                <div class="form-row">
                  <input v-model="passwordData.new_password" type="password" placeholder="Nueva contraseña" :disabled="isLoading" />
                  <input v-model="passwordData.new_password_confirmation" type="password" placeholder="Repite contraseña" :disabled="isLoading" />
                </div>
                <button type="submit" class="action-btn secure" :disabled="isLoading">Actualizar Seguridad</button>
              </form>
            </div>
          </div>
        </section>

        <!-- SECTION 3: ZONA DE PELIGRO -->
        <section class="settings-group danger-zone">
          <div class="group-header">
            <svg viewBox="0 0 24 24" class="icon-md" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
            <h3>Zona de Peligro</h3>
          </div>
          <div class="settings-card danger">
            <div class="danger-item">
              <div class="text">
                <h4>Cerrar Sesión</h4>
                <p>Salir de tu cuenta en este dispositivo.</p>
              </div>
              <button class="logout-btn-new" @click="handleLogout">Salir</button>
            </div>
            <div class="divider dark"></div>
            <div class="danger-item">
              <div class="text">
                <h4>Eliminar Cuenta</h4>
                <p>Borrar permanentemente todos tus datos y préstamos.</p>
              </div>
              <button class="delete-btn-new" @click="showDeleteModal = true">Dar de baja</button>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- DELETE MODAL -->
    <transition name="scale">
      <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
        <div class="modal-content-premium">
          <div class="modal-icon-danger">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </div>
          <h3>¿Eliminar tu cuenta?</h3>
          <p>Esta acción es irreversible y perderás todo el acceso a tus libros y servicios.</p>
          
          <div class="modal-buttons">
            <button class="btn-cancel" @click="showDeleteModal = false" :disabled="deleteLoading">Cancelar</button>
            <button class="btn-confirm-delete" @click="handleDeleteAccount" :disabled="deleteLoading">
              {{ deleteLoading ? 'Procesando...' : 'Confirmar Baja' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </section>
</template>

<style scoped>
.profile-page {
  min-height: calc(100vh - 80px);
  padding: 4rem 1rem;
  background: transparent;
  color: #e2e8f0;
  display: flex;
  justify-content: center;
}

.profile-container {
  width: 100%;
  max-width: 850px;
  animation: slideUp 0.6s ease;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Header */
.profile-main-header {
  margin-bottom: 3rem;
  background: linear-gradient(135deg, rgba(237, 77, 77, 0.1), rgba(0, 0, 0, 0));
  padding: 2.5rem;
  border-radius: 24px;
  border: 1px solid rgba(237, 77, 77, 0.2);
}

.header-content {
  display: flex;
  align-items: center;
  gap: 2rem;
}

.user-avatar-large {
  width: 100px;
  height: 100px;
  background: linear-gradient(135deg, #ed4d4d, #991b1b);
  border-radius: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  font-weight: bold;
  color: white;
  box-shadow: 0 10px 30px rgba(237, 77, 77, 0.3);
}

.header-text h2 {
  font-size: 2.2rem;
  margin: 0;
  color: #fff;
}

.user-handle {
  color: #94a3b8;
  margin: 0.2rem 0 0.8rem;
  font-size: 1.1rem;
}

.role-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem 1rem;
  background: rgba(255, 157, 0, 0.15);
  color: #fbbf24;
  border-radius: 999px;
  border: 1px solid rgba(251, 191, 36, 0.3);
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* Sections */
.settings-sections {
  display: flex;
  flex-direction: column;
  gap: 3rem;
}

.settings-group {
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}

.group-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding-left: 0.5rem;
  color: #ed4d4d;
}

.group-header h3 {
  margin: 0;
  font-size: 1.3rem;
  letter-spacing: 0.5px;
  color: #f8fafc;
}

.settings-card {
  background: rgba(17, 20, 30, 0.4);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.divider {
  height: 1px;
  background: #1f2335;
  margin: 1.5rem 0;
}

/* Forms */
.settings-form-row {
  display: flex;
  align-items: flex-end;
  gap: 1.5rem;
}

.settings-form-column {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-item {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.flex-1 { flex: 1; }

label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

input {
  padding: 0.9rem 1.2rem;
  background: #0a0c12;
  border: 1px solid #2d3348;
  border-radius: 12px;
  color: #fff;
  font-size: 1rem;
  transition: all 0.2s;
}

input:focus {
  outline: none;
  border-color: #ed4d4d;
  background: #0f121a;
  box-shadow: 0 0 0 3px rgba(237, 77, 77, 0.15);
}

input:disabled { opacity: 0.4; cursor: not-allowed; }

.action-btn {
  padding: 0.9rem 1.8rem;
  border-radius: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  background: #b91c1c;
  color: white;
  border: none;
}

.action-btn:hover:not(:disabled) {
  background: #dc2626;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(237, 77, 77, 0.3);
}

.action-btn.outline {
  background: transparent;
  border: 1px solid #2d3348;
  color: #94a3b8;
}

.action-btn.outline:hover {
  background: #1f2335;
  color: #fff;
  border-color: #4b5563;
}

.action-btn.secure {
  background: linear-gradient(135deg, #1e293b, #0f172a);
  border: 1px solid #334155;
}

.action-btn.secure:hover {
  background: #334155;
}

/* Danger Zone */
.danger-zone .settings-card.danger {
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(20, 10, 10, 0.4);
}

.danger-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.danger-item h4 {
  margin: 0;
  color: #fff;
  font-size: 1.1rem;
}

.danger-item p {
  margin: 0.3rem 0 0;
  color: #64748b;
  font-size: 0.9rem;
}

.logout-btn-new, .delete-btn-new {
  padding: 0.7rem 1.4rem;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.9rem;
}

.logout-btn-new {
  background: transparent;
  border: 1px solid #334155;
  color: #94a3b8;
}

.logout-btn-new:hover {
  background: #1f2937;
  color: #fff;
}

.delete-btn-new {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #f87171;
}

.delete-btn-new:hover {
  background: #b91c1c;
  color: white;
  border-color: #b91c1c;
  box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
}

/* Icons & Tags */
.icon-xs { width: 14px; height: 14px; }
.icon-sm { width: 18px; height: 18px; }
.icon-md { width: 22px; height: 22px; }

.info-tag {
  background: rgba(34, 197, 94, 0.15);
  color: #4ade80;
  padding: 0.4rem 1rem;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: bold;
}

.note { font-size: 0.8rem; color: #64748b; margin-top: 1rem; }

/* Alerts */
.alert {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.2rem;
  border-radius: 16px;
  margin-bottom: 2rem;
  font-size: 0.95rem;
}

.alert.success { background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); color: #4ade80; }
.alert.error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }

.fade-enter-active, .fade-leave-active { transition: all 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-10px); }

/* Modal Premium */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.modal-content-premium {
  background: #11141e;
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 28px;
  padding: 3rem;
  max-width: 450px;
  text-align: center;
}

.modal-icon-danger {
  width: 70px;
  height: 70px;
  margin: 0 auto 1.5rem;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 20px;
}

.modal-icon-danger svg { width: 40px; }

.modal-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 2rem;
}

.btn-cancel {
  background: #1f2335;
  color: #fff;
  border: 1px solid #2d3348;
  padding: 1rem;
  border-radius: 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn-confirm-delete {
  background: #b91c1c;
  color: white;
  border: none;
  padding: 1rem;
  border-radius: 14px;
  font-weight: 600;
  cursor: pointer;
}

@media (max-width: 640px) {
  .header-content { flex-direction: column; text-align: center; }
  .settings-form-row { flex-direction: column; align-items: stretch; }
  .form-row { grid-template-columns: 1fr; }
  .modal-buttons { grid-template-columns: 1fr; }
}
</style>
