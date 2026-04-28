# Documentación de Estilos — Librum Tenebris

Sistema visual del frontend (BibliotecaTerror, Vue 3 + Vite). Estética **dark-horror** con acentos rojo carmesí y técnica **glassmorphism** (transparencias + `backdrop-filter`).

Todos los componentes usan **CSS scoped por SFC** (no hay framework de estilos tipo Tailwind/Bootstrap, todo es **CSS vanilla** con custom properties puntuales).

---

## 1. PALETA DE COLORES

### Fondos (de más oscuro a más claro)

| Variable conceptual    | Color       | Uso                                                              |
|------------------------|-------------|------------------------------------------------------------------|
| **Fondo base**         | `#0a0b10`   | `body` — negro azulado profundo, casi puro.                      |
| **Fondo elevado 1**    | `#0d1017`   | Modales, dropdowns sobre el fondo base.                          |
| **Fondo elevado 2**    | `#0f121a`   | Inputs y campos de formulario.                                   |
| **Fondo elevado 3**    | `#101219`   | BookCard (degradado).                                            |
| **Superficie tarjeta** | `#141724`   | Tarjetas grandes, paneles admin.                                 |
| **Superficie alta**    | `#161823`   | Inicio de degradados de tarjetas.                                |
| **Superficie hover**   | `#1e2030`   | Estados hover de elementos secundarios.                          |
| **Superficie acento**  | `#1f2335`   | Top de degradados de tarjeta destacada.                          |

### Color de marca (rojo carmesí — terror)

```css
#ed4d4d        /* Rojo principal — acción, hover, énfasis */
#d12f2f        /* Rojo profundo — radial gradients del hero */
#5a1e1e        /* Rojo borgoña — sombra del fondo */
#fbd0d0        /* Rojo pastel — textos sobre fondo rojo translúcido */
```

### Texto (escala de grises azulados)

```css
#f7f7f8        /* Títulos H3 (casi blanco) */
#f4f4f5        /* Texto principal del body */
#f0f2f7        /* Texto en estados hover */
#eceef2        /* Texto en inputs */
#bdc2d0        /* Texto secundario (autores, descripciones) */
#8e95a8        /* Texto terciario (metadatos, "small") */
#5c6480        /* Texto deshabilitado */
#33394b        /* Bordes de inputs */
#2d3140        /* Bordes de tarjetas en reposo */
```

### Estados / feedback

```css
#ffc107        /* Amarillo de estrellas (rating) */
#55f385        /* Verde — éxito (con fondo rgba 0.1) */
#ff9d00        /* Naranja — advertencia / pendiente */
```

### Bordes y rgba decorativos

```css
border: 1px solid #2d3140;                       /* Tarjeta neutra */
border: 1px solid #33394b;                       /* Input neutro */
border: 1px solid rgba(237, 77, 77, 0.3);        /* Hero (acento rojo) */
border: 1px solid rgba(237, 77, 77, 0.4);        /* Rating badge */

background: rgba(237, 77, 77, 0.05) → 0.20;      /* Tonos del rojo translúcido */
background: rgba(20, 24, 36, 0.85);              /* Glass sobre fondo oscuro */
background: rgba(5, 6, 10, 0.85);                /* Modal overlay (más oscuro) */
background: rgba(0, 0, 0, 0.68);                 /* Backdrop de modal */
```

---

## 2. TIPOGRAFÍA

### Fuentes (3 niveles)

| Fuente                | Peso       | Uso                                                 |
|-----------------------|------------|-----------------------------------------------------|
| **Grenze** (serif)    | 400-900    | Fuente global del proyecto (`:root`).               |
| **Germania One**      | 400        | Solo títulos H1 del Hero (estilo gótico).           |
| **Playfair Display**  | regular    | Algunos títulos editoriales puntuales.              |

```css
@import url('https://fonts.googleapis.com/css2?family=Grenze:ital,wght@0,100..900;1,100..900&display=swap');
@import url('https://fonts.googleapis.com/css2?family=Germania+One&display=swap');
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display&display=swap');
```

### Tamaños de referencia

```css
font-size: clamp(1rem, 2.5vw, 2.2rem);  /* Hero H1 — fluido */
font-size: 0.95rem;                     /* H3 de BookCard */
font-size: 0.85rem;                     /* Rating badge */
font-size: 0.82rem;                     /* Autor (BookCard p) */
font-size: 0.78rem;                     /* "small" / metadata */
font-size: 0.68rem;                     /* Tag pill */

line-height: 1.5;                       /* Body por defecto */
line-height: 1.3;                       /* H3 (compacto) */
letter-spacing: 0.05em;                 /* Hero H1 — gótico */
letter-spacing: 0.03em;                 /* Tag pill */
```

### Reglas globales (`style.css`)

```css
:root {
  font-family: 'Grenze', serif;
  line-height: 1.5;
  font-weight: 400;
  color: #f4f4f5;
  background-color: #0a0b10;
  font-synthesis: none;
  text-rendering: optimizeLegibility;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
```

