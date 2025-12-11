<?php
// buscar_ubicacion.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$busqueda = isset($_GET['q']) ? $_GET['q'] : '';

if (empty($busqueda) || strlen($busqueda) < 3) {
    echo json_encode(['error' => 'Búsqueda demasiado corta']);
    exit;
}

// URL de OpenStreetMap con User-Agent apropiado
$url = "https://nominatim.openstreetmap.org/search?format=json&q=" . 
       urlencode($busqueda) . 
       "&countrycodes=ar&limit=5&accept-language=es";

// Configurar contexto con User-Agent
$opciones = [
    'http' => [
        'header' => "User-Agent: RentNonoApp/1.0 (contacto@rentnono.com)\r\n" .
                   "Accept-Language: es-ES,es;q=0.9\r\n"
    ]
];

$contexto = stream_context_create($opciones);

try {
    $resultado = file_get_contents($url, false, $contexto);
    
    if ($resultado === FALSE) {
        echo json_encode(['error' => 'Error en la conexión']);
        exit;
    }
    
    $datos = json_decode($resultado, true);
    
    if (empty($datos)) {
        echo json_encode(['mensaje' => 'No se encontraron resultados para "' . $busqueda . '"']);
    } else {
        echo json_encode($datos);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Excepción: ' . $e->getMessage()]);
}
?>