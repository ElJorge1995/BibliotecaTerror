# Cómo preparar una release de Librum Tenebris y subirla a Hostinger

Guía paso a paso para empaquetar el proyecto en una release estable y subirla a Hostinger.

- **Convención de nombrado**: `RELEASE_HOSTINGER_LIBRUMTENEBRIS_<YYYY-MM-DD>_V<N>` (mismo patrón que Reglado)
- **Última release**: ver carpeta más reciente en [`/ReleasesEstables/`](../ReleasesEstables/)
- **Dominio actual**: `mediumvioletred-grouse-788941.hostingersite.com` (subdominio temporal gratuito)
- **Plan**: Hostinger Premium Web Hosting

---

## 0. Estructura del repositorio

```
Proyecto Final/
├── BibliotecaTerror/      ← Frontend Vue 3 + Vite
├── ApiLoging/             ← Backend de autenticación (PHP MVC, JWT, mail)
├── backend/
│   └── libros_api/        ← Microservicio de libros (PHP plano)
├── database/              ← SQL fuente (en local)
├── Documentacion/         ← Documentación del proyecto (este archivo)
└── ReleasesEstables/      ← Paquetes listos para subir
    └── RELEASE_HOSTINGER_LIBRUMTENEBRIS_2026-05-05_V4/
        ├── README.md          ← changelog de la release
        ├── public_html/       ← lo que se sube tal cual
        ├── public_html.zip    ← comprimido para subida rápida
        └── database/          ← SQLs a importar en phpMyAdmin
            ├── 01_auth_tables.sql
            ├── 02_libros_tables.sql
            └── 03_libros_seed.sql
```

---

## 1. Pre-requisitos

| Herramienta | Versión | Para qué |
|---|---|---|
| Node.js + npm | 20.x o superior | Build del frontend |
| PHP | 8.x con `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json` | Backends |
| Composer | 2.x | Dependencias de `ApiLoging/vendor/` |
| Git | reciente | Versionado |
| PowerShell 5.1 | (viene con Windows) | Comandos de empaquetado |

> El `vendor/` de `ApiLoging` debe estar instalado en local — basta con ejecutar `composer install` una vez en `ApiLoging/` y no tocarlo más.

---

## 2. Decidir el nombre de la nueva release

```
RELEASE_HOSTINGER_LIBRUMTENEBRIS_<YYYY-MM-DD>_V<N>
```

- **Misma fecha + cambios incrementales**: incrementa `N` (V1 → V2 → V3 → V4 …)
- **Otro día**: nuevo nombre con la fecha de hoy y `V1`

Ejemplo: hoy es 2026-05-12 y antes había `..._2026-05-05_V4` → la nueva es `RELEASE_HOSTINGER_LIBRUMTENEBRIS_2026-05-12_V1`.

---

## 3. Pre-build: actualizar archivos fuente del frontend

Si has cambiado de dominio, hazlo **antes** del build para que los assets minificados ya salgan con el dominio correcto. Si no ha cambiado nada del dominio, salta este paso.

PowerShell — buscar y reemplazar dominio en archivos fuente:

```powershell
$old = 'bibliotecaterror.com'
$new = 'mediumvioletred-grouse-788941.hostingersite.com'

$root = 'c:\Users\sonic\Desktop\FP DAW\Año 2\Proyecto Final\BibliotecaTerror'
$files = Get-ChildItem -Path $root -Recurse -File -Include *.html,*.txt,*.xml,*.vue
foreach ($f in $files) {
  $bytes = [IO.File]::ReadAllBytes($f.FullName)
  $txt = [Text.Encoding]::UTF8.GetString($bytes)
  if ($txt.Contains($old)) {
    $txt = $txt.Replace($old, $new)
    [IO.File]::WriteAllBytes($f.FullName, [Text.Encoding]::UTF8.GetBytes($txt))
    Write-Output "MOD: $($f.Name)"
  }
}
```

Verificar que ya no quedan referencias al dominio viejo:

```powershell
Get-ChildItem 'c:\Users\sonic\Desktop\FP DAW\Año 2\Proyecto Final\BibliotecaTerror' -Recurse -File `
  | Select-String -Pattern $old -SimpleMatch | Select-Object Filename, LineNumber, Line
