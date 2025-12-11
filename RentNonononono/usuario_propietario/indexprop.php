<?php
// Verificar sesión y rol
include("../database/session.php");

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "propietario") {
    header("Location: ../index.php");
    exit;
}

$nombre = $_SESSION["nombre"];
$id_propietario = $_SESSION["id"];

// Obtener estadísticas reales desde la base de datos
include("../database/conexion.php");

try {
    // Propiedades totales del propietario
    $sql_propiedades = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado_publicacion = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
        SUM(CASE WHEN estado_publicacion = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado_publicacion = 'rechazada' THEN 1 ELSE 0 END) as rechazadas
    FROM propiedades 
    WHERE id_propietario = :id_propietario";
    
    $stmt_propiedades = $conn->prepare($sql_propiedades);
    $stmt_propiedades->execute([':id_propietario' => $id_propietario]);
    $estadisticas = $stmt_propiedades->fetch(PDO::FETCH_ASSOC);
    
    // Notificaciones no leídas
    $sql_notificaciones = "SELECT COUNT(*) as total FROM notificaciones 
                          WHERE id_usuario = :id_usuario AND leida = 0";
    $stmt_notif = $conn->prepare($sql_notificaciones);
    $stmt_notif->execute([':id_usuario' => $id_propietario]);
    $notificaciones_no_leidas = $stmt_notif->fetchColumn();
    
    // Comentarios totales (si tienes tabla opiniones)
    $sql_comentarios = "SELECT COUNT(*) as total FROM opiniones o
                       INNER JOIN propiedades p ON o.propiedad_id = p.id
                       WHERE p.id_propietario = :id_propietario";
    $stmt_comentarios = $conn->prepare($sql_comentarios);
    $stmt_comentarios->execute([':id_propietario' => $id_propietario]);
    $total_comentarios = $stmt_comentarios->fetchColumn();
    
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas: " . $e->getMessage());
    $estadisticas = ['total' => 0, 'aprobadas' => 0, 'pendientes' => 0, 'rechazadas' => 0];
    $notificaciones_no_leidas = 0;
    $total_comentarios = 0;
}

// Obtener propiedades para la tabla
try {
    $sql_mis_propiedades = "SELECT 
        p.id,
        p.titulo,
        p.descripcion,
        p.precio,
        p.precio_no_publicado,
        p.ambientes,
        p.sanitarios as banios,
        p.superficie,
        p.direccion,
        p.latitud,
        p.longitud,
        p.estado_publicacion,
        p.fecha_solicitud,
        i.ruta as imagen_principal
    FROM propiedades p
    LEFT JOIN imagenes_propiedades i ON p.id = i.id_propiedad AND i.es_principal = 1
    WHERE p.id_propietario = :id_propietario
    ORDER BY p.fecha_solicitud DESC";
    
    $stmt_props = $conn->prepare($sql_mis_propiedades);
    $stmt_props->execute([':id_propietario' => $id_propietario]);
    $mis_propiedades = $stmt_props->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error obteniendo propiedades: " . $e->getMessage());
    $mis_propiedades = [];
}

// Obtener notificaciones
try {
    $sql_notif_lista = "SELECT 
        n.id,
        n.titulo,
        n.mensaje,
        n.tipo,
        n.leida,
        n.fecha,
        p.titulo as propiedad_titulo
    FROM notificaciones n
    LEFT JOIN propiedades p ON n.id_propiedad = p.id
    WHERE n.id_usuario = :id_usuario
    ORDER BY n.fecha DESC
    LIMIT 10";
    
    $stmt_notif_lista = $conn->prepare($sql_notif_lista);
    $stmt_notif_lista->execute([':id_usuario' => $id_propietario]);
    $lista_notificaciones = $stmt_notif_lista->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error obteniendo notificaciones: " . $e->getMessage());
    $lista_notificaciones = [];
}

