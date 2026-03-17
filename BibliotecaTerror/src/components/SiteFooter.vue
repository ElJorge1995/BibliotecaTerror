<script setup>
const currentYear = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer">
    <div class="footer-inner">

      <!-- Col 1: Brand -->
      <div class="footer-col">
        <div class="footer-badge">LIBRUM TENEBRIS</div>
        <p class="footer-desc">
          El archivo nocturno definitivo de horror clásico y moderno. Libros, relatos y oscuridad entre portadas.
        </p>
        <div class="footer-socials">
          <a href="#" aria-label="Instagram" class="social-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
            </svg>
          </a>
          <a href="#" aria-label="Twitter / X" class="social-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
            </svg>
          </a>
          <a href="#" aria-label="Goodreads" class="social-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
            </svg>
          </a>
        </div>
      </div>

      <!-- Col 2: Navegación -->
      <div class="footer-col">
        <div class="footer-badge">NAVEGACIÓN</div>
        <nav class="footer-nav">
          <a href="/">Inicio</a>
          <a href="#">Catálogo</a>
          <a href="#">Novedades</a>
          <a href="#">Eventos</a>
          <a href="/registro">Registro</a>
        </nav>
      </div>

      <!-- Col 3: Contacto -->
      <div class="footer-col">
        <div class="footer-badge">CONTACTO</div>
        <address class="footer-contact">
          <p><strong>Email:</strong> contacto@bibliotecaterror.com</p>
          <p><strong>Horario:</strong> Siempre abierto (somos digitales)</p>
        </address>
      </div>

    </div>

    <!-- Bottom bar -->
    <div class="footer-bottom">
      <p>© {{ currentYear }} Librum Tenebris. Todos los derechos reservados.</p>
      <div class="footer-bottom-links">
        <a href="#">Términos legales</a>
        <span class="separator">·</span>
        <a href="#">Privacidad</a>
        <span class="separator">·</span>
        <a href="#">Cookies</a>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  width: 100vw;
  margin-left: calc(50% - 50vw);
  background: rgba(5, 6, 10, 0.85);
  border-top: 1px solid rgba(237, 77, 77, 0.2);
  color: #97a0b7;
  font-size: 0.875rem;
  padding: 3rem 2rem 0;
  backdrop-filter: blur(8px);
}

.footer-inner {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1.4fr 1fr 1fr;
  gap: 3rem;
  padding-bottom: 2.5rem;
}

/* Badge title (matching reference image bordered button) */
.footer-badge {
  display: inline-block;
  border: 1px solid rgba(237, 77, 77, 0.5);
  color: #d1d5e0;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.09em;
  padding: 0.3rem 0.7rem;
  border-radius: 4px;
  margin-bottom: 1rem;
}

/* Col 1 */
.footer-desc {
  margin: 0 0 1.2rem;
  line-height: 1.65;
  color: #828a9e;
  max-width: 28ch;
}

.footer-socials {
  display: flex;
  gap: 0.65rem;
}

.social-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: 1px solid #2d3348;
  border-radius: 6px;
  color: #7a8299;
  text-decoration: none;
  transition: color 0.2s ease, border-color 0.2s ease, background 0.2s ease;
}

.social-icon:hover {
  color: #ed4d4d;
  border-color: rgba(237, 77, 77, 0.45);
  background: rgba(237, 77, 77, 0.08);
}

/* Col 2 */
.footer-nav {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}

.footer-nav a {
  color: #8a94ab;
  text-decoration: none;
  transition: color 0.2s ease;
}

.footer-nav a:hover {
  color: #ed4d4d;
}

/* Col 3 */
.footer-contact {
  font-style: normal;
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}

.footer-contact p {
  margin: 0;
  color: #8a94ab;
}

.footer-contact strong {
  color: #d1d5e0;
}

/* Bottom bar */
.footer-bottom {
  max-width: 1100px;
  margin: 0 auto;
  border-top: 1px solid rgba(40, 46, 62, 0.8);
  padding: 1.2rem 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  color: #5c6480;
  font-size: 0.8rem;
}

.footer-bottom p {
  margin: 0;
}

.footer-bottom-links {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.footer-bottom-links a {
  color: #5c6480;
  text-decoration: none;
  transition: color 0.2s ease;
}

.footer-bottom-links a:hover {
  color: #ed4d4d;
}

.separator {
  color: #2d3348;
}

@media (max-width: 860px) {
  .footer-inner {
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
  }
}

@media (max-width: 560px) {
  .footer-inner {
    grid-template-columns: 1fr;
    gap: 1.8rem;
  }

  .footer-bottom {
    flex-direction: column;
    text-align: center;
  }
}
</style>
