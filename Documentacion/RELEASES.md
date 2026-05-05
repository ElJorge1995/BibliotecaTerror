# Cómo preparar una release de Librum Tenebris y subirla a Hostinger

Esta guía explica, paso a paso, cómo empaquetar el proyecto en una release estable como las que viven en [`/ReleasesEstables/`](../ReleasesEstables/) y cómo subirla a Hostinger.

- **Última release publicada**: [`v1.0.0_2026-04-28`](../ReleasesEstables/v1.0.0_2026-04-28/)
- **Dominio actualmente configurado**: `mediumvioletred-grouse-788941.hostingersite.com` (subdominio temporal gratuito de Hostinger)
- **Plan**: Hostinger Premium Web Hosting

---

## 0. Estructura del repositorio

```
Proyecto Final/
├── BibliotecaTerror/      ← Frontend Vue 3 + Vite
├── ApiLoging/             ← Backend de autenticación (PHP MVC, JWT, mail)
├── backend/
│   ├── libros_api/        ← Microservicio de libros (PHP plano)
│   └── cargalibros/       ← Scripts auxiliares (no se despliegan)
├── database/              ← SQL del schema y seed
├── Documentacion/         ← Documentación del proyecto (incluye este archivo)
└── ReleasesEstables/      ← Paquetes listos para subir a hosting
    └── v1.0.0_2026-04-28/
        ├── README.md
        ├── public_html/   ← lo que se sube tal cual
        └── database/      ← SQLs a importar en phpMyAdmin
```

---

## 1. Pre-requisitos del entorno local

| Herramienta | Versión | Para qué |
|---|---|---|
| Node.js + npm | 20.x o superior | Build del frontend |
| PHP | 8.x con `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json` | Backends |
| Composer | 2.x | Dependencias de `ApiLoging` |
| Git | cualquier reciente | Versionado |
| 7-Zip / WinRAR | opcional | Comprimir el `public_html` antes de subirlo |

---

## 2. Cómo crear una release nueva (paso a paso)

> Ejemplo: vamos a empaquetar `v1.0.1_2026-XX-XX`. Sustituye la fecha por la del día.

### 2.1. Versionado y carpeta destino

```bash
# Desde la raíz del proyecto
mkdir -p "ReleasesEstables/v1.0.1_$(date +%F)/public_html"
mkdir -p "ReleasesEstables/v1.0.1_$(date +%F)/database"
```

En PowerShell:

```powershell
$rel = "ReleasesEstables/v1.0.1_$(Get-Date -Format 'yyyy-MM-dd')"
New-Item -ItemType Directory -Force -Path "$rel/public_html"
New-Item -ItemType Directory -Force -Path "$rel/database"
```

### 2.2. Build del frontend (Vue + Vite)

```bash
cd BibliotecaTerror
npm ci                # instalación limpia desde package-lock
npm run build         # genera la carpeta dist/
```

Esto crea `BibliotecaTerror/dist/` con `index.html`, `assets/` (JS/CSS con hash), iconos, `og-image.png`, `robots.txt` y `sitemap.xml`.

Copia `dist/` al destino:

```bash
cp -r BibliotecaTerror/dist/* "ReleasesEstables/v1.0.1_$(date +%F)/public_html/"
```

PowerShell:

```powershell
Copy-Item -Recurse -Force BibliotecaTerror/dist/* "$rel/public_html/"
```

### 2.3. Copiar el backend de auth (`ApiLoging` → `public_html/auth/`)

```bash
mkdir -p "ReleasesEstables/v1.0.1_$(date +%F)/public_html/auth"
cp -r ApiLoging/* "ReleasesEstables/v1.0.1_$(date +%F)/public_html/auth/"
# Quitar el .env real (¡tiene credenciales!) — solo se sube .env.example
rm "ReleasesEstables/v1.0.1_$(date +%F)/public_html/auth/.env"
```

PowerShell:

```powershell
$dst = "$rel/public_html/auth"
New-Item -ItemType Directory -Force -Path $dst
Copy-Item -Recurse -Force ApiLoging/* $dst
Remove-Item -Force "$dst/.env" -ErrorAction SilentlyContinue
```

Asegúrate de que `auth/vendor/` está completo (composer instala dependencias). Si no, dentro de `ApiLoging/` ejecuta `composer install --no-dev --optimize-autoloader` antes de copiar.

Crea o copia el `.htaccess` que enruta todas las peticiones a `index.php` y bloquea archivos sensibles. Plantilla mínima:

```apache
# public_html/auth/.htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

<FilesMatch "^\.env|composer\.(json|lock)$">
  Require all denied
</FilesMatch>
```

