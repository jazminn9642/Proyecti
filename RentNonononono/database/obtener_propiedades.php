<?php
session_start();
require_once 'conexion.php';

// Verificar que sea un propietario
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'propietario') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$id_propietario = $_SESSION['id'];

try {
    // Obtener propiedades del propietario
    $sql = "SELECT 
        p.id,
        p.titulo,
        p.descripcion,
        p.precio,
        p.precio_no_publicado,
        p.ambientes,
        p.sanitarios as banios,
        p.superficie,
        p.direccion,
        p.estado_publicacion,
        p.fecha_solicitud,
        p.motivo_rechazo,
        p.servicios,
        i.ruta as imagen_principal
    FROM propiedades p
    LEFT JOIN imagenes_propiedades i ON p.id = i.id_propiedad AND i.es_principal = 1
    AND p.estado_publicacion != 'eliminado'
    WHERE p.id_propietario = :id_propietario
    ORDER BY p.fecha_solicitud DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_propietario' => $id_propietario]);
    $propiedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos para el frontend
    foreach ($propiedades as &$propiedad) {
        // Formatear precio
        $propiedad['precio_formateado'] = '$' . number_format($propiedad['precio'], 0, ',', '.');
        
        // Formatear fecha
        $fecha = new DateTime($propiedad['fecha_solicitud']);
        $propiedad['fecha_formateada'] = $fecha->format('d/m/Y');
        
        // Estado de publicación traducido
        $estados = [
            'pendiente' => 'Pendiente',
            'aprobado' => 'Aprobado',
            'rechazado' => 'Rechazado',
            'inactivo' => 'Inactivo'
        ];
        $propiedad['estado_texto'] = $estados[$propiedad['estado_publicacion']] ?? 'Desconocido';
        
        // Clase CSS para el estado
        $clases_estado = [
            'pendiente' => 'estado-pendiente',
            'aprobado' => 'estado-aprobado',
            'rechazado' => 'estado-rechazado',
            'inactivo' => 'estado-inactivo'
        ];
        $propiedad['estado_clase'] = $clases_estado[$propiedad['estado_publicacion']] ?? '';
        
        // Servicios como array
        if (!empty($propiedad['servicios'])) {
            $propiedad['servicios_array'] = json_decode($propiedad['servicios'], true);
        } else {
            $propiedad['servicios_array'] = [];
        }
        
        // Imagen por defecto si no hay principal
        if (empty($propiedad['imagen_principal'])) {
            $propiedad['imagen_principal'] = 'images/default-property.jpg';
        }
    }
    
    echo json_encode([
        'success' => true,
        'propiedades' => $propiedades,
        'total' => count($propiedades)
    ]);
    
} catch (Exception $e) {
    error_log("Error obteniendo propiedades: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener propiedades. Intente nuevamente.'
    ]);
}