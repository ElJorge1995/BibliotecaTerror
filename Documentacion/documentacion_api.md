# Documentación de la API REST — Librum Tenebris

Referencia completa de los endpoints expuestos por los dos backends PHP del
proyecto: **`ApiLoging`** (autenticación, JWT, gestión de usuarios) y
**`libros_api`** (catálogo, favoritos, préstamos).

| Backend     | Base URL local              | Base URL producción                                                            |
|-------------|-----------------------------|--------------------------------------------------------------------------------|
| ApiLoging   | `http://localhost:8000`     | `https://mediumvioletred-grouse-788941.hostingersite.com/auth`                |
| libros_api  | `http://localhost:8080`     | `https://mediumvioletred-grouse-788941.hostingersite.com/api/libros_api.php`  |

> En desarrollo Vite hace proxy de `/auth/*` al puerto 8000 y de `/api/*` al
> 8080. En producción Apache enruta a las subcarpetas correspondientes.

## Convenciones generales

| Aspecto             | Convención                                                                |
|---------------------|---------------------------------------------------------------------------|
| Formato de envío    | `application/json` (excepto subida de portadas, que usa `multipart/form-data`) |
| Formato de respuesta| `application/json; charset=utf-8`                                         |
| Autenticación       | `Authorization: Bearer <JWT>` (HS256, exp 24h)                            |
| Códigos de éxito    | `200` (general), `201` (creación), `204` (sin contenido)                  |
| Códigos de error    | `400` validación, `401` no auth, `403` sin permisos, `404` no encontrado, `429` rate limit, `500` error servidor |
| CORS                | Activado para todos los orígenes en dev; restringido por whitelist en prod |
| Rate limiting       | Por IP + endpoint, ventana deslizante en `rate_limits`                    |

---

# 1. ApiLoging — Autenticación

## 1.1 Endpoints públicos (sin token)

### `POST /auth/register`
Inicia el proceso de registro. **No crea el usuario directamente**: lo guarda en `pending_registrations` y envía email de verificación.

**Request body:**
```json
{
  "username": "joegranero",
  "email": "joe@example.com",
  "password": "MinSeisChars",
  "first_name": "Jorge",
  "last_name": "Núñez Granero",
  "dni": "12345678A",
  "phone": "+34 600 000 000"
}
```

**Respuestas:**
- `200` — `{ "message": "verification_email_sent" }`
- `400` — `{ "error": "validation_failed", "details": {...} }`
- `409` — `{ "error": "email_or_username_already_registered" }`
- `429` — `{ "error": "too_many_attempts" }`

---

### `POST /auth/login`

**Request body:**
```json
{
  "email": "joe@example.com",
  "password": "MinSeisChars"
}
```

**Respuestas:**
- `200` — `{ "token": "<JWT>", "user": { "id": 1, "email": "...", "role": "user", ... } }`
- `202` — `{ "message": "location_verification_required" }` (cuando hay alerta geo)
- `401` — `{ "error": "invalid_credentials" }`
- `403` — `{ "error": "account_banned" }` o `{ "error": "email_not_verified" }`
- `429` — `{ "error": "too_many_attempts" }`

---

### `GET /auth/verify-email?token=<token>`
Verifica el email mediante el token enviado por correo.
- `200` — Cuenta activada y movida desde `pending_registrations` a `users`.
- `400` — Token inválido / caducado / ya usado.

### `POST /auth/resend-verification`
**Body:** `{ "email": "joe@example.com" }`
- `200` — Email reenviado (respuesta genérica anti-enumeración).
- `429` — Rate limit.

### `POST /auth/request-password-reset`
**Body:** `{ "email": "joe@example.com" }`
- `200` — Respuesta genérica (no revela si existe el email).

### `POST /auth/reset-password`
**Body:** `{ "token": "<token>", "password": "NuevaPass123" }`
- `200` — Password actualizada y sesiones globales invalidadas.
- `400` — Token inválido o caducado.

### `GET /auth/confirm-email-change?token=<token>`
Confirma cambio de email (enlace recibido en el **nuevo** email).

### `POST /auth/confirm-login-location`
**Body:** `{ "token": "<token>" }` — Aprueba un login desde una IP/país sospechoso.

---

## 1.2 Endpoints autenticados (`Authorization: Bearer <JWT>`)

### `GET /auth/me`
Devuelve el usuario actual a partir del JWT.