### 2.4. Copiar el microservicio de libros (`backend/libros_api` → `public_html/api/`)

```bash
mkdir -p "ReleasesEstables/v1.0.1_$(date +%F)/public_html/api"
cp backend/libros_api/conexion.php "ReleasesEstables/v1.0.1_$(date +%F)/public_html/api/"
cp backend/libros_api/libros_api.php "ReleasesEstables/v1.0.1_$(date +%F)/public_html/api/"
cp backend/libros_api/get_title.php "ReleasesEstables/v1.0.1_$(date +%F)/public_html/api/"
mkdir -p "ReleasesEstables/v1.0.1_$(date +%F)/public_html/api/uploads/covers"
```

`.htaccess` recomendado para `api/` (bloquea includes internos y permite uploads):

```apache
# public_html/api/.htaccess
<FilesMatch "^(conexion|get_title)\.php$">
  Require all denied
</FilesMatch>
```

### 2.5. Copiar los SQL

```bash
cp database/01_apiloging_schema.sql "ReleasesEstables/v1.0.1_$(date +%F)/database/install_databases.sql"
cp database/02_libros_schema.sql   "ReleasesEstables/v1.0.1_$(date +%F)/database/"
cp database/03_libros_seed.sql     "ReleasesEstables/v1.0.1_$(date +%F)/database/seed_libros.sql"
```

> Si tienes un SQL combinado `install_databases.sql`, úsalo directamente. La nomenclatura final que espera el README de la release es `install_databases.sql` y `seed_libros.sql`.

### 2.6. Crear el `.htaccess` raíz (SPA fallback + cache + headers)

`public_html/.htaccess`:

```apache
# Reescritura SPA: todo lo que no sea archivo/carpeta real → index.html
RewriteEngine On
RewriteBase /

# Excluir /auth y /api del fallback SPA
RewriteCond %{REQUEST_URI} !^/auth/
RewriteCond %{REQUEST_URI} !^/api/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]

# Cache largo en assets con hash
<FilesMatch "\.(js|css|svg|png|jpg|jpeg|webp|woff2)$">
  Header set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>

# Compresión
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
</IfModule>

# Headers de seguridad básicos
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set Referrer-Policy "strict-origin-when-cross-origin"
```

### 2.7. Sanear `.env.example` y excluir artefactos de dev

Repasa que `public_html/auth/.env.example` no tiene secretos reales (solo placeholders). Borra del paquete cualquier archivo que sea de dev/test:

```bash
cd "ReleasesEstables/v1.0.1_$(date +%F)/public_html"
find . -name "output.txt" -o -name "server_logs.txt" -o -name "test_*.php" -o -name "migration.php" | xargs -r rm
```

### 2.8. Reemplazar el dominio por el de producción

Si la release apunta a un dominio distinto al actual, hacer buscar/reemplazar en TODOS los archivos del paquete:

PowerShell (recomendado en Windows porque preserva UTF-8 sin BOM en archivos minificados):

```powershell
$old = 'bibliotecaterror.com'                              # placeholder usado en dev
$new = 'mediumvioletred-grouse-788941.hostingersite.com'   # dominio real

$root = "$rel/public_html"
$files = Get-ChildItem -Path $root -Recurse -File -Include *.html,*.txt,*.xml,*.js,*.css,*.php,*.example
foreach ($f in $files) {
  $bytes = [IO.File]::ReadAllBytes($f.FullName)
  $txt = [Text.Encoding]::UTF8.GetString($bytes)
  if ($txt.Contains($old)) {
    $txt = $txt.Replace($old, $new)
    [IO.File]::WriteAllBytes($f.FullName, [Text.Encoding]::UTF8.GetBytes($txt))
    Write-Output "Reemplazado en: $($f.FullName)"
  }
}
```

Verificación final (no debe encontrar nada):

```powershell
Get-ChildItem -Path "$rel/public_html" -Recurse -File | Select-String -Pattern $old -SimpleMatch
```

bash:

```bash
# Listar antes
grep -rl "bibliotecaterror.com" "ReleasesEstables/v1.0.1_$(date +%F)/public_html"
# Reemplazar (cuidado con archivos binarios, los .png se filtran solos)
grep -rl "bibliotecaterror.com" "ReleasesEstables/v1.0.1_$(date +%F)/public_html" \
  | xargs sed -i 's/bibliotecaterror\.com/mediumvioletred-grouse-788941.hostingersite.com/g'
```

