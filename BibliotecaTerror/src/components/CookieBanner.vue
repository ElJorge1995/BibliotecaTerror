<script setup>
import { ref, onMounted } from 'vue'

const showBanner = ref(false)

const acceptCookies = () => {
  localStorage.setItem('cookies_accepted', 'true')
  showBanner.value = false
}

onMounted(() => {
  const accepted = localStorage.getItem('cookies_accepted')
  if (!accepted) {
    showBanner.value = true
  }
})
</script>

<template>
  <transition name="fade-slide">
    <div v-show="showBanner" class="cookie-banner">
      <div class="cookie-content">
        <div class="cookie-text">
          <h3>Un Pacto Necesario</h3>
          <p>Utilizamos una única cookie inofensiva y estrictamente necesaria por motivos de seguridad, para recordar quién eres cuando te paseas por el catálogo o alquilas tomos con tu cuenta. Tienes más detalles en nuestra <RouterLink to="/cookies" class="cookie-link" @click="showBanner=false">Política de Cookies</RouterLink>.</p>
        </div>
      </div>
      <button @click="acceptCookies" class="cookie-btn">Entendido</button>
    </div>
  </transition>
</template>

<style scoped>
.cookie-banner {
  position: fixed;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  width: calc(100% - 2rem);
  max-width: 600px;
  background: rgba(15, 18, 28, 0.95);
  border: 1px solid rgba(237, 77, 77, 0.3);
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  z-index: 9999;
  backdrop-filter: blur(10px);
}

.cookie-content {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.cookie-text h3 {
  margin: 0 0 0.3rem;
  color: #d1d5e0;
  font-size: 1.1rem;
}

.cookie-text p {
  margin: 0;
  color: #8a94ab;
  font-size: 0.85rem;
  line-height: 1.5;
}

.cookie-link {
  color: #ed4d4d;
  text-decoration: underline;
  text-underline-offset: 3px;
  transition: color 0.2s;
}

.cookie-link:hover {
  color: #ff7070;
}

.cookie-btn {
  background: #b91c1c;
  color: #fff;
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.2s, transform 0.1s;
}

.cookie-btn:hover {
  background: #dc2626;
  transform: translateY(-2px);
}

.cookie-btn:active {
  transform: translateY(0);
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.4s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translate(-50%, 20px);
}

@media (max-width: 600px) {
  .cookie-banner {
    flex-direction: column;
    text-align: center;
    bottom: 1rem;
  }
  
  .cookie-content {
    flex-direction: column;
  }
  
  .cookie-btn {
    width: 100%;
  }
}
</style>
