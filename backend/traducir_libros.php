<?php
// Aumenta el tiempo máximo de ejecución del script a 30 minutos
// Muy importante porque traducir cientos o miles de descripciones puede tardar mucho
ini_set('max_execution_time', 1800); // 1800 segundos = 30 minutos
require_once __DIR__ . '/vendor/autoload.php'; // ahora apunta dentro del proyecto
require_once "conexion.php";

use Stichoza\GoogleTranslate\GoogleTranslate; //importa la clase que se usa. LIBRERIA PHP QUE USA GOOGLE TRANSLATE SIN KEY NI PAGO

echo "<h2>Traduciendo libros existentes...</h2>";

$tr = new GoogleTranslate('es'); // traducir a español
// Consulta: SOLO los libros que todavía no tienen título_es o descripcion_es traducidos (o sea, al menos uno de los dos es NULL)
$stmt = $pdo->query("SELECT id, titulo, descripcion FROM libros WHERE titulo_es IS NULL OR descripcion_es IS NULL");
$libros = $stmt->fetchAll(PDO::FETCH_ASSOC); // Obtener todos los resultados como array asociativo

$total = 0; //contador de libros traduccidos con exito

foreach ($libros as $libro) {
    $id = $libro['id'];
    $titulo = $libro['titulo'];
    $descripcion = $libro['descripcion'];
// Traducimos solo si existe el texto original
    // (evitamos enviar cadenas vacías o null a la API)
    $titulo_es = $titulo ? $tr->translate($titulo) : null;
    $descripcion_es = $descripcion ? $tr->translate($descripcion) : null;

    $update = $pdo->prepare("UPDATE libros SET titulo_es = ?, descripcion_es = ? WHERE id = ?");
    $update->execute([$titulo_es, $descripcion_es, $id]);

    $total++;
    echo "Libro ID $id traducido.<br>";

    sleep(1); // evita saturar Google Translate
}

echo "<h3>Total libros traducidos: $total</h3>";