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

-- ---- Usuario administrador por defecto -----------------------------------
--
-- Credenciales:
--   Email:      admin@libraries.test
--   Password:   Admin1234
--   Rol:        admin
--   Email ya verificado (is_email_verified=1) → puede iniciar sesión sin
--   pasar por el flujo de verificación SMTP.
--
-- El hash bcrypt de abajo corresponde a 'Admin1234'. Para regenerarlo con
-- otra contraseña:
--   php -r "echo password_hash('TU_PASSWORD', PASSWORD_BCRYPT);"
--
-- IMPORTANTE: cambia la contraseña tras el primer login en producción
-- desde el panel de perfil del usuario admin.
--
USE `bibliouser`;

INSERT INTO `users` (
  `username`, `email`, `password`, `name`, `first_name`, `last_name`,
  `dni`, `phone`, `role`, `is_email_verified`, `email_verified_at`
) VALUES (
  'admin',
  'admin@libraries.test',
  '$2y$10$hHmLg.jrgHp3maOM1xh9gO4NENm4zLfxs3e0D8LK2bDt64oFeMoBO',
  'Administrador',
  'Administrador',
  '',
  '00000000A',
  NULL,
  'admin',
  1,
  NOW()
);

-- ---- Si Hostinger NO admite el guion en 'librum-tenebris' ----
--
-- Ejecuta antes de este script:
--     CREATE DATABASE `librum_tenebris` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Y en este archivo sustituye `librum-tenebris` por `librum_tenebris`.
-- También actualiza en `backend/libros_api/conexion.php`:
--     $db = "librum_tenebris";


-- ============================================================================
-- SEED: catálogo inicial de 100 libros (Open Library)
-- ============================================================================
--
-- Inserta los 100 libros de horror clásico del catálogo de demo. Fuente:
-- Open Library (Apache 2.0). Las portadas son URLs públicas en
-- covers.openlibrary.org. Si vuelves a importar este script con datos ya
-- presentes, los INSERTs colisionarán por PK/google_id; descomenta la línea
-- TRUNCATE de abajo para empezar limpio.
-- ============================================================================

SET NAMES utf8mb4;

USE `librum-tenebris`;

-- TRUNCATE TABLE `libros`;

INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (1,'OL-OL407498W','The Power of Darkness',NULL,'Edith Nesbit',3,'A horror book written by Edith Nesbit.',NULL,'https://covers.openlibrary.org/b/id/882715-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (2,'OL-OL3625242W','The Vampyre',NULL,'John William Polidori',3,'A horror book written by John William Polidori.',NULL,'https://covers.openlibrary.org/b/id/4871002-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (3,'OL-OL3388961W','The Great God Pan',NULL,'Arthur Machen',3,'A horror book written by Arthur Machen.',NULL,'https://covers.openlibrary.org/b/id/921610-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (4,'OL-OL41078W','The Fall of the House of Usher',NULL,'Edgar Allan Poe',3,'A horror book written by Edgar Allan Poe.',NULL,'https://covers.openlibrary.org/b/id/11838138-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (5,'OL-OL2288676W','Brood of the Witch-Queen',NULL,'Sax Rohmer',3,'A horror book written by Sax Rohmer.',NULL,'https://covers.openlibrary.org/b/id/2011286-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (6,'OL-OL81634W','Misery',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8259296-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (7,'OL-OL81626W','Carrie',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/9256043-L.jpg','Horror',2.0);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (8,'OL-OL41068W','The Black Cat',NULL,'Edgar Allan Poe',3,'A horror book written by Edgar Allan Poe.',NULL,'https://covers.openlibrary.org/b/id/11709016-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (9,'OL-OL8127201W','The King in Yellow',NULL,'Robert W. Chambers',3,'A horror book written by Robert W. Chambers.',NULL,'https://covers.openlibrary.org/b/id/4752788-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (10,'OL-OL2895536W','Carmilla',NULL,'Sheridan Le Fanu',3,'A horror book written by Sheridan Le Fanu.',NULL,'https://covers.openlibrary.org/b/id/973851-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (11,'OL-OL85891W','The Jewel of Seven Stars',NULL,'Bram Stoker',3,'A horror book written by Bram Stoker.',NULL,'https://covers.openlibrary.org/b/id/2760301-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (12,'OL-OL85892W','Dracula',NULL,'Bram Stoker',3,'A horror book written by Bram Stoker.',NULL,'https://covers.openlibrary.org/b/id/12216503-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (13,'OL-OL1833989W','Le fantôme de l\'opéra',NULL,'Gaston Leroux',3,'A horror book written by Gaston Leroux.',NULL,'https://covers.openlibrary.org/b/id/8245407-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (14,'OL-OL81632W','\'Salem’s Lot',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14654118-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (15,'OL-OL41072W','The Murders in the Rue Morgue',NULL,'Edgar Allan Poe',3,'A horror book written by Edgar Allan Poe.',NULL,'https://covers.openlibrary.org/b/id/11774455-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (16,'OL-OL24156W','The Strange Case of Dr. Jekyll and Mr. Hyde',NULL,'Robert Louis Stevenson',3,'A horror book written by Robert Louis Stevenson.',NULL,'https://covers.openlibrary.org/b/id/295773-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (17,'OL-OL183675W','The Castle of Otranto',NULL,'Horace Walpole',3,'A horror book written by Horace Walpole.',NULL,'https://covers.openlibrary.org/b/id/6468730-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (18,'OL-OL450063W','Frankenstein or The Modern Prometheus',NULL,'Mary Shelley',3,'A horror book written by Mary Shelley.',NULL,'https://covers.openlibrary.org/b/id/12356249-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (19,'OL-OL66534W','Northanger Abbey',NULL,'Jane Austen',3,'A horror book written by Jane Austen.',NULL,'https://covers.openlibrary.org/b/id/12567961-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (20,'OL-OL41081W','The Raven',NULL,'Edgar Allan Poe',3,'A horror book written by Edgar Allan Poe.',NULL,'https://covers.openlibrary.org/b/id/8246077-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (21,'OL-OL2771987W','Herland',NULL,'Charlotte Perkins Gilman',3,'A horror book written by Charlotte Perkins Gilman.',NULL,'https://covers.openlibrary.org/b/id/448130-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (22,'OL-OL52266W','The Invisible Man',NULL,'H. G. Wells',3,'A horror book written by H. G. Wells.',NULL,'https://covers.openlibrary.org/b/id/6419199-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (23,'OL-OL8193416W','The Picture of Dorian Gray',NULL,'Oscar Wilde',3,'A horror book written by Oscar Wilde.',NULL,'https://covers.openlibrary.org/b/id/14314858-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (24,'OL-OL5720023W','Twilight',NULL,'Stephenie Meyer',3,'A horror book written by Stephenie Meyer.',NULL,'https://covers.openlibrary.org/b/id/12641977-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (25,'OL-OL40962W','The Narrative of Arthur Gordon Pym',NULL,'Edgar Allan Poe',3,'A horror book written by Edgar Allan Poe.',NULL,'https://covers.openlibrary.org/b/id/7160877-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (26,'OL-OL276328W','Daisy Miller',NULL,'Henry James',3,'A horror book written by Henry James.',NULL,'https://covers.openlibrary.org/b/id/6482238-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (27,'OL-OL492658W','The Lightning Thief',NULL,'Rick Riordan',3,'A horror book written by Rick Riordan.',NULL,'https://covers.openlibrary.org/b/id/7239831-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (28,'OL-OL71042W','The Magician\'s Nephew',NULL,'C. S. Lewis',3,'A horror book written by C. S. Lewis.',NULL,'https://covers.openlibrary.org/b/id/1072931-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (29,'OL-OL63985W','The Legend of Sleepy Hollow',NULL,'Washington Irving',3,'A horror book written by Washington Irving.',NULL,'https://covers.openlibrary.org/b/id/8243083-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (30,'OL-OL103123W','Fahrenheit 451',NULL,'Ray Bradbury',3,'A horror book written by Ray Bradbury.',NULL,'https://covers.openlibrary.org/b/id/12993656-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (31,'OL-OL381550W','The Island of Dr. Moreau',NULL,'H. G. Wells',3,'A horror book written by H. G. Wells.',NULL,'https://covers.openlibrary.org/b/id/968312-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (32,'OL-OL28993W','The Subtle Knife',NULL,'Philip Pullman',3,'A horror book written by Philip Pullman.',NULL,'https://covers.openlibrary.org/b/id/12614549-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (33,'OL-OL276386W','The Turn of the Screw',NULL,'Henry James',3,'A horror book written by Henry James.',NULL,'https://covers.openlibrary.org/b/id/181493-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (34,'OL-OL27482W','The Hobbit',NULL,'J.R.R. Tolkien',3,'A horror book written by J.R.R. Tolkien.',NULL,'https://covers.openlibrary.org/b/id/14627509-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (35,'OL-OL28988W','Northern Lights',NULL,'Philip Pullman',3,'A horror book written by Philip Pullman.',NULL,'https://covers.openlibrary.org/b/id/8747028-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (36,'OL-OL32466W','A Christmas Carol',NULL,'Charles Dickens',3,'A horror book written by Charles Dickens.',NULL,'https://covers.openlibrary.org/b/id/12875748-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (37,'OL-OL81633W','The Shining',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/12376585-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (38,'OL-OL81613W','It',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8569284-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (39,'OL-OL679358W','Coraline',NULL,'Neil Gaiman',3,'A horror book written by Neil Gaiman.',NULL,'https://covers.openlibrary.org/b/id/14171421-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (40,'OL-OL81628W','The Gunslinger',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8396638-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (41,'OL-OL81631W','Pet Sematary',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/12015500-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (42,'OL-OL176025W','The Haunted Hotel',NULL,'Wilkie Collins',3,'A horror book written by Wilkie Collins.',NULL,'https://covers.openlibrary.org/b/id/2890291-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (43,'OL-OL81629W','The Green Mile',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/9334567-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (44,'OL-OL25595002W','Mary Shelley\'s Frankenstein; or, the Modern Prometheus (1818 text)',NULL,'Mary Shelley',3,'A horror book written by Mary Shelley.',NULL,'https://covers.openlibrary.org/b/id/7267770-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (45,'OL-OL5720022W','Breaking Dawn',NULL,'Stephenie Meyer',3,'A horror book written by Stephenie Meyer.',NULL,'https://covers.openlibrary.org/b/id/12643419-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (46,'OL-OL81630W','The Dead Zone',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14653991-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (47,'OL-OL41050W','The Masque of the Red Death',NULL,'Edgar Allan Poe',3,'A horror book written by Edgar Allan Poe.',NULL,'https://covers.openlibrary.org/b/id/11860130-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (48,'OL-OL5720027W','New Moon',NULL,'Stephenie Meyer',3,'A horror book written by Stephenie Meyer.',NULL,'https://covers.openlibrary.org/b/id/12643406-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (49,'OL-OL28996W','The Amber Spyglass',NULL,'Philip Pullman',3,'A horror book written by Philip Pullman.',NULL,'https://covers.openlibrary.org/b/id/12613246-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (50,'OL-OL81618W','The Stand',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/9255992-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (51,'OL-OL695211W','The Mysteries of Udolpho',NULL,'Ann Radcliffe',3,'A horror book written by Ann Radcliffe.',NULL,'https://covers.openlibrary.org/b/id/118207-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (52,'OL-OL81610W','Cujo',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8570003-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (53,'OL-OL103128W','The Illustrated Man',NULL,'Ray Bradbury',3,'A horror book written by Ray Bradbury.',NULL,'https://covers.openlibrary.org/b/id/9345484-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (54,'OL-OL2700647W','The Empty House and Other Ghost Stories',NULL,'Algernon Blackwood',3,'A horror book written by Algernon Blackwood.',NULL,'https://covers.openlibrary.org/b/id/2808629-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (55,'OL-OL505730W','The Magician',NULL,'William Somerset Maugham',3,'A horror book written by William Somerset Maugham.',NULL,'https://covers.openlibrary.org/b/id/2789948-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (56,'OL-OL77826W','Interview With the Vampire',NULL,'Anne Rice',3,'A horror book written by Anne Rice.',NULL,'https://covers.openlibrary.org/b/id/8401488-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (57,'OL-OL927199W','The Exorcist',NULL,'William Peter Blatty',3,'A horror book written by William Peter Blatty.',NULL,'https://covers.openlibrary.org/b/id/12715730-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (58,'OL-OL76578W','Witch Wood',NULL,'John Buchan',3,'A horror book written by John Buchan.',NULL,'https://covers.openlibrary.org/b/id/4781924-L.jpg','Horror',4.0);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (59,'OL-OL81623W','Firestarter',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/12015446-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (60,'OL-OL81616W','The Drawing of the Three',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14651245-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (61,'OL-OL262586W','Tales of Terror and Mystery',NULL,'Arthur Conan Doyle',3,'A horror book written by Arthur Conan Doyle.',NULL,'https://covers.openlibrary.org/b/id/8311421-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (62,'OL-OL81609W','Bag of Bones',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14653458-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (63,'OL-OL14853463W','Poems',NULL,'Percy Bysshe Shelley',3,'A horror book written by Percy Bysshe Shelley.',NULL,'https://covers.openlibrary.org/b/id/8231548-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (64,'OL-OL134834W','Flowers in the Attic',NULL,'V.C. Andrews',3,'A horror book written by V.C. Andrews.',NULL,'https://covers.openlibrary.org/b/id/14598487-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (65,'OL-OL81627W','Skeleton crew',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14657166-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (66,'OL-OL41059W','The Tell-Tale Heart',NULL,'Edgar Allan Poe',3,'A horror book written by Edgar Allan Poe.',NULL,'https://covers.openlibrary.org/b/id/11851436-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (67,'OL-OL81615W','Wizard and Glass',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14657088-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (68,'OL-OL81625W','A Torre Negra',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8443630-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (69,'OL-OL81612W','The Girl Who Loved Tom Gordon',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14653580-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (70,'OL-OL81601W','On Writing',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/9255939-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (71,'OL-OL619317W','The Haunted Bookshop',NULL,'Christopher Morley',3,'A horror book written by Christopher Morley.',NULL,'https://covers.openlibrary.org/b/id/935927-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (72,'OL-OL40873W','The Road',NULL,'Cormac McCarthy',3,'A horror book written by Cormac McCarthy.',NULL,'https://covers.openlibrary.org/b/id/198120-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (73,'OL-OL3171069W','The Haunting of Hill House',NULL,'Shirley Jackson',3,'A horror book written by Shirley Jackson.',NULL,'https://covers.openlibrary.org/b/id/4289014-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (74,'OL-OL81607W','Needful Things',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8404327-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (75,'OL-OL21747086W','Semilla del Diablo',NULL,'Ira Levin',3,'A horror book written by Ira Levin.',NULL,'https://covers.openlibrary.org/b/id/15054795-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (76,'OL-OL81602W','The Eyes of the Dragon',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8524085-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (77,'OL-OL2713465W','Nightmare Abbey',NULL,'Thomas Love Peacock',3,'A horror book written by Thomas Love Peacock.',NULL,'https://covers.openlibrary.org/b/id/1956635-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (78,'OL-OL81606W','Four Past Midnight',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8413143-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (79,'OL-OL81619W','Christine',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14655985-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (80,'OL-OL872225W','Aura',NULL,'Carlos Fuentes',3,'A horror book written by Carlos Fuentes.',NULL,'https://covers.openlibrary.org/b/id/4480453-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (81,'OL-OL81608W','Night Shift',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14651337-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (82,'OL-OL2056818W','Maus I',NULL,'Art Spiegelman',3,'A horror book written by Art Spiegelman.',NULL,'https://covers.openlibrary.org/b/id/10210168-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (83,'OL-OL149210W','Thinner',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8274288-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (84,'OL-OL15008W','The Collector',NULL,'John Fowles',3,'A horror book written by John Fowles.',NULL,'https://covers.openlibrary.org/b/id/3329936-L.jpg','Horror',3.0);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (85,'OL-OL1911336W','The Day of the Triffids',NULL,'John Wyndham',3,'A horror book written by John Wyndham.',NULL,'https://covers.openlibrary.org/b/id/535027-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (86,'OL-OL81597W','Cell',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14654054-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (87,'OL-OL81620W','The Dark Half',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8530409-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (88,'OL-OL483385W','Stuart Little',NULL,'E. B. White',3,'A horror book written by E. B. White.',NULL,'https://covers.openlibrary.org/b/id/10522876-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (89,'OL-OL81584W','Everything\'s Eventual. 14 Dark Tales',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/8585782-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (90,'OL-OL134890W','Petals on the Wind',NULL,'V. C. Andrews',2,'A horror book written by V. C. Andrews.',NULL,'https://covers.openlibrary.org/b/id/9358432-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (91,'OL-OL103194W','Something Wicked This Way Comes',NULL,'Ray Bradbury',3,'A horror book written by Ray Bradbury.',NULL,'https://covers.openlibrary.org/b/id/9346340-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (92,'OL-OL81621W','Different Seasons',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14655761-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (93,'OL-OL23480W','Red Dragon',NULL,'Thomas Harris',3,'A horror book written by Thomas Harris.',NULL,'https://covers.openlibrary.org/b/id/6997898-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (94,'OL-OL81599W','The talisman',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/14651566-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (95,'OL-OL81593W','The Tommyknockers',NULL,'Stephen King',3,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/6997458-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (96,'OL-OL2700635W','The Willows',NULL,'Algernon Blackwood',3,'A horror book written by Algernon Blackwood.',NULL,'https://covers.openlibrary.org/b/id/2751803-L.jpg','Horror',5.0);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (97,'OL-OL827326W','The Beetle',NULL,'Richard Marsh',3,'A horror book written by Richard Marsh.',NULL,'https://covers.openlibrary.org/b/id/2748335-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (98,'OL-OL81603W','Insomnia',NULL,'Stephen King',2,'A horror book written by Stephen King.',NULL,'https://covers.openlibrary.org/b/id/7886954-L.jpg','Horror',NULL);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (99,'OL-OL13116995W','The Monk: A Romance. In Three Volumes. By M. G. Lewis, ..',NULL,'Matthew Gregory Lewis',3,'A horror book written by Matthew Gregory Lewis.',NULL,'https://covers.openlibrary.org/b/id/6008132-L.jpg','Horror',2.0);
INSERT INTO `libros` (`id`, `google_id`, `titulo`, `titulo_es`, `autor`, `stock`, `descripcion`, `descripcion_es`, `portada`, `categoria`, `rating`) VALUES (100,'OL-OL3454854W','Jaws',NULL,'Peter Benchley',5,'A horror book written by Peter Benchley.',NULL,'https://covers.openlibrary.org/b/id/8440296-L.jpg','Horror',4.3);

-- Fin del seed (100 libros insertados).
-- Fin del script.
