# ApiLoging

API de autenticacion central del ecosistema Reglado.

Gestiona:
- registro con confirmacion por correo
- inicio y cierre de sesion con JWT
- recuperacion de contrasena
- cambio de email con confirmacion
- edicion basica de perfil
- listado de usuarios para administracion

## Requisitos

- PHP 8.1+
- MySQL o MariaDB
- Composer

## Instalacion

1. Instalar dependencias:

```bash
composer install
```

2. Crear `ApiLoging/.env` a partir de `ApiLoging/.env.example`.

3. Crear base de datos:

Instalacion limpia:

```sql
SOURCE database/create_regladousers.sql;
```

Base existente:

```sql
SOURCE database/schema.sql;
```

4. Arrancar la API:

```bash
php -S localhost:8000
```

## Variables de entorno principales

Base de datos:
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

JWT:
- `JWT_SECRET`
- `JWT_TTL_SECONDS`
- `JWT_ISSUER`

Seguridad:
- `APP_ENV`
- `CORS_ALLOWED_ORIGINS`
- `REDIRECT_ALLOWED_ORIGINS`

Correo:
- `MAIL_DRIVER`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_ENCRYPTION`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_FROM`
- `MAIL_FROM_NAME`

Flujos de correo:
- `EMAIL_VERIFY_URL_BASE`
- `EMAIL_VERIFY_REDIRECT_URL`
- `EMAIL_CHANGE_VERIFY_URL_BASE`
- `EMAIL_CHANGE_REDIRECT_URL`
- `PASSWORD_RESET_URL_BASE`

## Endpoints

Publicos:
- `POST /auth/register`
- `POST /auth/login`
- `GET /auth/verify-email?token=...`
- `POST /auth/resend-verification`
- `POST /auth/request-password-reset`
- `POST /auth/reset-password`

Protegidos:
- `GET /auth/me`
- `POST /auth/logout`
- `POST /auth/update-username`
- `POST /auth/update-name`
- `POST /auth/update-phone`
- `POST /auth/request-email-change`
- `GET /auth/confirm-email-change?token=...`
- `POST /auth/change-password`

Admin:
- `GET /auth/admin/users`

## Flujo general

1. Un frontend redirige al usuario a `GrupoReglado`.
2. `GrupoReglado` llama a `ApiLoging`.
3. `ApiLoging` emite un JWT.
4. El frontend de destino usa ese JWT en `Authorization: Bearer <token>`.
5. Cada backend de producto valida el token por su cuenta.

## Archivos importantes

- [index.php](c:\xampp\htdocs\Reglado\ApiLoging\index.php): punto de entrada y enrutado simple.
- [controllers/AuthController.php](c:\xampp\htdocs\Reglado\ApiLoging\controllers\AuthController.php): flujos de autenticacion y perfil.
- [middleware/AuthMiddleware.php](c:\xampp\htdocs\Reglado\ApiLoging\middleware\AuthMiddleware.php): validacion de JWT.
- [models/User.php](c:\xampp\htdocs\Reglado\ApiLoging\models\User.php): acceso a datos.
- [services/MailService.php](c:\xampp\htdocs\Reglado\ApiLoging\services\MailService.php): envio de correos.
- [database/create_regladousers.sql](c:\xampp\htdocs\Reglado\ApiLoging\database\create_regladousers.sql): script completo de base de datos.

## Notas de despliegue

- `JWT_SECRET` debe ser el mismo que usen los backends que validan los tokens.
- `CORS_ALLOWED_ORIGINS` debe incluir todos los frontends del ecosistema.
- `REDIRECT_ALLOWED_ORIGINS` debe incluir todos los destinos validos de `returnTo`.
