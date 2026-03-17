<script setup>
import { ref } from 'vue'

const formData = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  username: '',
  password: '',
  password_confirmation: ''
})

const loading = ref(false)
const error = ref('')
const successMessage = ref('')

const handleRegister = async () => {
  error.value = ''
  successMessage.value = ''
  
  if (formData.value.password !== formData.value.password_confirmation) {
    error.value = 'Las contraseñas no coinciden'
    return
  }

  if (formData.value.phone) {
    const phoneRegex = /^(?:\+34|0034)?[6789]\d{8}$/
    if (!phoneRegex.test(formData.value.phone)) {
      error.value = 'El teléfono debe ser un número español válido (Ej: 612345678)'
      return
    }
  }
  
  loading.value = true
  
  try {
    const res = await fetch('http://localhost:8000/auth/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(formData.value)
    })
    
    const data = await res.json()
    
    if (!res.ok) {
      error.value = data.error || 'Error al registrar usuario'
    } else {
      successMessage.value = '¡Registro en proceso! Por favor, revisa tu correo electrónico para confirmar tu cuenta.'
      formData.value = {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        username: '',
        password: '',
        password_confirmation: ''
      }
    }
  } catch (err) {
    console.error(err)
    error.value = 'Error de conexión con el servidor.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="register-page">
    <div class="register-card">
      <h1>Crear cuenta</h1>
      <p>Unete a la Librum Tenebris y guarda tus lecturas favoritas.</p>
      
      <div v-if="successMessage" class="success-message">
        <p>{{ successMessage }}</p>
      </div>
      
      <div v-if="error" class="error-message">
        <p>{{ error }}</p>
      </div>

      <form v-if="!successMessage" class="register-form" @submit.prevent="handleRegister">
        <div class="form-row">
          <label>
            Nombre
            <input type="text" v-model="formData.first_name" placeholder="Ej. Elena" required />
          </label>
          <label>
            Apellidos
            <input type="text" v-model="formData.last_name" placeholder="Ej. Salazar" required />
          </label>
        </div>

        <label>
          Correo electronico
          <input type="email" v-model="formData.email" placeholder="tu@email.com" required />
        </label>

        <label>
          Teléfono (opcional)
          <input type="tel" v-model="formData.phone" placeholder="Ej. 612345678" />
        </label>

        <label>
          Nombre de usuario
          <input type="text" v-model="formData.username" placeholder="Usuario lector" required />
        </label>

        <label>
          Contrasena
          <input type="password" v-model="formData.password" placeholder="Minimo 6 caracteres" required minlength="6" />
        </label>

        <label>
          Confirmar contrasena
          <input type="password" v-model="formData.password_confirmation" placeholder="Repite tu contrasena" required minlength="6" />
        </label>

        <button type="submit" :disabled="loading">
          {{ loading ? 'Registrando...' : 'Registrarme' }}
        </button>
      </form>
    </div>
  </section>
</template>

<style scoped>
.register-page {
  display: flex;
  justify-content: center;
  padding: 2.3rem 0 1rem;
}

.register-card {
  width: min(620px, 100%);
  border: 1px solid #303646;
  border-radius: 16px;
  padding: 1.6rem;
  background: linear-gradient(160deg, rgba(18, 21, 30, 0.95), rgba(10, 11, 16, 0.98));
}

h1 {
  margin: 0;
  color: #f6f6f7;
}

p {
  margin: 0.5rem 0 1.2rem;
  color: #b8bece;
}

.register-form {
  display: grid;
  gap: 0.9rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.9rem;
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

input:focus {
  outline: 2px solid rgba(237, 77, 77, 0.5);
  border-color: #ed4d4d;
}

button {
  margin-top: 0.4rem;
  border: 1px solid #ed4d4d;
  border-radius: 10px;
  background: #ed4d4d;
  color: #1a1313;
  font-weight: 700;
  padding: 0.7rem 0.9rem;
  cursor: pointer;
  transition: background 0.2s;
}

button:hover:not(:disabled) {
  background: #f26a6a;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error-message {
  background: rgba(237, 77, 77, 0.1);
  border: 1px solid rgba(237, 77, 77, 0.3);
  color: #ff8a8a;
  padding: 0.75rem;
  border-radius: 8px;
  margin-bottom: 1rem;
  text-align: center;
  font-size: 0.9rem;
}

.success-message {
  background: rgba(85, 243, 133, 0.1);
  border: 1px solid rgba(85, 243, 133, 0.3);
  color: #55f385;
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 1rem;
  text-align: center;
}
</style>
