<?php
require_once "conexion.php";

// BORRAR
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];

    $stmt = $pdo->prepare("DELETE FROM libros WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: admin.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM libros ORDER BY id DESC");
$libros = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel Admin</title>

<style>
body{font-family:Arial;background:#111;color:white;padding:20px;}
a{color:#e63946;text-decoration:none;}
table{width:100%;border-collapse:collapse;}
td,th{padding:10px;border-bottom:1px solid #333;}
</style>
</head>

<body>

<h1>Panel de administración</h1>

<a href="agregar_libro.php">➕ Añadir libro</a>

<table>
<tr>
<th>ID</th>
<th>Título</th>
<th>Autor</th>
<th>Acciones</th>
</tr>

<?php foreach($libros as $libro): ?>
<tr>
<td><?= $libro['id'] ?></td>
<td><?= htmlspecialchars($libro['titulo_es'] ?: $libro['titulo']) ?></td>
<td><?= htmlspecialchars($libro['autor']) ?></td>
<td>
<a href="editar_libro.php?id=<?= $libro['id'] ?>">✏️ Editar</a> |
<a href="admin.php?eliminar=<?= $libro['id'] ?>" onclick="return confirm('¿Seguro?')">🗑️ Borrar</a>
</td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>