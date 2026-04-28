# Documentación Base de Datos — Librum Tenebris

Motor: **MariaDB / MySQL** (XAMPP en local, Hostinger en producción)
Engine: **InnoDB** · Charset: **utf8mb4** · Collation: **utf8mb4_unicode_ci**

> **Particularidad clave**: el proyecto usa **dos bases de datos separadas**, no una. La razón está en la arquitectura: `ApiLoging` es un servicio de auth portable a cualquier proyecto del ecosistema Reglado, y `libros_api` es específico de Librum Tenebris. Separarlas permite reutilizar el sistema de auth sin arrastrar las tablas de catálogo.

---

## 1. TABLAS PRINCIPALES

### BD `bibliouser` — autenticación (9 tablas)

| Tabla                        | Propósito                                                         |
|------------------------------|-------------------------------------------------------------------|
| `users`                      | Cuentas de usuario (alma del sistema).                            |
| `pending_registrations`      | Registros en cola hasta que el usuario verifica el email.         |
| `email_verification_tokens`  | Tokens de verificación de email para cuentas ya creadas.          |
| `email_change_tokens`        | Tokens para confirmar cambio de email (al nuevo email).           |
| `password_reset_tokens`      | Tokens de recuperación de contraseña.                             |
| `revoked_tokens`             | JWTs revocados (logout, force-logout) — blacklist.                |
| `rate_limits`                | Contadores de throttling (login, register, reset, etc.).          |
| `security_events`            | Auditoría de eventos sensibles (logs de seguridad).               |
| `login_locations`            | Histórico de IP/país por login y alertas geo.                     |

### BD `librum-tenebris` — catálogo y operaciones (3 tablas)

| Tabla       | Propósito                                            |
|-------------|------------------------------------------------------|
| `libros`    | Catálogo de libros.                                  |
| `prestamos` | Préstamos (con valoración integrada del libro).      |
| `favoritos` | Marcados como favoritos por usuario.                 |

> **Total: 12 tablas en 2 bases de datos.**

---

## 2. TABLA `users`

Es la tabla central de toda la autenticación. Tiene **19 columnas** porque incorpora 5 features de seguridad encima del esquema básico (verificado contra `bibliouser.users` en XAMPP).

### Campos básicos del perfil

| Campo                | Tipo            | Notas                                                  |
|----------------------|-----------------|--------------------------------------------------------|
| `id`                 | INT AUTO_INCR   | PK.                                                    |
| `username`           | VARCHAR(100)    | UNIQUE, opcional.                                      |
| `email`              | VARCHAR(255)    | UNIQUE, obligatorio.                                   |
| `password`           | VARCHAR(255)    | **Hash BCRYPT** (60 chars, dejamos margen).            |
| `name`               | VARCHAR(255)    | Nombre completo (concatenación de first+last).         |
| `first_name`         | VARCHAR(100)    | Nombre.                                                |
| `last_name`          | VARCHAR(100)    | Apellidos.                                             |
| `dni`                | VARCHAR(20)     | DNI obligatorio (necesario para préstamos).            |
| `phone`              | VARCHAR(30)     | Móvil, opcional.                                       |
| `role`               | VARCHAR(50)     | `'user'` por defecto. Valores: `user`, `pro`, `admin`. |
| `created_at`         | TIMESTAMP       | Fecha de alta.                                         |

### Campos de seguridad / sesión

| Campo                       | Tipo       | Función                                                                 |
|-----------------------------|------------|-------------------------------------------------------------------------|
| `is_email_verified`         | TINYINT(1) | 0/1. El login bloquea con 403 si está a 0.                              |
| `email_verified_at`         | DATETIME   | Cuando se confirmó.                                                     |
| `password_changed_at`       | DATETIME   | Permite invalidar JWTs anteriores al cambio (`exp > iat` ya no basta).  |
| `current_session_id`        | CHAR(64)   | `sid` de la sesión activa. Una sola sesión por cuenta (kick-old).       |
| `sessions_invalidated_at`   | DATETIME   | Marca temporal: cualquier JWT con `iat < this` queda inválido.          |
| `require_password_reset`    | TINYINT(1) | Forzar reset al siguiente login (geo-alert "no he sido yo").            |
| `banned_at`                 | DATETIME   | NULL = activo. Si tiene fecha → cuenta baneada, no puede loguear.       |
| `banned_by`                 | INT        | FK auto-referencial al admin que ejecutó el ban (`ON DELETE SET NULL`). |

