<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import BookCard from '../components/BookCard.vue'
import booksApi from '../api/books.js'

const books = ref([])
const loading = ref(true)
const error = ref(null)
const authStore = useAuthStore()

const fetchRecomendaciones = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await booksApi.getRecomendaciones(32, authStore.user?.id || null)
    books.value = res.data.data || []
  } catch (err) {
    console.error('Error cargando recomendaciones:', err)
    error.value = 'No hemos podido conectar con la biblioteca. Inténtalo más tarde.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchRecomendaciones()
})
</script>

<template>
  <div class="recomendaciones-page page-container">
    <header class="page-header">
      <h1>Los Más Aclamados</h1>
      <p class="search-term">Adéntrate en las lecturas con más de 4 estrellas elegidas por nuestra comunidad oscura.</p>
    </header>

    <div class="results-container">
      <div v-if="loading" class="immersive-loader">
        <div class="spinner"></div>
      </div>

      <div v-else-if="error" class="error-msg">
        <span>⚠️</span> {{ error }}
      </div>

      <div v-else-if="books.length === 0" class="no-results">
        <div class="ghost-icon">👻</div>
        <h2>Silencio sepulcral...</h2>
        <p>Aún no hay libros valorados suficientemente bien en esta categoría.</p>
      </div>

      <div v-else class="grid">
        <BookCard
          v-for="book in books"
          :key="book.id"
          :id="Number(book.id)"
          :title="book.titulo_es || book.titulo"
          :author="book.autor"
          :portada="book.portada"
          :rating="book.rating"
          :isFavorito="Number(book.is_favorito) === 1"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem 1.5rem 5rem;
}

.page-header {
  margin-bottom: 2.5rem;
  border-bottom: 1px solid rgba(45, 51, 72, 0.4);
  padding-bottom: 1.5rem;
}

h1 {
  font-size: 2.2rem;
  margin: 0 0 0.5rem;
  color: #f2f2f3;
}

.search-term {
  font-size: 1.1rem;
  color: #97a0b7;
  margin: 0;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1.5rem;
}

.grid :deep(.book-card) {
  width: 100%;
  height: 100%;
}
.grid :deep(.cover-wrap) {
  aspect-ratio: 2 / 3;
}
.grid :deep(h3) {
  font-size: 1.1rem;
}

/* Spinner */
.immersive-loader { height: 30vh; display: flex; justify-content: center; align-items: center; }
.spinner { width: 40px; height: 40px; border: 3px solid rgba(237, 77, 77, 0.2); border-top-color: #ed4d4d; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

.error-msg {
  padding: 1rem 1.2rem;
  border-radius: 10px;
  background: rgba(237, 77, 77, 0.1);
  border: 1px solid rgba(237, 77, 77, 0.3);
  color: #f09090;
  font-size: 1.05rem;
  display: inline-block;
}

.no-results {
  text-align: center;
  padding: 4rem 1rem;
  background: rgba(20, 24, 36, 0.4);
  border-radius: 16px;
  border: 1px dashed #2d3348;
}

.ghost-icon { font-size: 4rem; margin-bottom: 1rem; opacity: 0.8; filter: drop-shadow(0 0 10px rgba(255,255,255,0.1)); }
.no-results h2 { font-size: 1.5rem; color: #e3e5eb; margin: 0 0 0.5rem; }
.no-results p { color: #97a0b7; font-size: 1rem; margin: 0; }
</style>
