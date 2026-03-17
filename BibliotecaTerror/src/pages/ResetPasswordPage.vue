<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import authApi from '../api/auth'

const route = useRoute()
const router = useRouter()

const token = ref('')
const password = ref('')
const confirmPassword = ref('')
const loading = ref(false)
const error = ref('')
const success = ref(false)

onMounted(() => {
  token.value = route.query.token || ''
  if (!token.value) {
    error.value = 'Token no válido o expirado.'
  }
})

const handleReset = async () => {
  if (password.value !== confirmPassword.value) {
    error.value = 'Las contraseñas no coinciden'
    return
  }
  
  error.value = ''
  loading.value = true
  try {
    await authApi.resetPassword(token.value, password.value, confirmPassword.value)
    success.value = true
    setTimeout(() => {
      router.push('/')
    }, 3000)
  } catch (err) {
    error.value = err.response?.data?.error || 'Error al restablecer la contraseña'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="reset-page">
    <div class="reset-card">
      <h2>Restablecer contraseña</h2>

      <div v-if="success" class="success-message">
        <p>Tu contraseña ha sido actualizada. Redirigiendo...</p>
      </div>
      
      <div v-if="error" class="error-message">
        <p>{{ error }}</p>
      </div>

      <form v-if="!success && token" class="reset-form" @submit.prevent="handleReset">
        <label>
          Nueva contraseña
          <input type="password" v-model="password" required minlength="6" />
        </label>
        <label>
          Confirmar nueva contraseña
          <input type="password" v-model="confirmPassword" required minlength="6" />
        </label>
        <button type="submit" :disabled="loading">
          {{ loading ? 'Guardando...' : 'Guardar contraseña' }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.reset-page {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 200px);
  padding: 2rem 1rem;
}

.reset-card {
  background: rgba(15, 17, 26, 0.85);
  border: 1px solid rgba(237, 77, 77, 0.15);
  border-radius: 12px;
  padding: 2.5rem;
  width: 100%;
  max-width: 450px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

h2 {
  margin: 0 0 1.5rem;
  color: #f5f5f4;
  text-align: center;
}

.reset-form {
  display: grid;
  gap: 1rem;
}

label {
  display: grid;
  gap: 0.35rem;
  color: #d6daE6;
  font-size: 0.9rem;
}

input {
  border: 1px solid #32384a;
  border-radius: 10px;
  padding: 0.65rem 0.8rem;
  background: #0f121a;
  color: #f0f1f4;
}

button {
  margin-top: 1rem;
  border: 1px solid #ed4d4d;
  border-radius: 10px;
  background: #ed4d4d;
  color: #1a1313;
  font-weight: 700;
  padding: 0.8rem;
  cursor: pointer;
}

.error-message {
  color: #ff8a8a;
  background: rgba(237, 77, 77, 0.1);
  padding: 1rem;
  border-radius: 8px;
  text-align: center;
  margin-bottom: 1rem;
}
.success-message {
  color: #55f385;
  background: rgba(85, 243, 133, 0.1);
  padding: 1rem;
  border-radius: 8px;
  text-align: center;
  margin-bottom: 1rem;
}
</style>
