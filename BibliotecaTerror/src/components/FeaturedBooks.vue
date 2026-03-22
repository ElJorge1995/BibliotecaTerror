<script setup>
import { ref, onMounted } from 'vue'
import BookCard from './BookCard.vue'
import booksApi from '../api/books.js'
import { useAuthStore } from '../stores/auth'

// Import Swiper Vue Components
import { Swiper, SwiperSlide } from 'swiper/vue'

// Import Swiper styles
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import 'swiper/css/effect-coverflow'

// Import required modules
import { Navigation, Pagination, EffectCoverflow, Autoplay } from 'swiper/modules'

const modules = [Navigation, Pagination, EffectCoverflow, Autoplay]

const books   = ref([])
const loading = ref(true)
const error   = ref(null)

const authStore = useAuthStore()

onMounted(async () => {
  try {
    const res = await booksApi.getRecientes(12, authStore.user?.id) // Pedimos 12 para que el carrusel tenga más elementos
    books.value = res.data.data ?? []
  } catch (e) {
    error.value = 'No se pudieron cargar los libros. Comprueba que el servidor de libros está activo.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="featured-books">
    <div class="section-head">
      <h2>Últimas incorporaciones</h2>
      <a href="#">Ver todo el catálogo</a>
    </div>

    <!-- Estado de carga -->
    <div v-if="loading" class="swiper-skeleton-cont">
      <div v-for="n in 4" :key="n" class="skeleton" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-msg">
      <span>⚠️</span> {{ error }}
    </div>

    <!-- Libros reales (Carrusel) -->
    <div v-else class="carousel-container">
      <swiper
        :effect="'coverflow'"
        :grabCursor="true"
        :centeredSlides="true"
        :slidesPerView="'auto'"
        :loop="true"
        :coverflowEffect="{
          rotate: 0,
          stretch: 0,
          depth: 100,
          modifier: 2.5,
          slideShadows: true,
        }"
        :autoplay="{
          delay: 3000,
          disableOnInteraction: false,
        }"
        :pagination="{ clickable: true }"
        :modules="modules"
        class="books-swiper"
      >
        <swiper-slide v-for="book in books" :key="book.id" class="b-slide">
          <BookCard
            :id="Number(book.id)"
            :title="book.titulo_es || book.titulo"
            :author="book.autor"
            :portada="book.portada"
            :rating="book.rating"
            :isFavorito="Number(book.is_favorito) === 1"
            class="large-card"
          />
        </swiper-slide>
      </swiper>
    </div>
  </section>
</template>

<style scoped>
.featured-books {
  margin: 2rem 0 3rem;
  overflow: hidden; /* Evita scroll horizontal por swiper effect */
}

.section-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 2rem;
}

h2 {
  margin: 0;
  color: #f2f2f3;
  font-size: 1.8rem;
}

a {
  color: #ed4d4d;
  text-decoration: none;
  font-weight: 600;
  font-size: 1rem;
}

/* ---- Componente Swiper ---- */
.carousel-container {
  width: 100%;
  padding: 1rem 0 3rem; 
}

.books-swiper {
  width: 100%;
  padding-top: 10px;
  padding-bottom: 50px; /* Espacio para la paginación */
}

.b-slide {
  background-position: center;
  background-size: cover;
  /* Proporción más estirada para libros */
  width: 260px; 
  height: 500px; 
}

/* Ajustes adicionales a BookCard desde el padre para forzar tamaño */
.b-slide :deep(.book-card) {
  width: 100%;
  height: 100%;
}

.b-slide :deep(h3) {
  font-size: 1.15rem;
}

/* Colores de la Paginación de Swiper */
:deep(.swiper-pagination-bullet) {
  background: var(--text-color, #bdc2d0);
  opacity: 0.5;
}
:deep(.swiper-pagination-bullet-active) {
  background: #ed4d4d;
  opacity: 1;
}

/* ---- Skeleton loader ---- */
.swiper-skeleton-cont {
  display: flex;
  gap: 2rem;
  justify-content: center;
  overflow: hidden;
  padding: 1rem 0;
}

.skeleton {
  width: 260px;
  height: 500px;
  flex-shrink: 0;
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
  font-size: 0.9rem;
}
</style>
