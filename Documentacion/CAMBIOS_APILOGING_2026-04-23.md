# Cambios ApiLoging — 23 de abril 2026

Actualización de seguridad del backend `ApiLoging` sincronizando con la versión maestra del ecosistema Reglado. Se añaden 5 features mayores al backend, se arregla un bug de seguridad del rate limit, y se actualiza el frontend para que siga compatible.

**Lo que NO se ha tocado:**
- Tu `.env` y `.env.example` (credenciales y keys).
- Campo `dni` ni los flujos que lo usan (`register`, `adminRegister`).
- Endpoints propios de BibliotecaTerror: `/auth/admin/register`, `/auth/admin/delete-user`, `/auth/delete-me`.
- Integración con `libros_api.php` (comprobación de préstamos activos antes de borrar cuenta).
- `test_update.php`, tu script de pruebas.
- El motor de Notion con las DBs de libros y préstamos.
- Política de password mínima de 6 caracteres (sigue igual).
- Lista de roles `['user', 'pro', 'admin']`.

---

## 1. Features nuevas del backend

### 1.1 Single-session enforcement (kick-old)
Solo puede haber una sesión activa por cuenta. Cada login genera un `sid` (session id) aleatorio, se guarda en `users.current_session_id` y se incluye como claim en el JWT. El middleware compara `jwt.sid == users.current_session_id`; si no coincide, devuelve `401 session expired`.

Política: la sesión **más reciente gana**. Si alguien roba credenciales y se loguea, el dueño recibe 401 al siguiente request y tiene que volver a entrar.

### 1.2 Ban + admin force-logout
Dos endpoints nuevos protegidos para moderación:
- `POST /auth/admin/set-ban` — banea o desbanea una cuenta. Un usuario baneado no puede loguear (403 `account banned`) y sus JWTs existentes son rechazados.
- `POST /auth/admin/force-logout` — cierra la sesión activa del usuario objetivo desde el admin.

Ambos exigen re-autenticación con la contraseña del admin (como defensa contra JWTs robados) y tienen protección anti-autodestrucción (`cannot target self`).

### 1.3 Alertas de login desde nuevo país (geo alerts)
En cada login se registra el país de origen (vía MaxMind GeoLite2-Country offline). Si el país es distinto al del último login legítimo, se envía un email al usuario con dos botones:
- **Sí, he sido yo** → confirma, el login cuenta como normal.
- **No, no he sido yo** → mata la sesión sospechosa + activa `require_password_reset` (obligatorio cambio de contraseña en el siguiente login).

Los links del email abren una página HTML servida por el propio backend en `/auth/confirm-login-location?token=X&decision=me|not-me`, con paleta oscura al estilo Librum Tenebris.

### 1.4 `requestEmailChange` ahora exige contraseña (fix de seguridad)
Antes, con un JWT robado un atacante podía cambiar el email asociado y luego pedir reset de contraseña al nuevo email → takeover completo. Ahora el endpoint exige `current_password` en el body.

**Cambio rompe-frontend**: el `api/auth.js` ha sido actualizado para enviar el password, y `ProfilePage.vue` ahora incluye un campo de contraseña en el formulario de cambio de email.

### 1.5 Rate limit simplificado (fix de vulnerabilidad)
El `RateLimiter::enforce` contaba cada intento de login (éxito o fallo) contra un contador común, lo que permitía que un atacante en ping-pong con el dueño agotara la cuota y bloqueara a ambos — quedando el atacante con la última sesión activa.

Solución: se han eliminado los scopes `login` y `login_email`; queda solo `login_lockout` como contador de fallos (5 fallos en 30 min por email). Los logins exitosos **no consumen cuota**, así el dueño siempre puede reclamar su sesión.

---

## 2. Archivos backend modificados

### Archivos sobrescritos
- `ApiLoging/utils/Security.php`
- `ApiLoging/services/JwtService.php` (claim `sid` obligatorio)
- `ApiLoging/services/MailService.php` (`sendLoginAlert` + tweak anti-spam)
- `ApiLoging/services/RateLimiter.php`
- `ApiLoging/services/SecurityLogger.php`
- `ApiLoging/middleware/AuthMiddleware.php` (check de `sid`)

