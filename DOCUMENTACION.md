# Librum Tenebris - Documentación Técnica (2026)

Librum Tenebris es una plataforma digital de gestión y catálogo bibliotecario enfocada al género de Terror, Suspenso y Fantasía Oscura. Este documento describe la arquitectura técnica, las dependencias y la estructura general del proyecto.

## 1. Arquitectura del Sistema

El proyecto está dividido en un monolito desacoplado (Servicios de Backend separados + Aplicación Frontend SPA).

### 1.1 Frontend (Capa de Cliente)
- **Framework Core**: Vue 3 (Composition API).
- **Herramienta de Construcción**: Vite.
- **Enrutamiento**: Vue Router (Modo History sin hashes).
- **Gestión de Estado**: Pinia (`useAuthStore` persiste el token JSON Web Token/Sesión y la información del usuario vivo).
- **Estilos**: Vanilla CSS con patrones Glassmorphism, CSS Modules encapsulados en SFC (Single-File Components).
- **Servidor Live**: Despliegue en `localhost:5173`.

### 1.2 Backend (Capa de Servicios PHP)
El Backend está descentralizado en carpetas independientes para simular microservicios de monolito:
1. **API de Autenticación (`/ApiLoging`)**:
   - Gestiona el Login, Registro y Emisión de tokens de sesión JWT o validaciones seguras (`login.php`, `register.php`, `api.php`).
   - Sirve bajo su propio puerto para segregación (`localhost:8000`).
2. **API de Catálogo y Alquileres (`/backend/libros_api`)**:
   - Expone rutas RESTful bajo `libros_api.php` mediante parámetros `?action=...`
   - Permite listar novedades, dar like, gestionar alquileres interactuando con la tabla `prestamos` y filtrando mediante `JOIN`.
   - Sirve en `localhost:8080`.

### 1.3 Base de Datos
- **Motor**: MySQL / MariaDB (Servidor XAMPP).
- **Controlador Base**: PHP Data Objects (PDO).
- **Tablas principales**:
   - `usuarios`: Credenciales de acceso y niveles de perfiles (roles como `admin` o `user`).
   - `libros`: Información del catálogo, portada URL, nota media publicadas, reseñas.
   - `prestamos`: Tabla pivot que une usuarios con libros, manteniendo un historial de fechas y ENUM strictos de status (`pendiente`, `activo`, `devuelto`).

---

## 2. Flujo Exigente de Negocio: Los Alquileres Físicos

1. **Solicitud de Alquiler**: El cliente accede a Catálogo -> Libro, pulsa "Alquilar libro" (si hay stock disponible en el conteo SQL). La BBDD crea una fila en `prestamos` como `'pendiente'`.
2. **Control en Tienda (Admin)**: El Cliente asiste físicamente. El administrador entra a su panel de `AdminPage.vue`, busca la reserva y pulsa **Activar**.
3. **Timer de Castigo**: Al activar, MySQL incrusta el `fecha_prestamo=NOW()` y establece `fecha_devolucion = DATE_ADD(NOW(), +14 DAYS)`. Comienza la cuenta regresiva en rojo si se excede.
4. **Devolución**: El usuario devuelve el libro en tienda real. El administrador pulsa **Devolver**. MySQL incrusta en `fecha_entregado` el datetime de la resolución.

---

## 3. Instrucciones para la Ejecución Local para Desarrolladores

Para alzar todo el proyecto en tu máquina local con éxito debes seguir el orden de encendido de servidores.

1. **Servidor Base de Datos XAMPP**: Enciende Apache y MySQL desde el Control Panel. Importa `librum-tenebris.sql` en phpMyAdmin.
2. **Terminal de API de Login**: En `\ApiLoging` lanza `php -S localhost:8000`.
3. **Terminal de API de Libros**: En `\Distribución` lanza `php -S localhost:8080 -t "backend/libros_api"`.
4. **Terminal Compilador de Frontend Vite**: En `\BibliotecaTerror` lanza `npm run dev`.

Acceder posteriormente a `http://localhost:5173/` montará toda la aplicación Vue inyectada junto a los microservicios activados y funcionales.
