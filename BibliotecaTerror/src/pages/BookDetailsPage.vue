<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import booksApi from '../api/books.js'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const book = ref(null)
const loading = ref(true)
const error = ref(null)

const isFavorito = ref(false)
const togglingFav = ref(false)

const checkFav = async (id) => {
  if (!authStore.isAuthenticated || !authStore.user?.id) return
  try {
    const res = await booksApi.checkFavorito(authStore.user.id, id)
    isFavorito.value = res.data.is_favorito
  } catch(e) {
    console.error('Error al comprobar favorito', e)
  }
}

const toggleFav = async () => {
  if (!authStore.isAuthenticated || !authStore.user?.id || !book.value) return
  togglingFav.value = true
  try {
    const res = await booksApi.toggleFavorito(authStore.user.id, book.value.id)
    isFavorito.value = res.data.is_favorito
  } catch(e) {
    console.error('Error cambiando favorito', e)
  } finally {
    togglingFav.value = false
  }
}

const loadBook = async (id) => {
  loading.value = true
  error.value = null
  try {
    const res = await booksApi.getById(id)
    book.value = res.data.data
    await checkFav(id)
  } catch (e) {
    if (e.response?.status === 404) {
      error.value = 'El libro que buscas no existe o ha sido retirado de la biblioteca.'
    } else {
      error.value = 'Hubo un error cargando los detalles del libro.'
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadBook(route.params.id)
})

watch(() => route.params.id, (newId) => {
  if (newId) loadBook(newId)
})

const goBack = () => {
  router.back()
}
</script>

<template>
  <div class="book-details-page">
    
    <!-- Loader -->
    <div v-if="loading" class="immersive-loader">
      <div class="spinner"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-container">
      <div class="ghost-icon">👻</div>
      <h2>Vaya...</h2>
      <p>{{ error }}</p>
      <button class="back-btn" @click="goBack">Volver atrás</button>
    </div>

    <!-- Contenido del Libro -->
    <template v-else-if="book">
      <!-- Fondo inmersivo desenfocado -->
      <div class="hero-bg" :style="{ backgroundImage: `url(${book.portada || ''})` }">
        <div class="hero-overlay"></div>
      </div>

      <div class="content-container">
         <button class="back-link" @click="goBack">
           ← Volver
         </button>

         <div class="book-hero">
           <!-- Columna Detalles -->
           <div class="book-info-col">
              <h1 class="book-title">{{ book.titulo_es || book.titulo }}</h1>
              <h2 v-if="book.titulo_es && book.titulo !== book.titulo_es" class="book-original-title">
                {{ book.titulo }}
              </h2>
              <div class="book-author">Por <span>{{ book.autor || 'Autor desconocido' }}</span></div>

              <!-- Extra info badges space -->
              <div class="book-badges">
                 <span class="badge" v-if="book.google_id">Google Books</span>
              </div>

              <div class="book-separator"></div>

              <div class="book-synopsis">
                <h3>Sinopsis</h3>
                <div class="description-text" v-html="book.descripcion_es || book.descripcion || 'No hay descripción disponible para este volumen.'"></div>
              </div>
              
              <div class="book-actions">
                <button class="action-btn primary-btn">Alquilar Libro</button>
                <button v-if="authStore.isAuthenticated" 
                        class="action-btn star-btn" 
                        :class="{ 'is-active': isFavorito }"
                        @click="toggleFav"
                        :disabled="togglingFav"
                        aria-label="Añadir a lista/favoritos">
                  <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                  </svg>
                </button>
              </div>
           </div>

           <!-- Columna Portada -->
           <div class="book-cover-col">
              <div class="cover-wrapper">
                <img v-if="book.portada" :src="book.portada" :alt="book.titulo_es || book.titulo" />
                <div v-else class="cover-placeholder">📖</div>
              </div>
           </div>
         </div>
      </div>
    </template>

  </div>
</template>

<style scoped>
.book-details-page {
  position: relative;
  min-height: calc(100vh - 70px);
  background: #0a0d14;
}

/* Background Inmersivo */
.hero-bg {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 60vh;
  min-height: 400px;
  background-size: cover;
  background-position: center;
  filter: blur(40px) saturate(1.5);
  opacity: 0.15;
  z-index: 0;
  pointer-events: none;
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, transparent, #0a0d14 90%);
}

.content-container {
  position: relative;
  z-index: 10;
  max-width: 1200px;
  margin: 0 auto;
  padding: 3rem 1.5rem 6rem;
}

.back-link {
  background: transparent;
  border: none;
  color: #97a0b7;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
  margin-bottom: 2rem;
  display: inline-flex;
  align-items: center;
  transition: color 0.2s ease;
}

.back-link:hover {
  color: #ed4d4d;
}

/* Layout Hero del Libro */
.book-hero {
  display: flex;
  flex-direction: column;
  gap: 3rem;
  align-items: center;
}

@media (min-width: 800px) {
  .book-hero {
    flex-direction: row;
    align-items: flex-start;
    gap: 4rem;
  }
}

/* Columna Portada */
.book-cover-col {
  flex-shrink: 0;
  width: 100%;
  max-width: 320px;
}

.cover-wrapper {
  width: 100%;
  aspect-ratio: 2/3;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(0,0,0,0.8), 0 0 0 1px rgba(255,255,255,0.1);
  background: #1e2030;
}

.cover-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cover-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 5rem;
}