### Archivos nuevos
- `ApiLoging/services/GeoLocationService.php`
- `ApiLoging/database/migrate_security_upgrade.sql`
- `ApiLoging/data/GeoLite2-Country.mmdb` (9 MB, fuera de git vía `.gitignore`)

### Archivos merged (patches quirúrgicos, preservando lo específico del proyecto)
- `ApiLoging/models/User.php`:
  - Añadidos 11 métodos: `getSecurityState`, `banUser`, `unbanUser`, `invalidateSessions`, `rotateSession`, `clearSession`, `setRequirePasswordReset`, `getLastLegitLoginCountry`, `recordLoginLocation`, `findLoginLocationByTokenHash`, `updateLoginLocationStatus`.
  - `updatePasswordHash` actualizado para setear `password_changed_at` (necesario para el middleware).
  - **Preservados:** `findByDni`, `create` con parámetro `dni`, `createPendingRegistration` con `dni`, `delete`.
- `ApiLoging/controllers/AuthController.php`:
  - `login`: añadidos checks de `banned_at` y `require_password_reset`, rotación de sid, hook geo, rate limit simplificado.
  - `verifyEmail` / `confirmEmailChange`: rotación de sid tras emitir JWT.
  - `resetPassword`: limpia `require_password_reset` y rota sid.
  - `changePassword`: rota sid vía nuevo `respondWithRotatedSession`.
  - `requestEmailChange`: exige `current_password` + verificación con `password_verify`.
  - `respondWithFreshSession`: conserva el sid existente (updates de perfil no reinician sesión).
  - `logout`: verifica firma antes de tocar BD, guarda solo hash, limpia `current_session_id`.
  - Nuevos: `adminForceLogout`, `adminSetBan`, `requireAdminForUserMutation`, `handleLoginLocation`, `buildLoginAlertToken`, `resolveLoginAlertBaseUrl`, `appendQuery`, `confirmLoginLocation`, `renderLoginLocationPage`.
  - **Preservados:** `adminRegister` con `dni`, `adminDeleteUser`, `deleteMe`, `hasActiveLoans`.
- `ApiLoging/index.php`:
  - Añadido `require` de `GeoLocationService.php`.
  - Añadidas 3 rutas: `/auth/admin/force-logout`, `/auth/admin/set-ban`, `/auth/confirm-login-location`.
  - **Preservadas** las rutas del proyecto.
- `ApiLoging/composer.json`:
  - Añadida dependencia `geoip2/geoip2: ^2.13`.
  - `composer.lock` y `vendor/` actualizados (4 paquetes nuevos).

---

## 3. Cambios de base de datos (ya aplicados a `bibliouser`)

Migración ejecutada: `ApiLoging/database/migrate_security_upgrade.sql` (idempotente, se puede re-ejecutar sin problema).

**Nuevas columnas en `users`:**
- `password_changed_at DATETIME NULL`
- `banned_at DATETIME NULL`
- `banned_by INT NULL` (FK a `users.id`, `ON DELETE SET NULL`)
- `sessions_invalidated_at DATETIME NULL`
- `current_session_id CHAR(64) NULL`
- `require_password_reset TINYINT(1) NOT NULL DEFAULT 0`

**Cambios en `revoked_tokens`:**
- Añadida columna `token_hash CHAR(64) NULL`.
- `token` pasa a ser `NULL`-able.
- Backfill automático con SHA-256 de tokens antiguos.
- Índice nuevo `idx_revoked_token_hash`.

**Tabla nueva `login_locations`:**
Registra cada intento de login con país, user agent, status (`neutral`, `pending`, `confirmed`, `rejected`) y token de confirmación cuando aplica.

**Compatibilidad con datos existentes:** los usuarios viejos siguen funcionando. Quedan con todos los campos nuevos en valor por defecto (NULL / 0), así que el login sigue como antes hasta que algo los active.