**Respuesta 200:**
```json
{
  "id": 1,
  "username": "joegranero",
  "email": "joe@example.com",
  "name": "Jorge Núñez Granero",
  "role": "user",
  "is_email_verified": true,
  "created_at": "2026-04-10 12:00:00"
}
```

**Respuesta 401:** Token ausente, inválido, caducado o revocado.

### `POST /auth/logout`
Revoca el JWT actual (lo añade a `revoked_tokens`).
- `200` — `{ "message": "logged_out" }`

### Actualizaciones de perfil

| Endpoint                       | Body                                          | Notas |
|--------------------------------|-----------------------------------------------|-------|
| `POST /auth/update-username`   | `{ "username": "..." }`                       | Único globalmente. |
| `POST /auth/update-name`       | `{ "first_name": "...", "last_name": "..." }` | |
| `POST /auth/update-phone`      | `{ "phone": "..." }`                          | E.164 recomendado. |
| `POST /auth/change-password`   | `{ "current_password": "...", "new_password": "..." }` | Invalida sesiones globales. |
| `POST /auth/request-email-change` | `{ "new_email": "..." }`                   | Envía link al nuevo email. |
| `POST /auth/delete-me`         | `{ "password": "..." }`                       | Bloquea si hay préstamos activos. |

---

## 1.3 Endpoints de administración (`role = admin`)

| Endpoint                         | Método | Body                                         | Acción |
|----------------------------------|--------|----------------------------------------------|--------|
| `/auth/admin/users`              | GET    | —                                            | Lista todos los usuarios. |
| `/auth/admin/update-role`        | POST   | `{ "user_id": int, "role": "user|pro|admin" }` | Cambia rol. |
| `/auth/admin/register`           | POST   | (campos de `register` + `role`)              | Crea usuario sin verificación email. |
| `/auth/admin/delete-user`        | POST   | `{ "user_id": int }`                         | Borra cuenta y cascadea tokens. |
| `/auth/admin/force-logout`       | POST   | `{ "user_id": int }`                         | Invalida todas las sesiones del usuario. |
| `/auth/admin/set-ban`            | POST   | `{ "user_id": int, "banned": bool }`         | Banear / desbanear. |

---

# 2. libros_api — Catálogo y préstamos

> A diferencia de `ApiLoging`, este backend usa el patrón `?action=` en la
> query string en lugar de rutas REST puras. El método HTTP varía según la
> acción.

## 2.1 Catálogo (público)

### `GET /libros_api.php?action=recientes&limit=8&usuario_id=0`
Últimos libros añadidos. Si `usuario_id > 0`, incluye flag `is_favorito`.