```

---

## 4. Build del frontend

```bash
cd "c:/Users/sonic/Desktop/FP DAW/Año 2/Proyecto Final/BibliotecaTerror"
npm run build
```

Salida esperada (~3 segundos): `✓ built in 3.0s` y un listado de archivos en `dist/`.

`BibliotecaTerror/dist/` ahora contiene `index.html`, `assets/` (JS y CSS con hash), iconos, `og-image.png`, `robots.txt`, `sitemap.xml` y `.htaccess`.

---

## 5. Crear la estructura de la release

> En este ejemplo el destino es `RELEASE_HOSTINGER_LIBRUMTENEBRIS_2026-05-12_V1`. Sustituye por el nombre real.

Bash (Git Bash en Windows):

```bash
REL="c:/Users/sonic/Desktop/FP DAW/Año 2/Proyecto Final/ReleasesEstables/RELEASE_HOSTINGER_LIBRUMTENEBRIS_2026-05-12_V1"
PREV="c:/Users/sonic/Desktop/FP DAW/Año 2/Proyecto Final/ReleasesEstables/RELEASE_HOSTINGER_LIBRUMTENEBRIS_2026-05-05_V4"
SRC="c:/Users/sonic/Desktop/FP DAW/Año 2/Proyecto Final"

# Estructura base
mkdir -p "$REL/public_html/auth" "$REL/public_html/api/uploads/covers" "$REL/database"
```

---

## 6. Copiar el frontend

```bash
# dist/ del frontend → public_html/ (incluye .htaccess, robots, sitemap, og-image)
cp -r "$SRC/BibliotecaTerror/dist/." "$REL/public_html/"
```

---

## 7. Copiar el backend de auth (`ApiLoging` → `public_html/auth/`)

```bash
# Copia todo ApiLoging incluyendo vendor/
cp -r "$SRC/ApiLoging/." "$REL/public_html/auth/"

# IMPORTANTE: borrar el .env real (tiene credenciales y mail password)
rm -f "$REL/public_html/auth/.env"

# El .htaccess de auth/ no está en el código fuente — heredar del release anterior
cp "$PREV/public_html/auth/.htaccess" "$REL/public_html/auth/.htaccess"
```

---

## 8. Copiar el microservicio de libros (`backend/libros_api` → `public_html/api/`)

```bash
cp "$SRC/backend/libros_api/conexion.php" "$REL/public_html/api/"
cp "$SRC/backend/libros_api/libros_api.php" "$REL/public_html/api/"
cp "$SRC/backend/libros_api/get_title.php" "$REL/public_html/api/"

# El .htaccess de api/ tampoco está en el fuente
cp "$PREV/public_html/api/.htaccess" "$REL/public_html/api/.htaccess"

# Copiar también las portadas que el admin haya subido en local
cp -r "$SRC/backend/libros_api/uploads/covers/." "$REL/public_html/api/uploads/covers/" 2>/dev/null
```

> **Nota técnica**: `libros_api.php` ya incluye un fix dinámico para resolver la raíz del backend de auth tanto en local (`../../ApiLoging`) como en Hostinger (`../auth`). No hay que parchearlo a mano.

---

## 9. Configurar las credenciales

### 9.1. El `.env` de `auth/` (BBDD 1: usuarios)

Heredar del release anterior si las credenciales no han cambiado:

```bash
cp "$PREV/public_html/auth/.env" "$REL/public_html/auth/.env"
```

O crear uno nuevo desde `.env.example`. Variables clave:

```env
APP_ENV=production

# BBDD de auth (Hostinger MySQL)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u238278696_jorgeUsuarios
DB_USER=u238278696_jorge1
DB_PASS=<contraseña real de la BBDD>

# JWT — generar con:  $bytes = New-Object byte[] 64; [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes); [System.BitConverter]::ToString($bytes).Replace('-','').ToLower()
JWT_SECRET=<128 caracteres hex>

# CORS (incluye variantes con y sin www)
CORS_ALLOWED_ORIGINS=https://mediumvioletred-grouse-788941.hostingersite.com,https://www.mediumvioletred-grouse-788941.hostingersite.com
REDIRECT_ALLOWED_ORIGINS=https://mediumvioletred-grouse-788941.hostingersite.com,https://www.mediumvioletred-grouse-788941.hostingersite.com

