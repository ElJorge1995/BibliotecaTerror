<?php
require_once __DIR__ . '/conexion.php';
$stmt = $pdo->query("SELECT titulo FROM libros LIMIT 1");
$book = $stmt->fetch();
echo $book['titulo'];
