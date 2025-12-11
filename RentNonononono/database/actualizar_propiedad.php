<?php
session_start();
require_once 'conexion.php';

// Este archivo sirve para actualizar los datos de una propiedad ya existente jj.

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

// Validar datos obligatorios
if (!isset($data['id']) || !isset($data['titulo']) || !isset($data['descripcion'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
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
    
    // Actualizar propiedad
    $sql = "UPDATE propiedades SET 
        titulo = :titulo,
        descripcion = :descripcion,
        ambientes = :ambientes,
        sanitarios = :banios,
        superficie = :superficie,
        direccion = :direccion,
        servicios = :servicios,
        precio = :precio,
        estado_publicacion = :estado
    WHERE id = :id AND id_propietario = :id_propietario";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':titulo' => $data['titulo'],
        ':descripcion' => $data['descripcion'],
        ':ambientes' => $data['ambientes'] ?? 1,
        ':banios' => $data['banios'] ?? 1,
        ':superficie' => $data['superficie'] ?? 0,
        ':direccion' => $data['direccion'] ?? '',
        ':servicios' => json_encode($data['servicios'] ?? []),
        ':precio' => $data['precio'] ?? 0,
        ':estado' => 'pendiente', // Vuelve a pendiente tras modificación
        ':id' => $data['id'],
        ':id_propietario' => $id_propietario
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Propiedad actualizada correctamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar propiedad']);
    }
    
} catch (Exception $e) {
    error_log("Error actualizando propiedad: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al actualizar propiedad. Intente nuevamente.'
    ]);
}