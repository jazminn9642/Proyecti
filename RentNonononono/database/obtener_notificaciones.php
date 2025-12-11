<?php
session_start();
require_once 'conexion.php';

//este archivo sirve para obtener notificaciones del propietario.

// Verificar que sea un propietario
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'propietario') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$id_propietario = $_SESSION['id'];

try {
    // Obtener notificaciones del propietario
    // SOLO tipos: aprobacion, rechazo, comentario (quitamos visita y recordatorio_pago)
    $sql = "SELECT 
        n.id,
        n.titulo,
        n.mensaje,
        n.tipo,
        n.leida,
        n.fecha,
        p.titulo as propiedad_titulo,
        p.id as propiedad_id
    FROM notificaciones n
    LEFT JOIN propiedades p ON n.id_propiedad = p.id
    WHERE n.id_usuario = :id_usuario 
    AND n.tipo IN ('aprobacion', 'rechazo', 'comentario', 'solicitud')
    ORDER BY n.fecha DESC
    LIMIT 20";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_usuario' => $id_propietario]);
    $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear fechas
    foreach ($notificaciones as &$notif) {
        $fecha = new DateTime($notif['fecha']);
        $hoy = new DateTime();
        $diferencia = $hoy->diff($fecha);
        
        if ($diferencia->days == 0) {
            if ($diferencia->h == 0) {
                $notif['tiempo_texto'] = 'Hace ' . $diferencia->i . ' minutos';
            } else {
                $notif['tiempo_texto'] = 'Hace ' . $diferencia->h . ' horas';
            }
        } elseif ($diferencia->days == 1) {
            $notif['tiempo_texto'] = 'Ayer, ' . $fecha->format('H:i');
        } else {
            $notif['tiempo_texto'] = $fecha->format('d/m/Y H:i');
        }
    }
    
    echo json_encode([
        'success' => true,
        'notificaciones' => $notificaciones,
        'total' => count($notificaciones)
    ]);
    
} catch (Exception $e) {
    error_log("Error obteniendo notificaciones: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al obtener notificaciones']);
}
?>