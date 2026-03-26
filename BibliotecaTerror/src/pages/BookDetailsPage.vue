<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import booksApi from '../api/books.js'
import { useAuthStore } from '../stores/auth'
import favoriteIcon from '../assets/favorite.svg'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const book = ref(null)
const loading = ref(true)
const error = ref(null)

const isFavorito = ref(false)
const togglingFav = ref(false)

const isRenting = ref(false)
const rentMsg = ref({ text: '', type: '' })

const handlePrestar = async () => {
  if (!authStore.isAuthenticated || !authStore.user?.id || !book.value) return
  isRenting.value = true
  rentMsg.value = { text: '', type: '' }
  
  try {
    await booksApi.prestarLibro(authStore.user.id, book.value.id, authStore.user.username)
    rentMsg.value = { text: '¡Libro prestado con éxito! Tienes este volumen reservado en tu cuenta.', type: 'success' }
    // Recargar para refrescar stock si lo tuvieramos en vista u otra info
    // (Omitimos reload entero para no hacer blinking visual de imagen, solo editamos stock local prop si existiera, pero de momento reload invisible de datos:)
    const res = await booksApi.getById(book.value.id)
    book.value = res.data.data
  } catch(err) {
    rentMsg.value = { 
      text: err.response?.data?.error || 'Error al procesar el préstamo en el servidor.',
      type: 'error'
    }
  } finally {
    isRenting.value = false
  }
}

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
                 <span class="badge badge-rating" v-if="book.rating && Number(book.rating) > 0">★ {{ Number(book.rating).toFixed(1) }} / 5</span>
              </div>

              <div class="book-separator"></div>

              <div class="book-synopsis">
                <h3>Sinopsis</h3>
                <div class="description-text" v-html="book.descripcion_es || book.descripcion || 'No hay descripción disponible para este volumen.'"></div>
              </div>
              
              <div v-if="rentMsg.text" :class="['rent-msg-box', rentMsg.type]">
                {{ rentMsg.text }}
              </div>

              <div class="book-actions">
                <button 
                  v-if="authStore.isAuthenticated"
                  class="action-btn primary-btn" 
                  @click="handlePrestar"
                  :disabled="isRenting || (book.stock !== undefined && book.stock <= 0)"
                >
                  {{ isRenting ? 'Procesando...' : ((book.stock !== undefined && book.stock <= 0) ? 'Agotado Temporalmente' : 'Solicitar préstamo') }}
                </button>

                <button v-if="authStore.isAuthenticated" 
                        class="action-btn star-btn" 
                        :class="{ 'is-active': isFavorito }"
                        @click="toggleFav"
                        :disabled="togglingFav"
                        aria-label="Añadir a lista/favoritos">
                  <img :src="favoriteIcon" alt="Favorito" class="fav-svg-icon" :class="{'active-fav': isFavorito}" />
                  <span v-if="false">★</span> <!-- Legacy spacing -->
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
  background: transparent;
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
  background: linear-gradient(to bottom, transparent, rgba(10, 11, 16, 0.8) 90%);
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

.badge-rating {
  background: rgba(255, 193, 7, 0.15);
  border-color: rgba(255, 193, 7, 0.4);
  color: #ffc107;
  font-size: 0.9rem;
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

.primary-btn:hover:not(:disabled) {
  background: #ff5e5e;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(237, 77, 77, 0.4);
}

.primary-btn:disabled {
  background: #33394b;
  color: #7a839e;
  box-shadow: none;
  cursor: not-allowed;
}

.rent-msg-box {
  margin-top: 2rem;
  padding: 1rem 1.2rem;
  border-radius: 8px;
  font-weight: 500;
  font-size: 0.95rem;
  animation: slideFadeIn 0.3s ease;
}

.rent-msg-box.success {
  background: rgba(34, 197, 94, 0.15);
  color: #4ade80;
  border: 1px solid rgba(34, 197, 94, 0.3);
}

.rent-msg-box.error {
  background: rgba(237, 77, 77, 0.15);
  color: #ff8a8a;
  border: 1px solid rgba(237, 77, 77, 0.3);
}

@keyframes slideFadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.star-btn {
  background: rgba(45, 51, 72, 0.3);
  color: #e3e5eb;
  border: 1px solid rgba(151, 160, 183, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 1rem;
  gap: 0.5rem;
}

.fav-svg-icon {
  width: 18px;
  height: 18px;
  filter: brightness(0) invert(1);
  transition: all 0.2s ease;
}

.fav-svg-icon.active-fav {
  filter: invert(24%) sepia(85%) saturate(7402%) hue-rotate(354deg) brightness(97%) contrast(115%);
  /* Esto equivale al color rgba rojo #ed4d4d aproximado */
}

.star-btn svg {
  transition: all 0.2s ease;
}

.star-btn:hover {
  background: rgba(237, 77, 77, 0.1);
  border-color: rgba(237, 77, 77, 0.4);
  color: #ed4d4d;
  transform: translateY(-2px);
}
.star-btn:hover .fav-svg-icon {
  filter: invert(34%) sepia(98%) saturate(1754%) hue-rotate(338deg) brightness(97%) contrast(92%);
}

.star-btn.is-active {
  background: rgba(237, 77, 77, 0.15);
  border-color: rgba(237, 77, 77, 0.5);
  color: #ed4d4d;
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
