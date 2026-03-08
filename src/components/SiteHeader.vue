<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import menuHamburgerIcon from '../assets/menu-hamburger.svg'

const emit = defineEmits(['open-login'])
const isMenuOpen = ref(false)

const toggleMenu = () => {
  isMenuOpen.value = !isMenuOpen.value
}

const closeMenu = () => {
  isMenuOpen.value = false
}

const openLoginFromMenu = () => {
  emit('open-login')
  closeMenu()
}
</script>

<template>
  <header class="site-header">
    <RouterLink to="/" class="brand" @click="closeMenu">
      <span class="brand-mark">BT</span>
      <div>
        <p class="brand-title">Biblioteca del Terror</p>
        <p class="brand-subtitle">Archivo nocturno de horror clasico y moderno</p>
      </div>
    </RouterLink>

    <div class="header-actions">
      <button
        type="button"
        class="menu-toggle"
        :aria-expanded="isMenuOpen"
        aria-controls="main-menu"
        aria-label="Abrir menu de navegacion"
        @click="toggleMenu"
      >
        <img :src="menuHamburgerIcon" alt="" class="menu-icon" />
      </button>

      <button class="login-button desktop-only" type="button" @click="emit('open-login')">
        Login / Registro
      </button>
    </div>

    <nav id="main-menu" class="main-nav" :class="{ 'is-open': isMenuOpen }" aria-label="Navegacion principal">
      <RouterLink to="/" class="nav-link" exact-active-class="is-active" @click="closeMenu">
        Inicio
      </RouterLink>
      <a href="#" class="nav-link" @click="closeMenu">Catalogo</a>
      <a href="#" class="nav-link" @click="closeMenu">Novedades</a>
      <a href="#" class="nav-link" @click="closeMenu">Eventos</a>
      <RouterLink to="/registro" class="nav-link" active-class="is-active" @click="closeMenu">
        Registro
      </RouterLink>

      <button class="login-button menu-login mobile-only" type="button" @click="openLoginFromMenu">
        Login / Registro
      </button>
    </nav>
  </header>
</template>

<style scoped>
.site-header {
  position: sticky;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10;
  display: grid;
  grid-template-columns: auto 1fr auto;
  grid-template-areas: 'brand nav actions';
  align-items: center;
  gap: 1rem;
  width: 100vw;
  margin-left: calc(50% - 50vw);
  padding: 1rem 1.5rem;
  background: rgba(0, 0, 0, 0.72);
  border-bottom: 1px solid rgba(237, 77, 77, 0.25);
  backdrop-filter: blur(6px);
}

.brand {
  grid-area: brand;
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
  justify-self: start;
  width: fit-content;
}

.brand-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.2rem;
  height: 2.2rem;
  border-radius: 6px;
  color: #f5f5f4;
  font-weight: 700;
  letter-spacing: 0.04em;
  background: linear-gradient(135deg, #8f1d1d, #d12f2f);
}

.brand-title {
  margin: 0;
  color: #f5f5f4;
  font-size: 1rem;
  font-weight: 700;
}

.brand-subtitle {
  margin: 0.15rem 0 0;
  color: #b9bcc6;
  font-size: 0.75rem;
}

.main-nav {
  grid-area: nav;
  display: flex;
  gap: 1rem;
  justify-self: center;
  justify-content: center;
  flex-wrap: wrap;
}

.nav-link {
  color: #e3e5eb;
  text-decoration: none;
  font-size: 0.95rem;
  padding-bottom: 0.2rem;
  border-bottom: 1px solid transparent;
  transition: color 0.2s ease, border-color 0.2s ease;
}

.nav-link:hover {
  color: #ed4d4d;
}

.nav-link.is-active {
  color: #f6f6f7;
  border-bottom-color: rgba(237, 77, 77, 0.6);
}

.header-actions {
  grid-area: actions;
  justify-self: end;
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.menu-toggle,
.login-button {
  border: 1px solid #ed4d4d;
  background: #ed4d4d;
  color: #120f11;
  font-weight: 700;
  border-radius: 999px;
  padding: 0.6rem 1rem;
  cursor: pointer;
}

.menu-toggle:hover,
.login-button:hover {
  background: #f26a6a;
}

.menu-toggle {
  display: none;
  border: 0;
  background: transparent;
  padding: 0.25rem;
  border-radius: 0;
  -webkit-tap-highlight-color: transparent;
}

.menu-toggle:focus,
.menu-toggle:focus-visible,
.menu-toggle:active {
  outline: none;
  box-shadow: none;
  background: transparent;
}

.menu-icon {
  width: 1.35rem;
  height: 1.35rem;
  display: block;
}

.mobile-only {
  display: none;
}

@media (max-width: 1024px) {
  .site-header {
    padding: 0.9rem 1rem;
    gap: 0.75rem;
    grid-template-columns: 1fr auto;
    grid-template-areas:
      'brand actions'
      'nav nav';
  }

  .main-nav {
    width: 100%;
    justify-self: stretch;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.7rem;
    margin-top: 0.2rem;
    padding: 0;
    max-height: 0;
    opacity: 0;
    overflow: hidden;
    pointer-events: none;
    border-radius: 12px;
    border: 1px solid transparent;
    background: rgba(12, 14, 22, 0.96);
    transform: translateY(-12px);
    transition:
      max-height 0.3s ease,
      opacity 0.2s ease,
      transform 0.3s ease,
      padding 0.2s ease,
      border-color 0.2s ease;
  }

  .main-nav.is-open {
    max-height: 320px;
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
    padding: 0.9rem;
    border-color: rgba(237, 77, 77, 0.25);
  }

  .nav-link {
    display: block;
    width: 100%;
    text-align: center;
    font-size: 0.96rem;
    padding: 0.65rem 0.8rem;
    border: 1px solid #2d3242;
    border-radius: 10px;
    background: #141824;
    color: #e8ebf2;
  }

  .nav-link:hover {
    color: #f6f6f7;
    border-color: rgba(237, 77, 77, 0.5);
    background: #171d2a;
  }

  .nav-link.is-active {
    border-bottom-color: transparent;
    border-color: rgba(237, 77, 77, 0.65);
    background: rgba(237, 77, 77, 0.18);
  }

  .menu-toggle,
  .mobile-only {
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .desktop-only {
    display: none;
  }

  .menu-login {
    width: 100%;
    justify-content: center;
    margin-top: 0.25rem;
  }
}

@media (max-width: 900px) {
  .site-header {
    gap: 0.6rem 0.7rem;
  }
}

@media (max-width: 640px) {
  .site-header {
    padding: 0.75rem 0.9rem;
  }

  .brand-subtitle {
    display: none;
  }

  .brand-title {
    font-size: 0.92rem;
  }

  .main-nav {
    gap: 0.65rem;
  }

  .menu-toggle,
  .login-button {
    padding: 0.5rem 0.8rem;
    font-size: 0.85rem;
  }
}
</style>
