<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import booksApi from '../api/books.js'

const authStore = useAuthStore()
const router = useRouter()
const rentals = ref([])
const loading = ref(true)
const error = ref(null)

const fetchRentals = async () => {
  if (!authStore.isAuthenticated || !authStore.user?.id) return
  
  loading.value = true
  error.value = null
  
  try {
    const res = await booksApi.getMisPrestamos(authStore.user.id)
    rentals.value = res.data.data || []
  } catch(err) {
    console.error(err)
    error.value = 'No hemos podido cargar tus préstamos'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (!authStore.isAuthenticated) {
    router.push('/login')
  } else {
    fetchRentals()
  }
})

const goToBook = (id) => {
  router.push(`/libros/${id}`)
}

// Función para formatear fechas de MySQL (YYYY-MM-DD HH:MM:SS) a local
const formatDate = (dateStr) => {
  if (!dateStr) return 'A la espera de recogida';
  const options = { year: 'numeric', month: 'short', day: 'numeric' };
  return new Date(dateStr).toLocaleDateString('es-ES', options);
}

const rateBook = async (rent, star) => {
  if (rent.tu_rating) return // Evita múltiples votos si ya está votado
  try {
    rent.isRating = true
    await booksApi.valorarPrestamo(rent.prestamo_id, star)
    rent.tu_rating = star
  } catch(e) {
    console.error('Error al valorar', e)
    alert('No se pudo enviar la valoración')
  } finally {
    rent.isRating = false
  }
}
</script>

<template>
  <div class="rentals-page">
    <header class="rentals-header">
      <div class="header-content">
        <h1>Mis Préstamos</h1>
        <p>Controla los libros que tienes actualmente reservados y sus fechas límite.</p>
      </div>
    </header>

    <div class="rentals-content">
      <!-- Loader animado -->
      <div v-if="loading" class="immersive-loader">
        <div class="spinner"></div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="error-container">
        <h2>Un problema en los archivos...</h2>
        <p>{{ error }}</p>
        <button class="retry-btn" @click="fetchRentals">Reintentar</button>
      </div>

      <!-- Empty State -->
      <div v-else-if="rentals.length === 0" class="empty-state">
        <div class="empty-icon">📅</div>
        <h2>Aún no has pedido prestado ningún libro</h2>
        <p>Explora nuestro catálogo y reserva tu primer volumen hoy mismo.</p>
        <button class="explore-btn" @click="router.push('/')">Explorar Biblioteca</button>
      </div>

      <!-- Lista de Alquileres -->
      <div v-else class="rentals-grid">
        <div 
          v-for="rent in rentals" 
          :key="rent.prestamo_id" 
          class="rental-card"
        >
          <div class="rental-cover-block" @click="goToBook(rent.libro_id)">
            <img v-if="rent.portada" :src="rent.portada" :alt="rent.titulo" />
            <div v-else class="placeholder-cover">📖</div>
          </div>
          
          <div class="rental-info-block">
            <h3 @click="goToBook(rent.libro_id)">{{ rent.titulo }}</h3>
            <p class="author">{{ rent.autor }}</p>
            
            <div class="rental-meta">
               <div class="meta-row">
                 <span class="meta-label">Prestado el:</span>
                 <span class="meta-value">{{ formatDate(rent.fecha_prestamo) }}</span>
               </div>
               <div class="meta-row highlight-row">
                 <span class="meta-label">Fecha Límite:</span>
                 <span class="meta-value alert">{{ formatDate(rent.fecha_devolucion) }}</span>
               </div>
               
               <div :class="['status-badge', rent.estado]">
                 {{ rent.estado === 'pendiente' ? 'RECOGER' : rent.estado.toUpperCase() }}
               </div>
               
               <!-- Bloque de 5 Estrellas -->
               <div class="rating-stars" :class="{ 'already-rated': rent.tu_rating, 'is-loading': rent.isRating }">
                 <span 
                   v-for="s in 5" :key="s" 
                   class="star" 
                   :class="{'active': s <= (rent.hoverRating || rent.tu_rating || 0)}"
                   @mouseover="!rent.tu_rating && (rent.hoverRating = s)"
                   @mouseleave="!rent.tu_rating && (rent.hoverRating = 0)"
                   @click="rateBook(rent, s)"
                 >
                   ★
                 </span>
               </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.rentals-page {
  min-height: calc(100vh - 70px);
  background: transparent;
  padding-bottom: 4rem;
}