---

## 4. Frontend (BibliotecaTerror)

### `src/api/auth.js`
- **Response interceptor 401** añadido: cuando el backend devuelve 401 (sesión invalidada, baneo, force-logout, kick-old por otro login) se elimina `auth_token` de localStorage. El estado reactivo de Pinia se encarga del resto.
- **`requestEmailChange`** firma actualizada: ahora `(new_email, current_password)`.

### `src/pages/ProfilePage.vue`
- Añadido campo `current_password` al `emailData` reactive.
- Añadido input password en el formulario de cambio de email con validación.
- Limpieza de ambos campos al éxito.

### Otros frontends NO se han tocado
- `src/stores/auth.js` sigue igual.
- Ningún otro componente afectado.

---

## 5. Configuración opcional del `.env` (no bloquea nada si falta)

Si quieres que los enlaces del email de alerta geo apunten a una URL concreta (por ejemplo cuando despliegues), añade:

```
LOGIN_ALERT_URL_BASE=http://localhost:8000/auth/confirm-login-location
```

Si la var falta:
- En dev (`APP_ENV=local` o sin setear): usa `http://localhost:8000/auth/confirm-login-location` por defecto.
- En producción (`APP_ENV=production`): la alerta geo se degrada a 'neutral' silenciosamente y no se envía email. El login nunca se rompe por esto.

Actualización mensual del `.mmdb` (opcional): documentada en `ApiLoging/data/README.md`. No es urgente; países cambian muy raramente.

---

## 6. PRUEBAS QUE DEBES EJECUTAR

Secuencia sugerida. Arranca los servidores según tu `DOCUMENTACION.md` (MySQL XAMPP + `php -S localhost:8000` + `php -S localhost:8080 -t "backend/libros_api"` + `npm run dev`).

### 6.1 Smoke test básico — login viejo sigue funcionando

Loguea con un usuario existente desde el frontend en `http://localhost:5173`. Debe:
- Entrar sin problemas con la misma contraseña.
- El JWT nuevo tiene un claim `sid` (puedes verificar decodificándolo en jwt.io si quieres).

**Si falla**: mira `/tmp/fp_api.log` por errores PHP.

### 6.2 Cambio de contraseña — sigue funcionando + nueva rotación de sesión

Desde la página de perfil, cambia tu contraseña. Debe:
- Aceptarla como antes.
- Responder con un token nuevo.
- El token anterior dejar de funcionar (si haces cualquier acción con el token viejo obtendrás 401).

### 6.3 Cambio de email — ahora exige contraseña

Desde perfil → sección Email:
- Intenta cambiar email SIN rellenar el campo de contraseña → debe fallar en frontend con "Confirma tu contraseña actual".
- Rellena nuevo email + contraseña correcta → debe enviar email de confirmación como antes.
- Rellena contraseña INCORRECTA → backend devuelve 401 `current password is incorrect`, el frontend muestra el error.

### 6.4 Kick-old entre dos dispositivos (single-session)

- En Chrome: login con usuario X.
- En Firefox (o incógnito): login con el mismo usuario X.
- Vuelve a Chrome y clica cualquier cosa que haga petición autenticada (p.ej. recargar perfil).
- Debe saltar 401 y el localStorage quedar sin token. La UI debería reflejarlo al refrescar.

### 6.5 Ban desde admin

Loguea como admin. **IMPORTANTE: el admin panel actual (`AdminPage.vue`) NO tiene todavía botón para banear.** Puedes probar la API directamente con curl:

```bash
# Sustituye $TOKEN por tu JWT de admin y $USER_ID por un user_id no admin
curl -X POST http://localhost:8000/auth/admin/set-ban \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"user_id":$USER_ID,"banned":true,"current_password":"TU_PASSWORD_ADMIN"}'
```

Luego intenta loguear con el usuario baneado → debe devolver 403 `account banned`.

Desbanear:
```bash
curl -X POST http://localhost:8000/auth/admin/set-ban \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"user_id":$USER_ID,"banned":false,"current_password":"TU_PASSWORD_ADMIN"}'
```

