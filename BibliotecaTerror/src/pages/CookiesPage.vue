<script setup>
</script>

<template>
  <div class="legal-page">
    <div class="legal-container">
      <h1 class="legal-title">Política de Cookies</h1>
      <p class="last-updated">Última actualización: 22 de marzo de 2026</p>
      
      <div class="legal-content">
        <h2>1. ¿Qué son las Cookies?</h2>
        <p>Una cookie es un pequeño archivo de texto, como un micro fragmento de pergamino, que nuestro servidor deposita en tu navegador web. No es magia negra, solo tecnología.</p>
        
        <h2>2. Las Cookies que Utilizamos</h2>
        <p>En Librum Tenebris somos férreos defensores de la oscuridad digital. Por ello, <strong>solamente usamos una (1) cookie estrictamente necesaria</strong>. Esta cookie guarda de manera encriptada un token (cadena de letras) que valida que tú y nadie más eres el dueño de tu cuenta cuando accedes a "Mis Alquileres" o reservas un tomo.</p>
        
        <h2>3. Consentimiento y Seguridad</h2>
        <p>Al ser esta cookie puramente técnica y obligatoria para que el sistema de alquiler funcione de manera segura y no se mezcle con otros usuarios, su uso no requiere de perfiles avanzados de marketing. Es indestructible y necesaria durante tu sesión. Al cerrar sesión, la cookie se esfumará.</p>
        
        <h2>4. Desactivación de Cookies</h2>
        <p>Si decides bloquear todas las cookies desde tu navegador medieval, serás repudiado por nuestros sistemas y no podrás añadir libros a tu reserva. Quedas advertido.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.legal-page {
  min-height: calc(100vh - 70px);
  padding: 4rem 1.5rem;
  color: #97a0b7;
}

.legal-container {
  max-width: 800px;
  margin: 0 auto;
  background: rgba(15, 18, 28, 0.6);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  padding: 3rem 4rem;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

.legal-title {
  color: #d1d5e0;
  font-size: 2.2rem;
  margin-top: 0;
  margin-bottom: 0.5rem;
  font-family: 'Playfair Display', serif;
}

.last-updated {
  font-size: 0.85rem;
  color: #5c6480;
  margin-bottom: 3rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  padding-bottom: 1rem;
}

.legal-content h2 {
  color: #ed4d4d;
  font-size: 1.3rem;
  margin-top: 2.5rem;
  margin-bottom: 1rem;
}

.legal-content p {
  line-height: 1.7;
  color: #8a94ab;
  margin-bottom: 1.5rem;
}
</style>
