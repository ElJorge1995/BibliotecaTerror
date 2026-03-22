<script setup>
</script>

<template>
  <div class="legal-page">
    <div class="legal-container">
      <h1 class="legal-title">Política de Privacidad</h1>
      <p class="last-updated">Última actualización: 22 de marzo de 2026</p>
      
      <div class="legal-content">
        <h2>1. Información que Recopilamos</h2>
        <p>Recopilamos únicamente los datos necesarios para gestionar tus reservas literarias. Esto incluye tu nombre de usuario, correo electrónico y el historial de volúmenes malditos que has decido llevarte a casa.</p>
        
        <h2>2. Uso de la Información</h2>
        <p>Tu información se utiliza exclusivamente para mantener el inventario de la biblioteca al día, contactarte si te excedes del plazo de devolución estipulado (14 días), y mejorar nuestro algoritmo de recomendaciones sombrías basado en tus valoraciones (estrellas).</p>
        
        <h2>3. Compartición de Datos</h2>
        <p>Tus datos son almacenados en nuestras catacumbas de servidor. No vendemos ni cedemos tu alma digital a terceros, oráculos, ni empresas de marketing externas.</p>
        
        <h2>4. Derechos del Usuario</h2>
        <p>Puedes solicitar a nuestros bibliotecarios la revisión o purga de tu historial en cualquier momento, siempre que no tengas tomos pendientes de devolver en nuestras estanterías.</p>
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
