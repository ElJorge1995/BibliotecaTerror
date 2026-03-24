<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import ghostLogo from '../assets/ghost-logo.svg'
import adminIcon from '../assets/admin-user-icon.svg'
import calendarIcon from '../assets/calendar.svg'
import settingsIcon from '../assets/settings.svg'
import favoriteIcon from '../assets/favorite.svg'
import { useAuthStore } from '../stores/auth'

const emit = defineEmits(['open-login'])
const router = useRouter()
const authStore = useAuthStore()
const isMenuOpen = ref(false)
const isProfileMenuOpen = ref(false)
const searchQuery = ref('')
const profileMenuRef = ref(null)

import booksApi from '../api/books.js'
const searchResults = ref([])
const isSearching = ref(false)
const showDropdown = ref(false)
let searchTimeout = null

const onSearchInput = () => {
  clearTimeout(searchTimeout)
  if (!searchQuery.value.trim()) {
    searchResults.value = []
    showDropdown.value = false
    return
  }
  
  showDropdown.value = true
  searchTimeout = setTimeout(async () => {
    isSearching.value = true
    try {
      const res = await booksApi.buscar(searchQuery.value)
      searchResults.value = res.data.data?.slice(0, 5) ?? []
    } catch (e) {
      console.error(e)
    } finally {
      isSearching.value = false
    }
  }, 250)
}

const closeDropdown = () => {
  setTimeout(() => { showDropdown.value = false }, 200)
}

const toggleProfileMenu = () => {
  isProfileMenuOpen.value = !isProfileMenuOpen.value
}

const closeMenus = () => {
  isMenuOpen.value = false
  isProfileMenuOpen.value = false
  showDropdown.value = false
}

const handleClickOutside = (event) => {
  if (profileMenuRef.value && !profileMenuRef.value.contains(event.target)) {
    isProfileMenuOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  clearTimeout(searchTimeout)
})

const toggleMenu = () => {
  isMenuOpen.value = !isMenuOpen.value
  if (isMenuOpen.value) isProfileMenuOpen.value = false
}

const openLoginFromMenu = () => {
  emit('open-login')
  closeMenus()
}

const handleSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ path: '/buscar', query: { q: searchQuery.value } })
    closeMenus()
  }
}

const goToDirectSearch = (titulo) => {
  router.push({ path: '/buscar', query: { q: titulo } })
  closeMenus()
}

const goToBook = (id) => {
  router.push(`/libro/${id}`)
  closeMenus()
}

const handleLogout = async () => {
  await authStore.logout()
  closeMenus()
  window.location.href = '/'
}

const userInitial = computed(() => {
  if (!authStore.user?.username) return '?'
  return authStore.user.username.charAt(0).toUpperCase()
})
</script>

