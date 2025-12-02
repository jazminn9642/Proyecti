<?php
session_start();
require_once __DIR__ . '/../database/conexion.php';
require_once __DIR__ . '/../database/session.php';

/* ====== HEADERS DE SEGURIDAD ====== */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

/* ====== SOLO ADMIN ====== */
if (!isset($_SESSION['admin_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$adminNombre = $_SESSION['admin_nombre'] ?? 'Administrador';

/* ====== FUNCIÓN SANITIZAR ====== */
function sanitizar($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

/* ====== TOKEN CSRF ====== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ====== PROCESAR EDICIÓN (POST) ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errores[] = "Token de seguridad inválido.";
    } else {
        // datos seguros
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $rol = isset($_POST['rol']) ? $_POST['rol'] : '';
        $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';

        // validaciones básicas
        $errores = [];
        if ($id <= 0) $errores[] = "ID inválido.";
        if ($nombre === '') $errores[] = "El nombre no puede estar vacío.";
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = "Correo inválido.";

        // mapa rol -> tabla
        $mapaTablas = [
            'admin' => 'usuario_admin',
            'propietario' => 'usuario_propietario',
            'visitante' => 'usuario_visitante'
        ];

        if (!isset($mapaTablas[$rol])) $errores[] = "Rol inválido.";

        if (empty($errores)) {
            $tabla = $mapaTablas[$rol];
            // Preparar UPDATE (no parametrizamos el nombre de la tabla; lo elegimos del mapa)
            $sql = "UPDATE `$tabla` SET nombre = :nombre, correo = :correo WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $ok = $stmt->execute([
                ':nombre' => $nombre,
                ':correo' => $correo,
                ':id' => $id
            ]);
            if ($ok) {
                // Registrar en logs
                $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion) VALUES (?, 'admin', ?)";
                $conn->prepare($logSql)->execute([$adminNombre, "Editó usuario ID $id ($rol)"]);
                
                header("Location: indexadmin.php?edit=ok");
                exit;
            } else {
                $errores[] = "Error al actualizar en la base de datos.";
            }
        }
    }
    // si hay errores, los mostramos más abajo (no redirect)
}

/* ====== PAGINACIÓN LOGS ====== */
$logsPorPagina = 20;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $logsPorPagina;

$totalLogs = $conn->query("SELECT COUNT(*) FROM logs_actividad")->fetchColumn();
$totalPaginas = ceil($totalLogs / $logsPorPagina);

/* ====== TRAER DATOS (LOGS + USUARIOS) ====== */
/* Logs con paginación */
$logs = $conn->query("
    SELECT usuario_nombre, rol, accion, fecha, DATE(fecha) as fecha_simple
    FROM logs_actividad
    ORDER BY fecha DESC
    LIMIT $logsPorPagina OFFSET $offset
")->fetchAll(PDO::FETCH_ASSOC);

/* Usuarios por tipo */
$admins = $conn->query("SELECT id, nombre, correo, 'admin' AS rol FROM usuario_admin")->fetchAll(PDO::FETCH_ASSOC);
$propietarios = $conn->query("SELECT id, nombre, correo, rol FROM usuario_propietario")->fetchAll(PDO::FETCH_ASSOC);
$visitantes = $conn->query("SELECT id, nombre, correo, rol FROM usuario_visitante")->fetchAll(PDO::FETCH_ASSOC);

/* Totales */
$totalAdmins = count($admins);
$totalPropietarios = count($propietarios);
$totalVisitantes = count($visitantes);
$totalUsuarios = $totalAdmins + $totalPropietarios + $totalVisitantes;

/* ====== ESTADÍSTICAS ADICIONALES ====== */
$logsHoy = $conn->query("
    SELECT COUNT(*) FROM logs_actividad 
    WHERE DATE(fecha) = CURDATE()
")->fetchColumn();

$usuariosActivos = $conn->query("
    SELECT COUNT(DISTINCT usuario_nombre) FROM logs_actividad 
    WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel Admin | RENTNONO</title>
<link rel="stylesheet" href="../estilos/admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <h2 class="logo">RENTNONO</h2>

        <button class="menu-btn activo" data-seccion="dashboard" id="btnDashboard">
            <i class="fa-solid fa-chart-line"></i> Dashboard
        </button>

        <button class="menu-btn" data-seccion="logs" id="btnLogs">
            <i class="fa-solid fa-clock"></i> Logs
        </button>

        <!-- Usuarios desplegable -->
        <button class="menu-btn" id="btnUsuarios">
            <i class="fa-solid fa-users"></i> Usuarios
            <i class="fa-solid fa-chevron-down flecha"></i>
        </button>

        <div class="submenu" id="submenuUsuarios">
            <button class="submenu-btn" data-tabla="admins">Administradores</button>
            <button class="submenu-btn" data-tabla="propietarios">Propietarios</button>
            <button class="submenu-btn" data-tabla="visitantes">Visitantes</button>
        </div>

        <button class="menu-btn logout-btn" id="btnLogout">
            <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
        </button>
    </aside>

    <!-- CONTENIDO -->
    <main class="contenido">

        <!-- DASHBOARD -->
        <section id="dashboard" class="seccion visible">
            <h2>👋 ¡Bienvenida, <?= htmlspecialchars($adminNombre) ?>!</h2>
            
            <div class="dashboard-resumen">
                <div class="resumen-card">
                    <i class="fa-solid fa-users"></i>
                    <h3>Total Usuarios</h3>
                    <span><?= $totalUsuarios ?></span>
                </div>
                <div class="resumen-card">
                    <i class="fa-solid fa-user-shield"></i>
                    <h3>Administradores</h3>
                    <span><?= $totalAdmins ?></span>
                </div>
                <div class="resumen-card">
                    <i class="fa-solid fa-house-user"></i>
                    <h3>Propietarios</h3>
                    <span><?= $totalPropietarios ?></span>
                </div>
                <div class="resumen-card">
                    <i class="fa-solid fa-user"></i>
                    <h3>Visitantes</h3>
                    <span><?= $totalVisitantes ?></span>
                </div>
                <div class="resumen-card">
                    <i class="fa-solid fa-list"></i>
                    <h3>Total Logs</h3>
                    <span><?= $totalLogs ?></span>
                </div>
            </div>

            <div class="estadisticas-avanzadas">
                <div class="resumen-card">
                    <i class="fa-solid fa-clock"></i>
                    <h3>Logs Hoy</h3>
                    <span><?= $logsHoy ?></span>
                </div>
                <div class="resumen-card">
                    <i class="fa-solid fa-user-check"></i>
                    <h3>Usuarios Activos</h3>
                    <span><?= $usuariosActivos ?></span>
                </div>
            </div>
        </section>

        <!-- LOGS -->
        <section id="logs" class="seccion">
            <h2>Logs de actividad</h2>
            
            <!-- Paginación -->
            <div class="paginacion">
                <?php if ($paginaActual > 1): ?>
                    <a href="?pagina=<?= $paginaActual - 1 ?>" class="pagina-btn">← Anterior</a>
                <?php endif; ?>
                
                <span>Página <?= $paginaActual ?> de <?= $totalPaginas ?></span>
                
                <?php if ($paginaActual < $totalPaginas): ?>
                    <a href="?pagina=<?= $paginaActual + 1 ?>" class="pagina-btn">Siguiente →</a>
                <?php endif; ?>
            </div>

            <div class="tabla-contenedor">
                <table id="tablaLogs">
                    <thead>
                        <tr>
                            <th data-ordenable="true">Usuario</th>
                            <th data-ordenable="true">Rol</th>
                            <th data-ordenable="true">Acción</th>
                            <th data-ordenable="true">Fecha</th>
                            <th data-ordenable="true">Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars($l['usuario_nombre']) ?></td>
                            <td><?= htmlspecialchars($l['rol']) ?></td>
                            <td><?= htmlspecialchars($l['accion']) ?></td>
                            <td><?= htmlspecialchars($l['fecha_simple']) ?></td>
                            <td><?= date('H:i:s', strtotime($l['fecha'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- USUARIOS -->
        <section id="usuarios" class="seccion">
            <h2 id="tituloUsuarios">Usuarios</h2>

            <!-- Tabla unificada visual: solo mostramos las 3 tablas por separado -->
            <div class="tabla-contenedor">
                <table id="tablaAdmins" class="tabla-usuarios">
                    <thead>
                        <tr>
                            <th data-ordenable="true">Nombre</th>
                            <th data-ordenable="true">Correo</th>
                            <th data-ordenable="true">Rol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['correo']) ?></td>
                            <td><?= htmlspecialchars($u['rol']) ?></td>
                            <td>
                                <button class="editarBtn" data-id="<?= $u['id'] ?>" data-rol="admin"><i class="fa-solid fa-pen"></i> Editar</button>
                                <button class="eliminarBtn" data-nombre="<?= htmlspecialchars($u['nombre']) ?>"><i class="fa-solid fa-trash"></i> Eliminar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <table id="tablaPropietarios" class="tabla-usuarios" style="display:none;">
                    <thead>
                        <tr>
                            <th data-ordenable="true">Nombre</th>
                            <th data-ordenable="true">Correo</th>
                            <th data-ordenable="true">Rol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($propietarios as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['correo']) ?></td>
                            <td><?= htmlspecialchars($u['rol']) ?></td>
                            <td>
                                <button class="editarBtn" data-id="<?= $u['id'] ?>" data-rol="propietario"><i class="fa-solid fa-pen"></i> Editar</button>
                                <button class="eliminarBtn" data-nombre="<?= htmlspecialchars($u['nombre']) ?>"><i class="fa-solid fa-trash"></i> Eliminar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <table id="tablaVisitantes" class="tabla-usuarios" style="display:none;">
                    <thead>
                        <tr>
                            <th data-ordenable="true">Nombre</th>
                            <th data-ordenable="true">Correo</th>
                            <th data-ordenable="true">Rol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitantes as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['correo']) ?></td>
                            <td><?= htmlspecialchars($u['rol']) ?></td>
                            <td>
                                <button class="editarBtn" data-id="<?= $u['id'] ?>" data-rol="visitante"><i class="fa-solid fa-pen"></i> Editar</button>
                                <button class="eliminarBtn" data-nombre="<?= htmlspecialchars($u['nombre']) ?>"><i class="fa-solid fa-trash"></i> Eliminar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<!-- MODAL EDITAR MEJORADO -->
<div class="modal" id="modalEditar" style="display:none;">
    <div class="modal-contenido modal-mejorado">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-edit"></i> Editar Usuario</h3>
            <span class="cerrar">&times;</span>
        </div>
        
        <div class="modal-body">
            <?php if (!empty($errores ?? [])): ?>
                <div class="errores">
                    <?php foreach ($errores as $err): ?>
                        <div class="error"><i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form id="formEditar" method="POST" action="indexadmin.php">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="rol" id="editRol">

                <div class="form-group">
                    <label for="editNombre"><i class="fa-solid fa-user"></i> Nombre</label>
                    <input type="text" name="nombre" id="editNombre" required>
                    <div class="form-help">Mínimo 2 caracteres</div>
                </div>

                <div class="form-group">
                    <label for="editCorreo"><i class="fa-solid fa-envelope"></i> Correo Electrónico</label>
                    <input type="email" name="correo" id="editCorreo" required>
                    <div class="form-help">Formato: usuario@ejemplo.com</div>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-shield"></i> Tipo de Usuario</label>
                    <div class="rol-display" id="displayRol">Administrador</div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar">Cancelar</button>
                    <button type="submit" class="btn-guardar">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DE CONFIRMACIÓN PARA ELIMINAR -->
<div class="modal" id="modalConfirmarEliminar" style="display:none;">
    <div class="modal-contenido modal-confirmacion">
        <div class="modal-header">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> Confirmar Eliminación</h3>
        </div>
        <div class="modal-body">
            <div class="confirmacion-icono">
                <i class="fa-solid fa-trash"></i>
            </div>
            <p id="textoConfirmacion">¿Estás seguro de que quieres eliminar este usuario?</p>
            <div class="modal-actions">
                <button type="button" class="btn-cancelar" id="cancelarEliminar">Cancelar</button>
                <button type="button" class="btn-eliminar-confirmar" id="confirmarEliminar">
                    <i class="fa-solid fa-trash"></i> Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../script/admin.js"></script>
</body>
</html>