<?php
require_once __DIR__ . '/conexion.php';
try {
    $pdo->exec("ALTER TABLE prestamos ADD COLUMN fecha_entregado DATETIME NULL DEFAULT NULL");
    echo "Exito";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
