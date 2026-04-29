<script setup>
const equipo = [
  {
    nombre: 'Jorge Núñez Granero',
    rol: 'Equipo de desarrollo',
    bio: 'Desarrollador del proyecto Librum Tenebris.',
  },
  {
    nombre: 'Alejandro del Campo Ortiz',
    rol: 'Equipo de desarrollo',
    bio: 'Desarrollador del proyecto Librum Tenebris.',
  },
  {
    nombre: 'Eva María Sánchez Zamora',
    rol: 'Equipo de desarrollo',
    bio: 'Desarrolladora del proyecto Librum Tenebris.',
  },
]

const inicialesDe = (nombre) => {
  return nombre
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0].toUpperCase())
    .join('')
}
</script>

<template>
  <div class="equipo-page">
    <header class="equipo-hero">
      <h1>Quiénes somos</h1>
      <p class="lead">
        Librum Tenebris es un proyecto académico desarrollado en el marco del CFGS de
        Desarrollo de Aplicaciones Web. Detrás de cada estantería digital, un equipo
        de tres personas haciendo posible que los libros del horror clásico y moderno
        encuentren un nuevo refugio.
      </p>
    </header>

    <section class="equipo-grid">
      <article v-for="m in equipo" :key="m.nombre" class="miembro-card">
        <div class="avatar">{{ inicialesDe(m.nombre) }}</div>
        <h2 class="nombre">{{ m.nombre }}</h2>
        <p class="rol">{{ m.rol }}</p>
        <p class="bio">{{ m.bio }}</p>
      </article>
    </section>

    <footer class="equipo-foot">
      <p>
        ¿Tienes preguntas sobre el proyecto? Escríbenos a
        <a href="mailto:contacto@bibliotecaterror.com">contacto@bibliotecaterror.com</a>.
      </p>
    </footer>
  </div>
</template>

<style scoped>
.equipo-page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 4rem 1.5rem 5rem;
  color: #97a0b7;
}

.equipo-hero {
  text-align: center;
  margin-bottom: 4rem;
}

.equipo-hero h1 {
  font-size: 2.5rem;
  color: #f4f4f5;
  margin: 0 0 1rem;
  font-family: 'Germania One', cursive, serif;
  letter-spacing: 0.05em;
}

.equipo-hero .lead {
  font-size: 1.1rem;
  line-height: 1.7;
  max-width: 720px;
  margin: 0 auto;
  color: #b8bece;
}

.equipo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1.5rem;
  margin-bottom: 4rem;
}

.miembro-card {
  background: rgba(17, 20, 30, 0.5);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(237, 77, 77, 0.18);
  border-radius: 16px;
  padding: 2rem 1.5rem;
  text-align: center;
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.miembro-card:hover {
  transform: translateY(-4px);
  border-color: rgba(237, 77, 77, 0.45);
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.5);
}

.avatar {
  width: 88px;
  height: 88px;
  margin: 0 auto 1.2rem;
  border-radius: 50%;
  background: linear-gradient(135deg, #b91c1c, #4a0e0e);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  font-weight: 700;
  color: #fff;
  box-shadow: 0 8px 24px rgba(185, 28, 28, 0.35);
  letter-spacing: 0.05em;
}

.nombre {
  margin: 0 0 0.4rem;
  color: #f0f2f7;
  font-size: 1.2rem;
  font-weight: 600;
}

.rol {
  margin: 0 0 1rem;
  color: #ed4d4d;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.bio {
  margin: 0;
  color: #97a0b7;
  font-size: 0.95rem;
  line-height: 1.55;
}

.equipo-foot {
  text-align: center;
  padding-top: 2.5rem;
  border-top: 1px solid rgba(45, 51, 72, 0.4);
}

.equipo-foot p {
  margin: 0;
  font-size: 0.95rem;
  color: #97a0b7;
}

.equipo-foot a {
  color: #ed4d4d;
  text-decoration: none;
  font-weight: 600;
}

.equipo-foot a:hover {
  text-decoration: underline;
}

@media (max-width: 640px) {
  .equipo-hero h1 {
    font-size: 2rem;
  }
  .equipo-hero .lead {
    font-size: 1rem;
  }
}
</style>
