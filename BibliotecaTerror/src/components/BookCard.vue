<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
  id: {
    type: Number,
    required: true
  },
  title: {
    type: String,
    required: true
  },
  author: {
    type: String,
    default: 'Autor desconocido'
  },
  portada: {
    type: String,
    default: null
  },
  year: {
    type: Number,
    default: null
  },
  tag: {
    type: String,
    default: null
  }
})

const router = useRouter()

const handlePointerMove = (event) => {
  const card = event.currentTarget
  const rect = card.getBoundingClientRect()
  const x = event.clientX - rect.left
  const y = event.clientY - rect.top

  card.style.setProperty('--spot-x', `${x}px`)
  card.style.setProperty('--spot-y', `${y}px`)
}

const clearSpotlight = (event) => {
  const card = event.currentTarget
  card.style.removeProperty('--spot-x')
  card.style.removeProperty('--spot-y')
}

const goToDetails = () => {
  router.push(`/libro/${props.id}`)
}
</script>

<template>
  <article class="book-card" @click="goToDetails" @pointermove="handlePointerMove" @pointerleave="clearSpotlight">

    <!-- Portada -->
    <div class="cover-wrap">
      <img v-if="portada" :src="portada" :alt="title" class="cover-img" />
      <div v-else class="cover-placeholder">
        <span>📖</span>
      </div>
    </div>

    <!-- Info -->
    <div class="info">
      <span v-if="tag" class="tag">{{ tag }}</span>
      <h3>{{ title }}</h3>
      <p>{{ author }}</p>
      <small v-if="year">{{ year }}</small>
    </div>
  </article>
</template>

<style scoped>
.book-card {
  --spot-x: 50%;
  --spot-y: 50%;
  border-radius: 12px;
  border: 1px solid #2d3140;
  background: linear-gradient(150deg, #161823, #101219);
  transition: transform 0.2s ease, border-color 0.2s ease;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.book-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(
    180px circle at var(--spot-x) var(--spot-y),
    rgba(245, 240, 220, 0.18),
    rgba(245, 240, 220, 0.08) 35%,
    transparent 70%
  );
  opacity: 0;
  transition: opacity 0.2s ease;
  pointer-events: none;
  z-index: 2;
}

.book-card:hover {
  transform: translateY(-4px);
  border-color: #ed4d4d;
}

.book-card:hover::before {
  opacity: 1;
}

/* ---- Portada ---- */
.cover-wrap {
  width: 100%;
  aspect-ratio: 2 / 3;
  overflow: hidden;
  flex-shrink: 0;
}

.cover-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.3s ease;
}

.book-card:hover .cover-img {
  transform: scale(1.04);
}

.cover-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1e2030, #13151e);
  font-size: 2.5rem;
  color: #3d4460;
}

/* ---- Info ---- */
.info {
  padding: 0.9rem 1rem;
  position: relative;
  z-index: 1;
}

.tag {
  display: inline-block;
  margin-bottom: 0.6rem;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  font-size: 0.68rem;
  letter-spacing: 0.03em;
  color: #fbd0d0;
  background: rgba(237, 77, 77, 0.2);
}

h3 {
  margin: 0;
  color: #f7f7f8;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

p {
  margin: 0.35rem 0 0;
  color: #bdc2d0;
  font-size: 0.82rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

small {
  display: block;
  margin-top: 0.5rem;
  color: #8e95a8;
  font-size: 0.78rem;
}
</style>
