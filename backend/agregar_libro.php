<?php
require_once "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $titulo = $_POST["titulo"] ?? null;
    $titulo_es = $_POST["titulo_es"] ?? null;
    $autor = $_POST["autor"] ?? null;
    $descripcion = $_POST["descripcion"] ?? null;
    $descripcion_es = $_POST["descripcion_es"] ?? null;
    $portada = $_POST["portada"] ?? null;
    $categoria = $_POST["categoria"] ?? null;
    $rating = $_POST["rating"] ?? null;

    if ($titulo && $autor) {

        try {
            $stmt = $pdo->prepare("
                INSERT INTO libros 
                (google_id, titulo, titulo_es, autor, descripcion, descripcion_es, portada, categoria, rating)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $titulo,
                $titulo_es,
                $autor,
                $descripcion,
                $descripcion_es,
                $portada,
                $categoria,
                $rating
            ]);

            $mensaje = "✅ Libro añadido correctamente";

        } catch (PDOException $e) {
            $mensaje = "❌ Error al insertar";
        }

    } else {
        $mensaje = "⚠️ Título y autor son obligatorios";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Añadir libro</title>

<style>
body{
    font-family: Arial;
    background:#111;
    color:white;
    padding:30px;
}

h1{
    text-align:center;
}

form{
    max-width:500px;
    margin:0 auto;
    background:#1e1e1e;
    padding:20px;
    border-radius:10px;
}

input, textarea{
    width:100%;
    padding:10px;
    margin-bottom:15px;
    border:none;
    border-radius:5px;
}

button{
    width:100%;
    padding:10px;
    background:#e63946;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

.mensaje{
    text-align:center;
    margin-bottom:20px;
}
.volver{
    text-align:center;
    margin-top:15px;
}
a{
    color:#e63946;
    text-decoration:none;
}
</style>
</head>

<body>

<h1>Añadir libro</h1>

<div class="mensaje"><?= $mensaje ?></div>

<form method="POST">

<input type="text" name="titulo" placeholder="Título (original)" required>

<input type="text" name="titulo_es" placeholder="Título en español (opcional)">

<input type="text" name="autor" placeholder="Autor" required>

<textarea name="descripcion" placeholder="Descripción original"></textarea>

<textarea name="descripcion_es" placeholder="Descripción en español"></textarea>

<input type="text" name="portada" placeholder="URL de la portada">

<input type="text" name="categoria" placeholder="Categoría">

<input type="number" step="0.1" name="rating" placeholder="Rating (0-5)">

<button type="submit">Guardar libro</button>

</form>

<div class="volver">
    <a href="admin.php">⬅ Volver al panel</a>
</div>

</body>
</html>