> **Lo más limpio**: en vez de reemplazar el dominio sobre el build, configurar la URL en el `index.html` fuente (`BibliotecaTerror/index.html`) y los `robots.txt`/`sitemap.xml` (`BibliotecaTerror/public/`) **antes** de hacer `npm run build`. Así los assets minificados ya salen con el dominio correcto y no hay que tocarlos a mano.

### 2.9. Escribir el README de la release

Copia y adapta `ReleasesEstables/v1.0.0_2026-04-28/README.md` como plantilla. Asegúrate de que documenta:

- Versión y fecha
- Dominio configurado
- Estructura del paquete
- Pasos de despliegue (BBDD, archivos, `.env`, `conexion.php`, dominio, SSL)
- Verificación post-despliegue
- Troubleshooting

### 2.10. Empaquetar en ZIP

```powershell
Compress-Archive -Path "$rel/public_html/*" -DestinationPath "$rel/public_html.zip"
```

bash:

```bash
cd "ReleasesEstables/v1.0.1_$(date +%F)" && zip -r public_html.zip public_html
```

El ZIP es lo que se sube al hosting.

---

## 3. Cómo subir la release a Hostinger

Hay tres formas. **El Administrador de archivos del hPanel es la más sencilla** y la recomendada para esta release.

### 3.1. Subir vía hPanel → Administrador de archivos (recomendado)

