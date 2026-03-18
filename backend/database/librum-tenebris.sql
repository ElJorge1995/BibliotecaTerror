CREATE DATABASE IF NOT EXISTS `librum-tenebris` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `librum-tenebris`;

CREATE TABLE IF NOT EXISTS `libros` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `google_id` VARCHAR(100) UNIQUE DEFAULT NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `titulo_es` VARCHAR(255) DEFAULT NULL,
    `autor` VARCHAR(255) NOT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `descripcion_es` TEXT DEFAULT NULL,
    `portada` VARCHAR(500) DEFAULT NULL,
    `categoria` VARCHAR(100) DEFAULT NULL,
    `rating` DECIMAL(3,1) DEFAULT NULL
);