<template>
  <!-- Sticky wrapper que contiene AMBOS: header y sub-nav -->
  <div class="header-wrapper">

    <!-- Header principal: logo + buscador + login -->
    <header class="site-header">
      <RouterLink to="/" class="brand" @click="closeMenus">
        <img :src="ghostLogo" alt="Fantasma logo" class="brand-ghost" />
          <p class="brand-title">Librum Tenebris</p>
      </RouterLink>

      <!-- Buscador central -->
      <div class="search-container">
        <form class="search-form" role="search" @submit.prevent="handleSearch">
          <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input v-model="searchQuery" 
                 @input="onSearchInput" 
                 @focus="searchQuery.trim() && (showDropdown = true)" 
                 @blur="closeDropdown" 
                 type="search" class="search-input" placeholder="Buscar libros, autores, géneros…"
                 aria-label="Buscar en la biblioteca" />
          <button type="submit" class="search-btn" aria-label="Buscar">Buscar</button>
        </form>

        <!-- Dropdown predictivo -->
        <div v-show="showDropdown" class="search-predictive-dropdown">
          <div v-if="isSearching" class="predictive-msg">Buscando coincidencia rápida...</div>
          <div v-else-if="searchResults.length === 0 && searchQuery.trim()" class="predictive-msg">
            No se encontraron libros rápidos. Pulsa Enter para búsqueda exhaustiva.
          </div>
          <template v-else>
            <div v-for="book in searchResults" :key="book.id" class="predictive-item" @click="goToBook(book.id)">
              <div class="pi-cover">
                <img v-if="book.portada" :src="book.portada" />
                <span v-else>📖</span>
              </div>
              <div class="pi-info">
                <h4>{{ book.titulo_es || book.titulo }}</h4>
                <p>{{ book.autor }}</p>
              </div>
            </div>
            <div class="predictive-footer" @click="handleSearch">
              Ver todos los resultados ({{ searchQuery }})
            </div>
          </template>
        </div>
      </div>

      <!-- Acciones: login + hamburguesa + user menu -->
      <div class="header-actions">
        <!-- Auth state -->
        <template v-if="authStore.isAuthenticated">
          <!-- Botón de admin (desktop y móvil) -->
          <RouterLink v-if="authStore.isAdmin" to="/admin" class="header-link admin-link" @click="closeMenus" aria-label="Panel de Administración">
            <img :src="adminIcon" alt="Admin Icon" class="admin-icon" />
          </RouterLink>
          
          <!-- Botón de usuario (desktop y móvil) -->
          <div class="profile-dropdown-container" ref="profileMenuRef">
            <button class="avatar-button" @click="toggleProfileMenu" aria-label="Abrir menú de usuario">
              {{ userInitial }}
            </button>
            <div v-show="isProfileMenuOpen" class="profile-dropdown">
              <div class="dropdown-header">
                <span class="user-greeting">Hola, {{ authStore.user.username }}</span>
                <span v-if="authStore.isAdmin" class="admin-badge">Admin</span>
              </div>
              <RouterLink to="/favoritos" class="dropdown-link dropdown-link-icon" @click="closeMenus">
                Mis Favoritos <img :src="favoriteIcon" alt="Favoritos" class="dropdown-svg-icon" />
              </RouterLink>
              <RouterLink to="/prestamos" class="dropdown-link dropdown-link-icon" @click="closeMenus">
                Mis Préstamos <img :src="calendarIcon" alt="Calendar" class="dropdown-svg-icon" />
              </RouterLink>
              <RouterLink to="/perfil" class="dropdown-link dropdown-link-icon" @click="closeMenus">
                Configuración <img :src="settingsIcon" alt="Settings" class="dropdown-svg-icon" />
              </RouterLink>
              <button class="dropdown-link logout-dropdown-btn" @click="handleLogout">Salir</button>
            </div>
          </div>
        </template>
        
        <!-- Guest state -->
        <template v-else>
          <button class="login-button desktop-only" type="button" @click="emit('open-login')">
            Iniciar sesión
          </button>
        </template>

        <!-- Hamburguesa móvil a la derecha -->
        <button type="button" class="menu-toggle" :aria-expanded="isMenuOpen" aria-controls="main-menu"
          aria-label="Abrir menú de navegación" @click="toggleMenu">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true">
            <rect y="4" width="22" height="2" rx="1" fill="currentColor" />
            <rect y="10" width="22" height="2" rx="1" fill="currentColor" />
            <rect y="16" width="22" height="2" rx="1" fill="currentColor" />
          </svg>
        </button>
      </div>
    </header>

    <!-- Sub-nav compacta -->
    <nav id="main-menu" class="main-nav" :class="{ 'is-open': isMenuOpen }" aria-label="Navegación principal">
      <div class="nav-inner">
        <RouterLink to="/" class="nav-link" exact-active-class="is-active" @click="closeMenus">Inicio</RouterLink>
        <RouterLink to="/buscar" class="nav-link" exact-active-class="is-active" @click="closeMenus">Catálogo</RouterLink>
        <RouterLink to="/novedades" class="nav-link" exact-active-class="is-active" @click="closeMenus">Novedades</RouterLink>
        <RouterLink to="/recomendaciones" class="nav-link" exact-active-class="is-active" @click="closeMenus">Recomendaciones</RouterLink>

        <!-- Solo en móvil -->
        <template v-if="authStore.isAuthenticated">
          <!-- Mobile links (Admin and profile are in header) -->
        </template>
        <template v-else>
          <button class="login-button mobile-login mobile-only" type="button" @click="openLoginFromMenu">
            Iniciar sesión
          </button>
        </template>
      </div>
    </nav>

  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Germania+One&display=swap');

/* ─── Sticky wrapper ──────────────────────────────────────── */
.header-wrapper {
  position: sticky;
  top: 0;
  left: 0;
  right: 0;
  z-index: 20;
  width: 100vw;
  margin-left: calc(50% - 50vw);
  background: rgba(5, 6, 10, 0.9);
  border-bottom: 1px solid rgba(237, 77, 77, 0.18);
  backdrop-filter: blur(8px);
}

/* ─── Header principal ────────────────────────────────────── */
.site-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.75rem 1.5rem;
  border-bottom: 1px solid rgba(40, 46, 62, 0.6);
}

/* ─── Brand ───────────────────────────────────────────────── */
.brand {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  text-decoration: none;
  flex-shrink: 0;
  flex: 1;
}

.brand-ghost {
  width: 2.2rem;
  height: 2.2rem;
  flex-shrink: 0;
}

.brand-title {
  margin: 0;
  color: #f5f5f4;
  font-family: 'Germania One', cursive, serif;
  font-size: 1.35rem;
  font-weight: 400;
  letter-spacing: 0.05em;
}