### Roles

Sí, hay roles. El sistema maneja **3 niveles**, validados con whitelist en backend:

```php
if (!in_array($role, ['user', 'pro', 'admin'])) {
    Response::json(['error' => 'invalid role'], 422);
}
```

- **`user`** → cliente normal. Por defecto.
- **`pro`** → usuario "premium" (placeholder, mismas capacidades que user de momento).
- **`admin`** → acceso al panel `/admin`, gestión de usuarios, libros y préstamos.

### Contraseña hasheada

Sí, **siempre con BCRYPT**:

```php
$hash = password_hash($password, PASSWORD_BCRYPT);  // al registrar
password_verify($plain, $user['password']);          // al loguear
```

Nunca se guarda la contraseña en plano. BCRYPT incluye **salt automático**, por lo que dos usuarios con la misma contraseña tienen hashes distintos.

### Índices y constraints

```sql
PRIMARY KEY (id)
UNIQUE KEY (username)
UNIQUE KEY (email)
KEY idx_users_banned_at (banned_at)
KEY idx_users_current_session_id (current_session_id)
FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL
```

---

## 3. TOKENS

### Tipos de tokens en el sistema

| Token                       | Tabla                       | Propósito                                              |
|-----------------------------|-----------------------------|--------------------------------------------------------|
| **JWT de sesión (login)**   | *no se guarda* (stateless)  | Autenticar peticiones. Se valida con firma HS256.      |
| **Verificación de email**   | `email_verification_tokens` | Activar cuenta tras registrarse.                       |
| **Pre-registro**            | `pending_registrations`     | Mismo concepto que arriba pero ANTES de crear el user. |
| **Cambio de email**         | `email_change_tokens`       | Confirmar nuevo email (link al nuevo correo).          |
| **Recuperación de password**| `password_reset_tokens`     | Resetear contraseña olvidada o forzada por geo-alert.  |
| **Alerta de login geo**     | `login_locations`           | Confirmar/rechazar login desde nuevo país.             |
| **JWTs revocados**          | `revoked_tokens`            | Blacklist de tokens cerrados con logout.               |

### Cómo se guardan (clave de seguridad)

**Nunca se guarda el token plano**. Patrón en todos los flujos:

```php
$plainToken = bin2hex(random_bytes(32));     // 64 chars hex, 256 bits de entropía
$tokenHash  = hash('sha256', $plainToken);   // lo que se guarda en BD
$expiresAt  = date('Y-m-d H:i:s', time() + $ttl);
```

- Por **email** viaja el `$plainToken`.
- En **BD** se guarda solo `$tokenHash`.
- Al recibir el token de vuelta, se hashea otra vez y se compara con el guardado.

> **Razón**: si un atacante vuelca la BD, los tokens son inservibles. Y un atacante con acceso al email no puede hacer ingeniería inversa del hash.

### Caducidad

| Token                     | TTL por defecto | Variable de entorno              |
|---------------------------|-----------------|----------------------------------|
| Verificación de email     | 24h             | `EMAIL_VERIFICATION_TTL_SECONDS` |
| Cambio de email           | 24h             | (mismo TTL que verificación)     |
| Reset de contraseña       | 24h             | (mismo TTL)                      |
| Alerta de login geo       | **7 días**      | hardcoded (`7 * 24 * 3600`)      |
| JWT de sesión             | 24h             | `JWT_TTL_SECONDS`                |

> Sí, **7 días** corresponde a las alertas geo. La razón es que el usuario puede tardar en revisar el email — no queremos invalidarlo en 24h porque entonces el atacante "gana" si el dueño no abre el correo el mismo día.

