<?php
session_start();
require_once 'conexion.php';

//Este archivo sirve para saber y obtener estadísticas en tiempo 
//reakkj del propietariooo

// Verificar que sea un propietario
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'propietario') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$id_propietario = $_SESSION['id'];

try {
    // 1. Propiedades totales y por estado
    $sql_propiedades = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado_publicacion = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
        SUM(CASE WHEN estado_publicacion = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado_publicacion = 'rechazada' THEN 1 ELSE 0 END) as rechazadas
    FROM propiedades 
    WHERE id_propietario = :id_propietario";
    
    $stmt_propiedades = $conn->prepare($sql_propiedades);
    $stmt_propiedades->execute([':id_propietario' => $id_propietario]);
    $estadisticas = $stmt_propiedades->fetch(PDO::FETCH_ASSOC);
    
    // 2. Notificaciones no leídas
    $sql_notificaciones = "SELECT COUNT(*) as total 
                          FROM notificaciones 
                          WHERE id_usuario = :id_usuario AND leida = 0";
    $stmt_notif = $conn->prepare($sql_notificaciones);
    $stmt_notif->execute([':id_usuario' => $id_propietario]);
    $notificaciones_no_leidas = $stmt_notif->fetchColumn();
    
    // 3. Comentarios totales (de propiedades aprobadas)
    $sql_comentarios = "SELECT COUNT(*) as total 
                       FROM opiniones o
                       INNER JOIN propiedades p ON o.propiedad_id = p.id
                       WHERE p.id_propietario = :id_propietario 
                       AND p.estado_publicacion = 'aprobada'";
    $stmt_comentarios = $conn->prepare($sql_comentarios);
    $stmt_comentarios->execute([':id_propietario' => $id_propietario]);
    $total_comentarios = $stmt_comentarios->fetchColumn();
    
    // Si no existe tabla opiniones, usar valor 0
    if ($total_comentarios === false) {
        $total_comentarios = 0;
    }
    
    echo json_encode([
        'success' => true,
        'total' => $estadisticas['total'] ?? 0,
        'aprobadas' => $estadisticas['aprobadas'] ?? 0,
        'pendientes' => $estadisticas['pendientes'] ?? 0,
        'rechazadas' => $estadisticas['rechazadas'] ?? 0,
        'notificaciones_no_leidas' => $notificaciones_no_leidas ?? 0,
        'comentarios' => $total_comentarios
    ]);
    
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener estadísticas',
        'total' => 0,
        'aprobadas' => 0,
        'pendientes' => 0,
        'rechazadas' => 0,
        'notificaciones_no_leidas' => 0,
        'comentarios' => 0
    ]);
}
?>