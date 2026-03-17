<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import authApi from '../api/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const status = ref('verifying') // verifying, success, error
const errorMessage = ref('')

onMounted(async () => {
  const token = route.query.token
  if (!token) {
    status.value = 'error'
    errorMessage.value = 'Token no encontrado en la URL.'
    return
  }

  try {
    // The backend has already verified the email and redirected us with a JWT auth token.
    // Set it in the store and fetch the user profile.
    authStore.setAuth(token, null)
    await authStore.fetchMe()
    
    if (!authStore.user) {
      throw new Error('No se pudo cargar el perfil del usuario.')
    }

    status.value = 'success'
    setTimeout(() => {
      router.push('/')
    }, 3000)
  } catch (err) {
    status.value = 'error'
    errorMessage.value = err.response?.data?.error || 'Error al verificar el correo.'
  }
})
</script>

<template>
  <div class="verify-page">
    <div class="verify-card">
      <template v-if="status === 'verifying'">
        <h2>Verificando tu correo...</h2>
        <p>Por favor espera un momento.</p>
      </template>

      <template v-else-if="status === 'success'">
        <h2>¡Correo verificado!</h2>
        <p class="success-text">Tu cuenta está activa. Redirigiendo a tu perfil...</p>
      </template>

      <template v-else>
        <h2>Error de Verificación</h2>
        <p class="error-text">{{ errorMessage }}</p>
        <button class="btn" @click="router.push('/')">Volver al inicio</button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.verify-page {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 200px);
  padding: 2rem 1rem;
}

.verify-card {
  background: rgba(15, 17, 26, 0.85);
  border: 1px solid rgba(237, 77, 77, 0.15);
  border-radius: 12px;
  padding: 2.5rem;
  width: 100%;
  max-width: 450px;
  text-align: center;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

h2 {
  margin: 0 0 1rem;
  color: #f5f5f4;
}

p {
  color: #97a0b7;
  margin-bottom: 1.5rem;
}

.success-text {
  color: #55f385;
}

.error-text {
  color: #ff8a8a;
  background: rgba(237, 77, 77, 0.1);
  padding: 1rem;
  border-radius: 8px;
}

.btn {
  background: #ed4d4d;
  color: white;
  border: none;
  padding: 0.8rem 1.5rem;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
}
.btn:hover {
  background: #f26a6a;
}
</style>