### ¿Se eliminan o se marcan como expirados?

**Las dos cosas, según el caso**:

- **Marca de uso (`used_at`)**: cuando un token se consume correctamente (verificación, reset...), se setea `used_at = NOW()` en la fila. Permite auditoría.
- **Expiración por timestamp**: se comprueba `expires_at > NOW()` al validar. Si está caducado se rechaza, pero la fila **se queda en BD** (útil para investigación forense).
- **Tokens en `pending_registrations` con conflicto**: si el email/registro queda obsoleto, se ejecuta `DELETE` (`User::deletePendingRegistration`). En cambio los `email_verification_tokens` ya consumidos solo se marcan, no se borran.
- **Limpieza periódica**: las filas con `expires_at` muy antiguo se podrían purgar con un cron, pero no es crítico (son ligeras).

---

## 4. CONFIRMACIÓN DE EMAIL

### Sí, hay una tabla aparte (de hecho dos)

**Diseño con DOS tablas**, distintas según el momento del flujo:

#### A) `pending_registrations` (antes de existir el usuario)

Cuando alguien hace `POST /auth/register`, NO se crea inmediatamente en `users`. Se inserta una fila en `pending_registrations` con todos los datos del formulario + el `token_hash`:

```sql
CREATE TABLE pending_registrations (
  id INT AUTO_INCREMENT PK,
  username, email, password_hash, name, first_name, last_name, dni, phone,
  token_hash CHAR(64) UNIQUE,
  expires_at DATETIME,
  used_at DATETIME NULL,
  created_at TIMESTAMP
);
```

> **Razón del diseño**: si el usuario abandona la verificación, el `email` no queda "consumido" en `users` (donde es UNIQUE). Y un atacante no puede "reservar" emails ajenos para bloquear que su dueño se registre, porque el flujo de verificación lo libera.

#### B) `email_verification_tokens` (usuarios ya existentes)

Para reenvíos de verificación cuando ya hay un user creado pero no verificado.

```sql
CREATE TABLE email_verification_tokens (
  id, user_id (FK → users ON DELETE CASCADE),
  token_hash CHAR(64) UNIQUE,
  expires_at, used_at, created_at
);
```

### Qué pasa al hacer clic en el enlace

Endpoint: `GET /auth/verify-email?token=<plain>`

