<script setup>
import { RouterLink } from 'vue-router'

defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal-overlay" @click.self="emit('close')">
      <section class="modal-card">
        <button class="close-button" type="button" @click="emit('close')">X</button>

        <h2>Iniciar sesion</h2>
        <p>Accede para guardar listas y recomendaciones.</p>

        <form class="auth-form" @submit.prevent>
          <label>
            Correo electronico
            <input type="email" placeholder="tu@email.com" required />
          </label>

          <label>
            Contrasena
            <input type="password" placeholder="********" required />
          </label>

          <button type="submit" class="submit-button">Entrar</button>
        </form>

        <div class="modal-actions">
          <RouterLink class="full-register-link" to="/registro" @click="emit('close')">
            No tengo cuenta, quiero registrarme
          </RouterLink>
        </div>
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
}

.modal-actions {
  margin-top: 0.9rem;
  display: grid;
  gap: 0.3rem;
}

.full-register-link {
  color: #f26a6a;
  text-decoration: none;
  font-size: 0.92rem;
}
</style>