# Microservicio de libros (mismo dominio)
LIBROS_API_URL=https://mediumvioletred-grouse-788941.hostingersite.com/api

# URLs de verificación/reset (apuntando a producción)
EMAIL_VERIFY_URL_BASE=https://mediumvioletred-grouse-788941.hostingersite.com/auth/verify-email
EMAIL_VERIFY_REDIRECT_URL=https://mediumvioletred-grouse-788941.hostingersite.com/verificacion-exitosa
EMAIL_CHANGE_VERIFY_URL_BASE=https://mediumvioletred-grouse-788941.hostingersite.com/auth/confirm-email-change
EMAIL_CHANGE_REDIRECT_URL=https://mediumvioletred-grouse-788941.hostingersite.com/configuracion
PASSWORD_RESET_URL_BASE=https://mediumvioletred-grouse-788941.hostingersite.com/restablecer-contrasena

# SMTP Gmail (con app password de 16 chars)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=<tu_email@gmail.com>
MAIL_PASSWORD=<app password gmail 16 chars>
MAIL_FROM=<tu_email@gmail.com>
MAIL_FROM_NAME=Librum Tenebris
```

### 9.2. `conexion.php` de `api/` (BBDD 2: libros)

Heredar del release anterior:

```bash
cp "$PREV/public_html/api/conexion.php" "$REL/public_html/api/conexion.php"
```

Contenido esperado:

```php
<?php
$host    = "localhost";
$db      = "u238278696_jorgeLibros";
$user    = "u238278696_jorge2";
$pass    = "<contraseña real de la BBDD de libros>";
$charset = "utf8mb4";
```

---

## 10. Copiar los SQLs

Los 3 SQLs ya están limpios (sin `CREATE DATABASE` ni `USE`) y listos para importar directamente en phpMyAdmin de Hostinger:

```bash
cp "$PREV/database/01_auth_tables.sql" "$REL/database/"
cp "$PREV/database/02_libros_tables.sql" "$REL/database/"
cp "$PREV/database/03_libros_seed.sql" "$REL/database/"
```

| Archivo | Importar en BBDD | Contiene |
|---|---|---|
| `01_auth_tables.sql` | `u238278696_jorgeUsuarios` | users, pending_registrations, tokens (verify/reset/change), revoked_tokens, rate_limits, security_events, login_locations |
| `02_libros_tables.sql` | `u238278696_jorgeLibros` | libros, favoritos, prestamos |
| `03_libros_seed.sql` | `u238278696_jorgeLibros` | INSERTs de los 101 libros del catálogo inicial |

---

## 11. Crear el README de la release

Heredar el del release anterior y editarlo:

```bash
cp "$PREV/README.md" "$REL/README.md"
```

Luego abrir `$REL/README.md` y modificar:

- Versión y fecha en la cabecera (`# Librum Tenebris — Release V<N> (<fecha>)`)
- Sección "Cambios respecto a V<anterior>" con el changelog real
- Mover la sección anterior a "Cambios heredados de V<anterior>"

---

## 12. Verificar que no queda dominio antiguo

```bash
grep -rl "bibliotecaterror" "$REL" 2>/dev/null || echo "(limpio)"
```

Debe devolver `(limpio)`. Si encuentra algo, repasar el paso 3.

---

## 13. Comprimir `public_html/` en ZIP

PowerShell:

```powershell
$rel = 'c:\Users\sonic\Desktop\FP DAW\Año 2\Proyecto Final\ReleasesEstables\RELEASE_HOSTINGER_LIBRUMTENEBRIS_2026-05-12_V1'
$zip = Join-Path $rel 'public_html.zip'
if (Test-Path $zip) { Remove-Item $zip }
Compress-Archive -Path (Join-Path $rel 'public_html\*') -DestinationPath $zip
$size = [math]::Round((Get-Item $zip).Length / 1MB, 2)
"ZIP: $size MB"
```

Un ZIP típico pesa **~5–6 MB**. La mayor parte es `vendor/` de Composer.

---

## 14. Subir a Hostinger

Hay tres formas. **El Administrador de archivos del hPanel es la recomendada**.

### 14.1. Vía hPanel → Administrador de archivos (recomendado)

