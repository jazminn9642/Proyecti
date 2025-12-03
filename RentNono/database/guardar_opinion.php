<?php
session_start();
require_once "conexion.php";

if (!isset($_POST["rating"], $_POST["comentario"], $_POST["id_propiedad"])) {
    die("Faltan datos para guardar la reseña");
}

$rating = intval($_POST["rating"]);
$comentario = trim($_POST["comentario"]);
$propiedad_id = intval($_POST["id_propiedad"]);
$usuario_id = $_SESSION["usuario_id"] ?? null;

if (!$usuario_id) {
    die("Debes iniciar sesión para dejar una reseña.");
}

$stmt = $conn->prepare("
    INSERT INTO opiniones (propiedad_id, usuario_id, rating, comentario, estado)
    VALUES (:pid, :uid, :rating, :comentario, 'pendiente')
");

$stmt->bindParam(":pid", $propiedad_id);
$stmt->bindParam(":uid", $usuario_id);
$stmt->bindParam(":rating", $rating);
$stmt->bindParam(":comentario", $comentario);

$stmt->execute();

echo "<h3>¡Reseña enviada! 😊</h3>";
echo "<p>Tu reseña será revisada por un administrador antes de hacerse pública.</p>";
echo '<a href="../usuario_visitante/detalle_publicaciones.php?id=' . $propiedad_id . '">Volver</a>';