1. Inicia sesión en [hpanel.hostinger.com](https://hpanel.hostinger.com).
2. Ve a tu hosting → **Archivos → Administrador de archivos**.
3. Entra en la carpeta `public_html/`.
4. **Borra todo lo que haya dentro** (Hostinger suele dejar un `default.php` o `index.html` de bienvenida).
5. Pulsa **Subir archivos** y selecciona `public_html.zip`.
6. Cuando termine de subir, haz clic derecho → **Extraer**. Se desempaquetará dentro de `public_html/`.
7. Borra el `.zip` cuando hayas comprobado que la extracción fue correcta.
8. **Importante**: comprueba que los `.htaccess` están presentes (a veces se filtran por ser archivos ocultos). Activa "Mostrar archivos ocultos" en el menú de configuración del Administrador.

### 3.2. Subir vía SFTP/FTP

Si vas a desplegar muchas veces, configura un cliente FTP (FileZilla, WinSCP) con las credenciales de hPanel → **Avanzado → Cuentas FTP**:

| Campo | Valor |
|---|---|
| Host | el que aparece en hPanel (suele ser `ftp.<tu-dominio>` o IP) |
| Puerto | 21 (FTP) o 22 (SFTP) |
| Usuario | el de la cuenta FTP |
| Contraseña | la que pongas en hPanel |

Sube el contenido de `public_html/` (no la carpeta entera, su contenido) a `/public_html/` del servidor.

### 3.3. Vía SSH (Premium/Business — más rápido para despliegues repetidos)

Activa SSH en hPanel → **Avanzado → Acceso SSH**.

```bash
# Subir el ZIP
scp -P <puerto> public_html.zip u<user>@<host>:/home/u<user>/

# Conectar
ssh -p <puerto> u<user>@<host>

# Una vez dentro
cd domains/<tu-dominio>/public_html
rm -rf *                                  # limpiar (ojo, irreversible)
unzip /home/u<user>/public_html.zip -d .
mv public_html/* .                        # si el zip incluyó la carpeta padre
rmdir public_html
```

### 3.4. Configurar la base de datos (en cualquiera de los 3 casos)

1. hPanel → **Bases de datos → MySQL** → **Crear base de datos**.
   - Anota: nombre, usuario, contraseña, host (`localhost` o el que indique Hostinger).
2. Abre **phpMyAdmin** desde hPanel.
3. Selecciona la BBDD recién creada → pestaña **Importar**.
4. Sube `database/install_databases.sql` → **Continuar**.
5. Repite con `database/seed_libros.sql`.

### 3.5. Crear el `.env` real del backend de auth

1. En el Administrador de archivos, entra en `public_html/auth/`.
2. Duplica `.env.example` y renombra la copia a `.env`.
3. Edita `.env` y rellena:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` con los datos del paso 3.4.
   - `JWT_SECRET` con una cadena aleatoria larga (mínimo 32 chars). Genera con `openssl rand -hex 64` o un generador online.
   - `LIBROS_API_URL=https://mediumvioletred-grouse-788941.hostingersite.com/api`.
   - SMTP (`MAIL_*`) si quieres correos de verificación/reset funcionando.
   - `CORS_ALLOWED_ORIGINS` y `REDIRECT_ALLOWED_ORIGINS`: cambia los `localhost` por `https://mediumvioletred-grouse-788941.hostingersite.com`.
   - `EMAIL_VERIFY_URL_BASE`, `EMAIL_VERIFY_REDIRECT_URL`, `EMAIL_CHANGE_*`, `PASSWORD_RESET_URL_BASE`: actualizar a la URL de producción.

### 3.6. Configurar `conexion.php` del microservicio de libros

Edita `public_html/api/conexion.php` con los mismos datos de BBDD del paso 3.4.

### 3.7. Activar SSL y forzar HTTPS

- En subdominios `*.hostingersite.com` el SSL ya viene preconfigurado por Hostinger. Espera 1-2 minutos tras subir.
- En dominios propios: hPanel → **SSL → Instalar SSL gratis (Let's Encrypt)**.
- En ambos casos: hPanel → **Avanzado → Redireccionar a HTTPS**.

---

## 4. Verificación post-despliegue

| Comprobación | Cómo |
|---|---|
| Frontend carga | Abrir `https://mediumvioletred-grouse-788941.hostingersite.com` → ver carrusel de novedades |
| Vue Router funciona | Navegar a `/buscar`, F5 → no 404 |
| Backend auth responde | `POST /auth/login` con credenciales test → JSON |
| Backend libros responde | `GET /api/libros_api.php?action=recientes&limit=4` → JSON con 4 libros |
| `robots.txt` y `sitemap.xml` accesibles | Abrir las URLs |
| OG preview correcto | Pegar URL en WhatsApp/Discord → ver fantasma rojo |
| SSL activo | Candado en la barra del navegador |
| Sesión persiste | Iniciar sesión, F5 → sigue logueado |

---

## 5. Cuando se compre un dominio definitivo

1. hPanel → **Dominios → Añadir dominio** → conectar el dominio comprado.
2. Apuntar el dominio a la carpeta `public_html` del subdominio actual (o moverla — hPanel guía esto).
3. Empaquetar una **release nueva** (`v1.0.1_<fecha>`) siguiendo esta guía. Cambia el dominio en el paso 2.8.
4. Subir como en la sección 3.

> No reutilices el ZIP del subdominio temporal: regenera el frontend (`npm run build`) para que los assets minificados ya incluyan el dominio definitivo en sus strings de email/footer/JSON-LD.

---

## 6. Troubleshooting común

| Síntoma | Causa probable | Solución |
|---|---|---|
| 500 al cargar `/` | `.htaccess` raíz mal copiado | Verificar que existe y `mod_rewrite` activo |
| 404 al refrescar `/buscar` | SPA fallback no funciona | Revisar `public_html/.htaccess`, los `.htaccess` a veces se filtran por ser ocultos al subir |
| Frontend pide a `localhost:8000` | Build cacheado del navegador | Hard refresh (Ctrl+F5), vaciar caché |
| `/auth/*` da 500 o página en blanco | Falta `.env` o credenciales BBDD mal | Revisar `public_html/auth/.env`. Mirar log de errores PHP en hPanel |
| `/api/libros_api.php` da 500 | `conexion.php` mal configurado | Editar credenciales BBDD |
| CORS error en consola | Si todo es mismo origen, no debería pasar | Revisar que el frontend usa rutas relativas (`/auth/*`, `/api/*`), no URLs absolutas a `localhost` |
| Composer dependencies missing | `vendor/` no se subió completo | Resubir `auth/vendor/` por SFTP, o vía SSH `cd auth && composer install --no-dev` |
| Mails no llegan | SMTP mal configurado | Revisar `.env` (`MAIL_*`). Hostinger Premium permite SMTP saliente; usa una app password de Gmail si usas `smtp.gmail.com` |

---

## 7. Checklist rápido antes de publicar una release

- [ ] `npm run build` sin errores
- [ ] `vendor/` de `ApiLoging` instalado con `--no-dev`
- [ ] `.env` real **NO** está en el paquete
- [ ] `.env.example` tiene placeholders, no secretos reales
- [ ] `.htaccess` raíz, `auth/.htaccess` y `api/.htaccess` presentes
- [ ] Dominio reemplazado en `index.html`, `sitemap.xml`, `robots.txt`, `assets/*.js`, `auth/.env.example`, `auth/controllers/AuthController.php`
- [ ] Artefactos de dev borrados (`output.txt`, `test_*.php`, etc.)
- [ ] SQLs en `database/` (`install_databases.sql` y `seed_libros.sql`)
- [ ] README de la release actualizado con versión, fecha y dominio
- [ ] `public_html.zip` generado para subida rápida
