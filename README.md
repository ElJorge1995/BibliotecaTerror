# Librum Tenebris — Proyecto Final FP DAW

Trabajo de Fin de Grado del ciclo **DAW** (Desarrollo de Aplicaciones Web).
Una biblioteca digital especializada en literatura de terror, con
autenticación centralizada, catálogo de libros y panel de carga.

## Estructura del repositorio

| Carpeta | Descripción |
|---|---|
| [`BibliotecaTerror/`](BibliotecaTerror/) | Frontend SPA en **Vue 3 + Vite**. Catálogo, fichas de libro, autenticación. |
| [`ApiLoging/`](ApiLoging/) | Backend **PHP MVC** de identidad: registro, login, JWT, gestión de usuarios. |
| [`backend/libros_api/`](backend/libros_api/) | API PHP del catálogo de libros (CRUD + uploads de portadas). |
| [`backend/cargalibros/`](backend/cargalibros/) | Panel de administración para alta, edición, importación y traducción de libros. |
| [`database/`](database/) | Esquemas SQL e instalador de las bases de datos. |
| [`Documentacion/`](Documentacion/) | Memoria del TFG: anteproyecto, planificación y documentación técnica. |
| [`ReleasesEstables/`](ReleasesEstables/) | Builds estables de cada componente. |

## Stack técnico

**Frontend** — Vue 3, Vite, Vue Router, Pinia, Axios, Swiper.
**Backend** — PHP 8 (arquitectura MVC), JWT para autenticación.
**Base de datos** — MySQL (dos bases: `bibliouser` y `librum-tenebris`).
**Entorno de desarrollo** — XAMPP (Apache + MySQL).

## Servicios y puertos (desarrollo)

| # | Servicio | Puerto | Carpeta |
|---|---|---|---|
| 1 | ApiLoging (auth/JWT) | `8000` | `ApiLoging/` |
| 2 | libros_api (catálogo) | `8080` | `backend/libros_api/` |
| 3 | Frontend Vite (Vue 3) | `5173` | `BibliotecaTerror/` |
| — | MySQL | `3306` | XAMPP Control Panel (manual) |

## Cómo arrancarlo

### 1. Base de datos

Arranca **MySQL desde el Control Panel de XAMPP** y ejecuta el instalador:

```sql
SOURCE database/install_databases.sql;
```

Esto crea las dos bases (`bibliouser` para usuarios y `librum-tenebris` para
el catálogo) y carga el seed inicial.

### 2. Backends y frontend (orden estricto)

Lanza los tres servicios en este orden, con una pequeña pausa entre cada
uno. Cada `&` envía el proceso a background y los logs van a `/tmp/`:

```bash
cd "ApiLoging" && php -S localhost:8000 > /tmp/libratenebris_01_apiloging.log 2>&1 &
sleep 1
php -S localhost:8080 -t "backend/libros_api" > /tmp/libratenebris_02_libros.log 2>&1 &
sleep 1
cd "BibliotecaTerror" && npm run dev > /tmp/libratenebris_03_vite.log 2>&1 &
sleep 3
```

> Si es la primera vez, ejecuta `npm install` dentro de `BibliotecaTerror/`
> antes del paso del frontend.

### 3. Verificación

Comprueba que los tres servicios responden:

```bash
curl -s -o /dev/null -w "ApiLoging  /auth/me  : HTTP %{http_code}\n" http://localhost:8000/auth/me
curl -s -o /dev/null -w "libros_api recientes : HTTP %{http_code}\n" "http://localhost:8080/libros_api.php?action=recientes&limit=1"
curl -s -o /dev/null -w "Vite       /         : HTTP %{http_code}\n" http://localhost:5173/
```

Códigos esperados:

| Endpoint | Código | Por qué |
|---|---|---|
| `ApiLoging /auth/me` sin token | **401** | El server responde y el middleware JWT actúa. |
| `libros_api ?action=recientes` | **200** | Catálogo accesible (requiere MySQL up). |
| `Vite /` | **200** | Servidor de desarrollo respondiendo. |

Una vez verificado, abre [http://localhost:5173](http://localhost:5173).

### 4. Cómo parar todo

```bash
taskkill //F //IM php.exe
taskkill //F //IM node.exe
```

> Esto mata **todos** los procesos PHP y Node del sistema. Si tienes otros
> proyectos corriendo, párelos selectivamente con `taskkill //PID <pid> //F`
> usando los PIDs de `netstat -ano | findstr LISTENING`.

## Documentación

Toda la memoria del TFG está en [`Documentacion/`](Documentacion/):

- [`DOCUMENTACION.md`](Documentacion/DOCUMENTACION.md) — documento general.
- [`documentacion_frontend.md`](Documentacion/documentacion_frontend.md) — arquitectura del frontend Vue.
- [`documentacion_backend.md`](Documentacion/documentacion_backend.md) — diseño de los backends PHP.
- [`documentacion_basedatos.md`](Documentacion/documentacion_basedatos.md) — modelo de datos y esquemas SQL.
- [`documentacion_estilos.md`](Documentacion/documentacion_estilos.md) — sistema de diseño y estilos.
- [`documentacion_seo.md`](Documentacion/documentacion_seo.md) — estrategia de SEO.
- [`TFG-Planificacion.pdf`](Documentacion/TFG-Planificacion.pdf) — planificación temporal.
- [`anteproyecto_daw.pdf`](Documentacion/anteproyecto_daw.pdf) — anteproyecto inicial.

## Autores

Proyecto desarrollado como Trabajo de Fin de Grado del ciclo de Desarrollo
de Aplicaciones Web por:

- **Jorge Núñez Granero**
- **Alejandro del Campo Ortiz**
- **Eva María Sánchez Zamora**
