-- ============================================================================
-- Librum Tenebris — Esquema BBDD ApiLoging (autenticación)
-- Base de datos: `bibliouser`
-- ============================================================================
--
-- Crea la base de datos de autenticación VACÍA para un despliegue nuevo
-- (Hostinger u otro). No incluye datos: tras ejecutarlo, el primer usuario
-- admin debe crearse desde la app o con la plantilla del final del archivo.
--
-- USO:
--   mysql -u <usuario> -p < 01_apiloging_schema.sql
-- O desde phpMyAdmin: pestaña Importar.
--
-- TRAS EJECUTARLO:
--   - Ajustar `ApiLoging/.env` con DB_NAME=bibliouser y credenciales reales
--   - Cambiar JWT_SECRET por uno nuevo (no usar el de desarrollo)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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

-- ---- login_locations (alertas de geolocalización en login) -------------
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

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- PLANTILLA opcional: crear el usuario admin inicial
-- ============================================================================
-- Descomenta y ajusta. Genera el hash con:
--   php -r "echo password_hash('TU_PASSWORD', PASSWORD_BCRYPT);"
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

-- Fin del script.
