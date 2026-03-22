CREATE DATABASE IF NOT EXISTS `librum-tenebris` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `librum-tenebris`;

CREATE TABLE IF NOT EXISTS `libros` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `google_id` VARCHAR(100) UNIQUE DEFAULT NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `titulo_es` VARCHAR(255) DEFAULT NULL,
    `autor` VARCHAR(255) NOT NULL,
    `stock` INT(11) DEFAULT 3,
    `descripcion` TEXT DEFAULT NULL,
    `descripcion_es` TEXT DEFAULT NULL,
    `portada` VARCHAR(500) DEFAULT NULL,
    `categoria` VARCHAR(100) DEFAULT NULL,
    `rating` DECIMAL(3,1) DEFAULT NULL
);

DROP TABLE IF EXISTS `favoritos`;
CREATE TABLE IF NOT EXISTS `favoritos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NOT NULL,
  `libro_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_libro` (`usuario_id`, `libro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

DROP TABLE IF EXISTS `prestamos`;
CREATE TABLE IF NOT EXISTS `prestamos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `libro_id` INT NOT NULL,
    `fecha_prestamo` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `fecha_devolucion` DATETIME NULL,
    `estado` ENUM('pendiente', 'activo', 'devuelto') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
