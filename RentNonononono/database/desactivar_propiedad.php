<?php
session_start();
require_once 'conexion.php';

//este archivo sirve paradesactivar una propiedad ojaja-

// Verificar que sea un propietario
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'propietario') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$id_propietario = $_SESSION['id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID de propiedad no especificado']);
    exit;
}

try {
    // Verificar que la propiedad pertenezca al propietario y esté aprobada
    $sql_verificar = "SELECT id FROM propiedades WHERE id = :id AND id_propietario = :id_propietario AND estado_publicacion = 'aprobado'";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->execute([
        ':id' => $data['id'],
        ':id_propietario' => $id_propietario
    ]);
    
    if ($stmt_verificar->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Propiedad no encontrada, no autorizada o no está aprobada']);
        exit;
    }
    
    // Cambiar estado a inactivo
    $sql = "UPDATE propiedades SET estado_publicacion = 'inactivo' WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([':id' => $data['id']]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Propiedad desactivada correctamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al desactivar propiedad']);
    }
    
} catch (Exception $e) {
    error_log("Error desactivando propiedad: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al desactivar propiedad. Intente nuevamente.'
    ]);
}