.rentals-header {
  background: linear-gradient(180deg, rgba(30, 32, 48, 0.4) 0%, transparent 100%);
  backdrop-filter: blur(5px);
  padding: 4rem 1.5rem 2rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.header-content {
  max-width: 1200px;
  margin: 0 auto;
}

.header-content h1 {
  font-size: 2.5rem;
  color: #fff;
  margin: 0 0 0.5rem 0;
}

.header-content p {
  color: #97a0b7;
  font-size: 1.1rem;
  margin: 0;
}

.rentals-content {
  max-width: 1200px;
  margin: 2rem auto;
  padding: 0 1.5rem;
}

/* Grilla de libros alquilados */
.rentals-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.5rem;
}

.rental-card {
  background: rgba(17, 20, 30, 0.4);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  display: flex;
  gap: 1rem;
  padding: 1rem;
  transition: transform 0.2s, box-shadow 0.2s;
}

.rental-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.5);
  border-color: #2d3348;
}

.rental-cover-block {
  width: 90px;
  height: 135px;
  border-radius: 6px;
  overflow: hidden;
  background: #1e2030;
  cursor: pointer;
  flex-shrink: 0;
}

.rental-cover-block img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.placeholder-cover {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}

.rental-info-block {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.rental-info-block h3 {
  margin: 0 0 0.2rem 0;
  font-size: 1.1rem;
  color: #fff;
  cursor: pointer;
}
.rental-info-block h3:hover { color: #ed4d4d; }

.rental-info-block .author {
  font-size: 0.85rem;
  color: #97a0b7;
  margin: 0 0 1rem 0;
}

.rental-meta {
  margin-top: auto;
  background: #0a0c12;
  padding: 0.8rem;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
}

.meta-label { color: #7a839e; }
.meta-value { color: #e3e5eb; font-weight: 500; }
.meta-value.alert { color: #ed4d4d; font-weight: bold; }

.status-badge {
  display: inline-block;
  align-self: flex-start;
  padding: 0.2rem 0.6rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 700;
  margin-top: 0.4rem;
}
.status-badge.activo { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
.status-badge.pendiente {
  background: rgba(255, 193, 7, 0.15);
  color: #ffc107;
  border-color: rgba(255, 193, 7, 0.3);
}
.status-badge.devuelto { background: rgba(151, 160, 183, 0.2); color: #97a0b7; }


/* Estados (Loader, Empty, Error) */
.immersive-loader { height: 40vh; display: flex; justify-content: center; align-items: center; }
.spinner { width: 40px; height: 40px; border: 3px solid rgba(237, 77, 77, 0.2); border-top-color: #ed4d4d; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

.empty-state, .error-container {
  text-align: center;
  padding: 5rem 1rem;
  background: #11141e;
  border-radius: 12px;
  border: 1px dashed #1f2335;
}

.empty-icon { font-size: 4rem; opacity: 0.7; margin-bottom: 1rem; }
.empty-state h2, .error-container h2 { color: #fff; margin: 0 0 0.5rem; }
.empty-state p, .error-container p { color: #97a0b7; margin-bottom: 2rem; }

.explore-btn, .retry-btn {
  background: #b91c1c;
  color: white;
  border: none;
  padding: 0.8rem 1.5rem;
  border-radius: 8px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.2s;
}
.explore-btn:hover, .retry-btn:hover { background: #dc2626; transform: translateY(-2px); }

/* Rating Stars */
.rating-stars {
  margin-top: 0.8rem;
  display: flex;
  gap: 0.3rem;
  font-size: 1.5rem;
  cursor: pointer;
  justify-content: center;
  user-select: none;
}
.rating-stars .star {
  color: #2d3348;
  transition: color 0.2s, transform 0.2s;
}
.rating-stars .star.active { color: #ffc107; text-shadow: 0 0 10px rgba(255,193,7,0.4); transform: scale(1.1); }
.rating-stars:not(.already-rated):not(.is-loading) .star:hover { color: #ffc107; transform: scale(1.2); }
.rating-stars.already-rated { cursor: default; }
.rating-stars.is-loading { opacity: 0.5; pointer-events: none; }
</style>
