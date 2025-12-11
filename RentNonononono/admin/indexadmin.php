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

/* ====== VARIABLE PARA MENSAJES DE CONFIRMACIÓN ====== */
$mensajeConfirmacion = '';
$tipoMensaje = '';

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

/**
 * Obtener servicios desde la base de datos
 */
function obtenerServicios($conn) {
    try {
        // Primero verificar si la tabla servicios existe
        $checkTable = $conn->query("SHOW TABLES LIKE 'servicios'")->fetch();
        if (!$checkTable) {
            // Crear tabla de servicios si no existe
            $conn->exec("CREATE TABLE IF NOT EXISTS servicios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(50) NOT NULL UNIQUE,
                icono VARCHAR(50) DEFAULT 'fa-solid fa-star',
                estado TINYINT(1) DEFAULT 1,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Insertar servicios por defecto
            $serviciosDefault = [
                ['wifi', 'fa-solid fa-wifi'],
                ['cochera', 'fa-solid fa-car'],
                ['patio', 'fa-solid fa-tree'],
                ['amoblado', 'fa-solid fa-couch'],
                ['aire acondicionado', 'fa-solid fa-snowflake'],
                ['calefacción', 'fa-solid fa-fire'],
                ['cable TV', 'fa-solid fa-tv'],
                ['pileta', 'fa-solid fa-swimming-pool'],
                ['seguridad 24hs', 'fa-solid fa-shield-alt']
            ];
            
            foreach ($serviciosDefault as $servicio) {
                $sqlInsert = "INSERT IGNORE INTO servicios (nombre, icono) VALUES (:nombre, :icono)";
                $stmt = $conn->prepare($sqlInsert);
                $stmt->execute([
                    ':nombre' => $servicio[0],
                    ':icono' => $servicio[1]
                ]);
            }
        }
        
        $sql = "SELECT * FROM servicios WHERE estado = 1 ORDER BY nombre";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo servicios: " . $e->getMessage());
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
        if ($rol === 'admin') {
            $columna_password = 'password_hash';
            $sqlInsert = "INSERT INTO `$tabla` (nombre, correo, $columna_password, estado, fecha_creacion) 
                          VALUES (:nombre, :correo, :password, 1, NOW())";
        } else {
            $columna_password = 'password';
            $sqlInsert = "INSERT INTO `$tabla` (nombre, correo, $columna_password, estado, fecha_creacion) 
                          VALUES (:nombre, :correo, :password, 1, NOW())";
        }
        
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
            $sql = "SELECT * FROM `$tabla` WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                // Asegurar que existan campos básicos
                if (!isset($usuario['estado'])) $usuario['estado'] = 1;
                if (!isset($usuario['fecha_creacion'])) {
                    $usuario['fecha_creacion'] = date('Y-m-d H:i:s');
                }
                
                // Obtener estadísticas según rol
                $estadisticas = [];
                
                // Para propietarios: propiedades publicadas
                if ($rol === 'propietario') {
                    $sqlPropiedades = "SELECT COUNT(*) as total FROM propiedades WHERE id_propietario = :id";
                    $stmtProp = $conn->prepare($sqlPropiedades);
                    $stmtProp->execute([':id' => $id]);
                    $estadisticas['propiedades'] = $stmtProp->fetchColumn();
                }
                
                // Para visitantes: favoritos
                if ($rol === 'visitante') {
                    $sqlFavoritos = "SELECT COUNT(*) as total FROM favoritos WHERE id_usuario = :id";
                    $stmtFav = $conn->prepare($sqlFavoritos);
                    $stmtFav->execute([':id' => $id]);
                    $estadisticas['favoritos'] = $stmtFav->fetchColumn();
                    
                    // Opiniones
                    $sqlOpiniones = "SELECT COUNT(*) as total FROM opiniones WHERE usuario_id = :id";
                    $stmtOp = $conn->prepare($sqlOpiniones);
                    $stmtOp->execute([':id' => $id]);
                    $estadisticas['opiniones'] = $stmtOp->fetchColumn();
                }
                
                // Para administradores: logs de actividad
                if ($rol === 'admin') {
                    $sqlLogs = "SELECT COUNT(*) as total FROM logs_actividad WHERE usuario_id = :id";
                    $stmtLogs = $conn->prepare($sqlLogs);
                    $stmtLogs->execute([':id' => $id]);
                    $estadisticas['logs'] = $stmtLogs->fetchColumn();
                }
                
                // Actividad reciente (últimas 3 acciones)
                $sqlActividad = "SELECT accion, fecha FROM logs_actividad 
                                WHERE usuario_id = :id 
                                ORDER BY fecha DESC LIMIT 3";
                $stmtAct = $conn->prepare($sqlActividad);
                $stmtAct->execute([':id' => $id]);
                $estadisticas['actividad_reciente'] = $stmtAct->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'usuario' => $usuario,
                    'estadisticas' => $estadisticas
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            }
            
        } catch (Exception $e) {
            error_log("Error obteniendo detalles de usuario: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    }
    
    exit;
}

/* ====== PROCESAR CAMBIO DE ESTADO DE USUARIOS ====== */
// SOLO UNA VEZ - He eliminado el bloque duplicado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
    error_log("=== SOLICITUD CAMBIAR ESTADO ===");
    error_log("Token recibido: " . ($_POST['csrf_token'] ?? 'NO TOKEN'));
    error_log("Token sesión: " . ($_SESSION['csrf_token'] ?? 'NO SESSION'));

    // --- Validar token CSRF ---
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("ERROR: Token CSRF no coincide");
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido. Recargá la página.']);
        exit;
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $rol = $_POST['rol'] ?? '';
    $estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 0;

    $mapaTablas = [
        'admin' => 'usuario_admin',
        'propietario' => 'usuario_propietario',
        'visitante' => 'usuario_visitante'
    ];

    if ($id <= 0 || !isset($mapaTablas[$rol])) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
        exit;
    }

    $tabla = $mapaTablas[$rol];

    try {
        // --- Verificar existencia de usuario ---
        $stmtCheck = $conn->prepare("SELECT id, nombre FROM `$tabla` WHERE id = :id");
        $stmtCheck->execute([':id' => $id]);
        $usuario = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
            exit;
        }

        // --- Asegurar columna estado ---
        $colCheck = $conn->query("SHOW COLUMNS FROM `$tabla` LIKE 'estado'")->fetch();
        if (!$colCheck) {
            $conn->exec("ALTER TABLE `$tabla` ADD COLUMN estado TINYINT(1) DEFAULT 1");
            error_log("Columna 'estado' añadida en $tabla");
        }

        // --- Actualizar estado ---
        $sql = "UPDATE `$tabla` SET estado = :estado WHERE id = :id";
        $stmtUpdate = $conn->prepare($sql);
        $stmtUpdate->execute([':estado' => $estado, ':id' => $id]);

        if ($stmtUpdate->rowCount() > 0) {
            // --- Registrar acción en logs ---
            $accionTxt = $estado ? 'activó' : 'inhabilitó';
            $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
            $conn->prepare($logSql)->execute([$adminNombre, "$accionTxt usuario '{$usuario['nombre']}' (ID: $id, Rol: $rol)"]);

            echo json_encode([
                'success' => true,
                'message' => 'Estado actualizado correctamente',
                'nuevo_estado' => $estado
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'No se realizaron cambios.']);
            exit;
        }

    } catch (Exception $e) {
        error_log("ERROR cambiando estado: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno en la base de datos.']);
        exit;
    }
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
                        
                        // Establecer mensaje de confirmación
                        $_SESSION['mensaje_confirmacion'] = 'Usuario editado correctamente';
                        $_SESSION['tipo_mensaje'] = 'success';
                        
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

/* ====== GESTIÓN DE SERVICIOS - AGREGAR NUEVO SERVICIO ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar_servicio') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido.']);
        exit;
    }
    
    $nombre_servicio = trim($_POST['nombre_servicio'] ?? '');
    $icono_servicio = trim($_POST['icono_servicio'] ?? 'fa-solid fa-star');
    
    // Validaciones
    $errores = [];
    
    if (empty($nombre_servicio)) $errores[] = 'El nombre del servicio es requerido';
    if (strlen($nombre_servicio) < 2) $errores[] = 'El nombre debe tener al menos 2 caracteres';
    if (strlen($nombre_servicio) > 50) $errores[] = 'El nombre no puede exceder 50 caracteres';
    
    // Verificar si el servicio ya existe
    if (empty($errores)) {
        try {
            // Asegurar que la tabla de servicios exista
            $checkTable = $conn->query("SHOW TABLES LIKE 'servicios'")->fetch();
            if (!$checkTable) {
                // Crear tabla de servicios si no existe
                $conn->exec("CREATE TABLE IF NOT EXISTS servicios (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nombre VARCHAR(50) NOT NULL UNIQUE,
                    icono VARCHAR(50) DEFAULT 'fa-solid fa-star',
                    estado TINYINT(1) DEFAULT 1,
                    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            }
            
            // Verificar si ya existe
            $sqlCheck = "SELECT COUNT(*) FROM servicios WHERE nombre = :nombre";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([':nombre' => $nombre_servicio]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                $errores[] = 'Este servicio ya está registrado';
            }
        } catch (Exception $e) {
            error_log("Error verificando servicio: " . $e->getMessage());
            $errores[] = 'Error al verificar el servicio';
        }
    }
    
    // Si hay errores, devolverlos
    if (!empty($errores)) {
        echo json_encode(['success' => false, 'errors' => $errores]);
        exit;
    }
    
    // Insertar nuevo servicio
    try {
        $sqlInsert = "INSERT INTO servicios (nombre, icono, estado) VALUES (:nombre, :icono, 1)";
        
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->execute([
            ':nombre' => $nombre_servicio,
            ':icono' => $icono_servicio
        ]);
        
        $nuevoId = $conn->lastInsertId();
        
        // Registrar en logs
        $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
        $conn->prepare($logSql)->execute([$adminNombre, "Agregó nuevo servicio: $nombre_servicio"]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Servicio agregado exitosamente',
            'id' => $nuevoId,
            'nombre' => $nombre_servicio,
            'icono' => $icono_servicio
        ]);
        
    } catch (Exception $e) {
        error_log("Error agregando servicio: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos']);
    }
    
    exit;
}

/* ====== OBTENER SERVICIOS PARA AJAX ====== */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion']) && $_GET['accion'] === 'obtener_servicios' && isset($_GET['ajax'])) {
    $servicios = obtenerServicios($conn);
    echo json_encode(['success' => true, 'servicios' => $servicios]);
    exit;
}

