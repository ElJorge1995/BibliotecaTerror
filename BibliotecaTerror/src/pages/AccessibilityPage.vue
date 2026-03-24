<script setup>
</script>

<template>
  <div class="legal-page">
    <div class="legal-container">
      <h1 class="legal-title">Declaración de Accesibilidad</h1>
      <p class="last-updated">Última actualización: 24 de marzo de 2026</p>
      
      <div class="legal-content">
        <h2>1. Nivel de Cumplimiento</h2>
        <p>En Librum Tenebris nos hemos comprometido a hacer accesible nuestro sitio web de conformidad con el nivel AA de las pautas WCAG 2.1. Nuestro objetivo es que este archivo digital sea navegable para cualquier lector, sin importar sus capacidades tecnológicas o físicas.</p>
        
        <h2>2. Medidas Adoptadas</h2>
        <p>Para asegurar la accesibilidad, hemos configurado un código semántico, fuertes contrastes entre fondos oscuros y textos legibles, y etiquetas descriptivas o ARIA para botones e iconos que garantizan el correcto funcionamiento con lectores de pantalla.</p>

        <h2>3. Limitaciones Conocidas</h2>
        <p>A pesar de nuestros esfuerzos, actualmente somos conscientes de las siguientes limitaciones puntuales:</p>
        <ul>
          <li>Algunas imágenes procedentes del archivo antiguo importado podrían carecer de texto alternativo (`alt`) completamente detallado.</li>
          <li>Ciertos detalles estéticos visuales sutiles (gradientes decorativos) pueden no ser perceptibles bajo forzado de contrastes altos del sistema operativo.</li>
        </ul>
        
        <h2>4. Solicitudes y Canal de Contacto</h2>
        <p>Si encuentras alguna barrera de accesibilidad o tienes sugerencias para mejorar tu experiencia utilizando lectores de pantalla, teclados u otros dispositivos de asistencia, por favor háznoslo saber a través de nuestros canales y lo corregiremos con urgencia:</p>
        <ul>
          <li><strong>Email:</strong> accesibilidad@bibliotecaterror.com</li>
          <li><strong>Mensaje:</strong> a través de nuestras redes sociales marcadas en el pie de página.</li>
        </ul>
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

.legal-content p, .legal-content ul {
  line-height: 1.7;
  color: #8a94ab;
  margin-bottom: 1.5rem;
}

.legal-content ul {
  padding-left: 1.5rem;
}

.legal-content li {
  margin-bottom: 0.8rem;
}

.legal-content strong {
  color: #d1d5e0;
}
</style>
