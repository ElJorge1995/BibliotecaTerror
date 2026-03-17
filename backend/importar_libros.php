<?php
require_once "conexion.php";

echo "<h2>Importando libros de terror...</h2>";

$total = 0; //contador de libros insertados

for ($pagina = 0; $pagina < 5; $pagina++) {//5 paginas

    $startIndex = $pagina * 40;//la api devuelve max. 40 resultados por pagina

    $url = "https://www.googleapis.com/books/v1/volumes?q=subject:horror&langRestrict=en&maxResults=40&startIndex=$startIndex";

    $respuesta = @file_get_contents($url);//El @ suprime warnings

    if (!$respuesta) {
        echo "Error en petición<br>";
        continue;
    }

    $datos = json_decode($respuesta, true); //convertir respuesta JSON en un array asociativo de PHP

    if (empty($datos['items'])) {
        continue;
    }

    foreach ($datos['items'] as $libro) {

        $google_id = $libro['id'] ?? null;// null si no encuentra nada
        $titulo = $libro['volumeInfo']['title'] ?? null;
        $autor = $libro['volumeInfo']['authors'][0] ?? null;
        $descripcion = $libro['volumeInfo']['description'] ?? null;
        $portada = $libro['volumeInfo']['imageLinks']['thumbnail'] ?? null;

        /* FILTROS: solo guardo libros que tienen autor, descripcion y portada */

        if (!$autor) continue;
        if (!$descripcion) continue;
        if (!$portada) continue;

        $descripcion = strip_tags($descripcion);//limpia la descripción de etiquetas HTML que a veces trae Google

        try {

            $stmt = $pdo->prepare("
                INSERT INTO libros
                (google_id, titulo, autor, descripcion, portada)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $google_id,
                $titulo,
                $autor,
                $descripcion,
                $portada
            ]);

            $total++; //Si llegó aquí → se insertó correctamente

        } catch (PDOException $e) {
            // ignorar duplicados
        }
    }

    sleep(1);     // Pausa de 1 segundo entre peticiones para no saturar la API de Google
    // (ayuda a evitar bloqueos por rate limit)
}

echo "<h3>Total libros insertados: $total</h3>";