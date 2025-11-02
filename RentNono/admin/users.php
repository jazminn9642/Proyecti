<?php
session_start();
include __DIR__ . '/../database/conexion.php';


if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

//Actualizar última actividad
$now = date('Y-m-d H:i:s');
$update = $conn->prepare("UPDATE usuario_admin SET last_activity = :now WHERE id = :id");
$update->execute([':now' => $now, ':id' => $_SESSION['admin_id']]);

//Refrescar datos del admin (nombre, correo, foto)
$stmtAdmin = $conn->prepare("SELECT nombre, correo, foto_perfil FROM usuario_admin WHERE id = :id");
$stmtAdmin->execute([':id' => $_SESSION['admin_id']]);
$admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
if ($admin) {
    $_SESSION['admin_nombre'] = $admin['nombre'];
    $_SESSION['admin_correo'] = $admin['correo'];
    $_SESSION['admin_foto'] = $admin['foto_perfil'];
}

//Eliminar usuario
if (isset($_GET['delete']) && isset($_GET['tabla'])) {
    $id = (int)$_GET['delete'];
    $tabla = $_GET['tabla'];

    if ($tabla === 'usuario_admin' && $id === (int)$_SESSION['admin_id']) {
        $msg = "No podés eliminar tu propia cuenta.";
    } else {
        $delete = $conn->prepare("DELETE FROM $tabla WHERE id = :id");
        $delete->execute([':id' => $id]);
        $msg = "Usuario eliminado correctamente.";
    }
}

// 🔹 Traer todos los usuarios
$stmt = $conn->query("
    SELECT 
        id,
        CONVERT(nombre USING utf8mb4) AS nombre,
        CONVERT(correo USING utf8mb4) AS correo,
        CONVERT(telefono USING utf8mb4) AS telefono,
        'Administrador' AS role,
        last_activity,
        'usuario_admin' AS tabla
    FROM usuario_admin
    UNION ALL
    SELECT 
        id,
        CONVERT(CAST(nombre AS CHAR) USING utf8mb4) AS nombre,
        CONVERT(CAST(correo AS CHAR) USING utf8mb4) AS correo,
        CONVERT(CAST(telefono AS CHAR) USING utf8mb4) AS telefono,
        'Propietario' AS role,
        NULL AS last_activity,
        'usuario_propietario' AS tabla
    FROM usuario_propietario
    UNION ALL
    SELECT 
        id,
        CONVERT(nombre USING utf8mb4) AS nombre,
        CONVERT(correo USING utf8mb4) AS correo,
        NULL AS telefono,
        'Visitante' AS role,
        NULL AS last_activity,
        'usuario_visitante' AS tabla
    FROM usuario_visitante
    ORDER BY nombre ASC
");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

//Estado online/offline
function isOnline($last_activity, $role) {
    if ($role !== 'Administrador' || !$last_activity) return false;
    return (time() - strtotime($last_activity)) <= 90;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel de Administración - RENTNONO</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
:root {
  --verde-principal: #82b16d;
  --verde-oscuro: #4b7c1d;
  --gris-fondo: #f4f6f5;
  --blanco: #ffffff;
  --texto: #2c3e50;
  --rojo: #e74c3c;
  --celeste: #5dade2;
  --sombra: rgba(0,0,0,0.08);
}
body {
  font-family: 'Poppins', sans-serif;
  background: var(--gris-fondo);
  margin: 0;
  padding: 0;
  color: var(--texto);
}

/* NAVBAR */
.admin-navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--verde-principal);
  color: white;
  padding: 18px 30px;
  border-bottom: 4px solid var(--verde-oscuro);
}
.admin-navbar h1 {
  margin: 0;
  font-size: 1.6rem;
  letter-spacing: 0.5px;
}
.nav-options {
  display: flex;
  align-items: center;
  gap: 20px;
}
.nav-options a {
  color: white;
  text-decoration: none;
  font-weight: 500;
  transition: opacity 0.3s;
}
.nav-options a:hover { opacity: 0.8; }
.nav-options img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid white;
}

/* CONTENEDOR PRINCIPAL */
.container {
  max-width: 1200px;
  margin: 40px auto;
  background: var(--blanco);
  border-radius: 14px;
  box-shadow: 0 4px 10px var(--sombra);
  padding: 25px 35px;
}
h2 {
  color: var(--verde-oscuro);
  margin-bottom: 15px;
  font-size: 1.5rem;
}

/* TABLA */
.table-container {
  overflow-x: auto;
}
table {
  width: 100%;
  border-collapse: collapse;
  border-radius: 12px;
  overflow: hidden;
}
thead {
  background-color: var(--verde-principal);
  color: white;
}
th, td {
  padding: 14px 12px;
  text-align: left;
  border-bottom: 1px solid #e0e0e0;
  font-size: 0.95rem;
}
tbody tr:hover {
  background-color: #f8f9f8;
}
.actions {
  display: flex;
  gap: 10px;
  align-items: center;
}
.actions i {
  cursor: pointer;
  font-size: 1.2rem;
  transition: transform 0.2s, opacity 0.2s;
}
.actions i:hover { transform: scale(1.15); opacity: 0.8; }

.edit-btn { color: var(--verde-oscuro); }
.delete-btn { color: var(--rojo); }
.view-btn { color: var(--celeste); }