---

## 3. FONDO GLOBAL — RADIAL GRADIENTS

El `body` lleva **dos radiales rojizos** sobre el fondo negro azulado, creando una sensación de iluminación irregular tipo "luna roja entre nubes":

```css
body {
  background:
    radial-gradient(circle at 90% 5%,  rgba(237, 77, 77, 0.17), transparent 32%),
    radial-gradient(circle at 10% 90%, rgba(90, 30, 30, 0.35),  transparent 40%),
    #0a0b10;
}
```

> **Esquina superior derecha**: rojo carmesí translúcido al 17%.
> **Esquina inferior izquierda**: rojo borgoña al 35%.

---

## 4. PATRONES VISUALES RECURRENTES

### Glassmorphism (el patrón estrella)

Todos los elementos flotantes (header sticky, dropdowns, badges, modales) usan la combinación:

```css
background: rgba(20, 24, 36, 0.85);   /* Fondo translúcido oscuro */
backdrop-filter: blur(8px);           /* Borrosidad detrás */
border: 1px solid rgba(255,255,255, 0.05);
```

Variantes:
- `blur(4px)` → badges pequeños (rating).
- `blur(8px)` → header, dropdowns.
- `blur(12px)+` → modales grandes.

### Spotlight cursor (BookCard)

Efecto de **luz que sigue al ratón** sobre las tarjetas de libro:

```css
.book-card::before {
  content: '';
  position: absolute; inset: 0;
  background: radial-gradient(
    180px circle at var(--spot-x) var(--spot-y),
    rgba(245, 240, 220, 0.18),
    rgba(245, 240, 220, 0.08) 35%,
    transparent 70%
  );
  opacity: 0;
  transition: opacity 0.2s ease;
}
.book-card:hover::before { opacity: 1; }
```

JavaScript actualiza las custom properties `--spot-x` / `--spot-y` con la posición del ratón.

### Levantar al hacer hover (cards/botones)

```css
.book-card:hover {
  transform: translateY(-4px);
  border-color: #ed4d4d;     /* Cambio de borde a rojo */
}
.cover-img:hover {
  transform: scale(1.04);    /* Zoom suave en la portada */
}
```

### Degradados diagonales en tarjetas

```css
background: linear-gradient(150deg, #161823, #101219);   /* Tarjeta normal */
background: linear-gradient(135deg, #1f2335, #141724);   /* Tarjeta destacada */
background: linear-gradient(160deg, #121521, #0d1017);   /* Modales */
```

### Hero con radial central rojo

```css
.hero-section {
  background:
    radial-gradient(circle at 50% 50%, rgba(209, 47, 47, 0.28), transparent 60%),
    linear-gradient(130deg, rgba(18, 16, 20, 0.95), rgba(10, 9, 11, 0.98));
  border: 1px solid rgba(237, 77, 77, 0.3);
  border-radius: 16px;
}
```

---

## 5. COMPONENTES — TOKENS DE DISEÑO

### Border radius (escalado coherente)

| Valor      | Uso                                                    |
|------------|--------------------------------------------------------|
| `4px`      | Detalles muy pequeños.                                 |
| `6px`      | Inputs móviles, botones secundarios.                   |
| `8px`      | Inputs desktop, chips, rating badges, etiquetas.       |
| `10px`     | Inputs en modales, dropdowns pequeños.                 |
| `12px`     | BookCard, paneles secundarios.                         |
| `16px`     | Hero, modales, secciones grandes.                      |
| `50%`      | Avatares circulares.                                   |
| `999px`    | Tag pills (forma de cápsula).                          |

### Sombras

```css
/* Elevación pequeña (badges sobre portadas) */
box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);

/* Elevación media (modales sobre overlay) */
box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);

/* Foco en input (anillo rojo translúcido) */
box-shadow: 0 0 0 3px rgba(237, 77, 77, 0.1);
```

### Inputs

```css
input {
  border: 1px solid #33394b;
  border-radius: 10px;
  padding: 0.6rem 0.75rem;
  background: #0f121a;
  color: #eceef2;
  font-family: inherit;
}

input:focus {
  border-color: #ed4d4d;
  box-shadow: 0 0 0 3px rgba(237, 77, 77, 0.1);
  outline: none;
}
```

### Botón principal

```css
.submit-btn {
  background: #ed4d4d;
  color: #f4f4f5;
  border: none;
  border-radius: 8px;
  padding: 0.7rem 1rem;
  font-family: inherit;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.1s;
}
.submit-btn:hover  { background: #ff5d5d; }
.submit-btn:active { transform: scale(0.98); }
```

### Tag / chip

```css
.tag {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  font-size: 0.68rem;
  letter-spacing: 0.03em;
  color: #fbd0d0;
  background: rgba(237, 77, 77, 0.2);
}
```

### Rating badge (estrella amarilla sobre cristal oscuro)

