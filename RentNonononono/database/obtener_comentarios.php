<?php
session_start();
require_once 'conexion.php';

//Este archivo sireeve para poder obtener los comentarios de las propiedades

// Verificar que sea un propietario
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'propietario') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$id_propietario = $_SESSION['id'];

try {
    // Verificar si existe la tabla opiniones
    $check_table = $conn->query("SHOW TABLES LIKE 'opiniones'")->fetch();
    
    if (!$check_table) {
        // Si no existe la tabla opiniones, devolver array vacío
        echo json_encode([
            'success' => true,
            'comentarios' => [],
            'rating_promedio' => 0,
            'total' => 0
        ]);
        exit;
    }
    
    // Obtener comentarios de propiedades aprobadas del propietario
    $sql = "SELECT 
        o.id,
        o.comentario,
        o.rating,
        o.fecha,
        u.nombre as usuario_nombre,
        p.titulo as propiedad_titulo,
        p.id as propiedad_id
    FROM opiniones o
    INNER JOIN propiedades p ON o.propiedad_id = p.id
    INNER JOIN usuario_visitante u ON o.usuario_id = u.id
    WHERE p.id_propietario = :id_propietario
    AND p.estado_publicacion = 'aprobada'
    AND o.estado = 'aprobada'
    ORDER BY o.fecha DESC
    LIMIT 15";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_propietario' => $id_propietario]);
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular rating promedio
    $rating_promedio = 0;
    if (!empty($comentarios)) {
        $suma_ratings = 0;
        foreach ($comentarios as $comentario) {
            $suma_ratings += intval($comentario['rating']);
        }
        $rating_promedio = round($suma_ratings / count($comentarios), 1);
    }
    
    // Formatear fechas
    foreach ($comentarios as &$comentario) {
        $comentario['fecha_formateada'] = date('d/m/Y', strtotime($comentario['fecha']));
    }
    
    echo json_encode([
        'success' => true,
        'comentarios' => $comentarios,
        'rating_promedio' => $rating_promedio,
        'total' => count($comentarios)
    ]);
    
} catch (Exception $e) {
    error_log("Error obteniendo comentarios: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener comentarios',
        'comentarios' => [],
        'rating_promedio' => 0,
        'total' => 0
    ]);
}
?>