/* ====== SUBIR PROPIEDAD COMO ADMIN ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'subir_propiedad_admin') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido.']);
        exit;
    }
    
    // Obtener y sanitizar datos
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = isset($_POST['precio']) ? (float)$_POST['precio'] : 0;
    $no_decirlo = isset($_POST['no_decirlo']) ? 1 : 0;
    $ambientes = isset($_POST['ambientes']) ? (int)$_POST['ambientes'] : 0;
    $banios = isset($_POST['banios']) ? (int)$_POST['banios'] : 0;
    $superficie = isset($_POST['superficie']) ? (int)$_POST['superficie'] : 0;
    $direccion = trim($_POST['direccion'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $servicios = isset($_POST['servicios']) ? $_POST['servicios'] : [];
    
    // Validaciones básicas
    $errores = [];
    
    if (empty($titulo)) $errores[] = 'El título es requerido';
    if (empty($descripcion)) $errores[] = 'La descripción es requerida';
    if (empty($direccion)) $errores[] = 'La dirección es requerida';
    if ($ambientes <= 0) $errores[] = 'Debe tener al menos 1 ambiente';
    if ($banios <= 0) $errores[] = 'Debe tener al menos 1 baño';
    if ($superficie <= 0) $errores[] = 'La superficie debe ser mayor a 0';
    
    if (empty($errores)) {
        try {
            $conn->beginTransaction();
            
            // Insertar propiedad directamente como aprobada (el admin no necesita aprobación)
            $sqlPropiedad = "INSERT INTO propiedades (
                titulo, descripcion, precio, precio_no_publicado, ambientes, sanitarios, 
                superficie, direccion, ciudad, provincia,
                id_propietario, estado_publicacion, fecha_solicitud, fecha_revision,
                id_admin_revisor, servicios, tipo, operacion, estado
            ) VALUES (
                :titulo, :descripcion, :precio, :precio_no_publicado, :ambientes, :sanitarios,
                :superficie, :direccion, :ciudad, :provincia,
                :id_propietario, 'aprobada', NOW(), NOW(),
                :admin_id, :servicios, 'casa', 'alquiler', 'a estrenar'
            )";
            
            $stmtPropiedad = $conn->prepare($sqlPropiedad);
            $servicios_str = !empty($servicios) ? implode(',', $servicios) : '';
            
            // Usar el ID del admin como propietario (o NULL si prefieres)
            $id_propietario = null; // Dejar NULL o usar un propietario específico
            
            $stmtPropiedad->execute([
                ':titulo' => $titulo,
                ':descripcion' => $descripcion,
                ':precio' => $no_decirlo ? 0 : $precio,
                ':precio_no_publicado' => $no_decirlo,
                ':ambientes' => $ambientes,
                ':sanitarios' => $banios,
                ':superficie' => $superficie,
                ':direccion' => $direccion,
                ':ciudad' => $ciudad,
                ':provincia' => $provincia,
                ':id_propietario' => $id_propietario,
                ':admin_id' => $_SESSION['admin_id'],
                ':servicios' => $servicios_str
            ]);
            
            $id_propiedad = $conn->lastInsertId();
            
            // Procesar imágenes si las hay
            if (!empty($_FILES['imagenes']) && $_FILES['imagenes']['name'][0] != '') {
                $year = date('Y');
                $month = date('m');
                $upload_dir = "../media/propiedades/$year/$month/";
                
                // Crear directorios si no existen
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Guardar cada imagen
                $total_imagenes = count($_FILES['imagenes']['name']);
                $imagenes_subidas = 0;
                
                for ($i = 0; $i < $total_imagenes; $i++) {
                    if ($_FILES['imagenes']['error'][$i] == UPLOAD_ERR_OK) {
                        $nombre_original = $_FILES['imagenes']['name'][$i];
                        $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
                        $nuevo_nombre = "propiedad_{$id_propiedad}_" . ($i + 1) . "_" . time() . ".$extension";
                        $ruta_completa = $upload_dir . $nuevo_nombre;
                        
                        if (move_uploaded_file($_FILES['imagenes']['tmp_name'][$i], $ruta_completa)) {
                            // Insertar en base de datos
                            $sql_img = "INSERT INTO imagenes_propiedades 
                                        (id_propiedad, nombre_archivo, ruta, es_principal, orden) 
                                        VALUES (:id_propiedad, :nombre_archivo, :ruta, :es_principal, :orden)";
                            
                            $stmt_img = $conn->prepare($sql_img);
                            $stmt_img->execute([
                                ':id_propiedad' => $id_propiedad,
                                ':nombre_archivo' => $nuevo_nombre,
                                ':ruta' => "propiedades/$year/$month/$nuevo_nombre",
                                ':es_principal' => ($i == 0 ? 1 : 0),
                                ':orden' => $i
                            ]);
                            
                            $imagenes_subidas++;
                        }
                    }
                }
            }
            
            // Registrar en logs
            $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
            $conn->prepare($logSql)->execute([$adminNombre, "Subió propiedad: $titulo (ID: $id_propiedad)"]);
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Propiedad subida exitosamente y publicada',
                'id' => $id_propiedad
            ]);
            
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Error subiendo propiedad: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'errors' => $errores]);
    }
    
    exit;
}

/* ====== CONFIGURACIÓN DE PAGINACIÓN ====== */
$usuariosPorPagina = 7;
$logsPorPagina = 7;

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

/* ====== PROCESAR SOLICITUDES PENDIENTES ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'gestionar_solicitud') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido.']);
        exit;
    }
    
    $id_propiedad = isset($_POST['id_propiedad']) ? (int)$_POST['id_propiedad'] : 0;
    $decision = isset($_POST['decision']) ? $_POST['decision'] : ''; // 'aprobar' o 'rechazar'
    $motivo_rechazo = isset($_POST['motivo_rechazo']) ? trim($_POST['motivo_rechazo']) : '';
    
    if ($id_propiedad <= 0 || !in_array($decision, ['aprobar', 'rechazar'])) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
        exit;
    }
    
    if ($decision === 'rechazar' && empty($motivo_rechazo)) {
        echo json_encode(['success' => false, 'error' => 'Debes ingresar un motivo para rechazar.']);
        exit;
    }
    
    try {
        // Obtener información de la propiedad
        $sqlPropiedad = "SELECT p.*, up.nombre as propietario_nombre, up.correo as propietario_correo 
                        FROM propiedades p 
                        LEFT JOIN usuario_propietario up ON p.id_propietario = up.id 
                        WHERE p.id = :id";
        $stmtProp = $conn->prepare($sqlPropiedad);
        $stmtProp->execute([':id' => $id_propiedad]);
        $propiedad = $stmtProp->fetch(PDO::FETCH_ASSOC);
        
        if (!$propiedad) {
            echo json_encode(['success' => false, 'error' => 'Propiedad no encontrada.']);
            exit;
        }
        
        // Verificar que esté pendiente
        if ($propiedad['estado_publicacion'] !== 'pendiente') {
            echo json_encode(['success' => false, 'error' => 'Esta solicitud ya fue procesada.']);
            exit;
        }
        
        // Iniciar transacción
        $conn->beginTransaction();
        
        if ($decision === 'aprobar') {
            // Aprobar propiedad
            $sqlAprobar = "UPDATE propiedades SET 
                          estado_publicacion = 'aprobada',
                          fecha_revision = NOW(),
                          id_admin_revisor = :admin_id,
                          motivo_rechazo = NULL
                          WHERE id = :id";
            
            $stmtAprobar = $conn->prepare($sqlAprobar);
            $stmtAprobar->execute([
                ':admin_id' => $_SESSION['admin_id'],
                ':id' => $id_propiedad
            ]);
            
            // Crear notificación de aprobación
            $tituloNotif = "¡Propiedad aprobada!";
            $mensajeNotif = "Tu propiedad \"{$propiedad['titulo']}\" ha sido aprobada y ya está visible en el sitio.";
            $tipoNotif = 'aprobacion';
            
            // Guardar en logs
            $accionLog = "Aprobó propiedad: {$propiedad['titulo']} (ID: $id_propiedad)";
            
        } else {
            // Rechazar propiedad
            $sqlRechazar = "UPDATE propiedades SET 
                           estado_publicacion = 'rechazada',
                           fecha_revision = NOW(),
                           id_admin_revisor = :admin_id,
                           motivo_rechazo = :motivo
                           WHERE id = :id";
            
            $stmtRechazar = $conn->prepare($sqlRechazar);
            $stmtRechazar->execute([
                ':admin_id' => $_SESSION['admin_id'],
                ':motivo' => $motivo_rechazo,
                ':id' => $id_propiedad
            ]);
            
            // Crear notificación de rechazo
            $tituloNotif = "Propiedad rechazada";
            $mensajeNotif = "Tu propiedad \"{$propiedad['titulo']}\" ha sido rechazada. Motivo: $motivo_rechazo";
            $tipoNotif = 'rechazo';
            
            // Guardar en logs
            $accionLog = "Rechazó propiedad: {$propiedad['titulo']} (ID: $id_propiedad)";
        }
        
        // Crear notificación para el propietario
        $sqlNotif = "INSERT INTO notificaciones (id_usuario, id_propiedad, titulo, mensaje, tipo, fecha) 
                    VALUES (:id_usuario, :id_propiedad, :titulo, :mensaje, :tipo, NOW())";
        $stmtNotif = $conn->prepare($sqlNotif);
        $stmtNotif->execute([
            ':id_usuario' => $propiedad['id_propietario'],
            ':id_propiedad' => $id_propiedad,
            ':titulo' => $tituloNotif,
            ':mensaje' => $mensajeNotif,
            ':tipo' => $tipoNotif
        ]);
        
        // Registrar en logs_actividad
        $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
        $conn->prepare($logSql)->execute([$adminNombre, $accionLog]);
        
        // Confirmar transacción
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $decision === 'aprobar' ? 'Propiedad aprobada correctamente' : 'Propiedad rechazada correctamente',
            'nuevo_estado' => $decision === 'aprobar' ? 'aprobada' : 'rechazada'
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error gestionando solicitud: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
    exit;
}

/* ====== OBTENER DETALLES DE SOLICITUD PARA MODAL ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'obtener_detalles_solicitud') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido.']);
        exit;
    }
    
    $id_propiedad = isset($_POST['id_propiedad']) ? (int)$_POST['id_propiedad'] : 0;
    
    if ($id_propiedad <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de propiedad inválido.']);
        exit;
    }
    
    try {
        // Obtener detalles completos de la propiedad
        $sql = "SELECT 
            p.*,
            up.nombre as propietario_nombre,
            up.correo as propietario_correo,
            up.telefono as propietario_telefono,
            GROUP_CONCAT(ip.ruta SEPARATOR '||') as imagenes_rutas,
            GROUP_CONCAT(ip.nombre_archivo SEPARATOR '||') as imagenes_nombres
        FROM propiedades p
        LEFT JOIN usuario_propietario up ON p.id_propietario = up.id
        LEFT JOIN imagenes_propiedades ip ON p.id = ip.id_propiedad
        WHERE p.id = :id_propiedad
        GROUP BY p.id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id_propiedad' => $id_propiedad]);
        $propiedad = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$propiedad) {
            echo json_encode(['success' => false, 'error' => 'Propiedad no encontrada.']);
            exit;
        }
        
        // Procesar servicios como array
        if (!empty($propiedad['servicios'])) {
            $propiedad['servicios_array'] = explode(',', $propiedad['servicios']);
        } else {
            $propiedad['servicios_array'] = [];
        }
        
        // Procesar imágenes
        $imagenes = [];
        if (!empty($propiedad['imagenes_rutas'])) {
            $rutas = explode('||', $propiedad['imagenes_rutas']);
            $nombres = explode('||', $propiedad['imagenes_nombres']);
            
            for ($i = 0; $i < count($rutas); $i++) {
                if (!empty($rutas[$i])) {
                    $imagenes[] = [
                        'ruta' => $rutas[$i],
                        'nombre' => $nombres[$i] ?? 'imagen_' . ($i + 1)
                    ];
                }
            }
        }
        $propiedad['imagenes_array'] = $imagenes;
        
        // Calcular precio a mostrar
        if ($propiedad['precio_no_publicado'] == 1) {
            $propiedad['precio_display'] = 'No publicado';
        } else {
            $propiedad['precio_display'] = '$' . number_format($propiedad['precio'], 0, ',', '.');
        }
        
        // Formatear fecha
        $propiedad['fecha_solicitud_formateada'] = date('d/m/Y H:i', strtotime($propiedad['fecha_solicitud']));
        
        echo json_encode([
            'success' => true,
            'propiedad' => $propiedad
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo detalles de solicitud: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
    exit;
}

/* ====== OBTENER SOLICITUDES PENDIENTES ====== */
$solicitudesPorPagina = 5;
$paginaSolicitudes = isset($_GET['pagina_solicitudes']) ? max(1, (int)$_GET['pagina_solicitudes']) : 1;
$offsetSolicitudes = ($paginaSolicitudes - 1) * $solicitudesPorPagina;

