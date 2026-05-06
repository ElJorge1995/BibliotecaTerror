<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import BookCard from './BookCard.vue'
import booksApi from '../api/books.js'
import { useAuthStore } from '../stores/auth'

// Import Swiper Vue Components
import { Swiper, SwiperSlide } from 'swiper/vue'

// Import Swiper styles
import 'swiper/css'
import 'swiper/css/pagination'
import 'swiper/css/effect-coverflow'

// Import required modules
import { Pagination, EffectCoverflow, Autoplay } from 'swiper/modules'

const modules = [Pagination, EffectCoverflow, Autoplay]

const books   = ref([])
const loading = ref(true)
const error   = ref(null)

const authStore = useAuthStore()

// Coverflow más suave en móvil: los laterales no se hunden tanto y la
// transición no descoloca los slides cuando hay poco espacio.
const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024)
const onResize = () => { viewportWidth.value = window.innerWidth }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

const coverflowEffect = computed(() => {
  if (viewportWidth.value <= 480) {
    return { rotate: 0, stretch: 0, depth: 40, modifier: 1, slideShadows: false }
  }
  if (viewportWidth.value <= 768) {
    return { rotate: 0, stretch: 0, depth: 70, modifier: 1.6, slideShadows: true }
  }
  return { rotate: 0, stretch: 0, depth: 100, modifier: 2.5, slideShadows: true }
})

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
      <RouterLink to="/buscar">Ver todo el catálogo</RouterLink>
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
        :coverflowEffect="coverflowEffect"
        :autoplay="{
          delay: 3000,
          disableOnInteraction: false,
        }"
        :pagination="{ clickable: true }"
        :modules="modules"
        class="books-swiper"
      >
        <swiper-slide v-for="(book, idx) in books" :key="book.id" class="b-slide">
          <BookCard
            :id="Number(book.id)"
            :title="book.titulo_es || book.titulo"
            :author="book.autor"
            :portada="book.portada"
            :rating="book.rating"
            :isFavorito="Number(book.is_favorito) === 1"
            :priority="idx < 3"
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

@media (max-width: 768px) {
  .featured-books {
    margin: 1rem 0 1rem;
  }

  .section-head {
    margin-bottom: 1rem;
  }

  h2 {
    font-size: 1.4rem;
  }

  a {
    font-size: 0.9rem;
  }

  .b-slide,
  .skeleton {
    width: 200px;
    height: 380px;
  }

  .b-slide :deep(h3) {
    font-size: 1rem;
  }

  .b-slide :deep(.info) {
    padding: 0.7rem 0.8rem;
  }

  .carousel-container {
    padding: 0;
  }

  .books-swiper {
    padding-top: 6px;
    padding-bottom: 28px;
  }
}

@media (max-width: 480px) {
  .featured-books {
    margin: 0.5rem 0 0.5rem;
  }

  .section-head {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.4rem;
    margin-bottom: 0.6rem;
  }

  h2 {
    font-size: 1.25rem;
  }

  .b-slide,
  .skeleton {
    width: 180px;
    height: 320px;
  }

  .b-slide :deep(.info) {
    padding: 0.6rem 0.7rem;
  }

  .b-slide :deep(p) {
    font-size: 0.78rem;
  }

  .swiper-skeleton-cont {
    gap: 1rem;
  }

  .books-swiper {
    padding-bottom: 24px;
  }
}
</style>
