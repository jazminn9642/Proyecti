<?php
session_start();
require_once 'conexion.php';

//Este archivo realiza una eliminación unicamente visual (es decir que no se eliminan datos de forma 
// permanenete en la base de datos, solo se hace una elimniacion simbólica en el panel del proppp)

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
    // Verificar que la propiedad pertenezca al propietario
    $sql_verificar = "SELECT id FROM propiedades WHERE id = :id AND id_propietario = :id_propietario";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->execute([
        ':id' => $data['id'],
        ':id_propietario' => $id_propietario
    ]);
    
    if ($stmt_verificar->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Propiedad no encontrada o no autorizada']);
        exit;
    }
    
    // SOFT DELETE: Marcar como eliminada en lugar de borrar
    $sql = "UPDATE propiedades SET 
        estado_publicacion = 'eliminado',
        fecha_eliminacion = NOW()
    WHERE id = :id AND id_propietario = :id_propietario";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':id' => $data['id'],
        ':id_propietario' => $id_propietario
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Propiedad eliminada correctamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar propiedad']);
    }
    
} catch (Exception $e) {
    error_log("Error eliminando propiedad: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al eliminar propiedad. Intente nuevamente.'
    ]);
}