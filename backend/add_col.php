<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=librum-tenebris;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('ALTER TABLE prestamos ADD COLUMN nombre_usuario VARCHAR(255) DEFAULT NULL AFTER usuario_id');
    echo 'Columna añadida correctamente';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