// Obtener comentarios (si tienes tabla opiniones)
try {
    $sql_comentarios_lista = "SELECT 
        o.id,
        o.comentario,
        o.rating,
        o.fecha,
        u.nombre as usuario_nombre,
        p.titulo as propiedad_titulo
    FROM opiniones o
    INNER JOIN propiedades p ON o.propiedad_id = p.id
    INNER JOIN usuario_visitante u ON o.usuario_id = u.id
    WHERE p.id_propietario = :id_propietario
    AND o.estado = 'aprobada'
    ORDER BY o.fecha DESC
    LIMIT 10";
    
    $stmt_comentarios_lista = $conn->prepare($sql_comentarios_lista);
    $stmt_comentarios_lista->execute([':id_propietario' => $id_propietario]);
    $lista_comentarios = $stmt_comentarios_lista->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular rating promedio
    $rating_promedio = 0;
    if (!empty($lista_comentarios)) {
        $suma_ratings = 0;
        foreach ($lista_comentarios as $comentario) {
            $suma_ratings += $comentario['rating'];
        }
        $rating_promedio = round($suma_ratings / count($lista_comentarios), 1);
    }
    
} catch (Exception $e) {
    error_log("Error obteniendo comentarios: " . $e->getMessage());
    $lista_comentarios = [];
    $rating_promedio = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Propietario | RentNono</title>
    <link rel="stylesheet" href="propietario.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin=""/>
    <style>
        /* Animaciones mejoradas */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        /* Preloader para imágenes */
        .preloader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #82b16d;
        }
        
        .imagen-cargando {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Notificaciones toast */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            max-width: 350px;
        }
        
        .toast-content {
            background: white;
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid #82b16d;
        }
        
        .toast-success {
            border-left-color: #28a745;
        }
        
        .toast-error {
            border-left-color: #dc3545;
        }
        
        .toast-warning {
            border-left-color: #ffc107;
        }
        
        /* Mejoras en el buscador de ubicaciones */
        .contenedor-busqueda {
            position: relative;
        }
        
        .lista-sugerencias-direccion {
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            max-height: 350px;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            margin-top: 8px;
            animation: fadeInUp 0.2s ease;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .sugerencia-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sugerencia-item:last-child {
            border-bottom: none;
        }
        
        .sugerencia-item:hover {
            background: linear-gradient(90deg, #f8f9fa, #e9ecef);
            transform: translateX(5px);
        }
        
        .sugerencia-item.activa {
            background: linear-gradient(90deg, #e8f5e9, #d4edda);
            border-left: 4px solid #82b16d;
        }
        
        .icono-sugerencia {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .info-sugerencia {
            flex: 1;
        }
        
        .nombre-lugar {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
            font-size: 14px;
        }
        
        .detalle-lugar {
            font-size: 12px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .badge-ubicacion {
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .badge-nono {
            background: linear-gradient(135deg, #ff9800, #ff5722);
        }
        
        .badge-chilecito {
            background: linear-gradient(135deg, #2196f3, #0d47a1);
        }
        
        /* Mejoras en la subida de imágenes */
        .area-subida-archivos.drag-over {
            border-color: #82b16d;
            background: rgba(130, 177, 109, 0.05);
            transform: scale(1.02);
        }
        
        .grid-imagenes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .imagen-preview {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 1;
        }
        
        .imagen-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .imagen-preview:hover img {
            transform: scale(1.1);
        }
        
        .overlay-imagen {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            padding: 8px;
            display: flex;
            justify-content: space-between;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .imagen-preview:hover .overlay-imagen {
            transform: translateY(0);
        }
        
        .btn-imagen {
            background: rgba(255,255,255,0.9);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-imagen:hover {
            background: white;
            transform: scale(1.1);
        }
        
        /* Mapa mejorado */
        #mapa-propiedad {
            width: 100%;
            height: 400px;
            border-radius: 12px;
            border: 2px solid #82b16d;
            box-shadow: 0 8px 25px rgba(130, 177, 109, 0.2);
        }
        
        .controles-mapa {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 1000;
        }
        
        .btn-control-mapa {
            width: 40px;
            height: 40px;
            background: white;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #2c3e50;
            font-size: 16px;
            transition: all 0.2s;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }
        
        .btn-control-mapa:hover {
            background: #82b16d;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(130, 177, 109, 0.3);
        }
        
        /* Estado del formulario */
        .formulario-loading {
            position: relative;
            opacity: 0.7;
            pointer-events: none;
        }
        
        .formulario-loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        
        /* Tooltips */
        [data-tooltip] {
            position: relative;
        }
        
        [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 5px;
        }
        
        /* Scrollbar personalizado */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #82b16d;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #6a9a58;
        }
    </style>
</head>
<body>

<!-- BARRA LATERAL -->
<aside class="barra-lateral">
    <div class="cabecera-barra">
        <h2 class="logo">Rent<span>Nono</span></h2>
        <div class="info-usuario">
            <i class="fa-solid fa-user-circle icono-usuario"></i>
            <span class="nombre-usuario"><?php echo htmlspecialchars($nombre); ?></span>
            <span class="rol-usuario">Propietario</span>
        </div>
    </div>

    <nav class="navegacion-barra">
        <ul>
            <li class="activo">
                <a href="#inicio" class="enlace-navegacion" id="nav-inicio">
                    <i class="fa-solid fa-house icono-navegacion"></i>
                    <span class="texto-navegacion">Inicio</span>
                </a>
            </li>
            <li>
                <a href="#formulario" class="enlace-navegacion" id="nav-formulario">
                    <i class="fa-solid fa-plus-circle icono-navegacion"></i>
                    <span class="texto-navegacion">Agregar propiedad</span>
                </a>
            </li>
            <li>
                <a href="#propiedades" class="enlace-navegacion" id="nav-propiedades">
                    <i class="fa-solid fa-building icono-navegacion"></i>
                    <span class="texto-navegacion">Mis propiedades</span>
                    <?php if ($estadisticas['total'] > 0): ?>
                    <span class="badge"><?php echo $estadisticas['total']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="#comentarios" class="enlace-navegacion" id="nav-comentarios">
                    <i class="fa-solid fa-comments icono-navegacion"></i>
                    <span class="texto-navegacion">Comentarios</span>
                    <?php if ($total_comentarios > 0): ?>
                    <span class="badge"><?php echo $total_comentarios; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="#notificaciones" class="enlace-navegacion" id="nav-notificaciones">
                    <i class="fa-solid fa-bell icono-navegacion"></i>
                    <span class="texto-navegacion">Notificaciones</span>
                    <?php if ($notificaciones_no_leidas > 0): ?>
                    <span class="badge nuevo"><?php echo $notificaciones_no_leidas; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </nav>

    <div class="pie-barra">
        <a href="../database/logout.php" class="boton-salir">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="contenido-principal">
    <!-- CABECERA -->
    <header class="cabecera-principal">
        <div class="izquierda-cabecera">
            <button class="boton-menu" id="botonMenu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h1 class="titulo-pagina" id="tituloPagina">Panel de Control</h1>
        </div>
        <div class="derecha-cabecera">
            <div class="icono-notificacion" onclick="mostrarSeccion('notificaciones')">
                <i class="fa-solid fa-bell"></i>
                <?php if ($notificaciones_no_leidas > 0): ?>
                <span class="contador-notificacion"><?php echo $notificaciones_no_leidas; ?></span>
                <?php endif; ?>
            </div>
            <div class="fecha-actual">
                <i class="fa-solid fa-calendar-day"></i>
                <span><?php echo date('d/m/Y'); ?></span>
            </div>
        </div>
    </header>

    <!-- SECCIÓN INICIO -->
    <section id="sec-inicio" class="seccion-contenido activa">
        <div class="cabecera-seccion">
            <h2>Bienvenido de nuevo, <?php echo htmlspecialchars($nombre); ?></h2>
            <p class="subtitulo-seccion">Gestión centralizada de tus propiedades en RentNono</p>
        </div>

        <div class="estadisticas-tablero">
            <div class="tarjeta-estadistica fade-in-up" style="animation-delay: 0.1s">
                <div class="icono-estadistica" style="background-color: #e3f2fd;">
                    <i class="fa-solid fa-building" style="color: #2196f3;"></i>
                </div>
                <div class="info-estadistica">
                    <h3 class="numero-estadistica"><?php echo $estadisticas['total']; ?></h3>
                    <p class="etiqueta-estadistica">Propiedades totales</p>
                </div>
            </div>
            <div class="tarjeta-estadistica fade-in-up" style="animation-delay: 0.2s">
                <div class="icono-estadistica" style="background-color: #e8f5e9;">
                    <i class="fa-solid fa-check-circle" style="color: #4caf50;"></i>
                </div>
                <div class="info-estadistica">
                    <h3 class="numero-estadistica"><?php echo $estadisticas['aprobadas']; ?></h3>
                    <p class="etiqueta-estadistica">Aprobadas</p>
                </div>
            </div>
            <div class="tarjeta-estadistica fade-in-up" style="animation-delay: 0.3s">
                <div class="icono-estadistica" style="background-color: #fff3e0;">
                    <i class="fa-solid fa-clock" style="color: #ff9800;"></i>
                </div>
                <div class="info-estadistica">
                    <h3 class="numero-estadistica"><?php echo $estadisticas['pendientes']; ?></h3>
                    <p class="etiqueta-estadistica">Pendientes</p>
                </div>
            </div>
            <div class="tarjeta-estadistica fade-in-up" style="animation-delay: 0.4s">
                <div class="icono-estadistica" style="background-color: #fce4ec;">
                    <i class="fa-solid fa-comment" style="color: #e91e63;"></i>
                </div>
                <div class="info-estadistica">
                    <h3 class="numero-estadistica"><?php echo $total_comentarios; ?></h3>
                    <p class="etiqueta-estadistica">Comentarios</p>
                </div>
            </div>
        </div>

        <div class="tarjetas-tablero">
            <div class="tarjeta tarjeta-interactiva" onclick="mostrarSeccion('formulario')">
                <div class="cabecera-tarjeta">
                    <i class="fa-solid fa-plus icono-tarjeta"></i>
                    <h3>Agregar propiedad</h3>
                </div>
                <div class="cuerpo-tarjeta">
                    <p>Completá el formulario y enviá tu solicitud al administrador para publicar una nueva propiedad.</p>
                </div>
                <div class="pie-tarjeta">
                    <span class="accion-tarjeta">Comenzar <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>

            <div class="tarjeta tarjeta-interactiva" onclick="mostrarSeccion('propiedades')">
                <div class="cabecera-tarjeta">
                    <i class="fa-solid fa-building icono-tarjeta"></i>
                    <h3>Mis propiedades</h3>
                </div>
                <div class="cuerpo-tarjeta">
                    <p>Revisá el estado de tus propiedades: Pendiente, Aprobada o Rechazada.</p>
                </div>
                <div class="pie-tarjeta">
                    <span class="accion-tarjeta">Ver propiedades <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>

            <div class="tarjeta tarjeta-interactiva" onclick="mostrarSeccion('comentarios')">
                <div class="cabecera-tarjeta">
                    <i class="fa-solid fa-comments icono-tarjeta"></i>
                    <h3>Comentarios y reseñas</h3>
                </div>
                <div class="cuerpo-tarjeta">
                    <p>Leé lo que opinan los visitantes sobre tus propiedades publicadas.</p>
                </div>
                <div class="pie-tarjeta">
                    <span class="accion-tarjeta">Ver comentarios <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>

            <div class="tarjeta tarjeta-interactiva" onclick="mostrarSeccion('notificaciones')">
                <div class="cabecera-tarjeta">
                    <i class="fa-solid fa-bell icono-tarjeta"></i>
                    <h3>Notificaciones</h3>
                </div>
                <div class="cuerpo-tarjeta">
                    <p>Tenés novedades recientes sobre el estado de tus propiedades y solicitudes.</p>
                </div>
                <div class="pie-tarjeta">
                    <span class="accion-tarjeta">Ver notificaciones <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </div>
    </section>

    <!-- SECCIÓN FORMULARIO -->
    <section id="sec-formulario" class="seccion-contenido oculto">
        <div class="cabecera-seccion">
            <h2>Agregar una propiedad</h2>
            <p class="subtitulo-seccion">Completá todos los datos para enviar la solicitud al administrador</p>
        </div>

        <form class="formulario-propiedad" id="formulario-propiedad" action="../database/guardar_propiedad.php" method="POST" enctype="multipart/form-data">
            <div class="grid-formulario">
                <div class="grupo-formulario">
                    <label for="titulo" class="etiqueta-formulario">Título de la propiedad *</label>
                    <input type="text" id="titulo" name="titulo" class="entrada-formulario" 
                           placeholder="Ej: Casa amplia de 3 ambientes en zona residencial" required
                           data-tooltip="Escribe un título atractivo que describa la propiedad">
                    <small class="ayuda-formulario">Un título claro y descriptivo atrae más visitas</small>
                </div>

                <div class="grupo-formulario">
                    <label for="descripcion" class="etiqueta-formulario">Descripción detallada *</label>
                    <textarea id="descripcion" name="descripcion" class="area-texto-formulario" 
                              rows="4" placeholder="Describí las características principales, ubicación, ventajas..." 
                              required data-tooltip="Describe todos los detalles importantes de la propiedad"></textarea>
                    <small class="ayuda-formulario">Incluí detalles como orientación, vistas, estado de conservación</small>
                </div>

                <div class="grupo-formulario">
                    <label for="precio" class="etiqueta-formulario">Precio mensual *</label>
                    <div class="contenedor-precio">
                        <div class="entrada-con-icono">
                            <i class="fa-solid fa-dollar-sign"></i>
                            <input type="number" id="precio" name="precio" class="entrada-formulario" 
                                   placeholder="120000" min="0" step="1">
                        </div>
                        <label class="etiqueta-checkbox">
                            <input type="checkbox" id="no-decirlo" name="no_decirlo">
                            <span>Prefiero no publicar el precio</span>
                        </label>
                    </div>
                </div>

                <div class="grupo-formulario ancho-completo">
                    <h3 class="titulo-seccion-formulario">Características principales</h3>
                    <div class="grid-caracteristicas">
                        <div class="entrada-caracteristica">
                            <label for="ambientes" class="etiqueta-formulario">Ambientes *</label>
                            <div class="entrada-con-icono">
                                <i class="fa-solid fa-door-open"></i>
                                <input type="number" id="ambientes" name="ambientes" class="entrada-formulario" 
                                       placeholder="3" min="1" required>
                            </div>
                        </div>
                        <div class="entrada-caracteristica">
                            <label for="banios" class="etiqueta-formulario">Baños *</label>
                            <div class="entrada-con-icono">
                                <i class="fa-solid fa-bath"></i>
                                <input type="number" id="banios" name="banios" class="entrada-formulario" 
                                       placeholder="1" min="1" required>
                            </div>
                        </div>
                        <div class="entrada-caracteristica">
                            <label for="superficie" class="etiqueta-formulario">Superficie (m²) *</label>
                            <div class="entrada-con-icono">
                                <i class="fa-solid fa-ruler-combined"></i>
                                <input type="number" id="superficie" name="superficie" class="entrada-formulario" 
                                       placeholder="80" min="10" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grupo-formulario ancho-completo">
                    <h3 class="titulo-seccion-formulario">Servicios incluidos</h3>
                    <div class="grid-servicios">
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="wifi">
                            <div class="item-servicio">
                                <i class="fa-solid fa-wifi"></i>
                                <span>WiFi</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="cochera">
                            <div class="item-servicio">
                                <i class="fa-solid fa-car"></i>
                                <span>Cochera</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="patio">
                            <div class="item-servicio">
                                <i class="fa-solid fa-tree"></i>
                                <span>Patio</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="amoblado">
                            <div class="item-servicio">
                                <i class="fa-solid fa-couch"></i>
                                <span>Amoblado</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="aire">
                            <div class="item-servicio">
                                <i class="fa-solid fa-snowflake"></i>
                                <span>Aire acondicionado</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="calefaccion">
                            <div class="item-servicio">
                                <i class="fa-solid fa-fire"></i>
                                <span>Calefacción</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="cable">
                            <div class="item-servicio">
                                <i class="fa-solid fa-tv"></i>
                                <span>Cable TV</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="pileta">
                            <div class="item-servicio">
                                <i class="fa-solid fa-water-ladder"></i>
                                <span>Pileta</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="seguridad">
                            <div class="item-servicio">
                                <i class="fa-solid fa-shield-alt"></i>
                                <span>Seguridad 24hs</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- CAMPO DE UBICACIÓN MEJORADO -->
                <div class="grupo-formulario ancho-completo contenedor-busqueda">
                    <label for="buscar-direccion" class="etiqueta-formulario">
                        <i class="fa-solid fa-map-location-dot"></i> Ubicación de la propiedad *
                    </label>
                    
                    <div class="entrada-con-icono">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" 
                               id="buscar-direccion" 
                               class="entrada-formulario" 
                               placeholder="Buscar en Chilecito o Nonogasta..."
                               autocomplete="off"
                               data-tooltip="Escribe una dirección, barrio o punto de referencia">
                    </div>
                    <small class="ayuda-formulario">
                        <i class="fa-solid fa-lightbulb"></i> Escribe el nombre de la localidad, barrio o dirección específica
                    </small>
                    
                    <div id="lista-sugerencias" class="lista-sugerencias-direccion" style="display: none;"></div>
                    
                    <input type="hidden" id="direccion" name="direccion" required>
                    <input type="hidden" id="latitud" name="latitud">
                    <input type="hidden" id="longitud" name="longitud">
                    <input type="hidden" id="ciudad" name="ciudad">
                    <input type="hidden" id="provincia" name="provincia">
                    
                    <!-- SECCIÓN DEL MAPA -->
                    <div class="seccion-mapa" id="seccion-mapa" style="display: none;">
                        <div class="cabecera-mapa">
                            <h4><i class="fa-solid fa-map"></i> Mapa de la ubicación</h4>
                            <button type="button" class="btn-mapa-opciones" onclick="alternarVistaMapa()">
                                <i class="fa-solid fa-layer-group"></i>
                                <span>Cambiar vista</span>
                            </button>
                        </div>
                        
                        <div class="contenedor-mapa">
                            <div id="mapa-propiedad"></div>
                            <div class="controles-mapa">
                                <button type="button" class="btn-control-mapa" onclick="acercarMapa()" title="Acercar">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <button type="button" class="btn-control-mapa" onclick="alejarMapa()" title="Alejar">
                                    <i class="fa-solid fa-minus"></i>
                                </button>
                                <button type="button" class="btn-control-mapa" onclick="centrarMarcador()" title="Centrar en marcador">
                                    <i class="fa-solid fa-crosshairs"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="info-ubicacion-mapa">
                            <div class="icono-info">
                                <i class="fa-solid fa-check-circle"></i>
                            </div>
                            <div class="texto-info">
                                <h5>Ubicación seleccionada</h5>
                                <p id="texto-ubicacion-mapa">Esperando selección...</p>
                                <div class="coordenadas">
                                    <span class="coord-item">
                                        <i class="fa-solid fa-latitude"></i>
                                        Lat: <span id="texto-latitud">-</span>
                                    </span>
                                    <span class="coord-item">
                                        <i class="fa-solid fa-longitude"></i>
                                        Lon: <span id="texto-longitud">-</span>
                                    </span>
                                </div>
                            </div>
                            <button type="button" class="btn-editar-ubicacion" onclick="editarUbicacion()">
                                <i class="fa-solid fa-pencil"></i> Cambiar
                            </button>
                        </div>
                        
                        <small class="ayuda-formulario">
                            <i class="fa-solid fa-info-circle"></i> 
                            Puedes arrastrar el marcador para ajustar la ubicación exacta
                        </small>
                    </div>
                    
                    <div class="mensaje-sin-ubicacion" id="mensaje-sin-ubicacion">
                        <i class="fa-solid fa-map-marked-alt"></i>
                        <p>Selecciona una ubicación para ver el mapa interactivo</p>
                    </div>
                </div>

                <!-- SUBIDA DE IMÁGENES MEJORADA -->
                <div class="grupo-formulario ancho-completo">
                    <h3 class="titulo-seccion-formulario">Imágenes de la propiedad *</h3>
                    <p class="ayuda-formulario">Subí imágenes de buena calidad (máximo 5 archivos, formatos: JPG, PNG, máximo 5MB cada una)</p>
                    
                    <div class="area-subida-archivos" id="areaSubidaArchivos">
                        <i class="fa-solid fa-cloud-upload-alt icono-subida"></i>
                        <p class="texto-subida">Arrastrá y soltá imágenes aquí o hacé clic para seleccionar</p>
                        <input type="file" id="imagenes" name="imagenes[]" multiple accept="image/*" required>
                        <div class="lista-archivos" id="listaArchivos"></div>
                    </div>
                    
                    <div class="grid-imagenes" id="gridImagenes">
                        <!-- Las imágenes preview se cargarán aquí -->
                    </div>
                    
                    <div class="contador-imagenes">
                        <small><span id="contadorSeleccionadas">0</span> imágenes seleccionadas (Máximo 5)</small>
                    </div>
                </div>

                <div class="grupo-formulario ancho-completo acciones-formulario">
                    <button type="button" class="boton-secundario" onclick="mostrarSeccion('inicio')">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="boton-principal" id="btnEnviarFormulario">
                        <i class="fa-solid fa-paper-plane"></i> Enviar solicitud
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- SECCIÓN MIS PROPIEDADES -->
    <section id="sec-propiedades" class="seccion-contenido oculto">
        <div class="cabecera-seccion">
            <h2>Mis propiedades</h2>
            <p class="subtitulo-seccion">Gestioná todas tus propiedades publicadas en RentNono</p>
            <div class="acciones-seccion">
                <button class="boton-secundario" onclick="mostrarSeccion('formulario')">
                    <i class="fa-solid fa-plus"></i> Nueva propiedad
                </button>
                <div class="grupo-filtro">
                    <select class="selector-filtro" id="filtroEstado" onchange="filtrarPropiedades()">
                        <option value="todas">Todas las propiedades</option>
                        <option value="aprobada">Aprobadas</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="rechazada">Rechazadas</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="contenedor-tabla-propiedades">
            <table class="tabla-propiedades">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Dirección</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <th>Fecha de solicitud</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaPropiedadesBody">
                    <?php if (!empty($mis_propiedades)): ?>
                        <?php foreach ($mis_propiedades as $propiedad): ?>
                        <?php 
                        $clase_estado = '';
                        $texto_estado = '';
                        switch($propiedad['estado_publicacion']) {
                            case 'aprobada':
                                $clase_estado = 'estado-aprobada';
                                $texto_estado = 'Aprobada';
                                break;
                            case 'pendiente':
                                $clase_estado = 'estado-pendiente';
                                $texto_estado = 'Pendiente';
                                break;
                            case 'rechazada':
                                $clase_estado = 'estado-rechazada';
                                $texto_estado = 'Rechazada';
                                break;
                        }
                        
                        $precio_display = $propiedad['precio_no_publicado'] ? 'No publicado' : '$' . number_format($propiedad['precio'], 0, ',', '.');
                        $fecha_solicitud = date('d/m/Y', strtotime($propiedad['fecha_solicitud']));
                        $imagen_url = !empty($propiedad['imagen_principal']) ? 
                            '../media/' . $propiedad['imagen_principal'] : 
                            'https://images.unsplash.com/photo-1518780664697-55e3ad937233?w=150';
                        ?>
                        <tr data-estado="<?php echo $propiedad['estado_publicacion']; ?>">
                            <td>
                                <div class="titulo-propiedad">
                                    <div class="imagen-propiedad" style="background-image: url('<?php echo $imagen_url; ?>')"></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($propiedad['titulo']); ?></strong>
                                        <small><?php echo $propiedad['ambientes']; ?> ambientes • <?php echo $propiedad['banios']; ?> baños • <?php echo $propiedad['superficie']; ?>m²</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="direccion-propiedad">
                                    <?php echo htmlspecialchars($propiedad['direccion']); ?>
                                    <?php if (!empty($propiedad['latitud']) && !empty($propiedad['longitud'])): ?>
                                    <small class="ver-mapa" onclick="verMapaPropiedad(<?php echo $propiedad['id']; ?>, <?php echo $propiedad['latitud']; ?>, <?php echo $propiedad['longitud']; ?>, '<?php echo htmlspecialchars($propiedad['direccion']); ?>')">
                                        <i class="fa-solid fa-map-marker-alt"></i> Ver mapa
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo $precio_display; ?></td>
                            <td><span class="badge-estado <?php echo $clase_estado; ?>"><?php echo $texto_estado; ?></span></td>
                            <td><?php echo $fecha_solicitud; ?></td>
                            <td>
                                <div class="botones-accion">
                                    <button class="boton-accion boton-ver" title="Ver detalles" onclick="verDetallesPropiedad(<?php echo $propiedad['id']; ?>)">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <?php if ($propiedad['estado_publicacion'] == 'pendiente'): ?>
                                    <button class="boton-accion boton-editar" title="Editar" onclick="editarPropiedad(<?php echo $propiedad['id']; ?>)">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="sin-datos-tabla">
                                <i class="fa-solid fa-building"></i>
                                <p>No tenés propiedades registradas aún.</p>
                                <button class="boton-principal" onclick="mostrarSeccion('formulario')">
                                    <i class="fa-solid fa-plus"></i> Agregar primera propiedad
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- SECCIÓN COMENTARIOS -->
    <section id="sec-comentarios" class="seccion-contenido oculto">
        <div class="cabecera-seccion">
            <h2>Comentarios y reseñas</h2>
            <p class="subtitulo-seccion">Feedback de los visitantes sobre tus propiedades</p>
            <?php if (!empty($lista_comentarios)): ?>
            <div class="resumen-calificacion">
                <div class="calificacion-general">
                    <span class="numero-calificacion"><?php echo $rating_promedio; ?></span>
                    <div class="estrellas-calificacion">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= floor($rating_promedio)): ?>
                            <i class="fa-solid fa-star"></i>
                            <?php elseif ($i == ceil($rating_promedio) && $rating_promedio - floor($rating_promedio) >= 0.5): ?>
                            <i class="fa-solid fa-star-half-alt"></i>
                            <?php else: ?>
                            <i class="fa-regular fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <span class="contador-calificacion">Basado en <?php echo count($lista_comentarios); ?> comentarios</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="contenedor-comentarios" id="listaComentarios">
            <?php if (!empty($lista_comentarios)): ?>
                <?php foreach ($lista_comentarios as $comentario): ?>
                <div class="tarjeta-comentario fade-in-up">
                    <div class="cabecera-comentario">
                        <div class="info-comentarista">
                            <div class="avatar-comentarista"><?php echo strtoupper(substr($comentario['usuario_nombre'], 0, 1)); ?></div>
                            <div>
                                <h4><?php echo htmlspecialchars($comentario['usuario_nombre']); ?></h4>
                                <div class="calificacion-comentario">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $comentario['rating']): ?>
                                        <i class="fa-solid fa-star"></i>
                                        <?php else: ?>
                                        <i class="fa-regular fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <span class="fecha-comentario"><?php echo date('d/m/Y', strtotime($comentario['fecha'])); ?></span>
                    </div>
                    <div class="cuerpo-comentario">
                        <h5><?php echo htmlspecialchars($comentario['propiedad_titulo']); ?></h5>
                        <p>"<?php echo htmlspecialchars($comentario['comentario']); ?>"</p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sin-datos-tabla">
                    <i class="fa-solid fa-comments"></i>
                    <p>Todavía no tenés comentarios en tus propiedades.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- SECCIÓN NOTIFICACIONES -->
    <section id="sec-notificaciones" class="seccion-contenido oculto">
        <div class="cabecera-seccion">
            <h2>Notificaciones</h2>
            <p class="subtitulo-seccion">Mantenete al día con las novedades de tus propiedades</p>
            <?php if (!empty($lista_notificaciones)): ?>
            <button class="boton-secundario marcar-todas-leidas" id="btnMarcarTodasLeidas">
                <i class="fa-solid fa-check-double"></i> Marcar todas como leídas
            </button>
            <?php endif; ?>
        </div>

        <div class="contenedor-notificaciones" id="listaNotificaciones">
            <?php if (!empty($lista_notificaciones)): ?>
                <?php foreach ($lista_notificaciones as $notificacion): ?>
                <?php 
                $icono = 'fa-bell';
                $color = '';
                switch($notificacion['tipo']) {
                    case 'aprobacion':
                        $icono = 'fa-check-circle';
                        $color = 'verde';
                        break;
                    case 'rechazo':
                        $icono = 'fa-times-circle';
                        $color = 'rojo';
                        break;
                    case 'solicitud':
                        $icono = 'fa-clock';
                        $color = 'amarillo';
                        break;
                    case 'comentario':
                        $icono = 'fa-comment';
                        $color = 'azul';
                        break;
                }
                
                if (!in_array($notificacion['tipo'], ['aprobacion', 'rechazo', 'solicitud', 'comentario'])) {
                    continue;
                }
                
                $fecha = new DateTime($notificacion['fecha']);
                $hoy = new DateTime();
                $diferencia = $hoy->diff($fecha);
                
                if ($diferencia->days == 0) {
                    if ($diferencia->h == 0) {
                        $tiempo_texto = 'Hace ' . $diferencia->i . ' minutos';
                    } else {
                        $tiempo_texto = 'Hace ' . $diferencia->h . ' horas';
                    }
                } elseif ($diferencia->days == 1) {
                    $tiempo_texto = 'Ayer, ' . $fecha->format('H:i');
                } else {
                    $tiempo_texto = $fecha->format('d/m/Y H:i');
                }
                ?>
                <div class="tarjeta-notificacion <?php echo $notificacion['leida'] ? '' : 'no-leida'; ?>" 
                     data-id="<?php echo $notificacion['id']; ?>">
                    <div class="icono-notificacion <?php echo $color; ?>">
                        <i class="fa-solid <?php echo $icono; ?>"></i>
                    </div>
                    <div class="contenido-notificacion">
                        <h4><?php echo htmlspecialchars($notificacion['titulo']); ?></h4>
                        <p><?php echo htmlspecialchars($notificacion['mensaje']); ?></p>
                        <?php if (!empty($notificacion['propiedad_titulo'])): ?>
                        <p class="propiedad-notificacion">
                            <i class="fa-solid fa-building"></i>
                            <?php echo htmlspecialchars($notificacion['propiedad_titulo']); ?>
                        </p>
                        <?php endif; ?>
                        <span class="tiempo-notificacion"><?php echo $tiempo_texto; ?></span>
                    </div>
                    <button class="accion-notificacion" onclick="marcarComoLeida(<?php echo $notificacion['id']; ?>)">
                        <i class="fa-solid fa-check"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sin-datos-tabla">
                    <i class="fa-solid fa-bell-slash"></i>
                    <p>No tenés notificaciones pendientes.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- MODAL DE DETALLES DE PROPIEDAD -->
<div class="modal" id="modalDetallesPropiedad" style="display:none;">
    <div class="modal-contenido modal-detalles">
        <div class="modal-header">
            <h3><i class="fa-solid fa-building"></i> Detalles de la Propiedad</h3>
            <span class="cerrar" onclick="cerrarModalDetalles()">&times;</span>
        </div>
        <div class="modal-body" id="detallesPropiedadContent">
            <!-- Los detalles se cargan dinámicamente -->
        </div>
    </div>
</div>

<!-- MODAL DEL MAPA DE PROPIEDAD -->
<div class="modal" id="modalMapaPropiedad" style="display:none;">
    <div class="modal-contenido modal-mapa">
        <div class="modal-header">
            <h3><i class="fa-solid fa-map"></i> Ubicación de la propiedad</h3>
            <span class="cerrar" onclick="cerrarModalMapa()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="mapa-modal" style="height: 400px; border-radius: 8px;"></div>
            <div class="info-mapa-modal">
                <h4 id="titulo-mapa-modal"></h4>
                <p id="direccion-mapa-modal"></p>
            </div>
        </div>
    </div>
</div>

<!-- TOAST NOTIFICATION -->
<div id="toastContainer" class="toast-notification"></div>

<!-- MODAL DE ERRORES -->
<div class="modal-error" id="modalErrorGlobal" style="display:none;">
    <div class="contenido-modal-error">
        <h3><i class="fa-solid fa-exclamation-triangle"></i> Error</h3>
        <div id="errorMessage"></div>
        <button onclick="document.getElementById('modalErrorGlobal').style.display='none'">Aceptar</button>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<script>
// Datos globales
const datosUsuario = {
    id: <?php echo $id_propietario; ?>,
    nombre: "<?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>"
};

const datosEstadisticas = {
    total: <?php echo $estadisticas['total']; ?>,
    aprobadas: <?php echo $estadisticas['aprobadas']; ?>,
    pendientes: <?php echo $estadisticas['pendientes']; ?>,
    rechazadas: <?php echo $estadisticas['rechazadas']; ?>
};

// Variable global para el mapa
let mapaModal = null;

// Función para mostrar mapa de propiedad existente
function verMapaPropiedad(id, lat, lng, direccion) {
    const modal = document.getElementById('modalMapaPropiedad');
    const titulo = document.getElementById('titulo-mapa-modal');
    const direccionElement = document.getElementById('direccion-mapa-modal');
    
    modal.style.display = 'block';
    titulo.textContent = 'Propiedad ID: ' + id;
    direccionElement.textContent = direccion;
    
    // Inicializar mapa si no existe
    if (!mapaModal) {
        mapaModal = L.map('mapa-modal').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(mapaModal);
    } else {
        mapaModal.setView([lat, lng], 15);
    }
    
    // Limpiar marcadores anteriores
    mapaModal.eachLayer(function(layer) {
        if (layer instanceof L.Marker) {
            mapaModal.removeLayer(layer);
        }
    });
    
    // Añadir marcador
    L.marker([lat, lng])
        .addTo(mapaModal)
        .bindPopup('<b>Propiedad</b><br>' + direccion)
        .openPopup();
}

function cerrarModalMapa() {
    document.getElementById('modalMapaPropiedad').style.display = 'none';
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('modalMapaPropiedad');
    if (event.target == modal) {
        cerrarModalMapa();
    }
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Sistema de RentNono inicializado');
});
</script>

<script src="../script/propietario.js"></script>

</body>
</html>