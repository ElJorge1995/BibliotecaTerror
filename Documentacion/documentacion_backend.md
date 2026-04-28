# Documentación Backend — Librum Tenebris

Stack: **PHP 8 (sin framework)** + **MySQL/MariaDB (XAMPP)** + **PDO**
Servicios independientes: `ApiLoging` (auth, puerto 8000) y `libros_api` (catálogo y préstamos, puerto 8080)

---

## 1. ESTRUCTURA DEL BACKEND

El backend **no es un único `api.php`** — está dividido en **dos microservicios** que se ejecutan en puertos distintos, y cada uno está organizado por capas (controllers, services, models, middleware, utils, config).

### A) `ApiLoging/` → API de autenticación (puerto 8000)

Arquitectura **MVC ligera con router IF/ELSE** centralizado en [`index.php`](../ApiLoging/index.php):

```
ApiLoging/
├── index.php              ← punto de entrada + router
├── config/
│   ├── Database.php       ← conexión PDO singleton
│   └── Env.php            ← carga del .env
├── controllers/
│   └── AuthController.php ← lógica de los 24 endpoints /auth/*
├── middleware/
│   └── AuthMiddleware.php ← verifica JWT + estado de sesión
├── services/
│   ├── JwtService.php       ← genera/verifica tokens
│   ├── MailService.php      ← envío SMTP
│   ├── NotionService.php    ← sincronización con Notion
│   ├── RateLimiter.php      ← throttling
│   ├── SecurityLogger.php   ← logs de seguridad
│   └── GeoLocationService.php ← lookup MaxMind GeoLite2
├── models/
│   └── User.php           ← consultas a la tabla users
├── utils/
│   ├── Response.php       ← helper JSON
│   └── Security.php       ← CORS, headers, transporte HTTPS
├── data/
│   └── GeoLite2-Country.mmdb ← base offline de países
└── database/
    └── migrate_security_upgrade.sql
```

### B) `backend/libros_api/` → API del catálogo (puerto 8080)

Un único archivo [`libros_api.php`](../backend/libros_api/libros_api.php) que actúa como **router REST por parámetro `?action=`** con un `switch` gigante. Comparte la conexión PDO de [`conexion.php`](../backend/libros_api/conexion.php).

```
backend/
├── libros_api/
│   ├── libros_api.php     ← router principal por ?action=
│   ├── conexion.php       ← PDO a `librum-tenebris`
│   ├── get_title.php      ← endpoint auxiliar
│   └── uploads/covers/    ← portadas subidas por admin
├── cargalibros/           ← scripts de importación (Google Books)
│   ├── importar_libros.php
│   └── traducir_libros.php
└── database/
    └── librum-tenebris_Final.sql
```

> **Razón de la separación**: la API de auth (`ApiLoging`) es portable a cualquier proyecto del ecosistema Reglado. La API de libros es específica de Librum Tenebris.

---

## 2. BASE DE DATOS — Conexión PDO

### Conexión (singleton)

[`ApiLoging/config/Database.php`](../ApiLoging/config/Database.php):

```php
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
```

- Singleton `static $pdo`: una sola conexión por petición.
- `ERRMODE_EXCEPTION`: cualquier error PDO lanza `PDOException` y se gestiona arriba con `try/catch`.
- `charset=utf8mb4`: emojis y caracteres internacionales sin pérdida.
- Credenciales por **variables de entorno** (`DB_HOST`, `DB_NAME`, etc.) cargadas desde `.env`.

### Consultas preparadas (clave anti-SQL injection)

