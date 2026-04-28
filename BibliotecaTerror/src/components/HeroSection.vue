<template>
  <section class="hero-section">
    <h1>Donde cada estantería guarda una pesadilla distinta</h1>
  </section>
</template>

<style scoped>
.hero-section {
  margin: 2rem auto 2.5rem;
  padding: 3rem;
  border-radius: 16px;
  border: 1px solid rgba(237, 77, 77, 0.3);
  background:
    radial-gradient(circle at 50% 50%, rgba(209, 47, 47, 0.28), transparent 60%),
    linear-gradient(130deg, rgba(18, 16, 20, 0.95), rgba(10, 9, 11, 0.98));
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
}

h1 {
  margin: 0;
  color: #f4f4f5;
  font-family: 'Germania One', cursive, serif;
  font-size: clamp(1rem, 2.5vw, 2.2rem);
  white-space: nowrap;
  letter-spacing: 0.05em;
  font-weight: 400;
}

@media (max-width: 768px) {
  h1 {
    white-space: normal;
    font-size: 1.8rem;
  }
}
</style>