/* ─── Search form & Dropdown ──────────────────────────────── */
.search-container {
  position: relative;
  flex: 0 1 1100px;
  max-width: 1100px;
  width: 100%;
}

.search-form {
  display: flex;
  align-items: center;
  background: rgba(20, 24, 36, 0.85);
  border: 1px solid #2d3348;
  border-radius: 999px;
  padding: 0 0.4rem 0 0.9rem;
  gap: 0.5rem;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  width: 100%;
  min-width: 0;
}

.search-form:focus-within {
  border-color: rgba(237, 77, 77, 0.5);
  box-shadow: 0 0 0 3px rgba(237, 77, 77, 0.1);
}

.search-icon {
  color: #5c6480;
  flex-shrink: 0;
}

.search-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #e3e5eb;
  font-size: 0.875rem;
  padding: 0.55rem 0;
  min-width: 0;
}

.search-input::placeholder {
  color: #4d5570;
}

.search-input::-webkit-search-cancel-button {
  display: none;
}

.search-btn {
  background: transparent;
  color: #c6cbdb;
  border: none;
  padding: 0.4rem 0.5rem 0.4rem 1rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  flex-shrink: 0;
  transition: color 0.2s ease;
}

.search-btn:hover {
  color: #f0f2f7;
}

/* Dropdown predictivo */
.search-predictive-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  left: 0;
  right: 0;
  background: #101219;
  border: 1px solid #2d3348;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.8);
  overflow: hidden;
  z-index: 100;
}

.predictive-msg {
  padding: 1.2rem;
  color: #97a0b7;
  font-size: 0.9rem;
  text-align: center;
}

.predictive-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem 1rem;
  cursor: pointer;
  border-bottom: 1px solid rgba(45, 51, 72, 0.5);
  transition: background 0.2s ease;
}

.predictive-item:hover {
  background: rgba(237, 77, 77, 0.08);
}

.pi-cover {
  width: 40px;
  height: 60px;
  border-radius: 4px;
  overflow: hidden;
  flex-shrink: 0;
  background: #1e2030;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}

.pi-cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pi-info h4 {
  margin: 0 0 0.25rem;
  color: #f2f2f3;
  font-size: 0.95rem;
  font-weight: 500;
}

.pi-info p {
  margin: 0;
  color: #8e95a8;
  font-size: 0.8rem;
}

.predictive-footer {
  padding: 0.75rem;
  text-align: center;
  color: #ed4d4d;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  background: rgba(237, 77, 77, 0.03);
  transition: background 0.2s;
}

.predictive-footer:hover {
  background: rgba(237, 77, 77, 0.1);
}

/* ─── Header actions ──────────────────────────────────────── */
.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
  flex: 1;
  justify-content: flex-end;
}

/* ─── Profile Dropdown ────────────────────────────────────── */
.profile-dropdown-container {
  position: relative;
}

.avatar-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1f2335, #141724);
  color: #ed4d4d;
  font-weight: bold;
  font-size: 1rem;
  border: 1px solid #32384a;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}
@media (min-width: 640px) {
  .avatar-button {
    width: 40px;
    height: 40px;
    font-size: 1.1rem;
  }
}

.avatar-button:hover {
  border-color: #ed4d4d;
  background: rgba(237, 77, 77, 0.1);
}

.profile-dropdown {
  position: absolute;
  top: calc(100% + 15px);
  right: -5px;
  width: 220px;
  background: #0d1017;
  border: 1px solid #2d3348;
  border-radius: 8px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
  overflow: hidden;
  z-index: 50;
  animation: dropdownFadeIn 0.2s ease forwards;
}
@media (min-width: 640px) {
  .profile-dropdown {
    top: calc(100% + 10px);
    right: 0;
  }
}

@keyframes dropdownFadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.dropdown-header {
  padding: 1rem;
  background: #141724;
  border-bottom: 1px solid #2d3348;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.user-greeting {
  font-size: 0.85rem;
  color: #c6cbdb;
  font-weight: bold;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.admin-badge {
  font-size: 0.65rem;
  background: rgba(255, 157, 0, 0.15);
  color: #ff9d00;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  border: 1px solid rgba(255, 157, 0, 0.3);
}

.admin-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid rgba(237, 77, 77, 0.3);
  background: rgba(237, 77, 77, 0.05);
  transition: all 0.2s;
  padding: 0;
}
@media (min-width: 640px) {
  .admin-link {
    width: 40px;
    height: 40px;
  }
}
.admin-link:hover {
  background: rgba(237, 77, 77, 0.15);
  border-color: rgba(237, 77, 77, 0.6);
}

.admin-icon {
  width: 20px;
  height: 20px;
}
@media (min-width: 640px) {
  .admin-icon {
    width: 24px;
    height: 24px;
  }
}

.admin-mobile-link {
  color: #ff9d00 !important;
}