**Todas las queries** usan **prepared statements** con placeholders nombrados (`:id`) o posicionales (`?`). Ejemplo en [`libros_api.php:99`](../backend/libros_api/libros_api.php#L99):

```php
$stmt = $pdo->prepare(
    "SELECT l.id, l.titulo FROM libros l
     WHERE l.titulo LIKE :q OR l.autor LIKE :q LIMIT 50"
);
$stmt->execute(['q' => $searchTerm]);
```

> Los datos del usuario **nunca** se concatenan a la SQL: viajan como parámetros separados que el driver de MySQL escapa automáticamente.

> **Excepción controlada**: en algunos `LEFT JOIN` opcionales (favoritos por usuario_id), el `usuario_id` se fuerza a `(int)` y luego se pasa por `$pdo->quote()` para inyectarlo como literal seguro en el fragmento de SQL dinámico.

---

## 3. AUTENTICACIÓN

### Cómo funciona el login

Flujo en [`AuthController::login()`](../ApiLoging/controllers/AuthController.php#L86):

1. **Throttling**: `RateLimiter::checkFailureLockout` (5 fallos / 30 min por email).
2. Validación de email + password no vacíos.
3. Búsqueda del usuario por email.
4. Verificación con `password_verify($password, $user['password'])`.
5. Si falla → registra fallo y devuelve **401**.
6. Comprobaciones extra:
   - `is_email_verified == 1` → si no, **403 email not verified**.
   - `banned_at IS NULL` → si no, **403 account banned**.
   - `require_password_reset == 0` → si no, dispara reset y **403**.
7. Resetea contador de fallos.
8. **Genera nuevo `session_id` (sid)** con `User::rotateSession()` y lo guarda en `users.current_session_id`.
9. **Genera JWT** firmado HS256 con el `sid` como claim.
10. Registra el país de origen (alerta si cambió de país).
11. Devuelve `{ token, user }`.

### El JWT se genera al iniciar sesión

[`JwtService::generate()`](../ApiLoging/services/JwtService.php#L22) crea el token con:

```php
$payload = [
    'iss'   => 'librum-auth',
    'iat'   => time(),
    'exp'   => time() + 86400,    // 24h por defecto
    'sub'   => $user['id'],
    'sid'   => $sid,              // single-session enforcement
    'email' => $user['email'],
    'name'  => $user['name'],
    'role'  => $user['role'],
    // ... más datos del perfil
];
return JWT::encode($payload, $secret, 'HS256');
```

Librería: `firebase/php-jwt` (vía Composer).

### Dónde se guarda el token

- **Frontend (Vue)**: en `localStorage` bajo la clave `auth_token`.
- En **cada petición** a un endpoint protegido, axios añade el header:
  ```
  Authorization: Bearer <token>
  ```
- **No se usa cookie** para el JWT. Decisión consciente: `localStorage` es más simple y CORS funciona sin complicaciones cross-origin.

### Verificación del usuario en cada petición

[`AuthMiddleware::handle()`](../ApiLoging/middleware/AuthMiddleware.php#L11) hace **5 comprobaciones** en orden:

1. **Extrae el Bearer** del header `Authorization`.
2. **Verifica firma + `iss` + `exp`** con `JwtService::verify()`.
3. **Token revocado**: busca `hash('sha256', $token)` en la tabla `revoked_tokens` (lookup O(1) por hash).
4. **Estado de seguridad** del usuario en BD:
   - `password_changed_at > iat` → JWT obsoleto, **401**.
   - `banned_at != NULL` → cuenta baneada, **401**.
   - `sessions_invalidated_at > iat` → force-logout previo, **401**.
5. **Single-session**: `jwt.sid == users.current_session_id` (con `hash_equals` para evitar timing attacks). Si no coincide → **401 session expired**.

> Si **cualquier** comprobación falla, el middleware corta la petición antes de tocar el controlador. **Fail-closed**: ante error inesperado de BD, devuelve 401 (nunca expone el endpoint).

---

## 4. SEGURIDAD

### password_hash sí, siempre

- **Algoritmo**: `password_hash($plain, PASSWORD_BCRYPT)` en `register`, `adminRegister`, `changePassword` y `resetPassword`.
- **Verificación**: `password_verify()` (resistente a timing attacks).
- **Política mínima**: 6 caracteres (validada en backend, no solo frontend).
- **Re-hashing**: al cambiar contraseña se actualiza `password_changed_at`, lo que **invalida automáticamente todos los JWTs anteriores** vía middleware.

### Validación de datos del usuario

Todas las entradas pasan por:

| Campo       | Validación                                                  |
|-------------|-------------------------------------------------------------|
| `email`     | `filter_var($email, FILTER_VALIDATE_EMAIL)`                 |
| `username`  | regex `/^[a-zA-Z0-9._-]{3,30}$/`                            |
| `phone`     | regex española `/^(?:\+34|0034)?[6789]\d{8}$/`              |
| `password`  | longitud mínima 6, comparación con confirmación             |
| `role`      | whitelist `['user', 'pro', 'admin']`                        |
| `rating`    | rango `1-5` con cast a `(int)`                              |
| `IDs`       | cast `(int)` + chequeo `> 0`                                |
| Tokens URL  | hex de 64 chars, hash SHA-256 antes de guardar/comparar     |

Cualquier validación fallida devuelve **422 Unprocessable Entity** con mensaje específico.

### SQL injection — cómo se evita

1. **Prepared statements en el 100% de las queries** (PDO con placeholders).
2. **Cast de tipo** antes de cualquier interpolación (`(int)`, `(string)`).
3. **`$pdo->quote()`** en los pocos fragmentos dinámicos que sobreviven (joins opcionales).
4. **Whitelist** para campos como `role`, `estado` de préstamo (`['pendiente', 'activo', 'devuelto']`).

### CORS

[`Security::bootstrapCors()`](../ApiLoging/utils/Security.php#L5):

```php
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if (!in_array($origin, $allowedOrigins, true)) {
    Response::json(['error' => 'origin not allowed'], 403);
}
header('Access-Control-Allow-Origin: ' . $origin);  // refleja el origen, no usa "*"
header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
```

- **Whitelist explícita** (no `Access-Control-Allow-Origin: *`).
- En dev: orígenes localhost de Vite (5173-5177).
- En producción: configurable vía `CORS_ALLOWED_ORIGINS` en `.env`.
- **Preflight `OPTIONS`** se responde con `204` antes del router.

> En `libros_api.php` el CORS es más laxo (`*`) porque los endpoints son públicos de lectura — la lógica sensible vive en `ApiLoging`.

### Headers de seguridad

[`Security::sendSecurityHeaders()`](../ApiLoging/utils/Security.php#L23):

```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); // solo en HTTPS
```

### Cookies seguras

El proyecto **no usa cookies** para sesión (el JWT viaja por `Authorization: Bearer`). Por tanto no hay riesgo de CSRF clásico. Si en el futuro se migrase a cookies, se aplicaría:

- `HttpOnly` (no accesible desde JS).
- `Secure` (solo HTTPS).
- `SameSite=Strict` o `Lax`.

### Rate limiting

[`RateLimiter`](../ApiLoging/services/RateLimiter.php) con tabla `rate_limits` (clave SHA-256 del scope+subject):

| Endpoint                  | Límite                          |
|---------------------------|---------------------------------|
| `register`                | 10 / 15 min por IP              |
| `login` (login_lockout)   | 5 fallos / 30 min por email     |
| `resend_verification`     | 5 / 15 min por IP+email         |
| `request_password_reset`  | 5 / 15 min por IP+email         |
| `request_email_change`    | 5 / 15 min por IP+user_id       |
| `logout`                  | 30 / 60s por IP                 |
| `admin_mutate`            | 30 / 60s por admin_id           |

> **Fail-closed**: si la BD del rate limiter cae, devuelve **503** en vez de dejar pasar la petición → un atacante no puede deshabilitar la protección tirando la tabla.

### JWT secret

[`Security::ensureStrongJwtSecret()`](../ApiLoging/utils/Security.php#L50):

- En **producción**: si `JWT_SECRET` está vacío, mide < 32 chars o vale `change-this-secret`, la API **se niega a arrancar** (500).
- En **dev** (`APP_ENV=local`): se tolera y se logea, para no romper la DX.

---

## 5. FUNCIONALIDADES DEL BACKEND

### A) `ApiLoging` (auth) — 24 endpoints

**Usuarios**
- Registro con verificación por email (token de 64 hex, expira en 24h).
- Login con JWT.
- Verificación de email + reenvío.
- Solicitud y reset de contraseña por email.
- Cambio de contraseña (exige password actual).
- Cambio de email (exige password actual + confirmación al nuevo email).
- Update de username, nombre/apellidos, teléfono.
- Borrado de cuenta (con check de préstamos activos).
- Logout (revoca JWT + limpia sesión).
- `/auth/me` para hidratar el frontend.

**Admin**
- Listar todos los usuarios.
- Actualizar rol (`user`/`pro`/`admin`).
- Crear cuenta manualmente (con DNI).
- Borrar cuenta de otro usuario.
- **Banear/desbanear** (`/auth/admin/set-ban`).
- **Forzar logout** de un usuario (`/auth/admin/force-logout`).

**Seguridad**
- Confirmación de login desde nuevo país (`/auth/confirm-login-location`).

### B) `libros_api` — 18 acciones (router por `?action=`)

**Libros (catálogo)**
- `recientes` — últimos N libros añadidos.
- `recomendaciones` — libros con rating ≥ 4.
- `buscar` — búsqueda por título / título_es / autor.
- `obtener` — detalle de un libro por id.
- `todos` — listado completo con favoritos cruzados.
- `crear` — alta de libro con upload de portada (admin).
- `editar_libro` — actualización de libro (admin).

**Préstamos**
- `prestar` — usuario reserva un libro (límite 2 simultáneos, descuenta stock atómicamente con transacción).
- `actualizar_prestamo` — admin cambia estado (`pendiente` → `activo` → `devuelto`), con lógica de stock y fecha de devolución a 14 días.
- `mis_prestamos` — historial del usuario.
- `todos_prestamos` — vista admin global.
- `count_active_loans` — usado por `ApiLoging` para validar borrado de cuentas.
- `admin_crear_prestamo` — admin crea préstamo manual buscando usuario por DNI (cross-DB query a `bibliouser`).

**Valoraciones (ratings)**
- `valorar_prestamo` — rating 1-5 sobre un préstamo devuelto. Recalcula automáticamente la **media** del libro con `AVG(rating)` de todos los préstamos.

**Favoritos**
- `toggle_favorito`, `check_favorito`, `mis_favoritos`.

---

## 6. API GOOGLE BOOKS

### Cómo se usa

Script único: [`backend/cargalibros/importar_libros.php`](../backend/cargalibros/importar_libros.php).

```php
$url = "https://www.googleapis.com/books/v1/volumes?q=subject:horror"
     . "&langRestrict=en&maxResults=40&startIndex=$startIndex";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$respuesta = curl_exec($ch);
$datos = json_decode($respuesta, true);
```

- Llamada con **cURL** (no `file_get_contents` — más control de errores y SSL).
- Itera **5 páginas × 40 libros = 200 libros** por ejecución.
- Filtra `subject:horror` y `langRestrict=en` (catálogo temático de terror).
- `sleep(1)` entre páginas para no saturar la API y evitar rate limit de Google.

### Datos guardados directamente en la BD

Sí, importación **directa** a la tabla `libros` con prepared statement:

```php
$stmt = $pdo->prepare("
    INSERT INTO libros (google_id, titulo, autor, descripcion, portada)
    VALUES (?, ?, ?, ?, ?)
");
```

**Filtros aplicados antes de insertar**:
- Sólo libros con autor + descripción + portada (los incompletos se descartan).
- `strip_tags()` en la descripción (Google a veces trae HTML).
- Duplicados se ignoran silenciosamente (`PDOException` capturada, normalmente clave `google_id` única).

> Se importa **una sola vez** al inicializar el catálogo. Después los libros se gestionan desde el panel admin (alta manual con upload de portada en `?action=crear`). No hay sincronización en vivo con Google Books.

---

## 7. MEJORAS DE SEGURIDAD AVANZADAS

### Geolocalización — qué pasa si cambia país

Servicio: [`GeoLocationService`](../ApiLoging/services/GeoLocationService.php) usando **MaxMind GeoLite2-Country** offline (`.mmdb`, sin red, sin terceros).

Flujo en [`AuthController::handleLoginLocation()`](../ApiLoging/controllers/AuthController.php#L1007):

1. En cada login se hace lookup de la IP → `country_code`.
2. Se compara con el último `country_code` de un login `confirmed` o `neutral`.
3. **Si coincide**: registra como `neutral` y sigue.
4. **Si NO coincide**:
   - Inserta en `login_locations` con `status = 'pending'` + `token_hash` + `token_expires_at` (7 días).
   - Envía email al usuario con dos botones:
     - **"Sí, he sido yo"** → marca `status='confirmed'`, login normal.
     - **"No, no he sido yo"** → `status='rejected'`, **mata la sesión** (`current_session_id = NULL`) y activa `require_password_reset = 1`.
5. Endpoint público `POST /auth/confirm-login-location` procesa la decisión.
6. **Degradación grácil**: si el `.mmdb` falta o la IP es privada (dev/localhost), no se dispara alerta — el login nunca se rompe por esto.

### Cómo se evitan múltiples sesiones (single-session enforcement)

Implementado en abril 2026, ver [`CAMBIOS_APILOGING_2026-04-23.md`](../CAMBIOS_APILOGING_2026-04-23.md).

1. Cada login genera un `sid` aleatorio de 64 hex (`User::rotateSession()`).
2. Se guarda en `users.current_session_id` y se incluye como claim en el JWT.
3. El middleware compara `jwt.sid == users.current_session_id` con `hash_equals()`.
4. **Política**: la sesión más reciente gana. Si entras desde otro dispositivo, el anterior recibe **401 session expired** en su próxima petición.
5. El interceptor del frontend detecta el 401, borra el `localStorage` y redirige a login.

> Esto cubre robo de credenciales: aunque un atacante consiga la password, en cuanto el dueño se logue, **expulsa al atacante automáticamente**. Y al revés — el dueño siempre puede recuperar su cuenta porque los logins exitosos no consumen cuota de rate limit.

### Cómo funciona lo de pedir contraseña al admin

Helper [`requireAdminForUserMutation()`](../ApiLoging/controllers/AuthController.php#L928):

Antes de ejecutar `set-ban` o `force-logout`, el admin debe enviar **su propia contraseña** en el body. El helper:

1. Verifica que el JWT pertenece a un admin (`requireAdmin`).
2. Aplica rate limit (`admin_mutate`: 30/60s por admin).
3. Verifica que `user_id` (target) es válido y **distinto del propio admin** (`cannot target self`).
4. Hace `password_verify($currentPassword, $admin['password'])`.
5. Si falla → registra `admin_mutation_bad_password` y devuelve **401**.

> **Razón**: defensa contra JWT robado. Aunque un atacante robe el token de un admin, no puede banear ni cerrar sesiones a nadie sin saber la contraseña real.

### Cómo funciona el baneo

Endpoint `POST /auth/admin/set-ban`:

1. Pasa por `requireAdminForUserMutation` (admin + password).
2. Lee `banned: true|false` del body.
3. Si `true`:
   - `User::banUser($userId, $adminId)` → setea `banned_at = NOW()`, `banned_by = $adminId`, `sessions_invalidated_at = NOW()`.
   - Resultado: el usuario **no puede loguear** (403 `account banned`) **y** todos sus JWTs vivos quedan inválidos (middleware detecta `banned_at != NULL`).
4. Si `false`:
   - `User::unbanUser($userId)` → setea `banned_at = NULL`. El usuario puede loguear de nuevo.
5. Se registra en `security_logs`.

> Combinación letal: ban + force-logout invalidan **todo** lo activo del usuario en menos de 1 segundo.

---

## 8. ERRORES / PROBLEMAS

### Lo que pasó con CORS

**Problema**: al principio el `Access-Control-Allow-Origin: *` parecía cómodo, pero rompía en cuanto el frontend mandaba `Authorization: Bearer ...` (los navegadores **no permiten** `*` con credenciales / headers custom complejos).

**Solución** ([`Security::bootstrapCors()`](../ApiLoging/utils/Security.php#L5)):

1. **Whitelist explícita** de orígenes en `.env` (`CORS_ALLOWED_ORIGINS`).
2. Se **refleja el `Origin`** del request en `Access-Control-Allow-Origin` (no `*`).
3. Se añade `Vary: Origin` para que las CDN cacheen por origen.
4. Si el origen no está autorizado → **403 origin not allowed** antes de tocar el router.
5. Preflight `OPTIONS` responde **204** y termina (sin pasar por la lógica de auth).

Durante el desarrollo se añadieron los puertos 5173-5177 a la whitelist para soportar varios proyectos Vite corriendo en paralelo.

### Lo difícil del backend

1. **Rotación de sesión y cómo afecta a flujos paralelos**: si el admin baneaba un usuario mientras este editaba su perfil, el `update-name` devolvía 401. Tuvimos que distinguir dos helpers:
   - `respondWithFreshSession`: conserva el `sid` actual (updates de perfil).
   - `respondWithRotatedSession`: rota el `sid` (cambio de password / reset).

2. **Stock de libros con concurrencia**: dos usuarios prestando el último ejemplar a la vez. Solución: `UPDATE libros SET stock = stock - 1 WHERE id = ? AND stock > 0` y verificar `rowCount() == 0` para detectar la condición de carrera. Todo dentro de una **transacción PDO** (`beginTransaction` / `commit` / `rollBack`).

3. **Bug del rate limiter (abril 2026)**: el contador unificado de login (éxitos+fallos) permitía un ataque ping-pong: el atacante y el dueño se bloqueaban entre sí, y el último en quedar fuera era el dueño (porque single-session daba prioridad a la sesión más reciente). **Fix**: `login_lockout` solo cuenta fallos. Los logins exitosos no consumen cuota → el dueño siempre puede reclamar.

4. **JWT y verificación de email**: al verificar un email pendiente teníamos que **crear el usuario** desde la tabla de pre-registros, **no marcar uno existente**. Esto evita que un atacante registre con el email de otro y le bloquee.

5. **Notion como espejo operativo**: la sincronización con Notion (CRUD de usuarios, libros y préstamos) está envuelta en `try/catch` que solo loguea — si Notion cae, el flujo principal (BD local) **nunca se bloquea**.

6. **Tokens de email seguros**: nunca guardamos el token plano. Generamos `bin2hex(random_bytes(32))`, mandamos el plano por email, guardamos el `hash('sha256', ...)`. Si se filtra la BD, los tokens son inservibles.

7. **Cross-DB query**: el endpoint `admin_crear_prestamo` necesita buscar usuario por DNI en la BD `bibliouser` (de `ApiLoging`) desde `librum-tenebris` (de `libros_api`). Se hace abriendo una **conexión PDO temporal** dedicada, sin mezclar con la principal.
