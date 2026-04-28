<script setup>
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import authApi from '../api/auth'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

const authStore = useAuthStore()
const router = useRouter()

const mode = ref('login') // 'login' or 'forgot'
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const successMsg = ref('')

const handleLogin = async () => {
  error.value = ''
  loading.value = true
  try {
    await authStore.login({ email: email.value, password: password.value })
    emit('close')
    window.location.reload()
  } catch (err) {
    error.value = authStore.error || 'Error al iniciar sesión'
  } finally {
    loading.value = false
  }
}

const handleForgot = async () => {
  error.value = ''
  successMsg.value = ''
  loading.value = true
  try {
    await authApi.requestPasswordReset(email.value)
    successMsg.value = 'Si tu cuenta existe, hemos enviado un enlace de recuperación.'
  } catch (err) {
    error.value = 'Error al procesar la solicitud.'
  } finally {
    loading.value = false
  }
}

const toggleMode = () => {
  mode.value = mode.value === 'login' ? 'forgot' : 'login'
  error.value = ''
  successMsg.value = ''
}
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal-overlay" @click.self="emit('close')">
      <section class="modal-card">
        <button class="close-button" type="button" @click="emit('close')">X</button>

        <template v-if="mode === 'login'">
          <h2>Iniciar sesión</h2>
          <p>Accede para guardar listas y recomendaciones.</p>

          <div v-if="error" class="error-message">{{ error }}</div>

          <form class="auth-form" @submit.prevent="handleLogin">
            <label>
              Correo electrónico
              <input type="email" v-model="email" placeholder="tu@email.com" required />
            </label>

            <label>
              Contraseña
              <input type="password" v-model="password" placeholder="********" required />
            </label>

            <button type="submit" class="submit-button" :disabled="loading">
              {{ loading ? 'Entrando...' : 'Entrar' }}
            </button>
          </form>

          <div class="modal-actions">
            <button type="button" class="text-link" @click="toggleMode">¿Olvidaste tu contraseña?</button>
            <RouterLink class="full-register-link" to="/registro" @click="emit('close')">
              No tengo cuenta, quiero registrarme
            </RouterLink>
          </div>
        </template>

        <template v-else>
          <h2>Recuperar contraseña</h2>
          <p>Ingresa tu correo y te enviaremos un enlace.</p>

          <div v-if="error" class="error-message">{{ error }}</div>
          <div v-if="successMsg" class="success-message">{{ successMsg }}</div>

          <form class="auth-form" @submit.prevent="handleForgot">
            <label>
              Correo electrónico
              <input type="email" v-model="email" placeholder="tu@email.com" required />
            </label>

            <button type="submit" class="submit-button" :disabled="loading">
              {{ loading ? 'Enviando...' : 'Enviar enlace' }}
            </button>
          </form>

          <div class="modal-actions">
            <button type="button" class="text-link" @click="toggleMode">Volver al inicio de sesión</button>
          </div>
        </template>

      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgba(0, 0, 0, 0.68);
  z-index: 40;
}

.modal-card {
  width: min(390px, 100%);
  border: 1px solid #333a4c;
  border-radius: 16px;
  padding: 1.6rem 1.2rem 1.4rem;
  background: linear-gradient(160deg, #121521, #0d1017);
  position: relative;
}

.close-button {
  position: absolute;
  right: 0.9rem;
  top: 0.8rem;
  border: 0;
  color: #c8cedd;
  background: transparent;
  padding: 0;
  font-size: 1.05rem;
  line-height: 1;
  cursor: pointer;
}

h2 {
  margin: 0;
  color: #f7f7f8;
}

p {
  margin: 0.4rem 0 1rem;
  color: #bbc2d2;
}

.auth-form {
  display: grid;
  gap: 0.85rem;
}

label {
  display: grid;
  gap: 0.3rem;
  color: #d2d7e4;
  font-size: 0.9rem;
}

input {
  border: 1px solid #33394b;
  border-radius: 10px;
  padding: 0.6rem 0.75rem;
  background: #0f121a;
  color: #eceef2;
}

.submit-button {
  border: 1px solid #ed4d4d;
  border-radius: 10px;
  padding: 0.68rem;
  background: #ed4d4d;
  color: #191315;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s;
}
.submit-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error-message {
  color: #ff8a8a;
  background: rgba(237, 77, 77, 0.1);
  padding: 0.5rem;
  border-radius: 6px;
  text-align: center;
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
}
.success-message {
  color: #55f385;
  background: rgba(85, 243, 133, 0.1);
  padding: 0.5rem;
  border-radius: 6px;
  text-align: center;
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
}

.modal-actions {
  margin-top: 0.9rem;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  align-items: center;
}

.full-register-link {
  color: #f26a6a;
  text-decoration: none;
  font-size: 0.92rem;
}

.text-link {
  background: none;
  border: none;
  color: #97a0b7;
  cursor: pointer;
  font-size: 0.9rem;
  padding: 0;
  text-decoration: underline;
}
</style>
