<script setup>
</script>

<template>
  <div class="legal-page">
    <div class="legal-container">
      <h1 class="legal-title">Términos y Condiciones Legales</h1>
      <p class="last-updated">Última actualización: 22 de marzo de 2026</p>
      
      <div class="legal-content">
        <h2>1. Aceptación de los Términos</h2>
        <p>Al acceder y utilizar el archivo de Librum Tenebris, usted acepta quedar vinculado al amparo de las reglas estipuladas. Si no está de acuerdo, le rogamos abandonar estas criptas virtuales.</p>
        
        <h2>2. Uso del Servicio y Alquileres</h2>
        <p>Los libros solicitados mediante el apartado de reservas digitales deben retirarse en nuestras tiendas físicas. El plazo oficial de penalización y/o cargos extra sólo dará comienzo cuando el administrador haga efectiva la marca comercial.</p>
        
        <h2>3. Propiedad Intelectual</h2>
        <p>El diseño gráfico, el horror conceptual y la estructura de metadatos nos pertenece. Las obras literarias, sin embargo, pertenecen a la eternidad o a sus respectivos poseedores del copyright.</p>
        
        <h2>4. Contacto y Resoluciones</h2>
        <p>Para litigios, reclamaciones o ritos oscuros, puede dirigirse a nuestro contacto oficial en el pie de página. El foro competente será el Tribunal del averno de su jurisdicción.</p>
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
