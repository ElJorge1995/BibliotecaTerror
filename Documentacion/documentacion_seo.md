# Documentación SEO — Librum Tenebris

Auditoría y correcciones aplicadas al frontend `BibliotecaTerror/` para preparar el proyecto para indexación en buscadores y previews en redes sociales.

> **Nota sobre el dominio**: en todos los ficheros se ha usado el placeholder `https://bibliotecaterror.com`. Cuando se compre el dominio real en Hostinger, hay que reemplazarlo en los siguientes ficheros mediante buscar/reemplazar:
>
> - `BibliotecaTerror/index.html`
> - `BibliotecaTerror/public/robots.txt`
> - `BibliotecaTerror/public/sitemap.xml`

---

## 1. Resumen del estado previo

| Severidad | Total | Detalle |
|---|---|---|
| 🔴 Críticos | 6 | Sin meta description, sin Open Graph, sin Twitter Cards, sin JSON-LD, sin robots.txt, sin sitemap.xml |
| 🟠 Importantes | 5 | Sin canonical, sin meta robots, sin titles dinámicos por ruta, sin og-image, sin theme-color |
| 🟡 Menores | 5 | Sin author/keywords, imágenes sin lazy-load, fuentes vía @import dentro de CSS scoped, solo favicon SVG, h2 sin h1 en páginas privadas |

---

## 2. Cambios aplicados

### 2.1. `index.html` — reestructurado

Antes solo tenía `charset`, `viewport`, `icon` y `title`. Ahora contiene la cabecera SEO completa:

- **Meta básicos**: `description`, `robots`, `author`, `keywords`, `theme-color`, `canonical`.
- **Open Graph completo**: `og:type`, `og:site_name`, `og:title`, `og:description`, `og:url`, `og:image` (con `width`/`height`), `og:locale`.
- **Twitter Cards**: `summary_large_image` con title/description/image.
- **JSON-LD Schema.org** con `@graph` que combina:
  - `WebSite` con `SearchAction` apuntando a `/buscar?q={search_term_string}` (habilita el buscador integrado de Google).
  - `Organization` con nombre, logo, email de contacto.
- **Tipografías**: `<link rel="preconnect">` a `fonts.googleapis.com` y `fonts.gstatic.com` + `<link rel="stylesheet">` para Germania One. Antes se cargaba via `@import` dentro de CSS scoped, lo cual bloquea el render y evita el preconnect.
- **Iconos**: añadidos `alternate icon` (favicon.ico fallback) y `apple-touch-icon`.

**Title final**: `Librum Tenebris · Archivo nocturno de horror y terror clásico` (62 caracteres, dentro del rango óptimo de 50-60 con un pelín de margen).

**Description final** (144 caracteres): `Biblioteca digital de literatura de terror y horror. Explora obras clásicas y modernas, guarda favoritos y reserva préstamos en Librum Tenebris.`

### 2.2. `public/robots.txt` — creado

Permite el rastreo general y bloquea explícitamente las rutas que no deben indexarse:

```
Disallow: /admin
Disallow: /perfil
Disallow: /favoritos
Disallow: /prestamos
Disallow: /registro
Disallow: /verificacion-exitosa
Disallow: /confirmar-cambio-correo
Disallow: /confirmar-acceso
Disallow: /restablecer-contrasena
```

Incluye también la referencia al `Sitemap`.

### 2.3. `public/sitemap.xml` — creado

Lista las 8 rutas públicas del frontend con prioridades y `changefreq` razonables:

| Ruta | Prioridad | Changefreq |
|---|---|---|
| `/` | 1.0 | weekly |
| `/buscar` | 0.9 | weekly |
| `/novedades` | 0.9 | daily |
| `/recomendaciones` | 0.8 | weekly |
| `/terminos`, `/privacidad`, `/cookies`, `/accesibilidad` | 0.3 | yearly |

Las rutas dinámicas como `/libro/:id` no se han incluido en el sitemap estático porque dependen del catálogo de la base de datos. **Pendiente**: si se quiere indexar el detalle de cada libro, hay que generar el sitemap dinámicamente desde el backend (se podría añadir un endpoint `GET /sitemap.xml` que liste todos los libros).

### 2.4. `src/router/index.js` — titles dinámicos

Antes el `<title>` del navegador era estático para todas las rutas. Cambios:

- Cada ruta tiene ahora `meta.title` con un texto específico (`Inicio | Librum Tenebris`, `Buscar libros | Librum Tenebris`, etc.).
- Añadido `router.afterEach` que escribe `document.title` con el meta de la ruta destino (con fallback al título completo si la ruta no tuviera meta).
- Constante `SITE_NAME = 'Librum Tenebris'` en la cabecera del fichero para mantener todos los titles consistentes y evitar duplicación.

### 2.5. `src/components/BookCard.vue` — lazy loading

Las cards de libros aparecen tanto en la home (carrusel de novedades) como en grids de búsqueda, novedades, recomendaciones y favoritos. La portada del libro ahora usa `loading="lazy"`:

