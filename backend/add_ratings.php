<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=librum-tenebris;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Añadir columna rating a libros
    try {
        $pdo->exec('ALTER TABLE libros ADD COLUMN rating DECIMAL(3,1) DEFAULT 0.0 AFTER stock');
        echo "Columna rating añadida a libros.\n";
    } catch(Exception $e) { echo "Omitido libros: " . $e->getMessage() . "\n"; }

    // Añadir columna rating a prestamos
    try {
        $pdo->exec('ALTER TABLE prestamos ADD COLUMN rating INT DEFAULT NULL AFTER estado');
        echo "Columna rating añadida a prestamos.\n";
    } catch(Exception $e) { echo "Omitido prestamos: " . $e->getMessage() . "\n"; }

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
