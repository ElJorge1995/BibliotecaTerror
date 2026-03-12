<script setup>
const currentYear = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer">
    <div class="footer-main">
      <p>Biblioteca del Terror</p>
      <p>contacto@bibliotecaterror.com</p>
    </div>

    <div class="footer-bottom">
      <a href="#">Terminos legales</a>
      <p>© {{ currentYear }} Librum Tenebris. Todos los derechos reservados.</p>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  width: 100vw;
  margin-left: calc(50% - 50vw);
  border-top: 1px solid #292d3b;
  padding: 2rem 1.5rem 2.2rem;
  background: rgba(0, 0, 0, 0.62);
  color: #97a0b7;
  font-size: 0.9rem;
  flex-direction: column;
}

.footer-main,
.footer-bottom {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

.footer-bottom {
  padding-top: 1.2rem;
  border-top: 1px solid #202430;
  margin-top: 1rem;
  align-items: center;
}

a {
  color: #c6cbdb;
  text-decoration: none;
}

a:hover {
  color: #ed4d4d;
}

p {
  margin: 0;
}

@media (max-width: 720px) {
  .footer-main,
  .footer-bottom {
    flex-direction: column;
  }
}
</style>
