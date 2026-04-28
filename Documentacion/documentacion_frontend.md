# Documentación Frontend — Librum Tenebris

Stack: **Vue 3 + Vite**

---

## 1. COMPONENTES (7 reutilizables)

Ubicados en `src/components/`:

- **SiteHeader.vue** — Navbar sticky con logo, buscador predictivo (debounce 250ms) y menú de usuario.
- **SiteFooter.vue** — Footer corporativo con enlaces legales.
- **BookCard.vue** — Tarjeta de libro con efecto spotlight al pasar el ratón, portada, autor, rating y favorito.
- **FeaturedBooks.vue** — Carrusel Swiper con efecto coverflow 3D y auto-play.
- **LoginModal.vue** — Modal con dos modos (login / recuperar contraseña) usando `<Teleport>`.
- **HeroSection.vue** — Sección principal con tipografía Germania One y gradientes.
- **CookieBanner.vue** — Banner de cookies global.

---

## 2. ROUTER (18 rutas en `src/router/index.js`)

| Ruta | Protección |
|---|---|
| `/` (home) | pública |
| `/buscar`, `/novedades`, `/recomendaciones` | públicas |
| `/libro/:id` | pública (dinámica) |
| `/registro`, `/restablecer-contrasena` | `guestOnly` |
| `/favoritos`, `/prestamos`, `/perfil` | `requiresAuth` |
| `/admin` | `requiresAuth + requiresAdmin` |
| `/verificacion-exitosa`, `/confirmar-cambio-correo`, `/confirmar-acceso` | tokens por URL |
| `/terminos`, `/privacidad`, `/cookies`, `/accesibilidad` | públicas |

**Guardias `beforeEach`**: hidratan el usuario desde token, redirigen según rol y estado de sesión. Todas las páginas pesadas se cargan con **lazy-loading** (`() => import(...)`).

---

## 3. AXIOS — Dos APIs separadas

### A) `src/api/auth.js` → `http://localhost:8000`

- **Auth**: `/auth/login`, `/auth/register`, `/auth/logout`, `/auth/me`
- **Verificación**: `/auth/verify-email`, `/auth/resend-verification`
- **Recuperación**: `/auth/request-password-reset`, `/auth/reset-password`
- **Perfil**: `/auth/update-name`, `/auth/update-username`, `/auth/update-phone`, `/auth/change-password`, `/auth/request-email-change`
- **Admin**: `/auth/admin/users`, `/auth/admin/update-role`, `/auth/admin/set-ban`, `/auth/admin/force-logout`, `/auth/admin/delete-user`

### B) `src/api/books.js` → `http://localhost:8080/libros_api.php`

Endpoint único con parámetro `action`:
`recientes`, `recomendaciones`, `buscar`, `obtener`, `check_favorito`, `toggle_favorito`, `mis_favoritos`, `crear`, `editar_libro`, `prestar`, `mis_prestamos`, `actualizar_prestamo`, `valorar_prestamo`.

**Detalle técnico**: un **interceptor de response** traduce 97 mensajes de error del backend (inglés) al español automáticamente, y otro de request añade `Authorization: Bearer <token>` en todas las llamadas.

---

## 4. INTERACTIVIDAD

- **Buscador predictivo en el header**: dropdown con 5 resultados en vivo, debounce 250ms, cierre por click-outside.
- **Skeleton loaders** mientras cargan las grillas de libros.
- **Toggle de favoritos sin recargar**: el icono cambia al instante.
- **Mensajes de error/éxito dinámicos** con auto-ocultado a los 5s y scroll automático.
- **Validación de teléfono español** con regex `/^(?:\+34|0034)?[6789]\d{8}$/`.
- **Modal de login con dos modos** (login ↔ recuperar contraseña).
- **Admin panel con tabs** (Usuarios, Libros, Préstamos) y filtros en tiempo real.
- **Sistema de valoraciones** (1-5 estrellas) tras un préstamo.
- **Empty states** temáticos (búsqueda vacía, sin favoritos, sin préstamos).

---

## 5. ORGANIZACIÓN DEL PROYECTO

```
src/
├── api/          (auth.js, books.js)
├── assets/       (SVGs: logo, iconos)
├── components/   (7 componentes reutilizables)
├── pages/        (18 vistas)
├── router/       (index.js con guardias)
├── stores/       (auth.js → Pinia)
├── App.vue       (shell: Header + RouterView + Footer + LoginModal + CookieBanner)
├── main.js
└── style.css
```

---

## 6. ESTADO / DATOS

- **Pinia** (`src/stores/auth.js`) con `user`, `token`, getters `isAuthenticated` / `isAdmin` y acciones `login`, `logout`, `fetchMe`.
- **Props**: `BookCard` recibe `id, title, author, portada, year, tag, rating, isFavorito`.
- **Emits**: `LoginModal` emite `close`; `SiteHeader` emite `open-login`.
- **Router params**: `/libro/:id` → `useRoute().params.id` en `BookDetailsPage`.
- **localStorage**: persiste `auth_token` entre sesiones.
- **Watchers**: `SearchPage` re-busca al cambiar `route.query.q`.

---

## 7. FEATURES TÉCNICAS DESTACADAS (PRESUMIR)

1. **Glassmorphism** en el header (backdrop-filter blur + bordes semitransparentes).
2. **Carrusel 3D coverflow** con Swiper en la home.
3. **Spotlight effect** en `BookCard` (variables CSS `--spot-x` / `--spot-y` actualizadas con `mousemove`).
4. **Tema oscuro "terror"** con gradientes rojos oscuros y tipografías Grenze + Germania One.
5. **JWT + interceptores Axios** con traducción automática ES/EN de errores del backend.
6. **Tres niveles de guardias de ruta** (`requiresAuth`, `requiresAdmin`, `guestOnly`).
7. **Lazy-loading de todas las páginas** (mejor performance).
8. **Búsqueda en tiempo real con debounce** y dropdown predictivo.
9. **Dashboard admin completo** con CRUD de libros (multipart/form-data para imágenes), gestión de roles, baneo y force-logout.
10. **Sistema completo de préstamos** con valoración posterior.

---

## Stack

- **Framework**: Vue 3.5 (Composition API + `<script setup>`)
- **Router**: Vue Router 4.6 (WebHistory, lazy loading)
- **Estado**: Pinia 3
- **HTTP**: Axios 1.13 (interceptores, traducción de errores)
- **Carrusel**: Swiper 12 (efecto coverflow 3D)
- **Build**: Vite 7 + @vitejs/plugin-vue
- **Servidor dev**: puerto 5173
- **Estilos**: CSS vanilla con gradientes, animaciones y backdrop-filter
