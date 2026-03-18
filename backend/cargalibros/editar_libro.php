<?php
require_once "conexion.php";

$id = $_GET['id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM libros WHERE id = ?");
$stmt->execute([$id]);
$libro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$libro) {
    die("Libro no encontrado");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $stmt = $pdo->prepare("
        UPDATE libros SET
        titulo = ?, titulo_es = ?, autor = ?,
        descripcion = ?, descripcion_es = ?,
        portada = ?, categoria = ?, rating = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['titulo'],
        $_POST['titulo_es'],
        $_POST['autor'],
        $_POST['descripcion'],
        $_POST['descripcion_es'],
        $_POST['portada'],
        $_POST['categoria'],
        $_POST['rating'],
        $id
    ]);

    header("Location: admin.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar libro</title>
<style>
body{font-family:Arial;background:#111;color:white;padding:30px;}
form{max-width:500px;margin:auto;background:#1e1e1e;padding:20px;border-radius:10px;}
input,textarea{width:100%;margin-bottom:10px;padding:10px;}
button{width:100%;padding:10px;background:#e63946;color:white;border:none;}
</style>
</head>

<body>

<h1>Editar libro</h1>

<form method="POST">

<input name="titulo" value="<?= htmlspecialchars($libro['titulo']) ?>">
<input name="titulo_es" value="<?= htmlspecialchars($libro['titulo_es']) ?>">
<input name="autor" value="<?= htmlspecialchars($libro['autor']) ?>">

<textarea name="descripcion"><?= htmlspecialchars($libro['descripcion']) ?></textarea>
<textarea name="descripcion_es"><?= htmlspecialchars($libro['descripcion_es']) ?></textarea>

<input name="portada" value="<?= htmlspecialchars($libro['portada']) ?>">
<input name="categoria" value="<?= htmlspecialchars($libro['categoria']) ?>">
<input name="rating" value="<?= htmlspecialchars($libro['rating']) ?>">

<button>Guardar cambios</button>

</form>

</body>
</html>