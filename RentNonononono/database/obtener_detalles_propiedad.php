<?php
session_start();
require_once 'conexion.php';

// Este archivo sirve para obetener información de una propiedada específasc

// Verificar que sea un propietario
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'propietario') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$id_propietario = $_SESSION['id'];
$id_propiedad = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_propiedad <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de propiedad inválido']);
    exit;
}

try {
    // Verificar que la propiedad pertenezca al propietario
    $sql_verificar = "SELECT id FROM propiedades 
                     WHERE id = :id_propiedad AND id_propietario = :id_propietario";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->execute([
        ':id_propiedad' => $id_propiedad,
        ':id_propietario' => $id_propietario
    ]);
    
    if ($stmt_verificar->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Propiedad no encontrada o no tienes permiso']);
        exit;
    }
    
    // Obtener detalles completos de la propiedad
    $sql_detalles = "SELECT 
        p.*,
        u.nombre as nombre_propietario,
        a.nombre as nombre_admin_revisor,
        GROUP_CONCAT(ip.ruta SEPARATOR ',') as rutas_imagenes
    FROM propiedades p
    LEFT JOIN usuario_propietario u ON p.id_propietario = u.id
    LEFT JOIN usuario_admin a ON p.id_admin_revisor = a.id
    LEFT JOIN imagenes_propiedades ip ON p.id = ip.id_propiedad
    WHERE p.id = :id_propiedad
    GROUP BY p.id";
    
    $stmt_detalles = $conn->prepare($sql_detalles);
    $stmt_detalles->execute([':id_propiedad' => $id_propiedad]);
    $propiedad = $stmt_detalles->fetch(PDO::FETCH_ASSOC);
    
    if (!$propiedad) {
        echo json_encode(['success' => false, 'error' => 'Propiedad no encontrada']);
        exit;
    }
    
    // Formatear datos para la respuesta
    $propiedad['fecha_solicitud_formateada'] = date('d/m/Y H:i', strtotime($propiedad['fecha_solicitud']));
    if ($propiedad['fecha_revision']) {
        $propiedad['fecha_revision_formateada'] = date('d/m/Y H:i', strtotime($propiedad['fecha_revision']));
    }
    
    // Separar servicios en array
    $propiedad['servicios_array'] = !empty($propiedad['servicios']) ? 
        explode(',', $propiedad['servicios']) : [];
    
    // Separar rutas de imágenes en array
    $propiedad['imagenes_array'] = !empty($propiedad['rutas_imagenes']) ? 
        explode(',', $propiedad['rutas_imagenes']) : [];
    
    echo json_encode([
        'success' => true,
        'propiedad' => $propiedad
    ]);
    
} catch (Exception $e) {
    error_log("Error obteniendo detalles: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al obtener detalles']);
}
?>