Lógica en [`AuthController::verifyEmail()`](../ApiLoging/controllers/AuthController.php#L156):

1. Hashea el token recibido: `$tokenHash = hash('sha256', $token)`.
2. Busca primero en `email_verification_tokens` (caso reenvío).
3. **Si no encuentra**, busca en `pending_registrations` (caso primer registro).
4. Si encuentra `pending_registrations`:
   - **Crea la fila en `users`** con todos los datos guardados, `is_email_verified = 1`, `email_verified_at = NOW()`.
   - Sincroniza la cuenta con Notion (best-effort).
   - **No** se borra `pending_registrations` automáticamente (se marca consumido por exclusión: la próxima búsqueda por email ya devolverá un user existente).
5. Si encuentra `email_verification_tokens`:
   - **Actualiza** `users.is_email_verified = 1`, `email_verified_at = NOW()`.
6. Genera un nuevo `sid`, emite un **JWT fresco** y redirige al frontend con `?token=<jwt>`.

> Resumen: **se mueve de una tabla a otra** en el caso de pre-registro (de `pending_registrations` → `users`). Para usuarios ya creados, simplemente se actualiza la columna en `users`.

---

## 5. RECUPERACIÓN DE CONTRASEÑA

Ya está implementada (no pendiente). Tabla dedicada: **`password_reset_tokens`**.

```sql
CREATE TABLE password_reset_tokens (
  id INT AUTO_INCREMENT PK,
  user_id INT,                   -- FK → users(id) ON DELETE CASCADE
  token_hash CHAR(64) UNIQUE,
  expires_at DATETIME,
  used_at DATETIME NULL,
  created_at TIMESTAMP,
  INDEX (user_id),
  INDEX (expires_at)
);
```

### Cómo funciona

**Flujo en dos pasos** (endpoints `request-password-reset` + `reset-password`):

#### Paso 1 — Solicitar (`POST /auth/request-password-reset`)
1. Rate limit: 5 / 15 min por IP+email.
2. Valida formato de email.
3. Busca al user. **Si no existe**, devuelve siempre el mismo mensaje genérico (`"if the account exists, a recovery email was sent"`) → no revela qué emails están registrados (defensa contra enumeración).
4. Genera token: `bin2hex(random_bytes(32))` → 64 chars hex.
5. Inserta `password_reset_tokens` con `hash('sha256', $token)` y expiración de 24h.
6. Envía email con la URL: `http://localhost:5173/restablecer-contrasena?token=<plain>`.

#### Paso 2 — Aplicar (`POST /auth/reset-password`)
1. Lee `token`, `new_password`, `new_password_confirmation`.
2. Valida coincidencia + longitud mínima 6.
3. Hashea el token recibido y busca en `password_reset_tokens`.
4. Verifica que `expires_at > NOW()` y `used_at IS NULL`.
5. **Comprueba que la nueva contraseña sea distinta de la actual** (`password_verify` contra el hash actual).
6. Actualiza:
   - `users.password = password_hash($new, PASSWORD_BCRYPT)`.
   - `users.password_changed_at = NOW()` → invalida JWTs anteriores.
   - `password_reset_tokens.used_at = NOW()`.
   - `users.require_password_reset = 0` (limpia el flag si venía de geo-alert).
7. **Rota la sesión**: nuevo `sid`, nuevo JWT → el usuario queda logueado en el dispositivo donde reseteó.

### Caducidad y reuso

- **TTL: 24 horas** (`EMAIL_VERIFICATION_TTL_SECONDS`, compartido).
- **Un solo uso**: tras consumir, `used_at != NULL` impide volver a usarlo.
- **No se borran** las filas (auditoría); el cron podría purgar las muy antiguas.

---

## 6. LIBROS

Tabla **`libros`** en BD `librum-tenebris`:

```sql
CREATE TABLE libros (
  id INT AUTO_INCREMENT PK,
  google_id VARCHAR(100) UNIQUE,    -- ID de Google Books (NULL si añadido a mano)
  titulo VARCHAR(255) NOT NULL,
  titulo_es VARCHAR(255),           -- traducción al español
  autor VARCHAR(255) NOT NULL,
  stock INT DEFAULT 3,              -- inventario
  descripcion TEXT,                 -- en inglés (Google)
  descripcion_es TEXT,              -- traducción
  portada VARCHAR(500),             -- URL (Google) o ruta local /uploads/covers/
  categoria VARCHAR(100),
  rating DECIMAL(3,1)               -- media (recalculada con AVG de prestamos.rating)
);
```

### Qué se guarda de cada libro

- **Identidad**: `id` interno, `google_id` (cuando viene de Google Books).
- **Contenido**: `titulo`, `titulo_es`, `autor`, `descripcion`, `descripcion_es`.
- **Visual**: `portada` (URL).
- **Operativa**: `stock` (entero, descontado en cada préstamo).
- **Clasificación**: `categoria`.
- **Reputación**: `rating` (1.0 a 5.0, una decimal).

### ¿Vienen de la API y se guardan en vuestra BD?

Sí. **Importación masiva** vía script [`backend/cargalibros/importar_libros.php`](../backend/cargalibros/importar_libros.php):

1. Llama a `https://www.googleapis.com/books/v1/volumes?q=subject:horror`.
2. Itera 5 páginas × 40 libros = hasta 200 libros.
3. Filtra los que tengan autor + descripción + portada.
4. **Inserta cada libro en `libros`** con prepared statement.
5. `google_id` UNIQUE evita duplicados (PDOException ignorada silenciosamente).

> Una vez importados, **viven 100% en nuestra BD**. La API de Google ya no se consulta en tiempo de ejecución (latencia baja, sin rate limits, sin dependencia externa).

Adicionalmente, los admins pueden:
- Subir libros a mano (`?action=crear`) con upload de portada propia (`uploads/covers/`).
- Editar (`?action=editar_libro`).
- No hay borrado (decisión: si borras un libro, los préstamos históricos quedan huérfanos).

---

## 7. PRÉSTAMOS

Tabla **`prestamos`** (singular en singular: nombre técnico):

```sql
CREATE TABLE prestamos (
  id INT AUTO_INCREMENT PK,
  usuario_id INT NOT NULL,                  -- → users.id (cross-DB)
  nombre_usuario VARCHAR(255),              -- denormalizado para histórico
  libro_id INT NOT NULL,                    -- → libros.id
  fecha_prestamo DATETIME DEFAULT NOW(),    -- inicio (NULL hasta activación)
  fecha_devolucion DATETIME,                -- fecha límite (inicio + 14 días)
  estado ENUM('pendiente','activo','devuelto') DEFAULT 'activo',
  rating INT,                               -- valoración del libro tras devolución (1-5)
  fecha_entregado DATETIME                  -- fecha real de devolución
);
```

### Cómo funciona el flujo

#### A) Solicitud (`?action=prestar` desde el usuario)

1. **Validación**: usuario no tiene ya 2 préstamos activos/pendientes (límite hard).
2. **Stock**: se comprueba `stock > 0` y se descuenta atómicamente con `UPDATE ... WHERE stock > 0` dentro de una **transacción**.
3. **Inserta** la fila con `estado = 'pendiente'`, `fecha_devolucion = NULL`.
4. Sincroniza con Notion (best-effort).

#### B) Activación (`?action=actualizar_prestamo`, admin: pendiente → activo)

1. Setea `estado = 'activo'`, `fecha_prestamo = NOW()`, `fecha_devolucion = NOW() + 14 días`.

#### C) Devolución (`?action=actualizar_prestamo`, admin: → devuelto)

1. Setea `estado = 'devuelto'`, `fecha_entregado = NOW()`.
2. **Devuelve la unidad al stock** (`UPDATE libros SET stock = stock + 1`).

#### D) Préstamo manual del admin (`?action=admin_crear_prestamo`)

- Busca usuario por DNI **en la otra BD** (`bibliouser.users`) con conexión PDO temporal.
- Crea el préstamo en estado `activo` directamente, con plazo configurable.

### Estados

| Estado       | Significado                                       |
|--------------|---------------------------------------------------|
| `pendiente`  | Reservado por el usuario, esperando que el admin lo entregue. |
| `activo`     | En manos del usuario, con fecha de devolución.    |
| `devuelto`   | Cerrado, ya devuelto.                             |

> El ENUM en MySQL valida en BD que no se introduzcan estados inventados.

### Por qué `nombre_usuario` está duplicado

Es **denormalización deliberada**: el `users.name` puede cambiar (cambio de apellidos, etc.), pero queremos que el histórico del préstamo refleje cómo se llamaba el usuario en el momento del préstamo. Es información histórica.

---

## 8. VALORACIONES

**Decisión arquitectónica**: las valoraciones **NO tienen tabla propia**. Van **integradas en `prestamos`** mediante la columna `rating INT`.

### Por qué

- Una valoración solo tiene sentido si has leído el libro.
- Has leído el libro porque has tenido un préstamo devuelto.
- → cada préstamo puede tener **una sola valoración** del libro que disfrutó (1-5 estrellas).
- → si vuelves a leerlo, generas otro préstamo y valoras otra vez.

### Cómo se introduce

Endpoint `POST /libros_api.php?action=valorar_prestamo`:

1. Body: `{ prestamo_id, rating }` (1-5).
2. Update: `prestamos.rating = $rating WHERE id = ?`.
3. **Recálculo automático de `libros.rating`** con la media de todas las valoraciones de ese libro:

```sql
UPDATE libros
SET rating = (
    SELECT COALESCE(AVG(rating), 0)
    FROM prestamos
    WHERE libro_id = ? AND rating IS NOT NULL
)
WHERE id = ?;
```

> El campo `libros.rating DECIMAL(3,1)` queda como **agregado materializado** que se actualiza en cada valoración nueva. Permite ordenar el catálogo por puntuación sin hacer JOIN cada vez.

### Sí, de 1 a 5 estrellas

Validación en backend:

```php
if ($rating < 1 || $rating > 5) {
    Response::json(['error' => 'Valoración inválida.'], 400);
}
```

---

## 9. RELACIONES

Sí, las relaciones que dijiste son **exactamente** así, pero conviene matizar:

### Diagrama lógico

```
                   bibliouser DB                |    librum-tenebris DB
                                                |
   ┌──────────────┐                             |
   │    users     │                             |
   └──────┬───────┘                             |
          │ (id)                                |
          ├────────────────────────────────────────────────────┐
          │                                                    │
          ▼ usuario_id (cross-DB)                              ▼
   ┌────────────────────┐ libro_id ┌──────────┐         ┌──────────────┐
   │     prestamos      │─────────▶│  libros  │◀────────│   favoritos  │
   │  (con rating col)  │          └──────────┘         └──────────────┘
   └────────────────────┘
```

### Relaciones reales

- **`users` 1:N `prestamos`** vía `prestamos.usuario_id`.
- **`libros` 1:N `prestamos`** vía `prestamos.libro_id`.
- **`users` 1:N `favoritos`** vía `favoritos.usuario_id`.
- **`libros` 1:N `favoritos`** vía `favoritos.libro_id`.
- **Valoración**: cada `prestamos` tiene **0 o 1** rating → un libro acumula N ratings (uno por cada préstamo que lo valoró).

### Equivalencia con tu pregunta

- ✔ **`usuario → préstamos → libros`** : SÍ. Es el JOIN principal del sistema (`mis_prestamos`, `todos_prestamos`).
- ✔ **`usuario → valoraciones → libros`** : SÍ, pero el "valoraciones" no es una tabla — es el `prestamos.rating`. La consulta sigue siendo: `users → prestamos.rating → libros`.

### Por qué no hay FK físicas entre `prestamos.usuario_id` y `users.id`

Porque están **en bases de datos distintas**. MySQL **no permite FK cross-database**, así que la integridad referencial entre préstamos y usuarios se garantiza **a nivel de aplicación**:

- Antes de borrar un usuario (`POST /auth/admin/delete-user` o `/auth/delete-me`), `ApiLoging` hace una llamada HTTP a `libros_api?action=count_active_loans&usuario_id=X`. Si tiene préstamos activos → 403, no se borra.
- Lo mismo en `admin_crear_prestamo`: el admin introduce el DNI, el backend valida que existe en `bibliouser.users` antes de insertar el préstamo.

---

## 10. COSAS PRO

### Claves foráneas

Sí, **dentro de cada BD** las FK están bien definidas. Cross-DB se gestiona en aplicación (ver punto 9).

#### En `bibliouser`

```sql
-- Auto-referencial: quién baneó a quién
users.banned_by → users.id ON DELETE SET NULL

-- Tokens en cascada: si borras un user, sus tokens se van solos
email_verification_tokens.user_id → users.id ON DELETE CASCADE
email_change_tokens.user_id      → users.id ON DELETE CASCADE
password_reset_tokens.user_id    → users.id ON DELETE CASCADE
login_locations.user_id          → users.id ON DELETE CASCADE

-- Auditoría preservada: SET NULL para no perder el evento aunque borres el user
security_events.user_id          → users.id ON DELETE SET NULL
```

> **Diseño deliberado**:
> - **CASCADE** en tokens: la información personal del usuario incluye sus tokens, deben morir con él (RGPD).
> - **SET NULL** en `security_events`: queremos saber que pasó algo aunque ya no exista el user (forense + auditoría legal).

#### En `librum-tenebris`

`prestamos` y `favoritos` **NO** tienen FK a `users` (cross-DB). Entre ellas tampoco son necesarias:

- Se podría añadir `prestamos.libro_id → libros.id`, pero se decidió no hacerlo para permitir borrado de libros sin romper histórico de préstamos. Es un trade-off consciente.

### Índices

#### Por unicidad
- `users.email` UNIQUE
- `users.username` UNIQUE
- `users.dni` (no UNIQUE en el SQL final, pero validado en aplicación)
- `libros.google_id` UNIQUE → bloquea duplicados de Google Books.
- `favoritos.(usuario_id, libro_id)` UNIQUE → no puedes marcar dos veces el mismo libro.
- Todos los `token_hash` UNIQUE → seguridad + lookup directo.

#### Para acelerar consultas frecuentes

| Tabla              | Índice                                           | Para qué                                              |
|--------------------|--------------------------------------------------|-------------------------------------------------------|
| `users`            | `idx_users_banned_at`                            | Listar baneados.                                      |
| `users`            | `idx_users_current_session_id`                   | Validación O(1) en cada middleware.                   |
| `pending_registrations` | `idx_pending_registrations_expiry`          | Cron de purga.                                        |
| `email_verification_tokens` | `idx_email_verification_user`           | Buscar tokens por user_id.                            |
| `email_verification_tokens` | `idx_email_verification_expiry`         | Limpieza por TTL.                                     |
| `password_reset_tokens` | `idx_password_reset_user` + `_expiry`        | Idem.                                                 |
| `revoked_tokens`   | `idx_revoked_token_hash`                         | Lookup en cada petición autenticada (middleware).     |
| `revoked_tokens`   | `idx_revoked_token_prefix` (prefix 255)          | Compatibilidad con tokens viejos guardados en plano.  |
| `rate_limits`      | UNIQUE `key_hash` + `idx_rate_limits_scope_updated` | Throttling rápido + purga de ventanas.             |
| `security_events`  | `idx_security_events_type_created`               | Filtrado del log por tipo de evento.                  |
| `security_events`  | `idx_security_events_user_created`               | Auditoría por usuario.                                |
| `login_locations`  | `idx_login_locations_user_created`               | Buscar último login por país.                         |
| `login_locations`  | `idx_login_locations_token_hash`                 | Confirmar/rechazar alerta vía link.                   |

### Normalización

El esquema cumple **3FN** con dos excepciones documentadas:

1. **`users.name`** existe junto a `first_name + last_name` → técnicamente redundante, pero se mantiene como columna calculada manualmente para evitar `CONCAT` en cada SELECT del `/auth/me`.
2. **`prestamos.nombre_usuario`** denormalizado → histórico (ver punto 7).
3. **`libros.rating`** materializado → agregado pre-calculado para evitar `AVG` en cada listado (ver punto 8).

Las tres son **decisiones conscientes** de optimización, no errores de diseño.

### Charset y collation

- **utf8mb4** en toda la BD → soporta emojis y caracteres CJK (chino, japonés, coreano) sin pérdida.
- **utf8mb4_unicode_ci** en `bibliouser` → comparaciones case-insensitive correctas para búsquedas (`WHERE email = 'X'` insensible a mayúsculas y a acentos).
- **utf8mb4_spanish_ci** en `prestamos` y `favoritos` → reglas de orden españolas (la 'ñ' va entre la 'n' y la 'o').

### Engine

**InnoDB en todas las tablas**:
- Soporta **transacciones** (necesario para préstamos atómicos: descontar stock + crear préstamo + commit).
- Soporta **foreign keys**.
- Row-level locking (no table locks como MyISAM) → mejor concurrencia.

### Transacciones reales en uso

Ejemplos en [`libros_api.php`](../backend/libros_api/libros_api.php):

```php
$pdo->beginTransaction();
try {
    // 1. Comprobar préstamos activos del usuario
    // 2. Comprobar stock
    // 3. UPDATE libros SET stock = stock - 1 WHERE id = ? AND stock > 0
    // 4. INSERT INTO prestamos ...
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    // ...
}
```

Garantiza que **nunca** se descuenta stock sin crear el préstamo, ni viceversa, ni dos usuarios reservan el último ejemplar a la vez.