**Respuesta 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 12,
      "google_id": "...",
      "titulo": "Drácula",
      "titulo_es": "Drácula",
      "autor": "Bram Stoker",
      "stock": 3,
      "portada": "/api/uploads/covers/drac_1234567890.jpg",
      "rating": 4.5,
      "is_favorito": 1
    }
  ]
}
```

### `GET /libros_api.php?action=recomendaciones&limit=32&usuario_id=0`
Libros con `rating >= 4.0`, ordenados desc.

### `GET /libros_api.php?action=buscar&q=<termino>&usuario_id=0`
Búsqueda por título / título traducido / autor (`LIKE %q%`). Máx 50 resultados.

### `GET /libros_api.php?action=todos&usuario_id=0`
Listado completo del catálogo.

### `GET /libros_api.php?action=obtener&id=<id>`
Detalle completo de un libro.
- `200` — Libro encontrado.
- `404` — `{ "error": "Libro no encontrado" }`.

---

## 2.2 Favoritos (requiere `usuario_id`)

### `POST /libros_api.php?action=toggle_favorito`
**Body:** `{ "usuario_id": 1, "libro_id": 12 }` — Marca/desmarca.

**Respuesta 200:** `{ "success": true, "is_favorito": true | false }`

### `GET /libros_api.php?action=check_favorito&usuario_id=1&libro_id=12`
**Respuesta 200:** `{ "success": true, "is_favorito": bool }`

### `GET /libros_api.php?action=mis_favoritos&usuario_id=1`
Lista de libros favoritos del usuario, ordenados por fecha de marcado.

---

## 2.3 Préstamos

### `GET /libros_api.php?action=count_active_loans&usuario_id=1`
Devuelve el número de préstamos activos o pendientes del usuario (para UI).

### `GET /libros_api.php?action=mis_prestamos&usuario_id=1`
Histórico completo de préstamos del usuario con datos del libro embebidos.

### `POST /libros_api.php?action=prestar`
**Body:** `{ "usuario_id": 1, "libro_id": 12, "nombre_usuario": "Jorge" }`

**Lógica:**
1. Valida que `count_active_loans < 2`.
2. Valida `stock > 0`.
3. Transacción: descuenta stock + inserta `prestamos` con `estado='pendiente'`.
4. Sincroniza con Notion.

**Respuestas:**
- `200` — `{ "success": true }`
- `400` — Stock 0 o concurrencia.
- `403` — Límite de 2 préstamos alcanzado.
- `500` — Error de transacción.

### `POST /libros_api.php?action=actualizar_prestamo` (admin)
**Body:** `{ "prestamo_id": 5, "estado": "pendiente|activo|devuelto" }`

**Comportamiento:**
- `pendiente → activo`: pone `fecha_prestamo=NOW()` y `fecha_devolucion=NOW()+14 días`.
- `* → devuelto`: pone `fecha_entregado=NOW()` y devuelve stock (`stock+1`).
- `devuelto → activo`: vuelve a descontar stock (corrección de error admin).

### `POST /libros_api.php?action=valorar_prestamo`
**Body:** `{ "prestamo_id": 5, "rating": 4 }` (rating 1–5)

Guarda la valoración del préstamo y **recalcula** el rating medio del libro
con `AVG(prestamos.rating)`.

### `GET /libros_api.php?action=todos_prestamos` (admin)
Listado de todos los préstamos del sistema con datos de usuario y libro.

### `POST /libros_api.php?action=admin_crear_prestamo` (admin)
Crea un préstamo manual desde el panel de admin (busca usuario por DNI).

**Body:** `{ "dni": "12345678A", "libro_titulo": "Drácula", "fecha_devolucion": "2026-06-01" }`

**Particularidad:** abre conexión cruzada a `bibliouser` para buscar el usuario por DNI.

---

## 2.4 CRUD de libros (admin)

### `POST /libros_api.php?action=crear` (`multipart/form-data`)

| Campo       | Tipo    | Obligatorio | Notas                             |
|-------------|---------|-------------|-----------------------------------|
| `google_id` | string  | sí          | ISBN o ID de Google Books.        |
| `titulo`    | string  | sí          |                                   |
| `autor`     | string  | sí          |                                   |
| `stock`     | int     | no (def. 3) |                                   |
| `categoria` | string  | sí          |                                   |
| `portada`   | file    | sí          | jpg / png / webp.                 |

**Respuesta 200:** `{ "success": true, "id": 42 }`

### `POST /libros_api.php?action=editar_libro` (`multipart/form-data`)
Igual que `crear` pero con `id` y `portada` opcional.

---

# 3. Errores comunes y troubleshooting

| Síntoma                                  | Causa probable                                       | Solución |
|------------------------------------------|------------------------------------------------------|----------|
| `401 Unauthorized` en endpoint público   | Falta el header `Authorization`.                     | Añadir `Bearer <token>`. |
| `401` con token presente                 | Token caducado, revocado o `sessions_invalidated_at` posterior al `iat`. | Reloguear. |
| `429 Too Many Attempts`                  | Rate limit superado.                                 | Esperar al reset (típicamente 15 min). |
| `403 account_banned`                     | Admin baneó la cuenta (`banned_at` no nulo).         | Contactar admin. |
| `500` en `prestar` con `Notion error`    | Notion API caída — el préstamo se ha creado igual.   | Sincronizar manualmente luego. |
| `400 La imagen de portada es obligatoria`| `enctype` ≠ `multipart/form-data` en el formulario.  | Revisar form. |

---

# 4. Tests con Postman

Importa la colección desde [`postman/Librum_Tenebris.postman_collection.json`](postman/Librum_Tenebris.postman_collection.json).

Variables de entorno recomendadas:

| Variable      | Valor dev                   | Valor producción                                      |
|---------------|-----------------------------|--------------------------------------------------------|
| `auth_url`    | `http://localhost:8000`     | `https://mediumvioletred-grouse-788941.hostingersite.com/auth` |
| `api_url`     | `http://localhost:8080`     | `https://mediumvioletred-grouse-788941.hostingersite.com/api`  |
| `jwt`         | (se rellena tras login)     | (se rellena tras login)                                |
| `usuario_id`  | (se rellena tras login)     | (se rellena tras login)                                |

La colección incluye un test script en `/auth/login` que extrae el token y
el `user.id` automáticamente y los guarda en las variables `jwt` y
`usuario_id`.
