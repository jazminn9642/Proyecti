<?php
session_start();
require_once __DIR__ . '/../database/conexion.php';
require_once __DIR__ . '/../database/session.php';

/* ====== HEADERS DE SEGURIDAD MEJORADOS ====== */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ====== SOLO ADMIN ====== */
if (!isset($_SESSION['admin_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$adminNombre = $_SESSION['admin_nombre'] ?? 'Administrador';

/* ====== FUNCIÓN SANITIZAR MEJORADA ====== */
function sanitizar($dato) {
    if (is_array($dato)) {
        return array_map('sanitizar', $dato);
    }
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

/* ====== TOKEN CSRF MEJORADO ====== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* ====== FUNCIONES AUXILIARES MEJORADAS ====== */

/**
 * Verificar y crear columna estado si no existe
 */
function verificarColumnaEstado($conn, $tabla) {
    try {
        // Verificar si la tabla existe primero
        $checkTable = $conn->query("SHOW TABLES LIKE '$tabla'")->fetch();
        if (!$checkTable) {
            error_log("Tabla $tabla no existe");
            return false;
        }
        
        $checkColumn = $conn->query("SHOW COLUMNS FROM `$tabla` LIKE 'estado'")->fetch();
        if (!$checkColumn) {
            $conn->exec("ALTER TABLE `$tabla` ADD COLUMN estado TINYINT(1) DEFAULT 1");
            error_log("Columna 'estado' añadida a la tabla $tabla");
        }
        return true;
    } catch (Exception $e) {
        error_log("Error al verificar/crear columna estado en $tabla: " . $e->getMessage());
        return false;
    }
}

/**
 * Construir consulta de usuarios con validación mejorada
 */
function construirConsultaUsuarios($conn, $tabla, $limit, $offset) {
    // Validar que la tabla existe
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE '$tabla'")->fetch();
        if (!$checkTable) {
            return null;
        }
        
        $checkEstado = $conn->query("SHOW COLUMNS FROM `$tabla` LIKE 'estado'")->fetch();
        if ($checkEstado) {
            return "SELECT id, nombre, correo, '$tabla' AS rol, COALESCE(estado, 1) as estado FROM `$tabla` LIMIT :limit OFFSET :offset";
        } else {
            return "SELECT id, nombre, correo, '$tabla' AS rol, 1 as estado FROM `$tabla` LIMIT :limit OFFSET :offset";
        }
    } catch (Exception $e) {
        error_log("Error construyendo consulta para tabla $tabla: " . $e->getMessage());
        return null;
    }
}

/**
 * Ejecutar consulta paginada de forma segura
 */
function obtenerUsuariosPaginados($conn, $tabla, $limit, $offset) {
    $sql = construirConsultaUsuarios($conn, $tabla, $limit, $offset);
    if (!$sql) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo usuarios de $tabla: " . $e->getMessage());
        return [];
    }
}

// Verificar y crear columna estado en todas las tablas de usuarios
$tablasUsuarios = ['usuario_admin', 'usuario_propietario', 'usuario_visitante'];
foreach ($tablasUsuarios as $tabla) {
    verificarColumnaEstado($conn, $tabla);
}

/* ====== PROCESAR AGREGAR NUEVO USUARIO ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar_usuario') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido.']);
        exit;
    }
    
    // Obtener y sanitizar datos
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $rol = trim($_POST['rol'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validaciones
    $errores = [];
    
    if (empty($nombre)) $errores[] = 'El nombre es requerido';
    if (strlen($nombre) < 2) $errores[] = 'El nombre debe tener al menos 2 caracteres';
    if (strlen($nombre) > 100) $errores[] = 'El nombre no puede exceder 100 caracteres';
    
    if (empty($correo)) $errores[] = 'El correo es requerido';
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Correo electrónico inválido';
    if (strlen($correo) > 255) $errores[] = 'El correo no puede exceder 255 caracteres';
    
    if (empty($rol) || !in_array($rol, ['admin', 'propietario', 'visitante'])) {
        $errores[] = 'Rol de usuario inválido';
    }
    
    if (empty($password)) $errores[] = 'La contraseña es requerida';
    if (strlen($password) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres';
    if ($password !== $confirm_password) $errores[] = 'Las contraseñas no coinciden';
    
    // Verificar si el correo ya existe
    if (empty($errores)) {
        $mapaTablas = [
            'admin' => 'usuario_admin',
            'propietario' => 'usuario_propietario',
            'visitante' => 'usuario_visitante'
        ];
        
        $tabla = $mapaTablas[$rol];
        
        try {
            $sqlCheck = "SELECT COUNT(*) FROM `$tabla` WHERE correo = :correo";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([':correo' => $correo]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                $errores[] = 'Este correo ya está registrado';
            }
        } catch (Exception $e) {
            error_log("Error verificando correo: " . $e->getMessage());
            $errores[] = 'Error al verificar el correo';
        }
    }
    
    // Si hay errores, devolverlos
    if (!empty($errores)) {
        echo json_encode(['success' => false, 'errors' => $errores]);
        exit;
    }
    
    // Insertar nuevo usuario
    try {
        // Hashear la contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Determinar el nombre de la columna de contraseña según la tabla
        $columna_password = ($rol === 'admin') ? 'contrasena' : 'password';
        
        $sqlInsert = "INSERT INTO `$tabla` (nombre, correo, $columna_password, estado, fecha_creacion) 
                      VALUES (:nombre, :correo, :password, 1, NOW())";
        
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->execute([
            ':nombre' => $nombre,
            ':correo' => $correo,
            ':password' => $password_hash
        ]);
        
        $nuevoId = $conn->lastInsertId();
        
        // Registrar en logs
        $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
        $conn->prepare($logSql)->execute([$adminNombre, "Agregó nuevo usuario $nombre (ID: $nuevoId, Rol: $rol)"]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Usuario agregado exitosamente',
            'id' => $nuevoId,
            'nombre' => $nombre,
            'correo' => $correo,
            'rol' => $rol
        ]);
        
    } catch (Exception $e) {
        error_log("Error agregando usuario: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos']);
    }
    
    exit;
}

/* ====== OBTENER DETALLES DE USUARIO PARA MODAL ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'obtener_detalles_usuario') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido.']);
        exit;
    }
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $rol = isset($_POST['rol']) ? $_POST['rol'] : '';
    
    $mapaTablas = [
        'admin' => 'usuario_admin',
        'propietario' => 'usuario_propietario',
        'visitante' => 'usuario_visitante'
    ];
    
    if ($id > 0 && isset($mapaTablas[$rol])) {
        $tabla = $mapaTablas[$rol];
        
        try {
            // Obtener detalles del usuario
            $sql = "SELECT id, nombre, correo, estado, 
                           COALESCE(fecha_creacion, NOW()) as fecha_creacion,
                           COALESCE(fecha_actualizacion, NOW()) as fecha_actualizacion
                    FROM `$tabla` 
                    WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                echo json_encode([
                    'success' => true,
                    'usuario' => $usuario
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            }
            
        } catch (Exception $e) {
            error_log("Error obteniendo detalles de usuario: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error en la base de datos']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    }
    
    exit;
}

/* ====== PROCESAR PAGINACIÓN VIA AJAX ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_pagina') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido']);
        exit;
    }
    
    $tipo = $_POST['tipo'] ?? '';
    $pagina = (int)($_POST['pagina'] ?? 1);
    if ($pagina < 1) $pagina = 1;
    
    $usuariosPorPagina = 10;
    $offset = ($pagina - 1) * $usuariosPorPagina;
    
    $mapaTablas = [
        'admins' => 'usuario_admin',
        'propietarios' => 'usuario_propietario', 
        'visitantes' => 'usuario_visitante'
    ];
    
    $mapaRoles = [
        'admins' => 'admin',
        'propietarios' => 'propietario',
        'visitantes' => 'visitante'
    ];
    
    if (isset($mapaTablas[$tipo])) {
        $tabla = $mapaTablas[$tipo];
        $rol = $mapaRoles[$tipo];
        
        // Obtener usuarios con la nueva función segura
        $usuarios = obtenerUsuariosPaginados($conn, $tabla, $usuariosPorPagina, $offset);
        
        // Devolver solo el HTML de la tabla
        if (!empty($usuarios)) {
            ob_start();
            foreach ($usuarios as $u): ?>
            <tr data-id="<?= $u['id'] ?>" data-rol="<?= $rol ?>">
                <td>
                    <div class="usuario-info">
                        <i class="fa-solid fa-<?= $rol === 'admin' ? 'user-shield' : ($rol === 'propietario' ? 'house-user' : 'user') ?>"></i>
                        <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </td>
                <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="rol-badge rol-<?= $rol ?>"><?= ucfirst($rol) ?></span></td>
                <td>
                    <label class="switch">
                        <input type="checkbox" class="toggle-estado" 
                               data-id="<?= (int)$u['id'] ?>" 
                               data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>"
                               <?= (int)$u['estado'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                        <span class="estado-texto"><?= (int)$u['estado'] ? 'Activo' : 'Inactivo' ?></span>
                    </label>
                </td>
                <td class="acciones-td">
                    <div class="acciones-container">
                        <button class="editarBtn" data-id="<?= (int)$u['id'] ?>" data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>" title="Editar usuario">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="eliminarBtn" data-id="<?= (int)$u['id'] ?>" data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" title="Eliminar usuario">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <button class="verDetallesBtn" data-id="<?= (int)$u['id'] ?>" data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" data-correo="<?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?>" title="Ver detalles">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach;
            $html = ob_get_clean();
            echo $html;
        } else {
            echo '<tr><td colspan="5" class="sin-datos-tabla"><i class="fa-solid fa-users"></i> No hay más usuarios</td></tr>';
        }
    } else {
        echo '<tr><td colspan="5" class="sin-datos-tabla"><i class="fa-solid fa-exclamation-triangle"></i> Tipo de usuario no válido</td></tr>';
    }
    exit;
}

/* ====== PROCESAR EDICIÓN (POST) MEJORADO ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // Inicializar array de errores
    $errores = [];
    
    // Procesar edición de usuario
    if ($_POST['accion'] === 'editar') {
        // Validar token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $errores[] = "Token de seguridad inválido.";
        } else {
            // Obtener y sanitizar datos
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
            $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
            $correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';

            // Validaciones mejoradas
            if ($id <= 0) $errores[] = "ID inválido.";
            if (empty($nombre)) $errores[] = "El nombre no puede estar vacío.";
            if (strlen($nombre) < 2) $errores[] = "El nombre debe tener al menos 2 caracteres.";
            if (strlen($nombre) > 100) $errores[] = "El nombre es demasiado largo.";
            
            if (empty($correo)) $errores[] = "El correo no puede estar vacío.";
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = "Correo inválido.";
            if (strlen($correo) > 255) $errores[] = "El correo es demasiado largo.";

            // Verificar que el correo no exista en otras tablas
            $mapaTablas = [
                'admin' => 'usuario_admin',
                'propietario' => 'usuario_propietario',
                'visitante' => 'usuario_visitante'
            ];

            if (!isset($mapaTablas[$rol])) {
                $errores[] = "Rol inválido.";
            }

            if (empty($errores)) {
                $tabla = $mapaTablas[$rol];
                
                // Verificar que el correo no exista en otras filas de la misma tabla
                $sqlCheck = "SELECT COUNT(*) FROM `$tabla` WHERE correo = :correo AND id != :id";
                $stmtCheck = $conn->prepare($sqlCheck);
                $stmtCheck->execute([':correo' => $correo, ':id' => $id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $errores[] = "El correo ya está en uso por otro usuario.";
                }
            }

            if (empty($errores)) {
                try {
                    // Preparar UPDATE con parámetros seguros
                    $sql = "UPDATE `$tabla` SET nombre = :nombre, correo = :correo, fecha_actualizacion = NOW() WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $ok = $stmt->execute([
                        ':nombre' => $nombre,
                        ':correo' => $correo,
                        ':id' => $id
                    ]);
                    
                    if ($ok && $stmt->rowCount() > 0) {
                        // Registrar en logs
                        $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
                        $conn->prepare($logSql)->execute([$adminNombre, "Editó usuario ID $id ($rol)"]);
                        
                        header("Location: indexadmin.php?edit=ok&seccion=usuarios");
                        exit;
                    } else {
                        $errores[] = "No se realizaron cambios o el usuario no existe.";
                    }
                } catch (Exception $e) {
                    error_log("Error al editar usuario: " . $e->getMessage());
                    $errores[] = "Error al actualizar en la base de datos.";
                }
            }
        }
    }
    
    // Procesar cambio de estado
    if ($_POST['accion'] === 'cambiar_estado') {
        // Validar token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $rol = isset($_POST['rol']) ? $_POST['rol'] : '';
        $estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 0;
        
        $mapaTablas = [
            'admin' => 'usuario_admin',
            'propietario' => 'usuario_propietario',
            'visitante' => 'usuario_visitante'
        ];
        
        if ($id > 0 && isset($mapaTablas[$rol])) {
            $tabla = $mapaTablas[$rol];
            
            // Verificar si la tabla tiene columna estado
            verificarColumnaEstado($conn, $tabla);
            
            try {
                $sql = "UPDATE `$tabla` SET estado = :estado, fecha_actualizacion = NOW() WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $ok = $stmt->execute([':estado' => $estado, ':id' => $id]);
                
                if ($ok && $stmt->rowCount() > 0) {
                    $accion = $estado ? 'activó' : 'inhabilitó';
                    $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
                    $conn->prepare($logSql)->execute([$adminNombre, "$accion usuario ID $id ($rol)"]);
                    
                    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado o sin cambios.']);
                    exit;
                }
            } catch (Exception $e) {
                error_log("Error cambiando estado usuario: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error en la base de datos.']);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
        exit;
    }
    
    // Procesar eliminación real (nueva funcionalidad)
    if ($_POST['accion'] === 'eliminar_usuario') {
        // Validar token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $rol = isset($_POST['rol']) ? $_POST['rol'] : '';
        
        $mapaTablas = [
            'admin' => 'usuario_admin',
            'propietario' => 'usuario_propietario',
            'visitante' => 'usuario_visitante'
        ];
        
        if ($id > 0 && isset($mapaTablas[$rol])) {
            $tabla = $mapaTablas[$rol];
            
            try {
                // Obtener nombre del usuario antes de eliminarlo para el log
                $sqlSelect = "SELECT nombre FROM `$tabla` WHERE id = :id";
                $stmtSelect = $conn->prepare($sqlSelect);
                $stmtSelect->execute([':id' => $id]);
                $usuario = $stmtSelect->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario) {
                    // Eliminar usuario
                    $sql = "DELETE FROM `$tabla` WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $ok = $stmt->execute([':id' => $id]);
                    
                    if ($ok && $stmt->rowCount() > 0) {
                        // Registrar en logs
                        $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
                        $conn->prepare($logSql)->execute([$adminNombre, "Eliminó usuario " . $usuario['nombre'] . " (ID: $id, Rol: $rol)"]);
                        
                        echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
                        exit;
                    }
                }
                
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
                exit;
            } catch (Exception $e) {
                error_log("Error eliminando usuario: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error en la base de datos.']);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
        exit;
    }
}

/* ====== CONFIGURACIÓN DE PAGINACIÓN ====== */
$usuariosPorPagina = 10;
$logsPorPagina = 20;

/* ====== PAGINACIÓN LOGS ====== */
$paginaLogs = isset($_GET['pagina_logs']) ? max(1, (int)$_GET['pagina_logs']) : 1;
$offsetLogs = ($paginaLogs - 1) * $logsPorPagina;

try {
    $totalLogs = $conn->query("SELECT COUNT(*) FROM logs_actividad")->fetchColumn();
    $totalPaginasLogs = ceil($totalLogs / $logsPorPagina);
} catch (Exception $e) {
    error_log("Error obteniendo total de logs: " . $e->getMessage());
    $totalLogs = 0;
    $totalPaginasLogs = 1;
}

/* ====== PAGINACIÓN USUARIOS ====== */
// Obtener página actual para cada tipo de usuario
$paginaAdmins = isset($_GET['pagina_admins']) ? max(1, (int)$_GET['pagina_admins']) : 1;
$paginaPropietarios = isset($_GET['pagina_propietarios']) ? max(1, (int)$_GET['pagina_propietarios']) : 1;
$paginaVisitantes = isset($_GET['pagina_visitantes']) ? max(1, (int)$_GET['pagina_visitantes']) : 1;

// Calcular offsets
$offsetAdmins = ($paginaAdmins - 1) * $usuariosPorPagina;
$offsetPropietarios = ($paginaPropietarios - 1) * $usuariosPorPagina;
$offsetVisitantes = ($paginaVisitantes - 1) * $usuariosPorPagina;

/* ====== TRAER DATOS (LOGS + USUARIOS) ====== */
/* Logs con paginación */
try {
    $logs = $conn->prepare("
        SELECT usuario_nombre, rol, accion, fecha, DATE(fecha) as fecha_simple
        FROM logs_actividad
        ORDER BY fecha DESC
        LIMIT :limit OFFSET :offset
    ");
    $logs->bindValue(':limit', $logsPorPagina, PDO::PARAM_INT);
    $logs->bindValue(':offset', $offsetLogs, PDO::PARAM_INT);
    $logs->execute();
    $logs = $logs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error obteniendo logs: " . $e->getMessage());
    $logs = [];
}

/* Usuarios por tipo con paginación INDIVIDUAL */
// Totales
try {
    $totalAdmins = $conn->query("SELECT COUNT(*) FROM usuario_admin")->fetchColumn();
    $totalPropietarios = $conn->query("SELECT COUNT(*) FROM usuario_propietario")->fetchColumn();
    $totalVisitantes = $conn->query("SELECT COUNT(*) FROM usuario_visitante")->fetchColumn();
} catch (Exception $e) {
    error_log("Error obteniendo totales de usuarios: " . $e->getMessage());
    $totalAdmins = $totalPropietarios = $totalVisitantes = 0;
}

// Datos paginados con estado usando función mejorada
$admins = obtenerUsuariosPaginados($conn, 'usuario_admin', $usuariosPorPagina, $offsetAdmins);
$propietarios = obtenerUsuariosPaginados($conn, 'usuario_propietario', $usuariosPorPagina, $offsetPropietarios);
$visitantes = obtenerUsuariosPaginados($conn, 'usuario_visitante', $usuariosPorPagina, $offsetVisitantes);

/* Totales generales */
$totalUsuarios = $totalAdmins + $totalPropietarios + $totalVisitantes;

/* ====== ESTADÍSTICAS ADICIONALES ====== */
try {
    $logsHoy = $conn->query("
        SELECT COUNT(*) FROM logs_actividad 
        WHERE DATE(fecha) = CURDATE()
    ")->fetchColumn();
    
    $usuariosActivos = $conn->query("
        SELECT COUNT(DISTINCT usuario_nombre) FROM logs_actividad 
        WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ")->fetchColumn();
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas: " . $e->getMessage());
    $logsHoy = 0;
    $usuariosActivos = 0;
}

// Calcular páginas para cada tipo de usuario
$totalPaginasAdmins = ceil($totalAdmins / $usuariosPorPagina);
$totalPaginasPropietarios = ceil($totalPropietarios / $usuariosPorPagina);
$totalPaginasVisitantes = ceil($totalVisitantes / $usuariosPorPagina);

// Determinar sección activa
$seccionActiva = isset($_GET['seccion']) ? $_GET['seccion'] : 'inicio';

// Determinar tabla activa en usuarios
$tablaActiva = isset($_GET['tabla']) && in_array($_GET['tabla'], ['admins', 'propietarios', 'visitantes', 'logs']) 
    ? $_GET['tabla'] 
    : 'admins';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin | RENTNONO</title>
    <link rel="stylesheet" href="../estilos/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="layout">

    <!-- SIDEBAR FIJO -->
    <aside class="sidebar">
        <h2 class="logo">RENTNONO</h2>

        <!-- PRIMERO: INICIO (antes Dashboard) -->
        <button class="menu-btn <?= $seccionActiva === 'inicio' ? 'activo' : '' ?>" data-seccion="inicio" id="btnInicio">
            <i class="fa-solid fa-house"></i> <span class="menu-text">Inicio</span>
        </button>

        <!-- SEGUNDO: USUARIOS desplegable -->
        <button class="menu-btn <?= $seccionActiva === 'usuarios' ? 'activo' : '' ?>" id="btnUsuarios">
            <i class="fa-solid fa-users"></i> <span class="menu-text">Usuarios</span>
            <i class="fa-solid fa-chevron-down flecha"></i>
        </button>

        <div class="submenu" id="submenuUsuarios" style="<?= $seccionActiva === 'usuarios' ? 'max-height: 240px;' : '' ?>">
            <button class="submenu-btn <?= $tablaActiva === 'admins' ? 'activo' : '' ?>" data-tabla="admins">Administradores</button>
            <button class="submenu-btn <?= $tablaActiva === 'propietarios' ? 'activo' : '' ?>" data-tabla="propietarios">Propietarios</button>
            <button class="submenu-btn <?= $tablaActiva === 'visitantes' ? 'activo' : '' ?>" data-tabla="visitantes">Visitantes</button>
            <button class="submenu-btn <?= $tablaActiva === 'logs' ? 'activo' : '' ?>" data-tabla="logs">Logs</button>
        </div>

        <!-- TERCERO: SOLICITUDES PENDIENTES -->
        <button class="menu-btn <?= $seccionActiva === 'solicitudes' ? 'activo' : '' ?>" data-seccion="solicitudes" id="btnSolicitudes">
            <i class="fa-solid fa-clock"></i> <span class="menu-text">Solicitudes Pendientes</span>
        </button>

        <!-- CUARTO: PROPIEDADES -->
        <button class="menu-btn <?= $seccionActiva === 'propiedades' ? 'activo' : '' ?>" data-seccion="propiedades" id="btnPropiedades">
            <i class="fa-solid fa-building"></i> <span class="menu-text">Propiedades</span>
        </button>

        <!-- QUINTO: ESTADÍSTICAS -->
        <button class="menu-btn <?= $seccionActiva === 'estadisticas' ? 'activo' : '' ?>" data-seccion="estadisticas" id="btnEstadisticas">
            <i class="fa-solid fa-chart-bar"></i> <span class="menu-text">Estadísticas</span>
        </button>

        <!-- ÚLTIMO: CERRAR SESIÓN -->
        <button class="menu-btn logout-btn" id="btnLogout">
            <i class="fa-solid fa-right-from-bracket"></i> <span class="menu-text">Cerrar sesión</span>
        </button>
    </aside>

    <!-- CONTENIDO -->
    <main class="contenido">

        <!-- INICIO (antes Dashboard) -->
        <section id="inicio" class="seccion <?= $seccionActiva === 'inicio' ? 'visible' : '' ?>">
            <div class="dashboard-header">
                <h2>¡Bienvenido, <?= htmlspecialchars($adminNombre, ENT_QUOTES, 'UTF-8') ?>!</h2>
                <div class="dashboard-time">
                    <i class="fa-solid fa-clock"></i>
                    <span id="currentTime"></span>
                </div>
            </div>
            
            <div class="dashboard-resumen">
                <div class="resumen-card total-usuarios">
                    <i class="fa-solid fa-users"></i>
                    <h3>Total Usuarios</h3>
                    <span><?= $totalUsuarios ?></span>
                    <div class="resumen-trend">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Total registrados</span>
                    </div>
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
                <div class="resumen-card">
                    <i class="fa-solid fa-database"></i>
                    <h3>Total Tablas</h3>
                    <span><?= count($tablasUsuarios) + 1 ?></span>
                </div>
            </div>
            
            <div class="dashboard-quick-actions">
                <h3><i class="fa-solid fa-bolt"></i> Acciones Rápidas</h3>
                <div class="quick-actions-grid">
                    <button class="quick-action-btn" onclick="mostrarSeccion('usuarios')">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Ver Usuarios</span>
                    </button>
                    <button class="quick-action-btn" onclick="mostrarSeccion('solicitudes')">
                        <i class="fa-solid fa-clock"></i>
                        <span>Ver Solicitudes</span>
                    </button>
                    <button class="quick-action-btn" id="refreshStats">
                        <i class="fa-solid fa-rotate"></i>
                        <span>Actualizar Estadísticas</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- LOGS DENTRO DE USUARIOS -->
        <section id="logs" class="seccion <?= $seccionActiva === 'usuarios' && $tablaActiva === 'logs' ? 'visible' : '' ?>">
            <div class="logs-header">
                <div class="tabla-title">
                    <h2>Logs de actividad (<span id="totalLogsCount"><?= $totalLogs ?></span> total)</h2>
                    <span class="tabla-subtitle">Registros de actividad del sistema</span>
                </div>
                <div class="logs-filters">
                    <input type="text" id="searchLogs" placeholder="Buscar en logs..." class="buscador-tabla">
                    <button class="filter-btn" id="filterToday">
                        <i class="fa-solid fa-calendar-day"></i> Hoy
                    </button>
                    <button class="filter-btn" id="clearFilters">
                        <i class="fa-solid fa-filter-circle-xmark"></i> Limpiar
                    </button>
                </div>
            </div>
            
            <!-- PAGINACIÓN LOGS ORIGINAL -->
            <?php if ($totalLogs > 0 && $totalPaginasLogs > 1): ?>
            <div class="paginacion-tabla logs-paginacion">
                <?php if ($paginaLogs > 1): ?>
                    <a href="?pagina_logs=<?= $paginaLogs - 1 ?>&seccion=usuarios&tabla=logs" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-left"></i>
                    </span>
                <?php endif; ?>
                
                <span class="info-pagina">
                    Pág. <?= $paginaLogs ?> de <?= $totalPaginasLogs ?>
                </span>
                
                <?php if ($paginaLogs < $totalPaginasLogs): ?>
                    <a href="?pagina_logs=<?= $paginaLogs + 1 ?>&seccion=usuarios&tabla=logs" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

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
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $l): ?>
                            <tr>
                                <td><?= htmlspecialchars($l['usuario_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="rol-badge rol-<?= htmlspecialchars($l['rol'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($l['rol'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars($l['accion'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($l['fecha_simple'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="hora-log"><?= date('H:i:s', strtotime($l['fecha'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="sin-datos-tabla">
                                    <i class="fa-solid fa-inbox"></i>
                                    No hay logs registrados
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- USUARIOS -->
        <section id="usuarios" class="seccion <?= $seccionActiva === 'usuarios' && $tablaActiva !== 'logs' ? 'visible' : '' ?>">
            <div class="usuarios-header">
                <h2 id="tituloUsuarios">Usuarios - <?= ucfirst($tablaActiva === 'admins' ? 'Administradores' : ($tablaActiva === 'propietarios' ? 'Propietarios' : ($tablaActiva === 'visitantes' ? 'Visitantes' : 'Logs'))) ?></h2>
                <div class="usuarios-stats">
                    <span class="stat-item">
                        <i class="fa-solid fa-users"></i>
                        <span id="totalUsuariosActivos">-</span> activos
                    </span>
                    <span class="stat-item">
                        <i class="fa-solid fa-clock"></i>
                        Actualizado: <span id="lastUpdate"><?= date('H:i:s') ?></span>
                    </span>
                    <!-- BOTÓN PARA AGREGAR NUEVO USUARIO - EN ROJO (solo en tablas de usuarios) -->
                    <?php if ($tablaActiva !== 'logs'): ?>
                    <button class="agregar-usuario-btn" id="btnAgregarUsuario">
                        <i class="fa-solid fa-user-plus"></i> Nuevo Usuario
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contenedor de tablas -->
            <div class="tabla-contenedor">
                <!-- Tabla Admins -->
                <div id="contenedorAdmins" class="contenedor-tabla-usuarios" style="<?= $tablaActiva === 'admins' ? '' : 'display:none;' ?>">
                    <div class="tabla-header">
                        <div class="tabla-title">
                            <h3>Administradores (<span id="totalAdmins"><?= $totalAdmins ?></span> total)</h3>
                            <span class="tabla-subtitle">Usuarios con acceso total al sistema</span>
                        </div>
                        <?php if ($totalPaginasAdmins > 1): ?>
                        <!-- PAGINACIÓN ORIGINAL -->
                        <div class="paginacion-tabla">
                            <?php if ($paginaAdmins > 1): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('admins', <?= $paginaAdmins - 1 ?>)">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </span>
                            <?php endif; ?>
                            
                            <span class="info-pagina">
                                Pág. <span id="paginaActualAdmins"><?= $paginaAdmins ?></span> de <?= $totalPaginasAdmins ?>
                            </span>
                            
                            <?php if ($paginaAdmins < $totalPaginasAdmins): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('admins', <?= $paginaAdmins + 1 ?>)">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <input type="text" class="buscador-tabla" placeholder="Buscar administradores..." data-tabla="admins">
                    
                    <div class="tabla-wrapper">
                        <table id="tablaAdmins" class="tabla-usuarios">
                            <thead>
                                <tr>
                                    <th data-ordenable="true">Nombre</th>
                                    <th data-ordenable="true">Correo</th>
                                    <th data-ordenable="true">Rol</th>
                                    <th data-ordenable="true">Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyAdmins">
                                <?php if (!empty($admins)): ?>
                                    <?php foreach ($admins as $u): ?>
                                    <tr data-id="<?= $u['id'] ?>" data-rol="admin">
                                        <td>
                                            <div class="usuario-info">
                                                <i class="fa-solid fa-user-shield"></i>
                                                <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="rol-badge rol-admin">Admin</span></td>
                                        <td>
                                            <label class="switch">
                                                <input type="checkbox" class="toggle-estado" 
                                                       data-id="<?= $u['id'] ?>" 
                                                       data-rol="admin"
                                                       <?= $u['estado'] ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                                <span class="estado-texto"><?= $u['estado'] ? 'Activo' : 'Inactivo' ?></span>
                                            </label>
                                        </td>
                                        <td class="acciones-td">
                                            <div class="acciones-container">
                                                <button class="editarBtn" data-id="<?= $u['id'] ?>" data-rol="admin" title="Editar usuario">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button class="eliminarBtn" data-id="<?= $u['id'] ?>" data-rol="admin" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" title="Eliminar usuario">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                                <button class="verDetallesBtn" data-id="<?= $u['id'] ?>" data-rol="admin" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" data-correo="<?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?>" title="Ver detalles">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="sin-datos-tabla">
                                            <i class="fa-solid fa-users"></i>
                                            No hay administradores registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabla Propietarios -->
                <div id="contenedorPropietarios" class="contenedor-tabla-usuarios" style="<?= $tablaActiva === 'propietarios' ? '' : 'display:none;' ?>">
                    <div class="tabla-header">
                        <div class="tabla-title">
                            <h3>Propietarios (<span id="totalPropietarios"><?= $totalPropietarios ?></span> total)</h3>
                            <span class="tabla-subtitle">Usuarios que publican propiedades</span>
                        </div>
                        <?php if ($totalPaginasPropietarios > 1): ?>
                        <!-- PAGINACIÓN ORIGINAL -->
                        <div class="paginacion-tabla">
                            <?php if ($paginaPropietarios > 1): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('propietarios', <?= $paginaPropietarios - 1 ?>)">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </span>
                            <?php endif; ?>
                            
                            <span class="info-pagina">
                                Pág. <span id="paginaActualPropietarios"><?= $paginaPropietarios ?></span> de <?= $totalPaginasPropietarios ?>
                            </span>
                            
                            <?php if ($paginaPropietarios < $totalPaginasPropietarios): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('propietarios', <?= $paginaPropietarios + 1 ?>)">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <input type="text" class="buscador-tabla" placeholder="Buscar propietarios..." data-tabla="propietarios">
                    
                    <div class="tabla-wrapper">
                        <table id="tablaPropietarios" class="tabla-usuarios">
                            <thead>
                                <tr>
                                    <th data-ordenable="true">Nombre</th>
                                    <th data-ordenable="true">Correo</th>
                                    <th data-ordenable="true">Rol</th>
                                    <th data-ordenable="true">Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyPropietarios">
                                <?php if (!empty($propietarios)): ?>
                                    <?php foreach ($propietarios as $u): ?>
                                    <tr data-id="<?= $u['id'] ?>" data-rol="propietario">
                                        <td>
                                            <div class="usuario-info">
                                                <i class="fa-solid fa-house-user"></i>
                                                <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="rol-badge rol-propietario">Propietario</span></td>
                                        <td>
                                            <label class="switch">
                                                <input type="checkbox" class="toggle-estado" 
                                                       data-id="<?= $u['id'] ?>" 
                                                       data-rol="propietario"
                                                       <?= $u['estado'] ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                                <span class="estado-texto"><?= $u['estado'] ? 'Activo' : 'Inactivo' ?></span>
                                            </label>
                                        </td>
                                        <td class="acciones-td">
                                            <div class="acciones-container">
                                                <button class="editarBtn" data-id="<?= $u['id'] ?>" data-rol="propietario" title="Editar usuario">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button class="eliminarBtn" data-id="<?= $u['id'] ?>" data-rol="propietario" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" title="Eliminar usuario">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                                <button class="verDetallesBtn" data-id="<?= $u['id'] ?>" data-rol="propietario" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" data-correo="<?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?>" title="Ver detalles">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="sin-datos-tabla">
                                            <i class="fa-solid fa-users"></i>
                                            No hay propietarios registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabla Visitantes -->
                <div id="contenedorVisitantes" class="contenedor-tabla-usuarios" style="<?= $tablaActiva === 'visitantes' ? '' : 'display:none;' ?>">
                    <div class="tabla-header">
                        <div class="tabla-title">
                            <h3>Visitantes (<span id="totalVisitantes"><?= $totalVisitantes ?></span> total)</h3>
                            <span class="tabla-subtitle">Usuarios que buscan propiedades</span>
                        </div>
                        <?php if ($totalPaginasVisitantes > 1): ?>
                        <!-- PAGINACIÓN ORIGINAL -->
                        <div class="paginacion-tabla">
                            <?php if ($paginaVisitantes > 1): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('visitantes', <?= $paginaVisitantes - 1 ?>)">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </span>
                            <?php endif; ?>
                            
                            <span class="info-pagina">
                                Pág. <span id="paginaActualVisitantes"><?= $paginaVisitantes ?></span> de <?= $totalPaginasVisitantes ?>
                            </span>
                            
                            <?php if ($paginaVisitantes < $totalPaginasVisitantes): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('visitantes', <?= $paginaVisitantes + 1 ?>)">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <input type="text" class="buscador-tabla" placeholder="Buscar visitantes..." data-tabla="visitantes">
                    
                    <div class="tabla-wrapper">
                        <table id="tablaVisitantes" class="tabla-usuarios">
                            <thead>
                                <tr>
                                    <th data-ordenable="true">Nombre</th>
                                    <th data-ordenable="true">Correo</th>
                                    <th data-ordenable="true">Rol</th>
                                    <th data-ordenable="true">Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyVisitantes">
                                <?php if (!empty($visitantes)): ?>
                                    <?php foreach ($visitantes as $u): ?>
                                    <tr data-id="<?= $u['id'] ?>" data-rol="visitante">
                                        <td>
                                            <div class="usuario-info">
                                                <i class="fa-solid fa-user"></i>
                                                <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="rol-badge rol-visitante">Visitante</span></td>
                                        <td>
                                            <label class="switch">
                                                <input type="checkbox" class="toggle-estado" 
                                                       data-id="<?= $u['id'] ?>" 
                                                       data-rol="visitante"
                                                       <?= $u['estado'] ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                                <span class="estado-texto"><?= $u['estado'] ? 'Activo' : 'Inactivo' ?></span>
                                            </label>
                                        </td>
                                        <td class="acciones-td">
                                            <div class="acciones-container">
                                                <button class="editarBtn" data-id="<?= $u['id'] ?>" data-rol="visitante" title="Editar usuario">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button class="eliminarBtn" data-id="<?= $u['id'] ?>" data-rol="visitante" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" title="Eliminar usuario">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                                <button class="verDetallesBtn" data-id="<?= $u['id'] ?>" data-rol="visitante" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" data-correo="<?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?>" title="Ver detalles">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="sin-datos-tabla">
                                            <i class="fa-solid fa-users"></i>
                                            No hay visitantes registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- SOLICITUDES PENDIENTES -->
        <section id="solicitudes" class="seccion <?= $seccionActiva === 'solicitudes' ? 'visible' : '' ?>">
            <div class="usuarios-header">
                <h2>Solicitudes Pendientes</h2>
                <div class="usuarios-stats">
                    <span class="stat-item">
                        <i class="fa-solid fa-clock"></i>
                        <span id="totalSolicitudes">0</span> pendientes
                    </span>
                    <span class="stat-item">
                        <i class="fa-solid fa-calendar-day"></i>
                        Hoy: <span id="solicitudesHoy">0</span>
                    </span>
                </div>
            </div>
            
            <div class="tabla-contenedor">
                <div class="sin-datos-tabla">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <h3>Módulo en desarrollo</h3>
                    <p>Próximamente podrás gestionar las solicitudes pendientes de usuarios</p>
                </div>
            </div>
        </section>

        <!-- PROPIEDADES -->
        <section id="propiedades" class="seccion <?= $seccionActiva === 'propiedades' ? 'visible' : '' ?>">
            <div class="usuarios-header">
                <h2>Propiedades</h2>
                <div class="usuarios-stats">
                    <span class="stat-item">
                        <i class="fa-solid fa-building"></i>
                        <span id="totalPropiedades">0</span> propiedades
                    </span>
                    <span class="stat-item">
                        <i class="fa-solid fa-check-circle"></i>
                        Activas: <span id="propiedadesActivas">0</span>
                    </span>
                </div>
            </div>
            
            <div class="tabla-contenedor">
                <div class="sin-datos-tabla">
                    <i class="fa-solid fa-home"></i>
                    <h3>Módulo en desarrollo</h3>
                    <p>Próximamente podrás gestionar todas las propiedades del sistema</p>
                </div>
            </div>
        </section>

        <!-- ESTADÍSTICAS -->
        <section id="estadisticas" class="seccion <?= $seccionActiva === 'estadisticas' ? 'visible' : '' ?>">
            <div class="usuarios-header">
                <h2>Estadísticas del Sistema</h2>
                <div class="usuarios-stats">
                    <span class="stat-item">
                        <i class="fa-solid fa-chart-line"></i>
                        Período: <span id="periodoEstadisticas">Este mes</span>
                    </span>
                    <span class="stat-item">
                        <i class="fa-solid fa-calendar-alt"></i>
                        Actualizado: <span id="fechaEstadisticas"><?= date('d/m/Y') ?></span>
                    </span>
                </div>
            </div>
            
            <div class="dashboard-resumen">
                <div class="resumen-card total-usuarios">
                    <i class="fa-solid fa-users"></i>
                    <h3>Crecimiento Usuarios</h3>
                    <span>+15%</span>
                    <div class="resumen-trend">
                        <i class="fa-solid fa-arrow-up"></i>
                        <span>vs mes anterior</span>
                    </div>
                </div>
                <div class="resumen-card">
                    <i class="fa-solid fa-building"></i>
                    <h3>Propiedades Activas</h3>
                    <span>24</span>
                </div>
                <div class="resumen-card">
                    <i class="fa-solid fa-eye"></i>
                    <h3>Visitas Totales</h3>
                    <span>1,245</span>
                </div>
                <div class="resumen-card">
                    <i class="fa-solid fa-handshake"></i>
                    <h3>Solicitudes Completadas</h3>
                    <span>89</span>
                </div>
            </div>
            
            <div class="tabla-contenedor" style="margin-top: 30px;">
                <div class="sin-datos-tabla">
                    <i class="fa-solid fa-chart-bar"></i>
                    <h3>Gráficos en desarrollo</h3>
                    <p>Próximamente verás gráficos detallados de las estadísticas del sistema</p>
                </div>
            </div>
        </section>

    </main>
</div>

<!-- MODALES -->
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
                        <div class="error"><i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form id="formEditar" method="POST" action="indexadmin.php">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="rol" id="editRol">

                <div class="form-group">
                    <label for="editNombre"><i class="fa-solid fa-user"></i> Nombre</label>
                    <input type="text" name="nombre" id="editNombre" required maxlength="100">
                    <div class="form-help">Mínimo 2 caracteres, máximo 100</div>
                    <div class="form-error" id="errorNombre"></div>
                </div>

                <div class="form-group">
                    <label for="editCorreo"><i class="fa-solid fa-envelope"></i> Correo Electrónico</label>
                    <input type="email" name="correo" id="editCorreo" required maxlength="255">
                    <div class="form-help">Formato: usuario@ejemplo.com</div>
                    <div class="form-error" id="errorCorreo"></div>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-shield"></i> Tipo de Usuario</label>
                    <div class="rol-display" id="displayRol">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>Administrador</span>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" id="cancelarEditar">Cancelar</button>
                    <button type="submit" class="btn-guardar" id="submitEditar">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR NUEVO USUARIO -->
<div class="modal" id="modalAgregarUsuario" style="display:none;">
    <div class="modal-contenido modal-mejorado">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus"></i> Agregar Nuevo Usuario</h3>
            <span class="cerrar">&times;</span>
        </div>
        
        <div class="modal-body">
            <form id="formAgregarUsuario">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="accion" value="agregar_usuario">

                <div class="form-group">
                    <label for="agregarNombre"><i class="fa-solid fa-user"></i> Nombre Completo</label>
                    <input type="text" name="nombre" id="agregarNombre" required maxlength="100" placeholder="Ej: Juan Pérez">
                    <div class="form-help">Mínimo 2 caracteres, máximo 100</div>
                    <div class="form-error" id="errorAgregarNombre"></div>
                </div>

                <div class="form-group">
                    <label for="agregarCorreo"><i class="fa-solid fa-envelope"></i> Correo Electrónico</label>
                    <input type="email" name="correo" id="agregarCorreo" required maxlength="255" placeholder="usuario@ejemplo.com">
                    <div class="form-help">Formato válido de correo electrónico</div>
                    <div class="form-error" id="errorAgregarCorreo"></div>
                </div>

                <div class="form-group">
                    <label for="agregarRol"><i class="fa-solid fa-shield"></i> Tipo de Usuario</label>
                    <select name="rol" id="agregarRol" required class="form-select">
                        <option value="" selected disabled>Seleccionar rol...</option>
                        <option value="admin">Administrador</option>
                        <option value="propietario">Propietario</option>
                        <option value="visitante">Visitante</option>
                    </select>
                    <div class="form-help">Selecciona el tipo de usuario</div>
                    <div class="form-error" id="errorAgregarRol"></div>
                </div>

                <div class="form-group">
                    <label for="agregarPassword"><i class="fa-solid fa-lock"></i> Contraseña</label>
                    <input type="password" name="password" id="agregarPassword" required minlength="8" placeholder="Mínimo 8 caracteres">
                    <div class="form-help">Mínimo 8 caracteres</div>
                    <div class="form-error" id="errorAgregarPassword"></div>
                </div>

                <div class="form-group">
                    <label for="agregarConfirmPassword"><i class="fa-solid fa-lock"></i> Confirmar Contraseña</label>
                    <input type="password" name="confirm_password" id="agregarConfirmPassword" required placeholder="Repite la contraseña">
                    <div class="form-help">Ambas contraseñas deben coincidir</div>
                    <div class="form-error" id="errorAgregarConfirmPassword"></div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" id="cancelarAgregarUsuario">Cancelar</button>
                    <button type="submit" class="btn-guardar" id="submitAgregarUsuario">
                        <i class="fa-solid fa-user-plus"></i> Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal" id="modalConfirmarEliminar" style="display:none;">
    <div class="modal-contenido modal-confirmacion">
        <div class="modal-header">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> Confirmar Eliminación</h3>
            <span class="cerrar">&times;</span>
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

<div class="modal" id="modalConfirmarLogout" style="display:none;">
    <div class="modal-contenido modal-confirmacion">
        <div class="modal-header">
            <h3><i class="fa-solid fa-door-open"></i> Cerrar Sesión</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div class="confirmacion-icono">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>
            <p>¿Estás seguro de que quieres cerrar sesión?</p>
            <div class="modal-actions">
                <button type="button" class="btn-cancelar" id="cancelarLogout">Cancelar</button>
                <button type="button" class="btn-logout-confirmar" id="confirmarLogout">
                    <i class="fa-solid fa-right-from-bracket"></i> Sí, Cerrar Sesión
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ver Detalles DEL OJITO -->
<div class="modal" id="modalVerDetalles" style="display:none;">
    <div class="modal-contenido modal-detalles">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-circle"></i> Detalles del Usuario</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div class="detalles-content">
                <div class="detalles-header">
                    <div class="avatar-detalles">
                        <i class="fa-solid fa-user" id="detallesIcono"></i>
                    </div>
                    <div class="detalles-titulo">
                        <h4 id="detallesNombre">Nombre Usuario</h4>
                        <span class="rol-badge" id="detallesRol">Admin</span>
                    </div>
                </div>
                
                <div class="detalles-info">
                    <div class="info-item">
                        <span class="info-label"><i class="fa-solid fa-envelope"></i> Correo:</span>
                        <span class="info-value" id="detallesCorreo">usuario@ejemplo.com</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fa-solid fa-id-card"></i> ID:</span>
                        <span class="info-value" id="detallesId">1</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fa-solid fa-toggle-on"></i> Estado:</span>
                        <span class="info-value" id="detallesEstado">Activo</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fa-solid fa-calendar-plus"></i> Fecha Creación:</span>
                        <span class="info-value" id="detallesFechaCreacion">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fa-solid fa-calendar-check"></i> Última Actualización:</span>
                        <span class="info-value" id="detallesFechaActualizacion">-</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancelar" id="cerrarDetalles">Cerrar</button>
            <button type="button" class="btn-editar-detalles" id="editarDesdeDetalles">
                <i class="fa-solid fa-pen"></i> Editar Usuario
            </button>
        </div>
    </div>
</div>

<script src="../script/admin.js"></script>
<script>
// Scripts iniciales en línea para mejor UX
document.addEventListener('DOMContentLoaded', function() {
    // Actualizar hora actual
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        const dateString = now.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = dateString + ' - ' + timeString;
        }
    }
    
    updateTime();
    setInterval(updateTime, 1000);
    
    // Actualizar timestamp de última actualización
    function updateLastUpdate() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit'
        });
        const updateElement = document.getElementById('lastUpdate');
        if (updateElement) {
            updateElement.textContent = timeString;
        }
    }
    
    updateLastUpdate();
    setInterval(updateLastUpdate, 60000); // Actualizar cada minuto
    
    // Calcular usuarios activos
    function calcularUsuariosActivos() {
        const activeToggles = document.querySelectorAll('.toggle-estado:checked');
        const totalElement = document.getElementById('totalUsuariosActivos');
        if (totalElement && activeToggles.length > 0) {
            totalElement.textContent = activeToggles.length;
        }
    }
    
    // Llamar después de que se cargue todo
    setTimeout(calcularUsuariosActivos, 1000);
    
    // Función global para mostrar secciones (usada por botones de acciones rápidas)
    window.mostrarSeccion = function(id) {
        // Esta función será implementada en admin.js
        console.log('Mostrando sección:', id);
        // La implementación real está en admin.js
    };
});
</script>
</body>
</html>