.dropdown-link {
  display: block;
  width: 100%;
  text-align: left;
  padding: 0.8rem 1rem;
  background: transparent;
  border: none;
  color: #97a0b7;
  font-size: 0.9rem;
  text-decoration: none;
  cursor: pointer;
  border-bottom: 1px solid #1a1e2b;
  transition: all 0.2s ease;
}

.dropdown-link:last-child {
  border-bottom: none;
}

.dropdown-link:hover {
  background: rgba(237, 77, 77, 0.08);
  color: #f0f2f7;
  padding-left: 1.25rem;
}

.logout-dropdown-btn {
  color: #ed4d4d;
}

.dropdown-link-icon {
  display: flex !important;
  justify-content: space-between;
  align-items: center;
}
.dropdown-svg-icon {
  width: 16px;
  height: 16px;
  opacity: 0.9;
  filter: brightness(0) invert(1);
}
.logout-dropdown-btn:hover {
  background: rgba(237, 77, 77, 0.15);
}

.logout-button.mobile-only {
  background: transparent;
  border: 1px solid #4d5570;
  color: #97a0b7;
  font-weight: 600;
  font-size: 0.85rem;
  border-radius: 8px; /* Mobile buttons are usually rectangles, not pills */
  padding: 0.5rem 1rem;
  cursor: pointer;
  transition: all 0.2s ease;
  width: 100%;
}
.logout-button.mobile-only:hover {
  border-color: #ff8a8a;
  color: #ff8a8a;
  background: rgba(237, 77, 77, 0.1);
}

.login-button {
  border: 1px solid rgba(237, 77, 77, 0.6);
  background: transparent;
  color: #ed4d4d;
  font-weight: 700;
  font-size: 0.85rem;
  border-radius: 999px;
  padding: 0.5rem 1rem;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease;
  white-space: nowrap;
}

.login-button:hover {
  background: rgba(237, 77, 77, 0.12);
}

.menu-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #c6cbdb;
  padding: 0.3rem;
  cursor: pointer;
  border-radius: 6px;
  -webkit-tap-highlight-color: transparent;
  transition: color 0.2s ease;
}

.menu-toggle:hover {
  color: #ed4d4d;
}

.menu-toggle:focus,
.menu-toggle:focus-visible {
  outline: none;
}

/* ─── Sub-nav compacta ────────────────────────────────────── */
.nav-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  /* centrado horizontal */
  gap: 0.25rem;
  padding: 0 1.5rem;
}

.nav-link {
  color: #97a0b7;
  text-decoration: none;
  font-size: 0.82rem;
  font-weight: 500;
  padding: 0.55rem 0.85rem;
  border-bottom: 2px solid transparent;
  transition: color 0.2s ease, border-color 0.2s ease;
  white-space: nowrap;
}

.nav-link:hover {
  color: #f0f2f7;
  border-bottom-color: rgba(237, 77, 77, 0.35);
}

.nav-link.is-active {
  color: #ed4d4d;
  border-bottom-color: #ed4d4d;
}

.mobile-only {
  display: none;
}

/* ─── Responsive ──────────────────────────────────────────── */
@media (max-width: 1024px) {
  .menu-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .desktop-only {
    display: none;
  }

  /* Nav colapsa en móvil */
  .main-nav {
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    pointer-events: none;
    transition: max-height 0.3s ease, opacity 0.2s ease;
  }

  .main-nav.is-open {
    max-height: 500px;
    opacity: 1;
    pointer-events: auto;
  }

  .nav-inner {
    flex-direction: column;
    align-items: stretch;
    padding: 0.75rem 1rem;
    gap: 0.4rem;
  }

  .nav-link {
    display: block;
    padding: 0.7rem 0.9rem;
    border: 1px solid #2d3348;
    border-bottom: 1px solid #2d3348;
    border-radius: 8px;
    background: #141824;
    text-align: center;
    font-size: 0.9rem;
  }

  .nav-link:hover {
    border-color: rgba(237, 77, 77, 0.4);
    background: #171d2a;
    color: #f0f2f7;
  }

  .nav-link.is-active {
    border-color: rgba(237, 77, 77, 0.65);
    background: rgba(237, 77, 77, 0.12);
    color: #ed4d4d;
  }

  .mobile-only {
    display: block;
  }

  .mobile-login {
    width: 100%;
    text-align: center;
    margin-top: 0.25rem;
    border-radius: 8px;
    padding: 0.7rem;
    font-size: 0.9rem;
  }
}

@media (max-width: 640px) {
  .site-header {
    padding: 0.65rem 0.9rem;
  }

  .brand-ghost {
    width: 1.9rem;
    height: 1.9rem;
  }

  .brand-title {
    font-size: 0.92rem;
  }

  .search-btn {
    display: none;
  }
}
</style>
