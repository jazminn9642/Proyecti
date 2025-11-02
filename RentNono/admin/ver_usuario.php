<?php
session_start();
include __DIR__ . '/../database/conexion.php';

// 🔒 Verificar sesión de administrador
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// 🔹 Obtener datos del usuario
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tabla = $_GET['tabla'] ?? '';

if (!in_array($tabla, ['usuario_propietario', 'usuario_visitante'])) {
    die("Acceso no válido.");
}

$stmt = $conn->prepare("SELECT * FROM $tabla WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Usuario no encontrado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Actividad del Usuario - RENTNONO</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
:root {
  --verde-principal: #82b16d;
  --verde-oscuro: #4b7c1d;
  --gris-fondo: #f8f9fa;
  --blanco: #ffffff;
  --texto: #2c3e50;
  --rojo: #e74c3c;
}
body {
  font-family: 'Poppins', sans-serif;
  background-color: var(--gris-fondo);
  color: var(--texto);
  margin: 0;
  padding: 20px;
}
.card {
  background: var(--blanco);
  border-radius: 12px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.1);
  padding: 25px;
  margin-bottom: 25px;
}
h2 span { color: var(--verde-oscuro); }
h3 { color: var(--verde-principal); }
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
}
th, td {
  padding: 10px;
  border-bottom: 1px solid #ddd;
  text-align: left;
}
th {
  background-color: var(--verde-principal);
  color: white;
}
.btn-volver {
  text-decoration: none;
  background: var(--verde-principal);
  color: white;
  padding: 10px 18px;
  border-radius: 8px;
  display: inline-block;
  margin-bottom: 20px;
  transition: background 0.3s;
}
.btn-volver:hover { background: var(--verde-oscuro); }
.table-empty {
  text-align: center;
  color: #888;
  font-style: italic;
}
</style>
</head>
<body>

<a href="users.php" class="btn-volver"><i class="fa-solid fa-arrow-left"></i> Volver al panel</a>

<div class="card">
  <h2>Actividad de <span><?= htmlspecialchars($user['nombre']); ?></span></h2>
  <p><strong>Correo:</strong> <?= htmlspecialchars($user['correo']); ?></p>
  <?php if (!empty($user['telefono'])): ?>
    <p><strong>Teléfono:</strong> <?= htmlspecialchars($user['telefono']); ?></p>
  <?php endif; ?>
  <hr>

  <?php if ($tabla === 'usuario_visitante'): ?>
    <h3><i class="fa-solid fa-star"></i> Publicaciones favoritas</h3>
    <table>
      <thead>
        <tr><th>Título</th><th>Tipo</th><th>Precio</th><th>Fecha</th></tr>
      </thead>
      <tbody>
        <!-- 🔹 Simulación de favoritos -->
        <tr><td>Casa en el centro</td><td>Alquiler</td><td>$150.000</td><td>2025-08-12</td></tr>
        <tr><td>Depto con vista al lago</td><td>Venta</td><td>U$S 80.000</td><td>2025-09-30</td></tr>
      </tbody>
    </table>

    <h3 style="margin-top:25px;"><i class="fa-solid fa-comments"></i> Comentarios realizados</h3>
    <table>
      <thead>
        <tr><th>Publicación</th><th>Comentario</th><th>Fecha</th></tr>
      </thead>
      <tbody>
        <tr><td>Casa en el centro</td><td>Hermosa propiedad, me gustaría visitar.</td><td>2025-09-10</td></tr>
        <tr><td>Depto con vista al lago</td><td>¿Sigue disponible?</td><td>2025-09-11</td></tr>
      </tbody>
    </table>

    <!-- ⚙️ CUANDO TENGAS LAS TABLAS REALES:
         SELECT * FROM favoritos WHERE id_usuario = :id
         SELECT * FROM comentarios WHERE id_usuario = :id -->
  
  <?php elseif ($tabla === 'usuario_propietario'): ?>
    <h3><i class="fa-solid fa-house"></i> Propiedades publicadas</h3>
    <table>
      <thead>
        <tr><th>Título</th><th>Dirección</th><th>Precio</th><th>Estado</th></tr>
      </thead>
      <tbody>
        <!-- 🔹 Simulación de publicaciones -->
        <tr><td>Departamento moderno</td><td>Calle San Martín 234</td><td>$250.000</td><td>Publicado</td></tr>
        <tr><td>Casa con pileta</td><td>Ruta 9 Km 32</td><td>$600.000</td><td>Reservado</td></tr>
      </tbody>
    </table>

    <h3 style="margin-top:25px;"><i class="fa-solid fa-comment-dots"></i> Comentarios recientes</h3>
    <table>
      <thead>
        <tr><th>Publicación</th><th>Comentario</th><th>Fecha</th></tr>
      </thead>
      <tbody>
        <tr><td>Departamento moderno</td><td>Excelente ubicación y precio justo.</td><td>2025-10-01</td></tr>
        <tr><td>Casa con pileta</td><td>Muy linda propiedad.</td><td>2025-10-03</td></tr>
      </tbody>
    </table>

    <!-- ⚙️ CUANDO TENGAS LAS TABLAS REALES:
         SELECT * FROM propiedades WHERE id_usuario = :id
         SELECT * FROM comentarios_propietarios WHERE id_usuario = :id -->
  <?php endif; ?>
</div>

</body>
</html>