try {
    // Contar total de solicitudes pendientes
    $totalSolicitudes = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'pendiente'")->fetchColumn();
    $totalPaginasSolicitudes = ceil($totalSolicitudes / $solicitudesPorPagina);
    
    // Obtener solicitudes pendientes con datos del propietario (CORREGIDO)
    $sqlSolicitudes = "SELECT 
        p.id,
        p.titulo,
        p.descripcion,
        p.precio,
        p.precio_no_publicado,
        p.ambientes,
        p.sanitarios,
        p.superficie,
        p.direccion,
        p.fecha_solicitud,
        up.nombre as propietario_nombre,
        up.correo as propietario_correo,
        ip.ruta as imagen_principal
    FROM propiedades p
    LEFT JOIN usuario_propietario up ON p.id_propietario = up.id
    LEFT JOIN imagenes_propiedades ip ON p.id = ip.id_propiedad AND ip.es_principal = 1
    WHERE p.estado_publicacion = 'pendiente'
    ORDER BY p.fecha_solicitud DESC
    LIMIT :limit OFFSET :offset";
    
    $stmtSolicitudes = $conn->prepare($sqlSolicitudes);
    $stmtSolicitudes->bindValue(':limit', $solicitudesPorPagina, PDO::PARAM_INT);
    $stmtSolicitudes->bindValue(':offset', $offsetSolicitudes, PDO::PARAM_INT);
    $stmtSolicitudes->execute();
    $solicitudes = $stmtSolicitudes->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error obteniendo solicitudes pendientes: " . $e->getMessage());
    $totalSolicitudes = 0;
    $totalPaginasSolicitudes = 1;
    $solicitudes = [];
}

/* ====== GESTIÓN DE PROPIEDADES PUBLICADAS ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'gestionar_propiedad') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido.']);
        exit;
    }
    
    $id_propiedad = isset($_POST['id_propiedad']) ? (int)$_POST['id_propiedad'] : 0;
    $accion = isset($_POST['tipo_accion']) ? $_POST['tipo_accion'] : ''; // 'ocultar' o 'mostrar'
    
    if ($id_propiedad <= 0 || !in_array($accion, ['ocultar', 'mostrar'])) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
        exit;
    }
    
    try {
        // Obtener información de la propiedad
        $sqlPropiedad = "SELECT p.*, up.nombre as propietario_nombre 
                        FROM propiedades p 
                        LEFT JOIN usuario_propietario up ON p.id_propietario = up.id 
                        WHERE p.id = :id";
        $stmtProp = $conn->prepare($sqlPropiedad);
        $stmtProp->execute([':id' => $id_propiedad]);
        $propiedad = $stmtProp->fetch(PDO::FETCH_ASSOC);
        
        if (!$propiedad) {
            echo json_encode(['success' => false, 'error' => 'Propiedad no encontrada.']);
            exit;
        }
        
        // Verificar que esté aprobada o inactiva
        if (!in_array($propiedad['estado_publicacion'], ['aprobada', 'inactiva'])) {
            echo json_encode(['success' => false, 'error' => 'Solo se pueden gestionar propiedades aprobadas o inactivas.']);
            exit;
        }
        
        // Cambiar estado de visibilidad
        $nuevo_estado = $accion === 'ocultar' ? 'inactiva' : 'aprobada';
        
        $sqlActualizar = "UPDATE propiedades SET estado_publicacion = :estado WHERE id = :id";
        $stmtActualizar = $conn->prepare($sqlActualizar);
        $stmtActualizar->execute([
            ':estado' => $nuevo_estado,
            ':id' => $id_propiedad
        ]);
        
        // Registrar en logs
        $accionTexto = $accion === 'ocultar' ? 'Ocultó' : 'Volvió a mostrar';
        $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
        $conn->prepare($logSql)->execute([$adminNombre, "$accionTexto propiedad: {$propiedad['titulo']} (ID: $id_propiedad)"]);
        
        echo json_encode([
            'success' => true,
            'message' => $accion === 'ocultar' ? 'Propiedad ocultada del sitio principal' : 'Propiedad visible nuevamente',
            'nuevo_estado' => $nuevo_estado
        ]);
        
    } catch (Exception $e) {
        error_log("Error gestionando propiedad: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
    exit;
}

/* ====== OBTENER PROPIEDADES PUBLICADAS ====== */
$propiedadesPorPagina = 8;
$paginaPropiedades = isset($_GET['pagina_propiedades']) ? max(1, (int)$_GET['pagina_propiedades']) : 1;
$offsetPropiedades = ($paginaPropiedades - 1) * $propiedadesPorPagina;

try {
    // Contar total de propiedades aprobadas e inactivas
    $totalPropiedades = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion IN ('aprobada', 'inactiva')")->fetchColumn();
    
    // Contar solo las visibles (aprobadas)
    $propiedadesVisibles = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'aprobada'")->fetchColumn();
    
    $totalPaginasPropiedades = ceil($totalPropiedades / $propiedadesPorPagina);
    
    // Obtener propiedades
    $sqlPropiedades = "SELECT 
        p.id,
        p.titulo,
        p.descripcion,
        p.precio,
        p.precio_no_publicado,
        p.ambientes,
        p.sanitarios,
        p.superficie,
        p.direccion,
        p.estado_publicacion,
        p.fecha_solicitud,
        p.fecha_revision,
        up.nombre as propietario_nombre,
        up.correo as propietario_correo,
        ip.ruta as imagen_principal
    FROM propiedades p
    LEFT JOIN usuario_propietario up ON p.id_propietario = up.id
    LEFT JOIN imagenes_propiedades ip ON p.id = ip.id_propiedad AND ip.es_principal = 1
    WHERE p.estado_publicacion IN ('aprobada', 'inactiva')
    ORDER BY 
        CASE 
            WHEN p.estado_publicacion = 'aprobada' THEN 1 
            WHEN p.estado_publicacion = 'inactiva' THEN 2 
        END,
        p.fecha_revision DESC
    LIMIT :limit OFFSET :offset";
    
    $stmtPropiedades = $conn->prepare($sqlPropiedades);
    $stmtPropiedades->bindValue(':limit', $propiedadesPorPagina, PDO::PARAM_INT);
    $stmtPropiedades->bindValue(':offset', $offsetPropiedades, PDO::PARAM_INT);
    $stmtPropiedades->execute();
    $propiedadesPublicadas = $stmtPropiedades->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error obteniendo propiedades publicadas: " . $e->getMessage());
    $totalPropiedades = 0;
    $propiedadesVisibles = 0;
    $totalPaginasPropiedades = 1;
    $propiedadesPublicadas = [];
}

/* ====== NOTIFICACIONES - CONTAR SOLICITUDES PENDIENTES ====== */
try {
    $solicitudesPendientes = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'pendiente'")->fetchColumn();
} catch (Exception $e) {
    error_log("Error contando solicitudes pendientes: " . $e->getMessage());
    $solicitudesPendientes = 0;
}

/* ====== VERIFICAR Y ACTUALIZAR CAMPO ESTADO_PUBLICACION PARA 'inactiva' ====== */
try {
    // Verificar si existe el valor 'inactiva' en el enum
    $checkEnum = $conn->query("SHOW COLUMNS FROM propiedades LIKE 'estado_publicacion'")->fetch(PDO::FETCH_ASSOC);
    if ($checkEnum && strpos($checkEnum['Type'], "'inactiva'") === false) {
        // Agregar 'inactiva' al enum si no existe
        $conn->exec("ALTER TABLE propiedades MODIFY estado_publicacion ENUM('pendiente','aprobada','rechazada','inactiva') DEFAULT 'pendiente'");
        error_log("Campo estado_publicacion actualizado con 'inactiva'");
    }
} catch (Exception $e) {
    error_log("Error verificando campo estado_publicacion: " . $e->getMessage());
}

/* ====== OBTENER SERVICIOS DISPONIBLES ====== */
$servicios_disponibles = obtenerServicios($conn);

/* ====== ENDPOINT PARA CONTAR SOLICITUDES (AJAX) ====== */
if (isset($_GET['accion']) && $_GET['accion'] === 'contar_solicitudes' && isset($_GET['ajax'])) {
    try {
        $total = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'pendiente'")->fetchColumn();
        echo json_encode(['success' => true, 'total' => $total]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'total' => 0]);
    }
    exit;
}

