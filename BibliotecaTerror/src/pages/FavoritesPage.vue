<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import booksApi from '../api/books.js'
import BookCard from '../components/BookCard.vue'

const authStore = useAuthStore()
const router = useRouter()
const books = ref([])
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  if (!authStore.isAuthenticated || !authStore.user?.id) {
    router.push('/')
    return
  }
  
  try {
    const res = await booksApi.getMisFavoritos(authStore.user.id)
    books.value = res.data.data || []
  } catch (err) {
    console.error('Error cargando favoritos', err)
    error.value = 'No hemos podido cargar tu lista de favoritos en este momento.'
  } finally {
    loading.value = false
  }
})

const exploreBooks = () => {
  router.push('/buscar')
}
</script>

<template>
  <div class="favorites-page page-container">
    <header class="page-header">
      <h1>Mis Favoritos</h1>
      <p class="subtitle">La colección personal de los libros que te han cautivado.</p>
    </header>

    <div class="results-container">
      
      <!-- Cargando -->
      <div v-if="loading" class="grid">
        <div v-for="n in 5" :key="n" class="skeleton" />
      </div>

      <!-- Errores -->
      <div v-else-if="error" class="error-msg">
        <span>⚠️</span> {{ error }}
      </div>

      <!-- Estado vacío (Sin favoritos) -->
      <div v-else-if="books.length === 0" class="empty-state">
        <div class="star-icon">⭐️</div>
        <h2>Aún no tienes favoritos</h2>
        <p>Explora la biblioteca y marca con una estrella los libros que quieras guardar aquí para futuras lecturas.</p>
        <button class="explore-btn" @click="exploreBooks">Explorar la biblioteca</button>
      </div>

      <!-- Grid de Libros favoritos -->
      <div v-else class="grid">
        <BookCard
          v-for="book in books"
          :key="book.id"
          :id="Number(book.id)"
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

.subtitle {
  font-size: 1.1rem;
  color: #97a0b7;
  margin: 0;
}

/* Grid replicado */
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

/* Skeleton loader */
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

/* Errores */
.error-msg {
  padding: 1rem 1.2rem;
  border-radius: 10px;
  background: rgba(237, 77, 77, 0.1);
  border: 1px solid rgba(237, 77, 77, 0.3);
  color: #f09090;
  display: inline-block;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 5rem 1rem;
  background: rgba(20, 24, 36, 0.4);
  border-radius: 16px;
  border: 1px dashed rgba(255, 193, 7, 0.3);
}

.star-icon {
  font-size: 4.5rem;
  margin-bottom: 1rem;
  opacity: 0.9;
  filter: drop-shadow(0 0 20px rgba(255, 193, 7, 0.2));
}

.empty-state h2 {
  font-size: 1.8rem;
  color: #e3e5eb;
  margin: 0 0 0.5rem;
}

.empty-state p {
  color: #97a0b7;
  font-size: 1.05rem;
  max-width: 500px;
  margin: 0 auto 2rem;
  line-height: 1.5;
}

.explore-btn {
  background: #ed4d4d;
  color: white;
  border: none;
  padding: 0.8rem 1.8rem;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 15px rgba(237, 77, 77, 0.3);
}

.explore-btn:hover {
  background: #ff5e5e;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(237, 77, 77, 0.4);
}
</style>
