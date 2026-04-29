-- ============================================================================
-- Librum Tenebris — Esquema BBDD libros_api (catálogo y préstamos)
-- Base de datos: `librum-tenebris`
-- ============================================================================
--
-- Crea la base de datos del catálogo VACÍA para un despliegue nuevo.
-- Para poblar la tabla `libros` con datos de demo, ejecutar después
-- `03_libros_seed.sql`.
--
-- USO:
--   mysql -u <usuario> -p < 02_libros_schema.sql
-- O desde phpMyAdmin: pestaña Importar.
--
-- ATENCIÓN — nombre con guion:
--   El nombre `librum-tenebris` requiere comillas inversas en TODAS las
--   queries. Si Hostinger no admite el guion, usa `librum_tenebris` (con
--   guion bajo) y actualiza:
--     - este fichero (busca y reemplaza)
--     - `03_libros_seed.sql`
--     - `backend/libros_api/conexion.php` → $db = "librum_tenebris";
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `librum-tenebris`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `librum-tenebris`;

-- ---- libros ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `libros` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `google_id` VARCHAR(100) DEFAULT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `titulo_es` VARCHAR(255) DEFAULT NULL,
  `autor` VARCHAR(255) NOT NULL,
  `stock` INT(11) DEFAULT 3,
  `descripcion` TEXT DEFAULT NULL,
  `descripcion_es` TEXT DEFAULT NULL,
  `portada` VARCHAR(500) DEFAULT NULL,
  `categoria` VARCHAR(100) DEFAULT NULL,
  `rating` DECIMAL(3,1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- favoritos ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `favoritos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `libro_id` INT(11) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_libro` (`usuario_id`,`libro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ---- prestamos ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prestamos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `nombre_usuario` VARCHAR(255) DEFAULT NULL,
  `libro_id` INT(11) NOT NULL,
  `fecha_prestamo` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `fecha_devolucion` DATETIME DEFAULT NULL,
  `estado` ENUM('pendiente','activo','devuelto') DEFAULT 'activo',
  `rating` INT(11) DEFAULT NULL,
  `fecha_entregado` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Fin del script.