/* ====== VERIFICAR MENSAJES DE CONFIRMACIÓN ====== */
if (isset($_SESSION['mensaje_confirmacion'])) {
    $mensajeConfirmacion = $_SESSION['mensaje_confirmacion'];
    $tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'success';
    unset($_SESSION['mensaje_confirmacion']);
    unset($_SESSION['tipo_mensaje']);
}
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
    <style>
        /* Estilos adicionales para formulario de propiedades */
        .formulario-admin-container {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .grid-formulario {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .grupo-formulario {
            margin-bottom: 20px;
        }
        
        .ancho-completo {
            grid-column: 1 / -1;
        }
        
        .etiqueta-formulario {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .entrada-formulario, .area-texto-formulario, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .entrada-formulario:focus, .area-texto-formulario:focus, .form-select:focus {
            outline: none;
            border-color: #4a6cf7;
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
        }
        
        .area-texto-formulario {
            min-height: 120px;
            resize: vertical;
        }
        
        .contenedor-precio {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .entrada-con-icono {
            position: relative;
        }
        
        .entrada-con-icono i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        .entrada-con-icono input {
            padding-left: 45px;
        }
        
        .etiqueta-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 14px;
            color: #555;
        }
        
        .titulo-seccion-formulario {
            font-size: 18px;
            margin: 20px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .grid-caracteristicas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .entrada-caracteristica {
            margin-bottom: 15px;
        }
        
        .grid-servicios {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        
        .checkbox-servicio {
            display: block;
            cursor: pointer;
        }
        
        .checkbox-servicio input {
            display: none;
        }
        
        .item-servicio {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s;
            background: #fafafa;
        }
        
        .checkbox-servicio input:checked + .item-servicio {
            background: #e3f2fd;
            border-color: #4a6cf7;
            color: #4a6cf7;
        }
        
        .item-servicio i {
            font-size: 18px;
        }
        
        .area-subida-archivos {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s;
            background: #fafafa;
            position: relative;
        }
        
        .area-subida-archivos:hover {
            border-color: #4a6cf7;
        }
        
        .area-subida-archivos input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .icono-subida {
            font-size: 48px;
            color: #999;
            margin-bottom: 15px;
        }
        
        .texto-subida {
            color: #666;
            margin-bottom: 10px;
        }
        
        .lista-archivos {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .item-archivo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
        }
        
        .item-archivo .nombre-archivo {
            flex: 1;
            margin-left: 10px;
        }
        
        .item-archivo .tamano-archivo {
            color: #666;
            font-size: 12px;
        }
        
        .item-archivo .eliminar-archivo {
            color: #ff4757;
            cursor: pointer;
            padding: 5px;
        }
        
        .acciones-formulario {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .boton-principal, .boton-secundario {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .boton-principal {
            background: #4a6cf7;
            color: white;
        }
        
        .boton-principal:hover {
            background: #3a5ce5;
            transform: translateY(-2px);
        }
        
        .boton-secundario {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .boton-secundario:hover {
            background: #e9ecef;
        }
        
        .btn-pequeno {
            padding: 5px 10px;
            font-size: 12px;
            background: #f0f0f0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .sin-servicios {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        .btn-agregar-servicio {
            background: #ff4757;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn-agregar-servicio:hover {
            background: #ff3742;
        }
        
        /* Estilos para íconos en modal de servicios */
        .iconos-populares {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .grid-iconos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .icono-option {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .icono-option:hover {
            border-color: #4a6cf7;
            background: #f0f4ff;
        }
        
        .link-ayuda {
            color: #4a6cf7;
            text-decoration: none;
            font-size: 12px;
        }
        
        .link-ayuda:hover {
            text-decoration: underline;
        }
        
        /* Estilos para propiedades publicadas */
        .estado-visible {
            background: #4CAF50;
            color: white;
        }
        
        .estado-oculta {
            background: #ff9800;
            color: white;
        }
        
        .badge-estado-propiedad {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-ocultar-propiedad, .btn-mostrar-propiedad {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-ocultar-propiedad {
            background: #ff9800;
            color: white;
        }
        
        .btn-ocultar-propiedad:hover {
            background: #f57c00;
        }
        
        .btn-mostrar-propiedad {
            background: #4CAF50;
            color: white;
        }
        
        .btn-mostrar-propiedad:hover {
            background: #388E3C;
        }
        
        .btn-ver-propiedad {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            color: #666;
        }
        
        .btn-ver-propiedad:hover {
            background: #f5f5f5;
        }
        
        /* Estilos para modal de confirmación */
        .modal-contenido {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.3);
            z-index: 1000;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        /* Overlay para modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        
        /* Modal activo */
        .modal.active {
            display: block;
        }
        
        .modal-overlay.active {
            display: block;
        }
        
        /* Estilos para mensajes de confirmación */
        .mensaje-confirmacion {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1001;
            animation: slideIn 0.3s ease-out;
            max-width: 400px;
        }
        
        .mensaje-confirmacion.error {
            background: #f44336;
        }
        
        .mensaje-confirmacion.warning {
            background: #ff9800;
        }
        
        .mensaje-confirmacion.info {
            background: #2196F3;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .cerrar-mensaje {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 18px;
            margin-left: 10px;
        }
    </style>
    <!-- Al principio del body o en el head -->
    <input type="hidden" id="csrf_token_input" value="<?= $csrf_token ?>">
</head>
<body>
<div class="layout">

    <!-- SIDEBAR FIJO -->
    <aside class="sidebar">
        <h2 class="logo">RENTNONO</h2>

        <!-- PRIMERO: INICIO -->
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

        <!-- TERCERO: PROPIEDADES PUBLICADAS (nuevo ícono de ojo) -->
        <button class="menu-btn <?= $seccionActiva === 'propiedadespublicadas' ? 'activo' : '' ?>" data-seccion="propiedadespublicadas" id="btnPropiedadespublicadas">
            <i class="fa-solid fa-eye"></i> <span class="menu-text">Propiedades Publicadas</span>
        </button>

        <!-- CUARTO: PROPIEDADES (formulario para admin) -->
        <button class="menu-btn <?= $seccionActiva === 'propiedades' ? 'activo' : '' ?>" data-seccion="propiedades" id="btnPropiedades">
            <i class="fa-solid fa-building"></i> <span class="menu-text">Propiedades</span>
        </button>

        <!-- QUINTO: SOLICITUDES PENDIENTES -->
        <button class="menu-btn <?= $seccionActiva === 'solicitudes' ? 'activo' : '' ?>" data-seccion="solicitudes" id="btnSolicitudes">
            <i class="fa-solid fa-clock"></i> 
            <span class="menu-text">Solicitudes Pendientes</span>
            <?php if ($solicitudesPendientes > 0): ?>
            <span class="badge-notificacion" id="badgeSolicitudes">
                <?= $solicitudesPendientes ?>
            </span>
            <?php endif; ?>
        </button>

        <!-- SEXTO: ESTADÍSTICAS -->
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

        <!-- INICIO -->
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
                <div class="tabla-title">
                    <h2>Solicitudes Pendientes</h2>
                    <span class="tabla-subtitle">Propiedades enviadas por propietarios para revisión</span>
                </div>
                <div class="usuarios-stats">
                    <span class="stat-item">
                        <i class="fa-solid fa-clock"></i>
                        <span id="totalSolicitudes"><?= $totalSolicitudes ?></span> pendientes
                    </span>
                    <span class="stat-item">
                        <i class="fa-solid fa-calendar-day"></i>
                        Hoy: <span id="solicitudesHoy"><?= $solicitudesHoy ?? 0 ?></span>
                    </span>
                    <button class="btn-refresh" id="refreshSolicitudes" title="Actualizar lista">
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>
                </div>
            </div>
            
            <?php if ($totalPaginasSolicitudes > 1): ?>
            <div class="paginacion-tabla">
                <?php if ($paginaSolicitudes > 1): ?>
                    <a href="?pagina_solicitudes=<?= $paginaSolicitudes - 1 ?>&seccion=solicitudes" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-left"></i>
                    </span>
                <?php endif; ?>
                
                <span class="info-pagina">
                    Pág. <?= $paginaSolicitudes ?> de <?= $totalPaginasSolicitudes ?>
                </span>
                
                <?php if ($paginaSolicitudes < $totalPaginasSolicitudes): ?>
                    <a href="?pagina_solicitudes=<?= $paginaSolicitudes + 1 ?>&seccion=solicitudes" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="tabla-contenedor solicitudes-container">
                <?php if (!empty($solicitudes)): ?>
                    <div class="lista-solicitudes">
                        <?php foreach ($solicitudes as $solicitud): ?>
                        <?php
                        // Calcular días pendiente
                        $fechaSolicitud = new DateTime($solicitud['fecha_solicitud']);
                        $hoy = new DateTime();
                        $diasPendiente = $hoy->diff($fechaSolicitud)->days;
                        
                        // Clase según días
                        $claseDias = '';
                        if ($diasPendiente > 7) $claseDias = 'dias-alto';
                        elseif ($diasPendiente > 3) $claseDias = 'dias-medio';
                        
                        // Precio
                        $precio = $solicitud['precio_no_publicado'] ? 'No publicado' : '$' . number_format($solicitud['precio'], 0, ',', '.');
                        
                        // Imagen
                        $imagen = !empty($solicitud['imagen_principal']) ? 
                            '../media/' . $solicitud['imagen_principal'] : 
                            'https://images.unsplash.com/photo-1518780664697-55e3ad937233?w=150';
                        ?>
                        <div class="tarjeta-solicitud" data-id="<?= $solicitud['id'] ?>">
                            <div class="solicitud-imagen" style="background-image: url('<?= $imagen ?>')">
                                <span class="badge-dias <?= $claseDias ?>"><?= $diasPendiente ?> días</span>
                            </div>
                            <div class="solicitud-info">
                                <div class="solicitud-header">
                                    <h3><?= htmlspecialchars($solicitud['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <span class="solicitud-precio"><?= $precio ?></span>
                                </div>
                                
                                <div class="solicitud-datos">
                                    <div class="dato-item">
                                        <i class="fa-solid fa-user"></i>
                                        <span><strong>Propietario:</strong> <?= htmlspecialchars($solicitud['propietario_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dato-item">
                                        <i class="fa-solid fa-envelope"></i>
                                        <span><?= htmlspecialchars($solicitud['propietario_correo'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dato-item">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <span><?= htmlspecialchars($solicitud['direccion'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dato-item">
                                        <i class="fa-solid fa-ruler-combined"></i>
                                        <span><?= $solicitud['superficie'] ?> m² • <?= $solicitud['ambientes'] ?> amb • <?= $solicitud['sanitarios'] ?> baños</span>
                                    </div>
                                </div>
                                
                                <div class="solicitud-descripcion">
                                    <p><?= htmlspecialchars(substr($solicitud['descripcion'], 0, 150)) ?>...</p>
                                </div>
                                
                                <div class="solicitud-footer">
                                    <span class="solicitud-fecha">
                                        <i class="fa-solid fa-calendar"></i>
                                        Enviada: <?= date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])) ?>
                                    </span>
                                    <div class="solicitud-acciones">
                                        <button class="btn-ver-solicitud" data-id="<?= $solicitud['id'] ?>">
                                            <i class="fa-solid fa-eye"></i> Ver detalles
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="sin-datos-tabla">
                        <i class="fa-solid fa-clipboard-check"></i>
                        <h3>¡No hay solicitudes pendientes!</h3>
                        <p>Todas las solicitudes han sido revisadas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- PROPIEDADES PUBLICADAS -->
        <section id="propiedadespublicadas" class="seccion <?= $seccionActiva === 'propiedadespublicadas' ? 'visible' : '' ?>">
            <div class="usuarios-header">
                <div class="tabla-title">
                    <h2>Propiedades Publicadas</h2>
                    <span class="tabla-subtitle">Gestiona las propiedades visibles en el sitio principal</span>
                </div>
                <div class="usuarios-stats">
                    <span class="stat-item">
                        <i class="fa-solid fa-building"></i>
                        <span id="totalPropiedades"><?= $totalPropiedades ?></span> propiedades
                    </span>
                    <span class="stat-item">
                        <i class="fa-solid fa-eye"></i>
                        Visibles: <span id="propiedadesVisibles"><?= $propiedadesVisibles ?></span>
                    </span>
                    <div class="filtros-propiedades">
                        <select class="selector-filtro" id="filtroPropiedades">
                            <option value="todas">Todas</option>
                            <option value="aprobada">Visibles</option>
                            <option value="inactiva">Ocultas</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <?php if ($totalPaginasPropiedades > 1): ?>
            <div class="paginacion-tabla">
                <?php if ($paginaPropiedades > 1): ?>
                    <a href="?pagina_propiedades=<?= $paginaPropiedades - 1 ?>&seccion=propiedadespublicadas" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-left"></i>
                    </span>
                <?php endif; ?>
                
                <span class="info-pagina">
                    Pág. <?= $paginaPropiedades ?> de <?= $totalPaginasPropiedades ?>
                </span>
                
                <?php if ($paginaPropiedades < $totalPaginasPropiedades): ?>
                    <a href="?pagina_propiedades=<?= $paginaPropiedades + 1 ?>&seccion=propiedadespublicadas" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="tabla-contenedor propiedades-container">
                <?php if (!empty($propiedadesPublicadas)): ?>
                    <div class="grid-propiedades" id="gridPropiedades">
                        <?php foreach ($propiedadesPublicadas as $propiedad): ?>
                        <?php
                        // Determinar estado
                        $estado = $propiedad['estado_publicacion'];
                        $esVisible = $estado === 'aprobada';
                        $claseEstado = $esVisible ? 'estado-visible' : 'estado-oculta';
                        $textoEstado = $esVisible ? 'Visible' : 'Oculta';
                        $iconoEstado = $esVisible ? 'fa-eye' : 'fa-eye-slash';
                        
                        // Precio
                        $precio = $propiedad['precio_no_publicado'] ? 'No publicado' : '$' . number_format($propiedad['precio'], 0, ',', '.');
                        
                        // Imagen
                        $imagen = !empty($propiedad['imagen_principal']) ? 
                            '../media/' . $propiedad['imagen_principal'] : 
                            'https://images.unsplash.com/photo-1518780664697-55e3ad937233?w=300&h=200&fit=crop';
                        
                        // Fecha
                        $fecha = $propiedad['fecha_revision'] ? date('d/m/Y', strtotime($propiedad['fecha_revision'])) : 
                                date('d/m/Y', strtotime($propiedad['fecha_solicitud']));
                        ?>
                        <div class="tarjeta-propiedad" data-id="<?= $propiedad['id'] ?>" data-estado="<?= $estado ?>">
                            <div class="propiedad-imagen" style="background-image: url('<?= $imagen ?>')">
                                <span class="badge-estado-propiedad <?= $claseEstado ?>">
                                    <i class="fa-solid <?= $iconoEstado ?>"></i> <?= $textoEstado ?>
                                </span>
                            </div>
                            <div class="propiedad-info">
                                <div class="propiedad-header">
                                    <h3><?= htmlspecialchars($propiedad['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <span class="propiedad-precio"><?= $precio ?></span>
                                </div>
                                
                                <div class="propiedad-datos">
                                    <div class="dato-propiedad">
                                        <i class="fa-solid fa-user"></i>
                                        <span><?= htmlspecialchars($propiedad['propietario_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dato-propiedad">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <span><?= htmlspecialchars($propiedad['direccion'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dato-propiedad">
                                        <i class="fa-solid fa-expand-arrows-alt"></i>
                                        <span><?= $propiedad['superficie'] ?> m² • <?= $propiedad['ambientes'] ?> amb • <?= $propiedad['sanitarios'] ?> baños</span>
                                    </div>
                                </div>
                                
                                <div class="propiedad-descripcion">
                                    <p><?= htmlspecialchars(substr($propiedad['descripcion'], 0, 100)) ?>...</p>
                                </div>
                                
                                <div class="propiedad-footer">
                                    <span class="propiedad-fecha">
                                        <i class="fa-solid fa-calendar"></i>
                                        <?= $esVisible ? 'Publicada' : 'Oculta desde' ?>: <?= $fecha ?>
                                    </span>
                                    <div class="propiedad-acciones">
                                        <?php if ($esVisible): ?>
                                        <button class="btn-ocultar-propiedad" data-id="<?= $propiedad['id'] ?>" data-titulo="<?= htmlspecialchars($propiedad['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-eye-slash"></i> Ocultar
                                        </button>
                                        <?php else: ?>
                                        <button class="btn-mostrar-propiedad" data-id="<?= $propiedad['id'] ?>" data-titulo="<?= htmlspecialchars($propiedad['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-eye"></i> Mostrar
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn-ver-propiedad" data-id="<?= $propiedad['id'] ?>">
                                            <i class="fa-solid fa-info-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="sin-datos-tabla">
                        <i class="fa-solid fa-building"></i>
                        <h3>No hay propiedades publicadas</h3>
                        <p>Las propiedades aparecerán aquí una vez que sean aprobadas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- PROPIEDADES (formulario para admin) -->
        <section id="propiedades" class="seccion <?= $seccionActiva === 'propiedades' ? 'visible' : '' ?>">
            <div class="usuarios-header">
                <div class="tabla-title">
                    <h2>Subir Propiedad</h2>
                    <span class="tabla-subtitle">Agrega propiedades directamente al sitio (sin necesidad de aprobación)</span>
                </div>
                <div class="usuarios-stats">
                    <button class="btn-agregar-servicio" id="btnAgregarServicio">
                        <i class="fa-solid fa-plus"></i> Agregar Servicio
                    </button>
                </div>
            </div>
            
            <div class="formulario-admin-container">
                <form class="formulario-propiedad-admin" id="formulario-propiedad-admin" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="accion" value="subir_propiedad_admin">
                    
                    <div class="grid-formulario">
                        <!-- Título -->
                        <div class="grupo-formulario">
                            <label for="titulo" class="etiqueta-formulario">Título de la propiedad *</label>
                            <input type="text" id="titulo" name="titulo" class="entrada-formulario" placeholder="Ej: Casa amplia de 3 ambientes" required>
                        </div>

                        <!-- Descripción -->
                        <div class="grupo-formulario">
                            <label for="descripcion" class="etiqueta-formulario">Descripción detallada *</label>
                            <textarea id="descripcion" name="descripcion" class="area-texto-formulario" rows="4" placeholder="Describe la propiedad..." required></textarea>
                        </div>

                        <!-- Precio -->
                        <div class="grupo-formulario">
                            <label for="precio" class="etiqueta-formulario">Precio mensual *</label>
                            <div class="contenedor-precio">
                                <div class="entrada-con-icono">
                                    <i class="fa-solid fa-dollar-sign"></i>
                                    <input type="number" id="precio" name="precio" class="entrada-formulario" placeholder="120000" min="0" step="1" required>
                                </div>
                                <label class="etiqueta-checkbox">
                                    <input type="checkbox" id="no-decirlo" name="no_decirlo">
                                    <span>No publicar precio</span>
                                </label>
                            </div>
                        </div>

                        <!-- Características -->
                        <div class="grupo-formulario ancho-completo">
                            <h3 class="titulo-seccion-formulario">Características principales</h3>
                            <div class="grid-caracteristicas">
                                <div class="entrada-caracteristica">
                                    <label for="ambientes" class="etiqueta-formulario">Ambientes *</label>
                                    <div class="entrada-con-icono">
                                        <i class="fa-solid fa-door-open"></i>
                                        <input type="number" id="ambientes" name="ambientes" class="entrada-formulario" placeholder="3" min="1" required>
                                    </div>
                                </div>
                                <div class="entrada-caracteristica">
                                    <label for="banios" class="etiqueta-formulario">Baños *</label>
                                    <div class="entrada-con-icono">
                                        <i class="fa-solid fa-bath"></i>
                                        <input type="number" id="banios" name="banios" class="entrada-formulario" placeholder="2" min="1" required>
                                    </div>
                                </div>
                                <div class="entrada-caracteristica">
                                    <label for="superficie" class="etiqueta-formulario">Superficie (m²) *</label>
                                    <div class="entrada-con-icono">
                                        <i class="fa-solid fa-ruler-combined"></i>
                                        <input type="number" id="superficie" name="superficie" class="entrada-formulario" placeholder="80" min="10" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Servicios -->
                        <div class="grupo-formulario ancho-completo">
                            <h3 class="titulo-seccion-formulario">
                                Servicios incluidos
                                <button type="button" class="btn-pequeno" id="btnRefreshServicios" title="Actualizar lista">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                            </h3>
                            <div class="grid-servicios" id="gridServicios">
                                <?php if (!empty($servicios_disponibles)): ?>
                                    <?php foreach ($servicios_disponibles as $servicio): ?>
                                    <label class="checkbox-servicio">
                                        <input type="checkbox" name="servicios[]" value="<?= htmlspecialchars($servicio['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="item-servicio">
                                            <i class="<?= htmlspecialchars($servicio['icono'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                            <span><?= htmlspecialchars($servicio['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="sin-servicios">No hay servicios disponibles. Agrega algunos primero.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Ubicación (simplificada para admin) -->
                        <div class="grupo-formulario">
                            <label for="direccion" class="etiqueta-formulario">Dirección *</label>
                            <input type="text" id="direccion" name="direccion" class="entrada-formulario" placeholder="Calle, número, ciudad" required>
                        </div>

                        <div class="grupo-formulario">
                            <label for="ciudad" class="etiqueta-formulario">Ciudad</label>
                            <input type="text" id="ciudad" name="ciudad" class="entrada-formulario" placeholder="Ciudad">
                        </div>

                        <div class="grupo-formulario">
                            <label for="provincia" class="etiqueta-formulario">Provincia</label>
                            <input type="text" id="provincia" name="provincia" class="entrada-formulario" placeholder="Provincia">
                        </div>

                        <!-- Imágenes -->
                        <div class="grupo-formulario ancho-completo">
                            <h3 class="titulo-seccion-formulario">Imágenes de la propiedad</h3>
                            <div class="area-subida-archivos" id="areaSubidaArchivosAdmin">
                                <i class="fa-solid fa-cloud-upload-alt icono-subida"></i>
                                <p class="texto-subida">Arrastra y suelta imágenes aquí o haz clic para seleccionar</p>
                                <input type="file" id="imagenes" name="imagenes[]" multiple accept="image/*">
                                <div class="lista-archivos" id="listaArchivosAdmin"></div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="grupo-formulario ancho-completo acciones-formulario">
                            <button type="button" class="boton-secundario" onclick="limpiarFormularioPropiedad()">
                                <i class="fa-solid fa-broom"></i> Limpiar
                            </button>
                            <button type="submit" class="boton-principal" id="btnSubirPropiedadAdmin">
                                <i class="fa-solid fa-cloud-upload"></i> Publicar Propiedad
                            </button>
                        </div>
                    </div>
                </form>
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

<!-- OVERLAY PARA MODALES -->
<div class="modal-overlay" id="modalOverlay"></div>

<!-- MODAL DE CONFIRMACIÓN -->
<div class="modal" id="modalConfirmacion" style="display:none;">
    <div class="modal-contenido modal-confirmacion">
        <div class="modal-header">
            <h3><i class="fa-solid fa-check-circle"></i> Confirmación</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div class="confirmacion-icono">
                <i class="fa-solid fa-check-circle" id="iconoConfirmacion"></i>
            </div>
            <p id="textoConfirmacion"></p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancelar" id="cerrarConfirmacion">Cerrar</button>
        </div>
    </div>
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

<!-- MODAL AGREGAR NUEVO SERVICIO -->
<div class="modal" id="modalAgregarServicio" style="display:none;">
    <div class="modal-contenido modal-mejorado">
        <div class="modal-header">
            <h3><i class="fa-solid fa-plus-circle"></i> Agregar Nuevo Servicio</h3>
            <span class="cerrar">&times;</span>
        </div>
        
        <div class="modal-body">
            <form id="formAgregarServicio">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="accion" value="agregar_servicio">

                <div class="form-group">
                    <label for="nombreServicio"><i class="fa-solid fa-tag"></i> Nombre del Servicio</label>
                    <input type="text" name="nombre_servicio" id="nombreServicio" required maxlength="50" 
                           placeholder="Ej: Gimnasio, Sauna, Lavandería...">
                    <div class="form-help">Nombre descriptivo del servicio (máx. 50 caracteres)</div>
                    <div class="form-error" id="errorNombreServicio"></div>
                </div>

                <div class="form-group">
                    <label for="iconoServicio"><i class="fa-solid fa-icons"></i> Icono (FontAwesome)</label>
                    <div class="entrada-con-icono">
                        <i class="fa-solid fa-star" id="previewIcono"></i>
                        <input type="text" name="icono_servicio" id="iconoServicio" 
                               placeholder="fa-solid fa-star" value="fa-solid fa-star">
                    </div>
                    <div class="form-help">
                        Usa clases de FontAwesome. Ej: fa-solid fa-wifi, fa-solid fa-car, etc.
                        <a href="https://fontawesome.com/icons" target="_blank" class="link-ayuda">
                            Ver íconos disponibles
                        </a>
                    </div>
                    <div class="form-error" id="errorIconoServicio"></div>
                </div>

                <div class="iconos-populares">
                    <h4>Íconos populares:</h4>
                    <div class="grid-iconos">
                        <button type="button" class="icono-option" data-icono="fa-solid fa-wifi">
                            <i class="fa-solid fa-wifi"></i> WiFi
                        </button>
                        <button type="button" class="icono-option" data-icono="fa-solid fa-car">
                            <i class="fa-solid fa-car"></i> Cochera
                        </button>
                        <button type="button" class="icono-option" data-icono="fa-solid fa-swimming-pool">
                            <i class="fa-solid fa-swimming-pool"></i> Pileta
                        </button>
                        <button type="button" class="icono-option" data-icono="fa-solid fa-dumbbell">
                            <i class="fa-solid fa-dumbbell"></i> Gimnasio
                        </button>
                        <button type="button" class="icono-option" data-icono="fa-solid fa-hot-tub">
                            <i class="fa-solid fa-hot-tub"></i> Jacuzzi
                        </button>
                        <button type="button" class="icono-option" data-icono="fa-solid fa-utensils">
                            <i class="fa-solid fa-utensils"></i> Cocina
                        </button>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" id="cancelarAgregarServicio">Cancelar</button>
                    <button type="submit" class="btn-guardar" id="submitAgregarServicio">
                        <i class="fa-solid fa-plus"></i> Agregar Servicio
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

<!-- MODAL CONFIRMAR SUBIR PROPIEDAD -->
<div class="modal" id="modalConfirmarSubirPropiedad" style="display:none;">
    <div class="modal-contenido modal-confirmacion">
        <div class="modal-header">
            <h3><i class="fa-solid fa-cloud-upload"></i> Confirmar Publicación</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div class="confirmacion-icono">
                <i class="fa-solid fa-building"></i>
            </div>
            <p>¿Estás seguro de que quieres publicar esta propiedad?</p>
            <p class="confirmacion-detalle">
                <i class="fa-solid fa-info-circle"></i>
                La propiedad será visible inmediatamente en el sitio principal.
            </p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancelar" id="cancelarSubirPropiedad">Cancelar</button>
            <button type="button" class="btn-confirmar-subir" id="confirmarSubirPropiedad">
                <i class="fa-solid fa-cloud-upload"></i> Sí, Publicar
            </button>
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

<!-- MODAL PARA VER DETALLES DE SOLICITUD -->
<div class="modal" id="modalDetallesSolicitud" style="display:none;">
    <div class="modal-contenido modal-detalles-solicitud">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-circle-check"></i> Revisar Solicitud</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div id="detallesSolicitudContent">
                <!-- Cargado dinámicamente -->
            </div>
        </div>
        <div class="modal-footer solicitud-acciones">
            <button type="button" class="btn-cancelar" id="cancelarRechazo">Cancelar</button>
            <button type="button" class="btn-rechazar" id="btnRechazarSolicitud">
                <i class="fa-solid fa-times-circle"></i> Rechazar
            </button>
            <button type="button" class="btn-aprobar" id="btnAprobarSolicitud">
                <i class="fa-solid fa-check-circle"></i> Aprobar
            </button>
        </div>
    </div>
</div>

<!-- MODAL PARA RECHAZAR SOLICITUD -->
<div class="modal" id="modalRechazarSolicitud" style="display:none;">
    <div class="modal-contenido modal-rechazar">
        <div class="modal-header">
            <h3><i class="fa-solid fa-times-circle"></i> Rechazar Solicitud</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <p id="textoRechazar">¿Estás seguro de que quieres rechazar esta solicitud? Ingresa el motivo:</p>
            <div class="form-group">
                <textarea id="motivoRechazo" rows="4" placeholder="Motivo del rechazo (obligatorio)" maxlength="500"></textarea>
                <small class="form-help">Máximo 500 caracteres. Este motivo se mostrará al propietario.</small>
                <div class="form-error" id="errorMotivoRechazo"></div>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancelar" id="cancelarRechazar">Cancelar</button>
            <button type="button" class="btn-confirmar-rechazar" id="confirmarRechazar">
                <i class="fa-solid fa-times-circle"></i> Sí, Rechazar
            </button>
        </div>
    </div>
</div>

<!-- MODAL PARA CONFIRMAR APROBACIÓN -->
<div class="modal" id="modalConfirmarAprobacion" style="display:none;">
    <div class="modal-contenido modal-confirmacion">
        <div class="modal-header">
            <h3><i class="fa-solid fa-check-circle"></i> Confirmar Aprobación</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div class="confirmacion-icono">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <p id="textoConfirmarAprobacion">¿Estás seguro de que quieres aprobar esta propiedad?</p>
            <p class="confirmacion-detalle">
                <i class="fa-solid fa-info-circle"></i>
                La propiedad se mostrará públicamente y se notificará al propietario.
            </p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancelar" id="cancelarAprobacion">Cancelar</button>
            <button type="button" class="btn-aprobar-confirmar" id="confirmarAprobacion">
                <i class="fa-solid fa-check-circle"></i> Sí, Aprobar
            </button>
        </div>
    </div>
</div>

<script src="../script/admin.js"></script>
<script>
// Scripts iniciales en línea para mejor UX
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar mensaje de confirmación si existe
    <?php if (!empty($mensajeConfirmacion)): ?>
    mostrarMensajeConfirmacion('<?= $mensajeConfirmacion ?>', '<?= $tipoMensaje ?>');
    <?php endif; ?>
    
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
    
    // Funciones específicas para el formulario de propiedades
    window.limpiarFormularioPropiedad = function() {
        const form = document.getElementById('formulario-propiedad-admin');
        if (form) {
            form.reset();
            const listaArchivos = document.getElementById('listaArchivosAdmin');
            if (listaArchivos) {
                listaArchivos.innerHTML = '';
            }
        }
    };
    
    // Sistema de modales mejorado
    const modales = document.querySelectorAll('.modal');
    const modalOverlay = document.getElementById('modalOverlay');
    
    // Función para mostrar modal
    function mostrarModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            if (modalOverlay) modalOverlay.style.display = 'block';
            
            // Centrar modal
            const contenido = modal.querySelector('.modal-contenido');
            if (contenido) {
                contenido.style.top = '50%';
                contenido.style.left = '50%';
                contenido.style.transform = 'translate(-50%, -50%)';
            }
        }
    }
    
    // Función para ocultar modal
    function ocultarModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            if (modalOverlay) modalOverlay.style.display = 'none';
        }
    }
    
    // Cerrar modal al hacer clic en la X
    document.querySelectorAll('.cerrar').forEach(cerrarBtn => {
        cerrarBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                ocultarModal(modal.id);
            }
        });
    });
    
    // Cerrar modal al hacer clic en overlay
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function() {
            modales.forEach(modal => {
                if (modal.style.display === 'block') {
                    ocultarModal(modal.id);
                }
            });
        });
    }
    
    // Función para mostrar mensajes de confirmación
    window.mostrarMensajeConfirmacion = function(mensaje, tipo = 'success') {
        const mensajeDiv = document.createElement('div');
        mensajeDiv.className = `mensaje-confirmacion ${tipo}`;
        mensajeDiv.innerHTML = `
            <i class="fa-solid fa-${tipo === 'success' ? 'check-circle' : tipo === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${mensaje}</span>
            <button class="cerrar-mensaje">&times;</button>
        `;
        
        document.body.appendChild(mensajeDiv);
        
        // Cerrar mensaje
        const cerrarBtn = mensajeDiv.querySelector('.cerrar-mensaje');
        cerrarBtn.addEventListener('click', function() {
            mensajeDiv.remove();
        });
        
        // Auto-cerrar después de 5 segundos
        setTimeout(() => {
            if (document.body.contains(mensajeDiv)) {
                mensajeDiv.remove();
            }
        }, 5000);
    };
    
    // Manejar cambio de estado de usuarios
    document.querySelectorAll('.toggle-estado').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const id = this.getAttribute('data-id');
            const rol = this.getAttribute('data-rol');
            const estado = this.checked ? 1 : 0;
            const estadoTexto = this.nextElementSibling.nextElementSibling;
            
            // Actualizar texto inmediatamente
            estadoTexto.textContent = estado ? 'Activo' : 'Inactivo';
            
            // Enviar petición al servidor
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `accion=cambiar_estado&csrf_token=<?= $csrf_token ?>&id=${id}&rol=${rol}&estado=${estado}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarMensajeConfirmacion(data.message, 'success');
                } else {
                    // Revertir cambio si hay error
                    this.checked = !this.checked;
                    estadoTexto.textContent = this.checked ? 'Activo' : 'Inactivo';
                    mostrarMensajeConfirmacion(data.error || 'Error al cambiar estado', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revertir cambio si hay error
                this.checked = !this.checked;
                estadoTexto.textContent = this.checked ? 'Activo' : 'Inactivo';
                mostrarMensajeConfirmacion('Error de conexión', 'error');
            });
        });
    });
    
    // Inicializar funcionalidades de agregar servicio
    const btnAgregarServicio = document.getElementById('btnAgregarServicio');
    
    if (btnAgregarServicio) {
        btnAgregarServicio.addEventListener('click', function() {
            mostrarModal('modalAgregarServicio');
        });
        
        // Cambiar ícono al escribir
        const iconoPreview = document.getElementById('previewIcono');
        const iconoServicioInput = document.getElementById('iconoServicio');
        
        if (iconoServicioInput && iconoPreview) {
            iconoServicioInput.addEventListener('input', function() {
                const nuevoIcono = this.value.trim();
                if (nuevoIcono) {
                    iconoPreview.className = nuevoIcono;
                }
            });
        }
        
        // Seleccionar íconos populares
        document.querySelectorAll('.icono-option').forEach(btn => {
            btn.addEventListener('click', function() {
                const icono = this.getAttribute('data-icono');
                if (iconoServicioInput) iconoServicioInput.value = icono;
                if (iconoPreview) iconoPreview.className = icono;
            });
        });
        
        // Manejar formulario de servicio
        const formAgregarServicio = document.getElementById('formAgregarServicio');
        if (formAgregarServicio) {
            formAgregarServicio.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarMensajeConfirmacion(data.message, 'success');
                        ocultarModal('modalAgregarServicio');
                        formAgregarServicio.reset();
                        if (iconoPreview) iconoPreview.className = 'fa-solid fa-star';
                        
                        // Actualizar lista de servicios
                        actualizarListaServicios();
                    } else {
                        if (data.errors) {
                            mostrarMensajeConfirmacion(data.errors.join('\n'), 'error');
                        } else if (data.error) {
                            mostrarMensajeConfirmacion(data.error, 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensajeConfirmacion('Error al agregar servicio', 'error');
                });
            });
        }
    }
    
    // Función para actualizar lista de servicios
    function actualizarListaServicios() {
        const gridServicios = document.getElementById('gridServicios');
        if (!gridServicios) return;
        
        fetch('?accion=obtener_servicios&ajax=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                gridServicios.innerHTML = '';
                if (data.servicios.length > 0) {
                    data.servicios.forEach(servicio => {
                        const label = document.createElement('label');
                        label.className = 'checkbox-servicio';
                        label.innerHTML = `
                            <input type="checkbox" name="servicios[]" value="${servicio.nombre}">
                            <div class="item-servicio">
                                <i class="${servicio.icono}"></i>
                                <span>${servicio.nombre}</span>
                            </div>
                        `;
                        gridServicios.appendChild(label);
                    });
                } else {
                    gridServicios.innerHTML = '<p class="sin-servicios">No hay servicios disponibles. Agrega algunos primero.</p>';
                }
            }
        })
        .catch(error => console.error('Error:', error));
    }
    
    // Botón para actualizar servicios
    const btnRefreshServicios = document.getElementById('btnRefreshServicios');
    if (btnRefreshServicios) {
        btnRefreshServicios.addEventListener('click', actualizarListaServicios);
    }
    
    // Manejar checkbox de precio
    const checkboxNoPublicar = document.getElementById('no-decirlo');
    const inputPrecio = document.getElementById('precio');
    
    if (checkboxNoPublicar && inputPrecio) {
        checkboxNoPublicar.addEventListener('change', function() {
            if (this.checked) {
                inputPrecio.required = false;
                inputPrecio.value = '';
                inputPrecio.placeholder = 'Opcional si no se publica';
            } else {
                inputPrecio.required = true;
                inputPrecio.placeholder = '120000';
            }
        });
    }
    
    // Manejar archivos de imágenes
    const areaSubida = document.getElementById('areaSubidaArchivosAdmin');
    const inputImagenes = document.querySelector('#areaSubidaArchivosAdmin input[type="file"]');
    const listaArchivos = document.getElementById('listaArchivosAdmin');
    
    if (areaSubida && inputImagenes) {
        // Click en área de subida
        areaSubida.addEventListener('click', function() {
            inputImagenes.click();
        });
        
        // Arrastrar y soltar
        areaSubida.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.backgroundColor = '#f0f8ff';
        });
        
        areaSubida.addEventListener('dragleave', function() {
            this.style.backgroundColor = '';
        });
        
        areaSubida.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.backgroundColor = '';
            
            if (e.dataTransfer.files.length > 0) {
                inputImagenes.files = e.dataTransfer.files;
                mostrarArchivosSeleccionados(inputImagenes.files);
            }
        });
        
        // Cambio de archivos
        inputImagenes.addEventListener('change', function() {
            mostrarArchivosSeleccionados(this.files);
        });
    }
    
    // Función para mostrar archivos seleccionados
    function mostrarArchivosSeleccionados(files) {
        if (!listaArchivos) return;
        
        listaArchivos.innerHTML = '';
        
        if (files.length === 0) {
            return;
        }
        
        Array.from(files).forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'item-archivo';
            
            // Formatear tamaño
            const tamano = file.size > 1024 * 1024 
                ? (file.size / (1024 * 1024)).toFixed(2) + ' MB'
                : (file.size / 1024).toFixed(2) + ' KB';
            
            item.innerHTML = `
                <i class="fa-solid fa-image"></i>
                <div class="nombre-archivo">${file.name}</div>
                <div class="tamano-archivo">${tamano}</div>
                <i class="fa-solid fa-times eliminar-archivo" data-index="${index}"></i>
            `;
            
            // Agregar evento para eliminar archivo
            const btnEliminar = item.querySelector('.eliminar-archivo');
            btnEliminar.addEventListener('click', function() {
                const dt = new DataTransfer();
                const archivos = Array.from(inputImagenes.files);
                archivos.splice(parseInt(this.getAttribute('data-index')), 1);
                
                archivos.forEach(archivo => {
                    dt.items.add(archivo);
                });
                
                inputImagenes.files = dt.files;
                mostrarArchivosSeleccionados(dt.files);
            });
            
            listaArchivos.appendChild(item);
        });
    }
    
    // Enviar formulario de propiedad
    const formPropiedad = document.getElementById('formulario-propiedad-admin');
    
    if (formPropiedad) {
        formPropiedad.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validaciones básicas
            const titulo = document.getElementById('titulo').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const direccion = document.getElementById('direccion').value.trim();
            
            if (!titulo || !descripcion || !direccion) {
                mostrarMensajeConfirmacion('Por favor completa los campos obligatorios', 'error');
                return;
            }
            
            // Mostrar modal de confirmación
            mostrarModal('modalConfirmarSubirPropiedad');
        });
        
        // Confirmar publicación
        const btnConfirmarSubir = document.getElementById('confirmarSubirPropiedad');
        if (btnConfirmarSubir) {
            btnConfirmarSubir.addEventListener('click', function() {
                const formData = new FormData(formPropiedad);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    ocultarModal('modalConfirmarSubirPropiedad');
                    
                    if (data.success) {
                        mostrarMensajeConfirmacion(data.message, 'success');
                        limpiarFormularioPropiedad();
                        
                        // Redirigir a propiedades publicadas
                        setTimeout(() => {
                            window.location.href = '?seccion=propiedadespublicadas';
                        }, 1500);
                    } else {
                        if (data.errors) {
                            mostrarMensajeConfirmacion(data.errors.join('\n'), 'error');
                        } else if (data.error) {
                            mostrarMensajeConfirmacion(data.error, 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensajeConfirmacion('Error al subir la propiedad', 'error');
                    ocultarModal('modalConfirmarSubirPropiedad');
                });
            });
        }
        
        // Cancelar publicación
        const btnCancelarSubir = document.getElementById('cancelarSubirPropiedad');
        if (btnCancelarSubir) {
            btnCancelarSubir.addEventListener('click', function() {
                ocultarModal('modalConfirmarSubirPropiedad');
            });
        }
    }
    
    // Funcionalidad para filtrar propiedades publicadas
    const filtroPropiedades = document.getElementById('filtroPropiedades');
    if (filtroPropiedades) {
        filtroPropiedades.addEventListener('change', function() {
            const filtro = this.value;
            const propiedades = document.querySelectorAll('.tarjeta-propiedad');
            
            propiedades.forEach(propiedad => {
                const estado = propiedad.getAttribute('data-estado');
                
                if (filtro === 'todas') {
                    propiedad.style.display = 'block';
                } else if (filtro === estado) {
                    propiedad.style.display = 'block';
                } else {
                    propiedad.style.display = 'none';
                }
            });
        });
    }
    
    // Funcionalidad para ocultar/mostrar propiedades
    document.addEventListener('click', function(e) {
        // Botón ocultar propiedad
        if (e.target.closest('.btn-ocultar-propiedad')) {
            const btn = e.target.closest('.btn-ocultar-propiedad');
            const id = btn.getAttribute('data-id');
            const titulo = btn.getAttribute('data-titulo');
            
            if (confirm(`¿Estás seguro de que quieres ocultar la propiedad "${titulo}"?`)) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=gestionar_propiedad&csrf_token=<?= $csrf_token ?>&id_propiedad=${id}&tipo_accion=ocultar`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarMensajeConfirmacion(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        mostrarMensajeConfirmacion(data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensajeConfirmacion('Error al ocultar la propiedad', 'error');
                });
            }
        }
        
        // Botón mostrar propiedad
        if (e.target.closest('.btn-mostrar-propiedad')) {
            const btn = e.target.closest('.btn-mostrar-propiedad');
            const id = btn.getAttribute('data-id');
            const titulo = btn.getAttribute('data-titulo');
            
            if (confirm(`¿Estás seguro de que quieres volver a mostrar la propiedad "${titulo}"?`)) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=gestionar_propiedad&csrf_token=<?= $csrf_token ?>&id_propiedad=${id}&tipo_accion=mostrar`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarMensajeConfirmacion(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        mostrarMensajeConfirmacion(data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensajeConfirmacion('Error al mostrar la propiedad', 'error');
                });
            }
        }
    });
});

// ====== VARIABLES GLOBALES PARA JS ======
const GLOBAL_CSRF_TOKEN = "<?= $csrf_token ?>";
const GLOBAL_ADMIN_NOMBRE = "<?= htmlspecialchars($adminNombre, ENT_QUOTES, 'UTF-8') ?>";

// ====== FUNCIÓN PARA MOSTRAR MENSAJES ======
function mostrarMensajeConfirmacion(mensaje, tipo = 'success') {
    const mensajeDiv = document.createElement('div');
    mensajeDiv.className = `mensaje-confirmacion ${tipo}`;
    mensajeDiv.innerHTML = `
        <i class="fa-solid fa-${tipo === 'success' ? 'check-circle' : tipo === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${mensaje}</span>
        <button class="cerrar-mensaje">&times;</button>
    `;
    
    document.body.appendChild(mensajeDiv);
    
    // Cerrar mensaje
    const cerrarBtn = mensajeDiv.querySelector('.cerrar-mensaje');
    cerrarBtn.addEventListener('click', function() {
        mensajeDiv.remove();
    });
    
    // Auto-cerrar después de 5 segundos
    setTimeout(() => {
        if (document.body.contains(mensajeDiv)) {
            mensajeDiv.remove();
        }
    }, 5000);
}

// ====== MANEJO DE TOGGLE DE ESTADO ======
function inicializarTogglesEstado() {
    // Usamos delegación de eventos para mejor performance
    document.addEventListener('click', function(e) {
        // Verificar si el click fue en elementos relacionados al toggle
        const target = e.target;
        
        // Verificar diferentes formas de click en el toggle
        if (target.classList.contains('toggle-estado') || 
            target.classList.contains('slider') ||
            target.closest('.slider') ||
            (target.closest('.switch') && !target.classList.contains('estado-texto'))) {
            
            e.preventDefault();
            e.stopPropagation();
            
            // Obtener el checkbox toggle-estado
            let toggle;
            if (target.classList.contains('toggle-estado')) {
                toggle = target;
            } else {
                // Buscar el checkbox dentro del switch
                const switchElement = target.closest('.switch');
                if (switchElement) {
                    toggle = switchElement.querySelector('.toggle-estado');
                }
            }
            
            if (!toggle || toggle.disabled) return;
            
            // Obtener datos del usuario
            const id = toggle.getAttribute('data-id');
            const rol = toggle.getAttribute('data-rol');
            const estadoActual = toggle.checked;
            const nuevoEstado = !estadoActual;
            
            // Encontrar elementos relacionados
            const fila = toggle.closest('tr');
            const nombreElement = fila.querySelector('.usuario-info');
            const nombreUsuario = nombreElement ? nombreElement.textContent.trim() : 'Usuario';
            const estadoTexto = toggle.parentElement.querySelector('.estado-texto');
            
            // Confirmación antes de cambiar
            const accionTexto = nuevoEstado ? 'HABILITAR' : 'DESHABILITAR';
            const mensajeConfirm = nuevoEstado ? 
                `¿Estás seguro de que quieres HABILITAR al usuario "${nombreUsuario}"?` :
                `¿Estás seguro de que quieres DESHABILITAR al usuario "${nombreUsuario}"?\n\n⚠️ El usuario NO podrá iniciar sesión hasta que sea habilitado nuevamente.`;
            
            if (!confirm(mensajeConfirm)) {
                return; // Usuario canceló la operación
            }
            
            // Deshabilitar temporalmente el toggle
            toggle.disabled = true;
            const textoOriginal = estadoTexto.textContent;
            estadoTexto.textContent = 'Procesando...';
            
            // Preparar datos para enviar al servidor
            const formData = new FormData();
            formData.append('accion', 'cambiar_estado');
            formData.append('csrf_token', GLOBAL_CSRF_TOKEN);
            formData.append('id', id);
            formData.append('rol', rol);
            formData.append('estado', nuevoEstado ? '1' : '0');
            
            // DEBUG: Ver datos que se envían
            console.log('Enviando cambio de estado:', {
                id: id,
                rol: rol,
                estado: nuevoEstado ? 'Activo (1)' : 'Inactivo (0)',
                usuario: nombreUsuario
            });
            
            // Enviar petición al servidor
            fetch('indexadmin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Respuesta del servidor:', data);
                
                // Rehabilitar el toggle
                toggle.disabled = false;
                
                if (data.success) {
                    // Éxito - Actualizar interfaz
                    toggle.checked = nuevoEstado;
                    estadoTexto.textContent = nuevoEstado ? 'Activo' : 'Inactivo';
                    
                    // Feedback visual en la fila
                    if (nuevoEstado) {
                        fila.classList.remove('usuario-inactivo');
                        fila.style.backgroundColor = '';
                        fila.style.opacity = '1';
                    } else {
                        fila.classList.add('usuario-inactivo');
                        fila.style.backgroundColor = '#fff8f8';
                        fila.style.opacity = '0.8';
                    }
                    
                    // Mostrar mensaje de éxito
                    const mensajeExito = nuevoEstado ? 
                        `✅ Usuario "${nombreUsuario}" habilitado correctamente` :
                        `⚠️ Usuario "${nombreUsuario}" deshabilitado. No podrá iniciar sesión.`;
                    
                    mostrarMensajeConfirmacion(mensajeExito, nuevoEstado ? 'success' : 'warning');
                    
                    // Actualizar estadísticas si existen
                    actualizarContadorActivos();
                    
                    // Registrar en consola
                    console.log(`Estado cambiado: ${nombreUsuario} -> ${nuevoEstado ? 'ACTIVO' : 'INACTIVO'}`);
                    
                } else {
                    // Error - Revertir cambios
                    toggle.checked = estadoActual;
                    estadoTexto.textContent = textoOriginal;
                    
                    const errorMsg = data.error || 'Error desconocido al cambiar estado';
                    mostrarMensajeConfirmacion(`❌ ${errorMsg}`, 'error');
                    
                    console.error('Error del servidor:', data);
                }
            })
            .catch(error => {
                console.error('Error de red:', error);
                
                // Revertir cambios por error de red
                toggle.disabled = false;
                toggle.checked = estadoActual;
                estadoTexto.textContent = textoOriginal;
                
                mostrarMensajeConfirmacion('❌ Error de conexión con el servidor', 'error');
            });
        }
    });
}

// ====== FUNCIÓN PARA ACTUALIZAR CONTADOR DE ACTIVOS ======
function actualizarContadorActivos() {
    try {
        const activeToggles = document.querySelectorAll('.toggle-estado:checked');
        const totalElement = document.getElementById('totalUsuariosActivos');
        
        if (totalElement && activeToggles.length > 0) {
            totalElement.textContent = activeToggles.length;
        }
        
        // También actualizar en cada tabla visible
        document.querySelectorAll('.contenedor-tabla-usuarios').forEach(contenedor => {
            if (contenedor.style.display !== 'none') {
                const togglesActivos = contenedor.querySelectorAll('.toggle-estado:checked');
                const totalTabla = contenedor.querySelector('.tabla-title h3 span');
                if (totalTabla) {
                    // Extraer número actual y mostrar activos/total
                    const texto = totalTabla.textContent;
                    const match = texto.match(/\((\d+)/);
                    if (match) {
                        const total = match[1];
                        totalTabla.textContent = `(${togglesActivos.length}/${total} activos)`;
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error al actualizar contador:', error);
    }
}

// ====== INICIALIZACIÓN AL CARGAR LA PÁGINA ======
document.addEventListener('DOMContentLoaded', function() {
    console.log('Panel Admin inicializado');
    console.log('Token CSRF:', GLOBAL_CSRF_TOKEN ? 'PRESENTE' : 'FALTANTE');
    console.log('Admin:', GLOBAL_ADMIN_NOMBRE);
    
    // Inicializar toggles de estado
    inicializarTogglesEstado();
    
    // Actualizar contador inicial
    setTimeout(actualizarContadorActivos, 1000);
    
    // Actualizar hora cada segundo
    function actualizarHora() {
        const ahora = new Date();
        const opciones = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        const fechaHora = ahora.toLocaleDateString('es-ES', opciones);
        
        const elementoHora = document.getElementById('currentTime');
        if (elementoHora) {
            elementoHora.textContent = fechaHora;
        }
        
        const elementoActualizacion = document.getElementById('lastUpdate');
        if (elementoActualizacion) {
            elementoActualizacion.textContent = ahora.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
    }
    
    actualizarHora();
    setInterval(actualizarHora, 1000);
    
    // Manejar búsqueda en tablas
    document.querySelectorAll('.buscador-tabla').forEach(buscador => {
        buscador.addEventListener('input', function() {
            const valorBusqueda = this.value.toLowerCase();
            const tabla = this.closest('.contenedor-tabla-usuarios').querySelector('table');
            const filas = tabla.querySelectorAll('tbody tr');
            
            filas.forEach(fila => {
                const textoFila = fila.textContent.toLowerCase();
                fila.style.display = textoFila.includes(valorBusqueda) ? '' : 'none';
            });
        });
    });
    
    // Mostrar mensajes de confirmación de PHP si existen
    <?php if (!empty($mensajeConfirmacion)): ?>
    mostrarMensajeConfirmacion('<?= addslashes($mensajeConfirmacion) ?>', '<?= $tipoMensaje ?>');
    <?php endif; ?>
});

// ====== FUNCIONES GLOBALES PARA BOTONES DE ACCIÓN RÁPIDA ======
window.mostrarSeccion = function(idSeccion) {
    // Ocultar todas las secciones
    document.querySelectorAll('.seccion').forEach(seccion => {
        seccion.classList.remove('visible');
    });
    
    // Mostrar la sección solicitada
    const seccion = document.getElementById(idSeccion);
    if (seccion) {
        seccion.classList.add('visible');
        
        // Actualizar menú lateral
        document.querySelectorAll('.menu-btn').forEach(btn => {
            btn.classList.remove('activo');
        });
        
        // Marcar botón correspondiente como activo
        const btnMenu = document.querySelector(`[data-seccion="${idSeccion}"]`);
        if (btnMenu) {
            btnMenu.classList.add('activo');
        }
        
        // Si es usuarios, mostrar la primera tabla
        if (idSeccion === 'usuarios') {
            document.querySelectorAll('.contenedor-tabla-usuarios').forEach((contenedor, index) => {
                contenedor.style.display = index === 0 ? 'block' : 'none';
            });
        }
        
        // Scroll al inicio
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        console.log(`Sección cambiada a: ${idSeccion}`);
    }
};

// Función para limpiar formulario de propiedades
window.limpiarFormularioPropiedad = function() {
    const form = document.getElementById('formulario-propiedad-admin');
    if (form) {
        form.reset();
        const listaArchivos = document.getElementById('listaArchivosAdmin');
        if (listaArchivos) {
            listaArchivos.innerHTML = '';
        }
        mostrarMensajeConfirmacion('Formulario limpiado', 'info');
    }
};
</script>
</body>
</html>