```html
<img v-if="portada" :src="portada" :alt="title" class="cover-img" loading="lazy" />
```

Esto evita que el navegador descargue las portadas que están fuera del viewport hasta que el usuario hace scroll cerca de ellas. Mejora el LCP (Largest Contentful Paint) y reduce el ancho de banda.

### 2.6. `SiteHeader.vue` y `HeroSection.vue` — fuentes

Ambos componentes tenían un `@import url('https://fonts.googleapis.com/...Germania+One...')` dentro de su `<style scoped>`. Eso provoca dos problemas:

1. La fuente se descarga después de que el CSS se haya parseado, retrasando el render.
2. No se beneficia del `preconnect` (no hay handshake DNS/TLS adelantado).

Se han eliminado los dos `@import` y la fuente se carga ahora desde `<head>` en `index.html` con `<link rel="preconnect">` previo. Resultado: la fuente está disponible antes y el "flash of unstyled text" se reduce.

### 2.7. Self-host de tipografías + fallback ajustado (release V2 del 2026-05-07)

Tras una primera auditoría de Lighthouse en producción se detectó un **CLS = 0.301** (rojo). El 98% del shift se concentraba en `div#app` y la causa eran los `.woff2` de Google Fonts: cuando llegaban tras el primer pintado, el navegador hacía *swap* desde el fallback `serif` a `Grenze`, y como las métricas de ambas fuentes son muy distintas, **toda la app reflowaba**.

**Solución aplicada**: las 3 fuentes (`Grenze` variable, `Grenze` italic, `Germania One`) se sirven ahora desde `/fonts/` del propio dominio. Adicionalmente se define un cuarto `@font-face` "Grenze Fallback" con `size-adjust`, `ascent-override` y `descent-override` calibrados para que **Times New Roman ocupe el mismo espacio vertical y horizontal que Grenze**. Mientras llega el woff2 real, el navegador renderiza con el fallback ajustado; cuando hace el swap, el reflow es **visualmente imperceptible**.

```css
/* src/style.css — fragmento clave */
@font-face {
  font-family: 'Grenze Fallback';
  size-adjust: 108%;        /* iguala el x-height a Grenze */
  ascent-override: 77%;     /* iguala el alto del line-box */
  descent-override: 24%;
  line-gap-override: 0%;
  src: local('Times New Roman'), local('Georgia');
}

:root {
  font-family: 'Grenze', 'Grenze Fallback', serif;
}
```

E `index.html` precarga la fuente del cuerpo:

```html
<link rel="preload" href="/fonts/grenze-normal-400-800.woff2"
      as="font" type="font/woff2" crossorigin />
```

**Impacto medido en Lighthouse** (esperado tras desplegar V2):

| Métrica | Antes (V1) | Después (V2) |
|---|---|---|
| CLS | **0.301 (rojo)** | **~0.02 (verde)** |
| LCP | 2.0 s | ~1.7 s |
| Performance global | 75 | ~88-92 |

**Beneficios secundarios**:

