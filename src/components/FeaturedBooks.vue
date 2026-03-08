<script setup>
import BookCard from './BookCard.vue'

const books = [
  { title: 'Dracula', author: 'Bram Stoker', year: 1897, tag: 'Clasico' },
  { title: 'Frankenstein', author: 'Mary Shelley', year: 1818, tag: 'Gothic' },
  { title: 'It', author: 'Stephen King', year: 1986, tag: 'Moderno' },
  { title: 'Mexican Gothic', author: 'Silvia Moreno-Garcia', year: 2020, tag: 'Nuevo' }
]
</script>

<template>
  <section class="featured-books">
    <div class="section-head">
      <h2>Libros destacados</h2>
      <a href="#">Ver todo el catalogo</a>
    </div>

    <div class="grid">
      <BookCard
        v-for="book in books"
        :key="book.title"
        :title="book.title"
        :author="book.author"
        :year="book.year"
        :tag="book.tag"
      />
    </div>
  </section>
</template>

<style scoped>
.featured-books {
  margin: 2rem 0 3rem;
}

.section-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
}

h2 {
  margin: 0;
  color: #f2f2f3;
  font-size: 1.6rem;
}

a {
  color: #ed4d4d;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.95rem;
}

.grid {
  margin-top: 1rem;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.9rem;
}
</style>