### 6.6 Alerta geo (cambio de país)

Requiere forzar una IP pública. Con curl usando header `X-Forwarded-For` que el backend sí respeta:

```bash
# Login 1 desde "España"
curl -X POST http://localhost:8000/auth/login \
  -H 'Content-Type: application/json' -H 'X-Forwarded-For: 217.76.144.1' \
  -d '{"email":"tu_email@dominio","password":"tu_password"}'

# Login 2 desde "US" — ESTE debe disparar email de alerta
curl -X POST http://localhost:8000/auth/login \
  -H 'Content-Type: application/json' -H 'X-Forwarded-For: 8.8.8.8' \
  -d '{"email":"tu_email@dominio","password":"tu_password"}'
```

En BD debe aparecer una fila nueva en `login_locations` con `status='pending'` y `token_hash` relleno:
```sql
SELECT id, country_code, status, token_hash IS NOT NULL AS has_token
FROM login_locations WHERE user_id = <tu_id> ORDER BY id DESC LIMIT 3;
```

Si el SMTP está bien configurado en `.env`, te llegará el email. Clica **"No, no he sido yo"** → abre una página HTML del backend con paleta oscura estilo Librum Tenebris. Verifica después:
```sql
SELECT require_password_reset, current_session_id FROM users WHERE id = <tu_id>;
-- require_password_reset debe ser 1, current_session_id debe ser NULL.
```

Al intentar loguear otra vez, devuelve 403 `password reset required` y se envía email de reset automáticamente.

### 6.7 Preservados los endpoints propios del proyecto

- **`POST /auth/admin/register`** (admin crea cuenta con dni) — debe seguir funcionando igual que antes.
- **`POST /auth/admin/delete-user`** (admin borra cuenta) — igual, incluyendo la comprobación de préstamos activos vía `libros_api`.
- **`POST /auth/delete-me`** (usuario autoelimina cuenta) — igual, también con comprobación de préstamos.

Prueba un flujo de los tres con el admin panel existente.

### 6.8 Login lockout sigue bloqueando fuerza bruta

Con 5 intentos fallidos seguidos a una cuenta:
- Intentos 1-4: `401 invalid credentials`.
- Intento 5 y siguientes: `429 account temporarily locked, try again later`.
- Bloqueo dura 30 min. Un login exitoso lo resetea.

---

## 7. Pendientes opcionales para el futuro

Cosas que NO se han hecho en este port pero podrías querer hacer más adelante:

1. **Botón de banear/desbanear y force-logout en `AdminPage.vue`**. Actualmente hay que usar curl. En el proyecto maestro de Reglado hay una implementación completa con menú de 3 puntos que se puede adaptar.
2. **Página `/confirmar-acceso` como componente Vue** (actualmente el backend renderiza HTML directamente). Más fino pero requiere nueva ruta y fetch del componente.
3. **Cron para actualizar mensualmente el `.mmdb` de MaxMind**. Sin esto, el fichero se queda con el snapshot de la fecha de descarga; países cambian poco pero conviene mantenerlo al día a medio plazo.
4. **Política de privacidad actualizada** con la cláusula sobre registro de IP/país por motivos de seguridad (lo exige el RGPD).

---

## 8. Referencias en el proyecto Reglado

Los specs y planes originales (en inglés/español técnico) de cada feature, si quieres el detalle de diseño:

- `docs/superpowers/specs/2026-04-22-admin-ban-force-logout-design.md`
- `docs/superpowers/specs/2026-04-23-single-session-enforcement-design.md`
- `docs/superpowers/specs/2026-04-23-geo-login-alerts-design.md`
- `docs/superpowers/plans/2026-04-22-admin-ban-force-logout.md`
- `docs/superpowers/plans/2026-04-23-single-session-enforcement.md`
- `docs/superpowers/plans/2026-04-23-geo-login-alerts.md`

(Solo accesibles desde el proyecto Reglado, no desde aquí.)