.status-online { color: var(--verde-oscuro); font-weight: 600; }
.status-offline { color: #999; }

/* MODAL */
.modal {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.5);
  justify-content: center; align-items: center;
  z-index: 1000;
}
.modal-content {
  background: white;
  border-radius: 12px;
  padding: 25px;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.2);
  text-align: center;
}
.modal-content input {
  width: 100%;
  padding: 10px;
  margin-bottom: 10px;
  border-radius: 6px;
  border: 1px solid #ccc;
}
button {
  border: none;
  border-radius: 8px;
  cursor: pointer;
}
.save-btn {
  background: var(--verde-principal);
  color: white;
  padding: 10px 15px;
}
.cancel-btn {
  background: var(--rojo);
  color: white;
  padding: 10px 15px;
  margin-top: 10px;
}
@media (max-width: 768px) {
  .container { padding: 20px; }
  th, td { padding: 10px; font-size: 0.85rem; }
}
</style>
</head>
<body>

<header class="admin-navbar">
  <h1>RENTNONO</h1>
  <div class="nav-options">
    <span>¡Bienvenid@, <strong><?= htmlspecialchars($_SESSION['admin_nombre'] ?? 'Administrador'); ?></strong>!</span>
    <?php if (!empty($_SESSION['admin_foto'])): ?>
      <img src="../uploads/<?= htmlspecialchars($_SESSION['admin_foto']); ?>" alt="Foto de perfil">
    <?php endif; ?>
    <a href="#" onclick="openProfileModal()"><i class="fa-solid fa-user-pen"></i></a>
    <a href="logout_admin.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
  </div>
</header>

<div class="container">
  <?php if (isset($msg)): ?><p><?= $msg; ?></p><?php endif; ?>

  <h2><i class="fa-solid fa-users"></i> Usuarios registrados</h2>

  <!--Barra de búsqueda -->
  <div style="margin-bottom:20px;">
    <input type="text" id="searchInput" placeholder="Buscar..." 
           style="width:50%; padding:10px; border-radius:8px; border:1px solid #ccc;">
  </div>

  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Correo</th>
          <th>Teléfono</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
          <tr>
            <td><?= $u['id']; ?></td>
            <td><?= htmlspecialchars($u['nombre']); ?></td>
            <td><?= htmlspecialchars($u['correo']); ?></td>
            <td><?= htmlspecialchars($u['telefono'] ?? ''); ?></td>
            <td><?= $u['role']; ?></td>
            <td><?= isOnline($u['last_activity'], $u['role']) ? '<span class="status-online">● En línea</span>' : '<span class="status-offline">● Desconectado</span>'; ?></td>
            <td class="actions">
              <i class="fa-solid fa-pen-to-square edit-btn" onclick="openEditModal(<?= $u['id']; ?>, '<?= htmlspecialchars($u['nombre']); ?>', '<?= htmlspecialchars($u['correo']); ?>', '<?= htmlspecialchars($u['telefono'] ?? ''); ?>', '<?= $u['tabla']; ?>')"></i>
              <a href="?delete=<?= $u['id']; ?>&tabla=<?= $u['tabla']; ?>" onclick="return confirm('¿Eliminar este usuario?');"><i class="fa-solid fa-trash delete-btn"></i></a>
              <?php if ($u['role'] != 'Administrador'): ?>
                <a href="ver_usuario.php?tabla=<?= $u['tabla']; ?>&id=<?= $u['id']; ?>"><i class="fa-solid fa-eye view-btn"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL EDITAR PERFIL -->
<div id="profileModal" class="modal">
  <div class="modal-content">
    <h3 style="color: var(--verde-oscuro); margin-bottom: 15px;">Editar perfil</h3>
    <form method="POST" action="update_profile.php" enctype="multipart/form-data">
      <input type="text" name="nombre" value="<?= htmlspecialchars($_SESSION['admin_nombre']); ?>" placeholder="Nombre" required>
      <input type="email" name="correo" value="<?= htmlspecialchars($_SESSION['admin_correo']); ?>" placeholder="Correo" required>
      <label>Foto de perfil:</label>
      <input type="file" name="foto_perfil" accept="image/*">
      <button type="submit" class="save-btn">Guardar cambios</button>
    </form>
    <button onclick="closeProfileModal()" class="cancel-btn">Cancelar</button>
  </div>
</div>

<!-- MODAL EDITAR USUARIO -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <h3 style="color: var(--verde-oscuro); margin-bottom: 15px;">Editar Usuario</h3>
    <form method="POST" action="update_user.php">
      <input type="hidden" name="id" id="edit_id">
      <input type="hidden" name="tabla" id="edit_tabla">
      <input type="text" name="nombre" id="edit_nombre" placeholder="Nombre" required>
      <input type="email" name="correo" id="edit_correo" placeholder="Correo" required>
      <input type="text" name="telefono" id="edit_telefono" placeholder="Teléfono">
      <button type="submit" class="save-btn">Guardar cambios</button>
    </form>
    <button onclick="closeEditModal()" class="cancel-btn">Cancelar</button>
  </div>
</div>

<script>
//Búsqueda dinámica
document.getElementById("searchInput").addEventListener("keyup", function() {
  const filter = this.value.toLowerCase();
  const rows = document.querySelectorAll("tbody tr");
  rows.forEach(row => {
    const nameCell = row.querySelector("td:nth-child(2)");
    if (nameCell) {
      const nameText = nameCell.textContent.toLowerCase();
      row.style.display = nameText.includes(filter) ? "" : "none";
    }
  });
});

function openProfileModal() {
  document.getElementById("profileModal").style.display = "flex";
}
function closeProfileModal() {
  document.getElementById("profileModal").style.display = "none";
}

function openEditModal(id, nombre, correo, telefono, tabla) {
  document.getElementById("edit_id").value = id;
  document.getElementById("edit_nombre").value = nombre;
  document.getElementById("edit_correo").value = correo;
  document.getElementById("edit_telefono").value = telefono;
  document.getElementById("edit_tabla").value = tabla;
  document.getElementById("editModal").style.display = "flex";
}
function closeEditModal() {
  document.getElementById("editModal").style.display = "none";
}
</script>
</body>
</html>
