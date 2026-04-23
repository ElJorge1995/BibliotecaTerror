-- ============================================================================
-- Librum Tenebris — Script de instalación inicial de bases de datos
-- Generado: 2026-04-23
-- ============================================================================
--
-- Este script crea las DOS bases de datos que necesita el proyecto en un
-- servidor limpio (Hostinger u otro). Ejecutarlo una sola vez tras crear
-- el usuario MySQL con permisos suficientes.
--
-- USO en Hostinger (vía phpMyAdmin o CLI):
--   mysql -u <usuario> -p < install_databases.sql
--
-- Tras ejecutarlo, recuerda:
--   1. Ajustar `ApiLoging/.env` (DB_NAME=bibliouser) y `backend/libros_api/
--      conexion.php` (db="librum-tenebris") con las credenciales reales.
--   2. Si tu hosting no permite nombres con guion ('librum-tenebris'), ver
--      sección FINAL de este archivo para renombrar.
--   3. Subir los ficheros de código, copiar el `.mmdb` a ApiLoging/data/,
--      y hacer `composer install` en ApiLoging.
--   4. Opcional: insertar un admin inicial (ver bloque al final).
--
-- NOTA: No se incluyen datos existentes — este script es para un sistema
-- NUEVO. Si estás migrando un sistema antiguo, usa mysqldump con --data.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- BBDD 1: bibliouser (ApiLoging — autenticación centralizada)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `bibliouser`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `bibliouser`;

-- ---- users --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `password_changed_at` DATETIME DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) DEFAULT NULL,
  `dni` VARCHAR(20) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'user',
  `is_email_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `banned_at` DATETIME DEFAULT NULL,
  `banned_by` INT(11) DEFAULT NULL,
  `sessions_invalidated_at` DATETIME DEFAULT NULL,
  `current_session_id` CHAR(64) DEFAULT NULL,
  `require_password_reset` TINYINT(1) NOT NULL DEFAULT 0,
  `email_verified_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_banned_at` (`banned_at`),
  KEY `idx_users_current_session_id` (`current_session_id`),
  KEY `fk_users_banned_by` (`banned_by`),
  CONSTRAINT `fk_users_banned_by` FOREIGN KEY (`banned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- pending_registrations ---------------------------------------------
CREATE TABLE IF NOT EXISTS `pending_registrations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `dni` VARCHAR(20) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_pending_registrations_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- email_verification_tokens -----------------------------------------
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_email_verification_user` (`user_id`),
  KEY `idx_email_verification_expiry` (`expires_at`),
  CONSTRAINT `fk_email_verification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- email_change_tokens -----------------------------------------------
CREATE TABLE IF NOT EXISTS `email_change_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `new_email` VARCHAR(255) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_email_change_user` (`user_id`),
  KEY `idx_email_change_expiry` (`expires_at`),
  CONSTRAINT `fk_email_change_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- password_reset_tokens ---------------------------------------------
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_expiry` (`expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- revoked_tokens ----------------------------------------------------
CREATE TABLE IF NOT EXISTS `revoked_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `token` TEXT DEFAULT NULL,
  `token_hash` CHAR(64) DEFAULT NULL,
  `revoked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_revoked_token_hash` (`token_hash`),
  KEY `idx_revoked_token_prefix` (`token`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- rate_limits -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `key_hash` CHAR(64) NOT NULL,
  `scope_name` VARCHAR(100) NOT NULL,
  `attempts` INT(11) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`key_hash`),
  KEY `idx_rate_limits_scope_updated` (`scope_name`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- security_events ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `security_events` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(100) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `context_json` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_security_events_type_created` (`event_type`,`created_at`),
  KEY `idx_security_events_user_created` (`user_id`,`created_at`),
  CONSTRAINT `fk_security_events_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- login_locations (alertas geo) -------------------------------------
CREATE TABLE IF NOT EXISTS `login_locations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `country_code` CHAR(2) DEFAULT NULL,
  `country_name` VARCHAR(100) DEFAULT NULL,
  `user_agent` VARCHAR(512) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'neutral',
  `token_hash` CHAR(64) DEFAULT NULL,
  `token_expires_at` DATETIME DEFAULT NULL,
  `token_used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_locations_user_created` (`user_id`,`created_at`),
  KEY `idx_login_locations_token_hash` (`token_hash`),
  CONSTRAINT `fk_login_locations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- BBDD 2: librum-tenebris (libros_api — catálogo y préstamos)
-- ============================================================================
--
-- ATENCIÓN: el nombre con guion ('librum-tenebris') requiere comillas inversas
-- en TODAS las queries. Si Hostinger no lo acepta, usa 'librum_tenebris' y
-- actualiza `backend/libros_api/conexion.php` con el nuevo nombre.
--

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

-- ============================================================================
-- FINAL: limpieza y notas
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- ---- PLANTILLA para crear el usuario admin inicial (descomenta y ajusta) ----
--
-- USE `bibliouser`;
--
-- -- El hash de abajo corresponde a la contraseña 'cambiar_en_primer_login'.
-- -- Genera uno nuevo con:   php -r "echo password_hash('TU_PASSWORD', PASSWORD_BCRYPT);"
-- -- y pégalo abajo. O crea un registro normal desde la app y luego:
-- --     UPDATE users SET role='admin' WHERE id=1;
--
-- INSERT INTO `users` (
--   `username`, `email`, `password`, `name`, `first_name`, `last_name`,
--   `dni`, `phone`, `role`, `is_email_verified`, `email_verified_at`
-- ) VALUES (
--   'admin',
--   'admin@libraries.test',
--   '$2y$10$...PEGA_AQUI_EL_HASH_BCRYPT...',
--   'Administrador',
--   'Administrador',
--   '',
--   '00000000A',
--   NULL,
--   'admin',
--   1,
--   NOW()
-- );

-- ---- Si Hostinger NO admite el guion en 'librum-tenebris' ----
--
-- Ejecuta antes de este script:
--     CREATE DATABASE `librum_tenebris` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Y en este archivo sustituye `librum-tenebris` por `librum_tenebris`.
-- También actualiza en `backend/libros_api/conexion.php`:
--     $db = "librum_tenebris";

-- Fin del script.
