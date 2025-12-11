<?php
session_start();
require_once 'conexion.php';

// Este archivo sirve para marcar las notifaciones como leidash

// Verificar que sea un propietario
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'propietario') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$id_propietario = $_SESSION['id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['accion'])) {
    echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
    exit;
}

try {
    if ($data['accion'] === 'marcar_leida' && isset($data['id'])) {
        // Marcar una notificación específica como leída
        $sql = "UPDATE notificaciones 
                SET leida = 1 
                WHERE id = :id AND id_usuario = :id_usuario";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => intval($data['id']),
            ':id_usuario' => $id_propietario
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Notificación marcada como leída']);
        
    } elseif ($data['accion'] === 'marcar_todas_leidas') {
        // Marcar todas las notificaciones del usuario como leídas
        $sql = "UPDATE notificaciones 
                SET leida = 1 
                WHERE id_usuario = :id_usuario AND leida = 0";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id_usuario' => $id_propietario]);
        
        echo json_encode(['success' => true, 'message' => 'Todas las notificaciones marcadas como leídas']);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    error_log("Error marcando notificación: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al actualizar notificación']);
}
?>