```css
.global-rating-badge {
  position: absolute; top: 8px; right: 8px;
  background: rgba(10, 12, 18, 0.85);
  backdrop-filter: blur(4px);
  border: 1px solid rgba(255, 193, 7, 0.4);
  color: #fff;
  font-weight: 700;
  font-size: 0.85rem;
  padding: 0.3rem 0.6rem;
  border-radius: 8px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
}
.global-rating-badge .star-icon { color: #ffc107; }
```

---

## 6. ANIMACIONES Y TRANSICIONES

### Curvas (siempre `ease`, sin spring exóticos)

```css
transition: transform 0.2s ease;                          /* Hover de card */
transition: background 0.2s ease, transform 0.1s;         /* Botón */
transition: border-color 0.2s ease, box-shadow 0.2s ease; /* Input focus */
transition: opacity 0.2s ease;                            /* Spotlight cursor */
transition: max-height 0.3s ease, opacity 0.2s ease;      /* Menú móvil */
transition: transform 0.3s ease;                          /* Imagen portada zoom */
transition: all 0.4s ease;                                /* Acordeones */
```

> **Regla**: 0.1s para feedback de clic, 0.2-0.3s para hover/focus, 0.4s+ solo para revelar contenido.

### Keyframes definidos

#### Fade-in del dropdown
```css
@keyframes dropdownFadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to   { opacity: 1; transform: translateY(0); }
}
.dropdown { animation: dropdownFadeIn 0.2s ease forwards; }
```

#### Shimmer (loading skeleton)
```css
@keyframes shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position:  200% 0; }
}
.skeleton { animation: shimmer 1.4s infinite; }
```

---

## 7. ICONOGRAFÍA — SVG INLINE

Todos los iconos viven en [`src/assets/`](../BibliotecaTerror/src/assets/) como **SVG estáticos** (no librería externa, no Font Awesome):

| Archivo                   | Función                                        |
|---------------------------|------------------------------------------------|
| `ghost-logo.svg`          | Logo de la marca (fantasma).                   |
| `book.svg`                | Icono de libros / catálogo.                    |
| `favorite.svg`            | Corazón de favoritos.                          |
| `calendar.svg`            | Préstamos / fechas.                            |
| `settings.svg`            | Engranaje de perfil.                           |
| `person.svg`              | Avatar genérico.                               |
| `admin-user-icon.svg`     | Icono de admin con corona.                     |
| `menu-hamburger.svg`      | Menú móvil.                                    |

> **Tinte por filtro CSS**: el SVG se carga en blanco/neutro y se recolorea con `filter: invert(...) sepia(...) hue-rotate(...)`. Ejemplo (rojo carmesí):

```css
filter: invert(24%) sepia(85%) saturate(7402%)
        hue-rotate(354deg) brightness(97%) contrast(115%)
        drop-shadow(0 2px 4px rgba(0,0,0,0.8));
```

Esto evita tener N versiones SVG (una por color).

### Emojis decorativos puntuales

- `📖` → placeholder cuando un libro no tiene portada.
- `★` → icono Unicode para estrellas de rating (dorado vía CSS, no SVG).

---

## 8. LAYOUT — CONTENEDOR Y RESPONSIVE

### Contenedor principal

```css
#app {
  width: min(1100px, 92%);
  margin: 0 auto;
}
.app-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
main { flex: 1; padding: 0 0 1rem; }
```

> **Anchura**: 1100px máximo, con margen lateral del 4% en pantallas pequeñas.

### Breakpoints

```css
@media (max-width: 768px) {
  /* Tablet y móvil: tipografía adaptativa, navbar plegable */
  h1 { white-space: normal; font-size: 1.8rem; }
}
```

> Solo se usa **un breakpoint principal** (768px). El diseño es **mobile-friendly por construcción** gracias a `clamp()` y unidades relativas.

### Aspect ratios

```css
.cover-wrap { aspect-ratio: 2 / 3; }   /* Portadas de libros — vertical clásico */
```

---

## 9. RESUMEN — IDENTIDAD VISUAL EN UNA FRASE

> **"Biblioteca gótica nocturna iluminada por luces rojas tenues, con superficies de cristal oscuro flotando sobre un fondo negro azulado."**

### Los 3 elementos de marca

1. **Color rojo carmesí `#ed4d4d`** como acento único — sale del fondo negro como sangre o luz de emergencia.
2. **Glassmorphism translúcido** (`rgba` + `backdrop-filter: blur`) — todo lo flotante es semitransparente.
3. **Tipografía gótica `Germania One`** solo en el Hero — el resto en `Grenze` (serif moderno legible).

### Lo que NO usa el proyecto

- Sin framework CSS (no Tailwind, no Bootstrap).
- Sin CSS-in-JS, todo en `<style scoped>` por SFC.
- Sin librería de iconos (SVG estáticos manuales).
- Sin animaciones complejas (todo con `transition` + 2 keyframes).
- Sin modo claro / theme switching — la estética **dark es permanente** por temática (terror).