1. Iniciar sesión en [hpanel.hostinger.com](https://hpanel.hostinger.com).
2. Ir a tu hosting → **Archivos → Administrador de archivos**.
3. Entrar en `public_html/`.
4. **Borrar todo lo que haya dentro** (la release anterior).
5. Pulsar **Subir archivos** y seleccionar `public_html.zip`.
6. Cuando termine la subida → clic derecho sobre el ZIP → **Extraer**. Se desempaqueta en `public_html/`.
7. Borrar el ZIP cuando hayas comprobado que la extracción fue correcta.
8. **Importante**: activa "Mostrar archivos ocultos" en el menú de configuración del Administrador para verificar que los `.htaccess` se subieron (a veces los archivos ocultos se filtran).

### 14.2. Vía SFTP/FTP (FileZilla, WinSCP)

Credenciales en hPanel → **Avanzado → Cuentas FTP**. Subir el contenido de `public_html/` (no la carpeta entera, su contenido) a `/public_html/` del servidor.

### 14.3. Vía SSH (Premium/Business — más rápido)

Activar SSH en hPanel → **Avanzado → Acceso SSH**.

```bash
# Subir el ZIP
scp -P <puerto> public_html.zip u<id>@<host>:/home/u<id>/

# Conectar
ssh -p <puerto> u<id>@<host>

# Ya dentro
cd domains/<tu-dominio>/public_html
rm -rf *                                  # borrar release anterior
unzip /home/u<id>/public_html.zip -d .
```

---

## 15. Importar las BBDDs (solo la primera vez o si hay cambios de schema)

> Si las tablas ya están creadas en Hostinger desde una release anterior, salta este paso — no se reimportan a no ser que haya migración.

1. hPanel → **Bases de datos → MySQL** → comprobar que existen las 2 BBDDs (`u238278696_jorgeUsuarios` y `u238278696_jorgeLibros`).
2. Abrir **phpMyAdmin** desde hPanel.
3. Importar `database/01_auth_tables.sql` en `u238278696_jorgeUsuarios`.
4. Importar `database/02_libros_tables.sql` en `u238278696_jorgeLibros`.
5. Importar `database/03_libros_seed.sql` también en `u238278696_jorgeLibros`.

### 15.1. Arreglar portadas con URL `localhost` (si aplica)

Si hay registros con portadas que apuntan a `http://localhost:8080/...` (subidas en local antes del despliegue), ejecutar en phpMyAdmin → BBDD `u238278696_jorgeLibros` → SQL:

```sql
UPDATE libros
SET portada = REPLACE(portada, 'http://localhost:8080/uploads/covers/', '/api/uploads/covers/')
WHERE portada LIKE 'http://localhost:8080%';
```

Las portadas físicas (PNG) están en `public_html/api/uploads/covers/` del ZIP.

---

## 16. Verificación post-despliegue

| Comprobación | Cómo |
|---|---|
| Frontend carga | Abrir `https://<dominio>` → ver carrusel de novedades |
| Vue Router funciona | Navegar a `/buscar`, refrescar (F5) → no 404 |
| Backend auth responde | `POST /auth/login` con credenciales → JSON |
| Backend libros responde | `GET /api/libros_api.php?action=recientes&limit=4` → JSON con 4 libros |
| `robots.txt` y `sitemap.xml` accesibles | Abrir las URLs |
| OG preview correcto | Pegar URL en WhatsApp/Discord → ver fantasma rojo |
| SSL activo | Candado en la barra del navegador |
| Sesión persiste | Iniciar sesión, F5 → sigue logueado |
| Mobile responsive | Abrir DevTools, simular 393×852 (Redmi Note 14 Pro) → todo cabe sin scroll horizontal |

---

## 17. Troubleshooting

| Síntoma | Causa probable | Solución |
|---|---|---|
| 500 al cargar `/` | `.htaccess` raíz mal copiado o `mod_rewrite` desactivado | Verificar que `.htaccess` está y `mod_rewrite` activo en hPanel → Avanzado |
| 404 al refrescar `/buscar` | SPA fallback no funciona | Revisar `public_html/.htaccess` (los archivos ocultos a veces se filtran al subir) |
| Frontend pide a `localhost:8000` | Build viejo en caché | Hard refresh (Ctrl+F5), vaciar caché del navegador |
| `/auth/*` da 500 | Falta `.env` o credenciales BBDD mal | Revisar `public_html/auth/.env`. Mirar log de errores PHP en hPanel |
| `/api/libros_api.php` da 500 | `conexion.php` mal configurado o `libros_api.php` antiguo (V1) | Verificar credenciales en `conexion.php`. Si es la V1, ese archivo tenía un bug — usar V2+ |
| Composer dependencies missing | `vendor/` no se subió completo | Resubir `auth/vendor/` por SFTP, o vía SSH `cd auth && composer install --no-dev` |
| Mails no llegan | SMTP mal configurado | Revisar `MAIL_*` en `.env`. Para Gmail usar app password (16 chars) — no la contraseña normal |
| Imágenes de libros con `localhost:8080` | Portadas subidas en local antes del despliegue | Ejecutar el UPDATE de la sección 15.1 |
| Header se sale del viewport en mobile | Probable que estés en una release anterior a V4 | V4+ tiene `overflow-x: clip` y header compacto a 480px |

---

## 18. Checklist rápido antes de subir

- [ ] `npm run build` sin errores
- [ ] `vendor/` de `ApiLoging` instalado
- [ ] `.env` con `JWT_SECRET` real (no `change-this-secret`)
- [ ] `.env` con CORS apuntando al dominio real
- [ ] `conexion.php` con la contraseña de la BBDD de libros
- [ ] `.htaccess` raíz, `auth/.htaccess` y `api/.htaccess` presentes
- [ ] No queda `bibliotecaterror.com` en ningún archivo (`grep -rl`)
- [ ] SQLs en `database/` (los 3: auth, libros, seed)
- [ ] README de la release actualizado con versión, fecha y changelog
- [ ] `public_html.zip` generado
- [ ] Probado en local (Vite dev server) antes del build

---

## 19. Apéndice — comandos completos en una sola tirada

Una vez tengas todo listo (build hecho, dominios actualizados), este bloque genera la release completa de un golpe:

```bash
REL="c:/Users/sonic/Desktop/FP DAW/Año 2/Proyecto Final/ReleasesEstables/RELEASE_HOSTINGER_LIBRUMTENEBRIS_$(date +%Y-%m-%d)_V1"
PREV="c:/Users/sonic/Desktop/FP DAW/Año 2/Proyecto Final/ReleasesEstables/RELEASE_HOSTINGER_LIBRUMTENEBRIS_2026-05-05_V4"
SRC="c:/Users/sonic/Desktop/FP DAW/Año 2/Proyecto Final"

mkdir -p "$REL/public_html/auth" "$REL/public_html/api/uploads/covers" "$REL/database"

cp -r "$SRC/BibliotecaTerror/dist/." "$REL/public_html/"

cp -r "$SRC/ApiLoging/." "$REL/public_html/auth/"
rm -f "$REL/public_html/auth/.env"
cp "$PREV/public_html/auth/.htaccess" "$REL/public_html/auth/.htaccess"
cp "$PREV/public_html/auth/.env" "$REL/public_html/auth/.env"

cp "$SRC/backend/libros_api/conexion.php" "$REL/public_html/api/"
cp "$SRC/backend/libros_api/libros_api.php" "$REL/public_html/api/"
cp "$SRC/backend/libros_api/get_title.php" "$REL/public_html/api/"
cp "$PREV/public_html/api/.htaccess" "$REL/public_html/api/.htaccess"
cp "$PREV/public_html/api/conexion.php" "$REL/public_html/api/conexion.php"
cp -r "$SRC/backend/libros_api/uploads/covers/." "$REL/public_html/api/uploads/covers/" 2>/dev/null

cp "$PREV/database/01_auth_tables.sql" "$REL/database/"
cp "$PREV/database/02_libros_tables.sql" "$REL/database/"
cp "$PREV/database/03_libros_seed.sql" "$REL/database/"

cp "$PREV/README.md" "$REL/README.md"

# Verificación final
grep -rl "bibliotecaterror" "$REL" 2>/dev/null && echo "⚠ DOMINIO ANTIGUO PRESENTE" || echo "✓ Dominio limpio"

echo "Release lista en: $REL"
```

Después abrir el `README.md` para editar el changelog y ejecutar el bloque de PowerShell del paso 13 para crear el ZIP.
