<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import BookCard from '../components/BookCard.vue'
import booksApi from '../api/books.js'

const route = useRoute()
const books = ref([])
const loading = ref(false)
const error = ref(null)

const doSearch = async (query) => {
  if (!query) {
    books.value = []
    loading.value = false
    return
  }
  
  loading.value = true
  error.value = null

  try {
    const res = await booksApi.buscar(query)
    books.value = res.data.data ?? []
  } catch (e) {
    console.error(e)
    error.value = 'Ocurrió un error al buscar libros. Inténtalo de nuevo más tarde.'
  } finally {
    loading.value = false
  }
}

// Buscar al cargar la página si ya hay un query
onMounted(() => {
  doSearch(route.query.q)
})

// Reactividad cuando la ruta cambia (si el usuario hace una nueva búsqueda desde el header)
watch(() => route.query.q, (newQ) => {
  doSearch(newQ)
})
</script>

<template>
  <div class="search-page page-container">
    <header class="page-header">
      <h1>Resultados de búsqueda</h1>
      <p v-if="route.query.q" class="search-term">mostrando resultados para "<strong>{{ route.query.q }}</strong>"</p>
      <p v-else class="search-term">Escribe algo en el buscador para empezar.</p>
    </header>

    <div class="results-container">
      <!-- Loading state -->
      <div v-if="loading" class="grid">
        <div v-for="n in 8" :key="n" class="skeleton" />
      </div>

      <!-- Error message -->
      <div v-else-if="error" class="error-msg">
        <span>⚠️</span> {{ error }}
      </div>

      <!-- No results -->
      <div v-else-if="route.query.q && books.length === 0" class="no-results">
        <div class="ghost-icon">👻</div>
        <h2>No hemos encontrado nada</h2>
        <p>Prueba con otros términos de búsqueda, otro autor o libro.</p>
      </div>

      <!-- Results Grid -->
      <div v-else-if="books.length > 0" class="grid">
        <BookCard
          v-for="book in books"
          :key="book.id"
          :id="book.id"
          :title="book.titulo_es || book.titulo"
          :author="book.autor"
          :portada="book.portada"
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

.search-term strong {
  color: #ed4d4d;
}

/* Grid similar al FeaturedBooks original (pero adaptado para la carátula) */
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1.5rem;
}

/* Forzar que en el grid de búsqueda la card siga usando la proporción adecuada */
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

/* ---- Skeleton loader ---- */
.skeleton {
  width: 100%;
  aspect-ratio: 2/3;
  border-radius: 12px;
  background: linear-gradient(90deg, #1a1d2e 25%, #22263a 50%, #1a1d2e 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ---- Error ---- */
.error-msg {
  padding: 1rem 1.2rem;
  border-radius: 10px;
  background: rgba(237, 77, 77, 0.1);
  border: 1px solid rgba(237, 77, 77, 0.3);
  color: #f09090;
  font-size: 1.05rem;
  display: inline-block;
}

/* ---- Empty state ---- */
.no-results {
  text-align: center;
  padding: 4rem 1rem;
  background: rgba(20, 24, 36, 0.4);
  border-radius: 16px;
  border: 1px dashed #2d3348;
}

.ghost-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
  opacity: 0.8;
  filter: drop-shadow(0 0 10px rgba(255,255,255,0.1));
}

.no-results h2 {
  font-size: 1.5rem;
  color: #e3e5eb;
  margin: 0 0 0.5rem;
}

.no-results p {
  color: #97a0b7;
  font-size: 1rem;
  margin: 0;
}
</style>
