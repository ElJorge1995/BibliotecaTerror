<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  author: {
    type: String,
    required: true
  },
  year: {
    type: Number,
    required: true
  },
  tag: {
    type: String,
    required: true
  }
})

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
</script>

<template>
  <article class="book-card" @pointermove="handlePointerMove" @pointerleave="clearSpotlight">
    <span class="tag">{{ tag }}</span>
    <h3>{{ title }}</h3>
    <p>{{ author }}</p>
    <small>{{ year }}</small>
  </article>
</template>

<style scoped>
.book-card {
  --spot-x: 50%;
  --spot-y: 50%;
  padding: 1.1rem;
  border-radius: 12px;
  border: 1px solid #2d3140;
  background: linear-gradient(150deg, #161823, #101219);
  transition: transform 0.2s ease, border-color 0.2s ease;
  position: relative;
  overflow: hidden;
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
}

.book-card:hover {
  transform: translateY(-4px);
  border-color: #ed4d4d;
}

.book-card:hover::before {
  opacity: 1;
}

.book-card > * {
  position: relative;
  z-index: 1;
}

.tag {
  display: inline-block;
  margin-bottom: 0.7rem;
  padding: 0.25rem 0.55rem;
  border-radius: 999px;
  font-size: 0.7rem;
  letter-spacing: 0.03em;
  color: #fbd0d0;
  background: rgba(237, 77, 77, 0.2);
}

h3 {
  margin: 0;
  color: #f7f7f8;
  font-size: 1.1rem;
}

p {
  margin: 0.45rem 0 0;
  color: #bdc2d0;
}

small {
  display: block;
  margin-top: 0.7rem;
  color: #8e95a8;
}
</style>