/* Columna Información */
.book-info-col {
  flex: 1;
}

.book-title {
  font-size: 2.5rem;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 0.5rem 0;
  line-height: 1.1;
  letter-spacing: -0.02em;
}

.book-original-title {
  font-size: 1.2rem;
  font-weight: 400;
  color: #8e95a8;
  margin: 0 0 1rem 0;
  font-style: italic;
}

.book-author {
  font-size: 1.15rem;
  color: #b0b6c9;
  margin-bottom: 1.5rem;
}

.book-author span {
  color: #f2f2f3;
  font-weight: 600;
}

.book-badges {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
}

.badge {
  background: rgba(45, 51, 72, 0.5);
  border: 1px solid rgba(151, 160, 183, 0.2);
  color: #c6cbdb;
  padding: 0.35rem 0.8rem;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 500;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.book-separator {
  height: 1px;
  background: linear-gradient(to right, rgba(237, 77, 77, 0.4), transparent);
  margin-bottom: 2.5rem;
}

.book-synopsis h3 {
  font-size: 1.25rem;
  color: #f2f2f3;
  margin: 0 0 1rem 0;
}

.description-text {
  font-size: 1.05rem;
  line-height: 1.7;
  color: #a3abbd;
  max-width: 800px;
}

.description-text :deep(p) {
  margin-bottom: 1rem;
}

.description-text :deep(br) {
  display: block;
  content: "";
  margin-top: 0.5rem;
}

/* Botones de acción */
.book-actions {
  display: flex;
  gap: 1rem;
  margin-top: 3rem;
}

.action-btn {
  padding: 0.8rem 1.5rem;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.primary-btn {
  background: #ed4d4d;
  color: white;
  border: none;
  box-shadow: 0 4px 15px rgba(237, 77, 77, 0.3);
}

.primary-btn:hover {
  background: #ff5e5e;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(237, 77, 77, 0.4);
}

.star-btn {
  background: rgba(45, 51, 72, 0.3);
  color: #e3e5eb;
  border: 1px solid rgba(151, 160, 183, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 1rem;
}

.star-btn svg {
  transition: all 0.2s ease;
}

.star-btn:hover {
  background: rgba(255, 193, 7, 0.1);
  border-color: rgba(255, 193, 7, 0.4);
  color: #ffc107;
  transform: translateY(-2px);
}

.star-btn.is-active {
  background: rgba(255, 193, 7, 0.15);
  border-color: rgba(255, 193, 7, 0.5);
  color: #ffc107;
}

.star-btn:hover svg, .star-btn.is-active svg {
  fill: rgba(255, 193, 7, 0.8);
}

/* Estados */
.immersive-loader {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 60vh;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(237, 77, 77, 0.2);
  border-top-color: #ed4d4d;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin { 100% { transform: rotate(360deg); } }

.error-container {
  text-align: center;
  padding: 6rem 1rem;
}

.ghost-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
  opacity: 0.8;
}

.error-container h2 {
  color: #e3e5eb;
  margin: 0 0 0.5rem;
}

.error-container p {
  color: #97a0b7;
  margin-bottom: 2rem;
}

.back-btn {
  background: #1e2030;
  color: #e3e5eb;
  border: 1px solid #2d3348;
  padding: 0.6rem 1.2rem;
  border-radius: 6px;
  cursor: pointer;
}

.back-btn:hover {
  background: #2d3348;
}
</style>
