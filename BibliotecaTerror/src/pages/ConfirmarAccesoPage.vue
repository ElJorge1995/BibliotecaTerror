<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import authApi from '../api/auth'

const route = useRoute()
const router = useRouter()
const state = ref('loading') // loading | confirmed | rejected | expired | invalid | error

const title = computed(() => ({
  loading: 'Procesando tu respuesta...',
  confirmed: '¡Gracias por confirmar!',
  rejected: 'Sesión cerrada por seguridad',
  expired: 'Enlace expirado',
  invalid: 'Enlace no válido',
  error: 'No hemos podido procesarlo',
}[state.value] || 'Confirmación de acceso'))

const description = computed(() => ({
  loading: 'Estamos registrando tu respuesta. Un momento...',
  confirmed: 'Hemos registrado que reconoces este inicio de sesión. Tu cuenta sigue protegida y no necesitas hacer nada más.',
  rejected: 'Hemos cerrado la sesión sospechosa. La próxima vez que accedas te pediremos cambiar la contraseña por seguridad — recibirás un correo con las instrucciones.',
  expired: 'Este enlace ya fue usado o ha caducado. Si recibiste otro aviso más reciente, abre ese email en su lugar.',
  invalid: 'El enlace que has abierto no es correcto. Revisa el correo y vuelve a intentarlo desde el enlace que te enviamos.',
  error: 'Hubo un problema al contactar con el servidor. Inténtalo de nuevo en unos minutos.',
}[state.value] || ''))

const toneClass = computed(() => ({
  loading: 'tone-loading',
  confirmed: 'tone-success',
  rejected: 'tone-danger',
  expired: 'tone-warning',
  invalid: 'tone-warning',
  error: 'tone-warning',
}[state.value] || 'tone-warning'))

onMounted(async () => {
  const token = typeof route.query.token === 'string' ? route.query.token : ''
  const decision = typeof route.query.decision === 'string' ? route.query.decision : ''

  if (!token || !['me', 'not-me'].includes(decision)) {
    state.value = 'invalid'
    return
  }

  try {
    const res = await authApi.confirmLoginLocation(token, decision)
    const allowed = ['confirmed', 'rejected', 'expired', 'invalid']
    state.value = allowed.includes(res?.data?.state) ? res.data.state : 'error'
  } catch {
    state.value = 'error'
  }
})
</script>

<template>
  <div class="confirm-page">
    <div class="confirm-card" :class="toneClass">
      <div class="icon-wrap" aria-hidden="true">
        <svg v-if="state === 'loading'" viewBox="0 0 24 24" class="spin">
          <circle cx="12" cy="12" r="10" stroke-dasharray="50" stroke-dashoffset="25" />
        </svg>
        <svg v-else-if="state === 'confirmed'" viewBox="0 0 24 24">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
          <polyline points="22 4 12 14.01 9 11.01" />
        </svg>
        <svg v-else-if="state === 'rejected'" viewBox="0 0 24 24">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
        <svg v-else-if="state === 'expired'" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" />
          <polyline points="12 6 12 12 16 14" />
        </svg>
        <svg v-else viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="13" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
      </div>

      <h2>{{ title }}</h2>
      <p class="description">{{ description }}</p>

      <button v-if="state !== 'loading'" class="btn" type="button" @click="router.push('/')">
        Ir al inicio
      </button>
    </div>
  </div>
</template>

<style scoped>
.confirm-page {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 200px);
  padding: 2rem 1rem;
}

.confirm-card {
  background: rgba(15, 17, 26, 0.85);
  border: 1px solid rgba(237, 77, 77, 0.15);
  border-radius: 12px;
  padding: 2.5rem;
  width: 100%;
  max-width: 450px;
  text-align: center;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

.icon-wrap {
  display: inline-flex;
  width: 68px;
  height: 68px;
  border-radius: 50%;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.1rem;
  background: rgba(237, 77, 77, 0.12);
}

.icon-wrap svg {
  width: 34px;
  height: 34px;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  fill: none;
}

.tone-loading .icon-wrap {
  background: rgba(151, 160, 183, 0.15);
  color: #97a0b7;
}
.tone-success .icon-wrap {
  background: rgba(34, 197, 94, 0.15);
  color: #4ade80;
}
.tone-danger .icon-wrap {
  background: rgba(237, 77, 77, 0.15);
  color: #ff8a8a;
}
.tone-warning .icon-wrap {
  background: rgba(255, 193, 7, 0.15);
  color: #ffc107;
}

.spin { animation: spin 1s linear infinite; }
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

h2 {
  margin: 0 0 0.8rem;
  color: #f5f5f4;
  font-size: 1.45rem;
}

.description {
  color: #97a0b7;
  line-height: 1.6;
  margin-bottom: 1.5rem;
}

.btn {
  background: #b91c1c;
  color: white;
  border: none;
  padding: 0.75rem 1.4rem;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: background 0.18s ease;
}
.btn:hover {
  background: #dc2626;
}
</style>
