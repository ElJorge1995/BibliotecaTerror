<?php
require_once "conexion.php";

$stmt = $pdo->query("SELECT * FROM libros");
$libros = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Catálogo de libros</title>

<style>
body{
    font-family: Arial;
    background:#111;
    color:white;
    margin:0;
    padding:30px;
}

h1{
    text-align:center;
    margin-bottom:40px;
}

.catalogo{
    display:grid;
    grid-template-columns: repeat(auto-fill,minmax(220px,1fr));
    gap:25px;
}

.libro{
    background:#1e1e1e;
    padding:15px;
    border-radius:8px;
    text-align:center;
}

.libro img{
    width:150px;
    height:220px;
    object-fit:cover;
    margin-bottom:10px;
}

.titulo{
    font-weight:bold;
    margin-bottom:5px;
}

.autor{
    color:#bbb;
    margin-bottom:10px;
}

.descripcion{
    font-size:14px;
    color:#ddd;
}
</style>
</head>

<body>

<h1>Catálogo de libros de terror</h1>

<div class="catalogo">

<?php foreach($libros as $libro): ?>

<div class="libro">

<?php if($libro['portada']){ ?>
<img src="<?= htmlspecialchars($libro['portada']) ?>">
<?php } ?>

<div class="titulo">
<?= htmlspecialchars($libro['titulo_es'] ?: $libro['titulo']) ?>
</div>

<div class="autor">
<?= htmlspecialchars($libro['autor']) ?>
</div>

<div class="descripcion">
<?= htmlspecialchars(substr($libro['descripcion_es'] ?: $libro['descripcion'], 0, 120)) ?>...
</div>

</div>

<?php endforeach; ?>

</div>

</body>
</html>