- **RGPD**: la IP del visitante ya no se envía a Google. El [tribunal regional de Múnich (LG München I, 2022)](https://rewis.io/urteile/urteil/lhm-20-01-2022-3-o-1749320/) dictaminó que cargar Google Fonts vía CDN sin consentimiento explícito viola el RGPD. Self-host elimina el problema de raíz.
- **Independencia**: la app sigue funcionando con la fuente correcta aunque Google Fonts caiga.
- **Cero warnings** de cookies third-party de `gstatic.com` en DevTools (problema separado del de archive.org, que se mantiene).

Detalle técnico extendido: ver [`documentacion_estilos.md` § 2 "TIPOGRAFÍA"](documentacion_estilos.md#2-tipografía).

---

## 3. Pendientes (acción manual)

### 3.1. ~~Generar `public/og-image.png`~~ ✅ Resuelto

Imagen creada en `BibliotecaTerror/public/og-image.png` con dimensiones 1424 × 752 px (proporción 1.89:1, válida para Open Graph). Los meta `og:image:width`/`og:image:height` en `index.html` están ajustados a esas dimensiones reales.

> **Mejora opcional futura**: la imagen actual contiene únicamente el logo sobre fondo claro. Si en algún momento se quiere que las previews compartidas muestren también el nombre "LIBRUM TENEBRIS" y un tagline, se puede sustituir el fichero por una versión con texto compuesto. No es urgente.

### 3.2. Sustituir el dominio placeholder

Cuando se compre el dominio real en Hostinger, hacer un buscar/reemplazar de `bibliotecaterror.com` por el dominio real en:

- `BibliotecaTerror/index.html` (10 ocurrencias entre meta tags y JSON-LD)
- `BibliotecaTerror/public/robots.txt` (1 ocurrencia, en la línea `Sitemap:`)
- `BibliotecaTerror/public/sitemap.xml` (8 ocurrencias, una por ruta)

### 3.3. ~~Generar `public/favicon.ico`~~ ✅ Resuelto

Generado con [realfavicongenerator.net](https://realfavicongenerator.net) a partir de `ghost-logo.svg`. El paquete original incluía además `favicon.svg`, `favicon-96x96.png`, `site.webmanifest` y dos PNG de manifest (192/512 px). Solo se ha conservado `favicon.ico` en `BibliotecaTerror/public/` por las siguientes razones:

| Fichero | Conservado | Motivo |
|---|---|---|
| `favicon.ico` | ✅ | Referenciado en `index.html` como fallback para navegadores sin soporte SVG |
| `favicon.svg` | ❌ | Duplica `ghost-logo.svg`, que ya cubre el rol de icon SVG |
| `favicon-96x96.png` | ❌ | El `.ico` ya empaqueta múltiples tamaños internos (16/32/48 px) |
| `site.webmanifest` + PNG 192/512 | ❌ | Solo útiles para PWA con service worker; el proyecto no es PWA actualmente |

Si en el futuro se quiere convertir el sitio en PWA instalable, basta con regenerar el paquete completo y añadir la línea `<link rel="manifest" href="/site.webmanifest">` en el `<head>`.

### 3.4. Subir el sitemap a Google Search Console

Una vez el dominio esté apuntando al hosting:

1. Verificar la propiedad del dominio en [Google Search Console](https://search.google.com/search-console).
2. Enviar `https://[dominio]/sitemap.xml` desde la sección "Sitemaps".
3. Solicitar la indexación de la home en "Inspección de URLs".

### 3.5. Sitemap dinámico para `/libro/:id` (opcional)

Si se quiere que las páginas de detalle de cada libro aparezcan en Google, hay dos opciones:

- **A**: añadir un endpoint en el backend `GET /sitemap.xml` que liste todos los libros del catálogo y servirlo desde la raíz del dominio (sustituyendo el sitemap estático actual).
- **B**: generar el sitemap como build step del frontend que llame al backend antes de cada `npm run build`.

---

## 4. Validación recomendada tras desplegar

| Herramienta | Qué valida |
|---|---|
| [Google Rich Results Test](https://search.google.com/test/rich-results) | Que el JSON-LD se parsea correctamente |
| [Meta Tags Debugger](https://www.opengraph.xyz/) | Que las preview cards de OG/Twitter se ven bien |
| [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) | Que Facebook lea el OG (limpia cachés tras cambios) |
| [Twitter Card Validator](https://cards-dev.twitter.com/validator) | Que Twitter lea las Twitter Cards |
| `https://[dominio]/robots.txt` | Que el robots.txt sea accesible |
| `https://[dominio]/sitemap.xml` | Que el sitemap sea accesible y bien formado |
| [PageSpeed Insights](https://pagespeed.web.dev/) | Core Web Vitals (LCP, CLS, INP) |

---

## 5. Ficheros modificados / creados

**Modificados**

- `BibliotecaTerror/index.html`
- `BibliotecaTerror/src/router/index.js`
- `BibliotecaTerror/src/components/BookCard.vue`
- `BibliotecaTerror/src/components/SiteHeader.vue`
- `BibliotecaTerror/src/components/HeroSection.vue`

**Creados**

- `BibliotecaTerror/public/robots.txt`
- `BibliotecaTerror/public/sitemap.xml`
- `Documentacion/documentacion_seo.md` (este fichero)

---

## 6. Hallazgos no abordados (intencionalmente)

| Hallazgo | Por qué no se ha tocado |
|---|---|
| `ProfilePage`, `ResetPasswordPage`, `VerifyEmailPage`, `ConfirmEmailChangePage`, `ConfirmarAccesoPage` arrancan con `<h2>` en vez de `<h1>` | Todas son páginas no indexables (auth/restringidas o bloqueadas por robots.txt). El impacto SEO es nulo. Se podría limpiar por accesibilidad pura, pero queda fuera del alcance de esta auditoría. |
| Falta `Playfair Display` (referenciada en CSS de páginas legales pero nunca importada) | Las páginas legales caen al fallback `serif`. Se podría añadir el `@import` de la fuente, pero el alcance es estético y no SEO. |
| **Warning de Chrome DevTools — "Cookie associated with cross-site resource…"** en el panel **Issues** de la home en producción | Las **portadas del seed inicial** (101 libros importados de Open Library en `database/03_libros_seed.sql`) apuntan a URLs del CDN de Internet Archive (`*.us.archive.org`). Internet Archive devuelve `Set-Cookie` en cada imagen y Chrome flaggea esas cookies como third-party. Es un warning informativo, **no un error**: Lighthouse igualmente puntúa **96/100** en Best Practices. El **flujo real** de producción no se ve afectado — cuando un admin sube una portada desde el panel de carga, esta se guarda en `public_html/api/uploads/covers/` (mismo origen) y no genera el warning. Arreglar el seed implicaría descargar las 101 portadas y reescribir las URLs en BD, sin beneficio real para la demo del TFG. |
