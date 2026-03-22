<template>
  <section class="hero-section">
    <p class="eyebrow">Edicion de marzo</p>
    <h1>Donde cada estanteria guarda una pesadilla distinta</h1>
    <p class="description">
      Descubre clasicos malditos, novelas goticas y nuevas voces del horror.
      Un espacio digital para lectores que buscan tension, misterio y oscuridad.
    </p>
    <div class="actions">
      <RouterLink to="/buscar" class="primary">Explorar coleccion</RouterLink>
      <RouterLink to="/recomendaciones" class="ghost">Ver recomendaciones</RouterLink>
    </div>
  </section>
</template>

<style scoped>
.hero-section {
  margin: 2rem auto 2.5rem;
  padding: 2.4rem 2rem;
  border-radius: 16px;
  border: 1px solid rgba(237, 77, 77, 0.3);
  background:
    radial-gradient(circle at 10% 10%, rgba(209, 47, 47, 0.28), transparent 35%),
    linear-gradient(130deg, rgba(18, 16, 20, 0.95), rgba(10, 9, 11, 0.98));
}

.eyebrow {
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  font-size: 0.74rem;
  color: #f26a6a;
}

h1 {
  margin: 0.8rem 0 0;
  color: #f4f4f5;
  font-size: clamp(1.8rem, 3.3vw, 3rem);
  max-width: 18ch;
}

.description {
  margin: 1rem 0 0;
  max-width: 65ch;
  color: #c8ccd8;
}

.actions {
  margin-top: 1.4rem;
  display: flex;
  gap: 0.8rem;
  flex-wrap: wrap;
}

.primary,
.ghost {
  padding: 0.7rem 1rem;
  border-radius: 10px;
  font-weight: 700;
  cursor: pointer;
}

.primary {
  border: 1px solid #ed4d4d;
  background: #ed4d4d;
  color: #141218;
}

.ghost {
  border: 1px solid #383d4d;
  background: transparent;
  color: #e7e9ee;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
}
.primary {
  text-decoration: none;
  display: inline-flex;
  align-items: center;
}
</style>
