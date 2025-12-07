<?php
require_once '../database/conexion.php'; // usa tu conexión PDO

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $propiedad_id = $_POST['propiedad_id'];
    $rating = $_POST['rating'];
    $comentario = trim($_POST['comentario']);

    if (empty($rating)) {
        die("⚠️ Debes seleccionar una puntuación.");
    }

    // Guardar reseña como pendiente
    $stmt = $conn->prepare("INSERT INTO opiniones (propiedad_id, rating, comentario, aprobado)
                            VALUES (:propiedad_id, :rating, :comentario, 0)");
    $stmt->bindParam(':propiedad_id', $propiedad_id, PDO::PARAM_INT);
    $stmt->bindParam(':rating', $rating);
    $stmt->bindParam(':comentario', $comentario);

    if ($stmt->execute()) {
        echo "<script>
            alert('✅ Tu reseña fue enviada y está pendiente de aprobación.');
            window.history.back();
        </script>";
    } else {
        echo "<script>
            alert('❌ Ocurrió un error al guardar la reseña.');
            window.history.back();
        </script>";
    }
}
?>

