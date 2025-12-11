<?php
session_start();
require_once 'conexion.php';

// Verificar que sea un propietario
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'propietario') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Debes iniciar sesión como propietario.']);
    exit;
}

$id_propietario = $_SESSION['id'];
$nombre_propietario = $_SESSION['nombre'];
$correo_propietario = $_SESSION['correo'];

// Función para crear notificación
function crearNotificacion($conn, $id_usuario, $tipo, $titulo, $mensaje, $id_propiedad = null) {
    $sql = "INSERT INTO notificaciones (id_usuario, id_propiedad, titulo, mensaje, tipo, fecha) 
            VALUES (:id_usuario, :id_propiedad, :titulo, :mensaje, :tipo, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_usuario' => $id_usuario,
        ':id_propiedad' => $id_propiedad,
        ':titulo' => $titulo,
        ':mensaje' => $mensaje,
        ':tipo' => $tipo
    ]);
    return $conn->lastInsertId();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Validar datos básicos
        $errores = [];
        
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = isset($_POST['precio']) ? floatval($_POST['precio']) : 0;
        $no_decirlo = isset($_POST['no_decirlo']) ? 1 : 0;
        $ambientes = intval($_POST['ambientes'] ?? 0);
        $banios = intval($_POST['banios'] ?? 0);
        $superficie = intval($_POST['superficie'] ?? 0);
        $direccion = trim($_POST['direccion'] ?? '');
        $servicios = isset($_POST['servicios']) ? implode(',', $_POST['servicios']) : '';
        
        // Validaciones
        if (empty($titulo)) $errores[] = 'El título es requerido';
        if (strlen($titulo) < 10) $errores[] = 'El título debe tener al menos 10 caracteres';
        if (strlen($titulo) > 255) $errores[] = 'El título es demasiado largo (máximo 255 caracteres)';
        
        if (empty($descripcion)) $errores[] = 'La descripción es requerida';
        if (strlen($descripcion) < 20) $errores[] = 'La descripción debe tener al menos 20 caracteres';
        
        if (!$no_decirlo && $precio <= 0) $errores[] = 'El precio debe ser mayor a 0';
        
        if ($ambientes <= 0) $errores[] = 'Debe tener al menos 1 ambiente';
        if ($banios <= 0) $errores[] = 'Debe tener al menos 1 baño';
        if ($superficie < 10) $errores[] = 'La superficie debe ser de al menos 10m²';
        if (empty($direccion)) $errores[] = 'La dirección es requerida';
        
        // Validar imágenes
        if (!isset($_FILES['imagenes']) || count($_FILES['imagenes']['name']) == 0) {
            $errores[] = 'Debes subir al menos una imagen';
        } else {
            $total_imagenes = count($_FILES['imagenes']['name']);
            if ($total_imagenes > 5) {
                $errores[] = 'Máximo 5 imágenes permitidas';
            }
            
            // Validar cada imagen
            for ($i = 0; $i < $total_imagenes; $i++) {
                $tipo = $_FILES['imagenes']['type'][$i];
                $tamano = $_FILES['imagenes']['size'][$i];
                
                // Tipos permitidos
                $tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                
                if (!in_array($tipo, $tipos_permitidos)) {
                    $errores[] = 'Solo se permiten imágenes JPG, PNG o GIF';
                    break;
                }
                
                if ($tamano > 5 * 1024 * 1024) { // 5MB
                    $errores[] = 'Cada imagen debe ser menor a 5MB';
                    break;
                }
            }
        }
        
        if (!empty($errores)) {
            echo json_encode(['success' => false, 'errors' => $errores]);
            exit;
        }
        
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Insertar propiedad
        $sql = "INSERT INTO propiedades (
            id_propietario, titulo, descripcion, precio, precio_no_publicado,
            ambientes, sanitarios, superficie, direccion, servicios,
            estado_publicacion, fecha_solicitud, tipo, operacion, estado
        ) VALUES (
            :id_propietario, :titulo, :descripcion, :precio, :precio_no_publicado,
            :ambientes, :sanitarios, :superficie, :direccion, :servicios,
            'pendiente', NOW(), 'casa', 'alquiler', 'a estrenar'
        )";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id_propietario' => $id_propietario,
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':precio' => $no_decirlo ? 0 : $precio,
            ':precio_no_publicado' => $no_decirlo,
            ':ambientes' => $ambientes,
            ':sanitarios' => $banios,
            ':superficie' => $superficie,
            ':direccion' => $direccion,
            ':servicios' => $servicios
        ]);
        
        $id_propiedad = $conn->lastInsertId();
        
        // Procesar imágenes
        $year = date('Y');
        $month = date('m');
        $upload_dir = "../media/propiedades/$year/$month/";
        
        // Crear directorios si no existen
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Guardar cada imagen
        $imagenes_subidas = 0;
        for ($i = 0; $i < $total_imagenes; $i++) {
            $nombre_original = $_FILES['imagenes']['name'][$i];
            $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
            $nuevo_nombre = "propiedad_{$id_propiedad}_" . ($i + 1) . "_" . time() . ".$extension";
            $ruta_completa = $upload_dir . $nuevo_nombre;
            
            if (move_uploaded_file($_FILES['imagenes']['tmp_name'][$i], $ruta_completa)) {
                // Insertar en base de datos
                $sql_img = "INSERT INTO imagenes_propiedades 
                            (id_propiedad, nombre_archivo, ruta, es_principal, orden) 
                            VALUES (:id_propiedad, :nombre_archivo, :ruta, :es_principal, :orden)";
                
                $stmt_img = $conn->prepare($sql_img);
                $stmt_img->execute([
                    ':id_propiedad' => $id_propiedad,
                    ':nombre_archivo' => $nuevo_nombre,
                    ':ruta' => "propiedades/$year/$month/$nuevo_nombre",
                    ':es_principal' => ($i == 0 ? 1 : 0),
                    ':orden' => $i
                ]);
                
                $imagenes_subidas++;
            }
        }
        
        if ($imagenes_subidas == 0) {
            throw new Exception("No se pudieron subir las imágenes");
        }
        
        // Crear notificación para el propietario
        crearNotificacion(
            $conn,
            $id_propietario,
            'solicitud',
            'Solicitud enviada',
            "Tu propiedad \"$titulo\" ha sido enviada para revisión. Será revisada por nuestro equipo administrativo.",
            $id_propiedad
        );
        
        // Registrar log
        $log_sql = "INSERT INTO logs_actividad (usuario_id, usuario_nombre, rol, accion) 
                   VALUES (:usuario_id, :usuario_nombre, :rol, :accion)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':usuario_id' => $id_propietario,
            ':usuario_nombre' => $nombre_propietario,
            ':rol' => 'propietario',
            ':accion' => "Solicitó publicación de propiedad: $titulo (ID: $id_propiedad)"
        ]);
        
        // Confirmar transacción
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Propiedad enviada exitosamente. Será revisada por el administrador.',
            'id_propiedad' => $id_propiedad,
            'titulo' => $titulo
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error guardando propiedad: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error al guardar la propiedad: ' . $e->getMessage()
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>