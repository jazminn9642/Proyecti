// ============================================
// SISTEMA DE PANEL DE PROPIETARIO - RENTNONO
// ============================================

document.addEventListener("DOMContentLoaded", function() {
    console.log("🚀 Iniciando sistema de RentNono...");
    
    // Inicializar todos los componentes
    inicializarNavegacion();
    inicializarInterfaz();
    inicializarSubidaImagenes();
    inicializarBuscadorLaRioja();
    inicializarMapaLaRioja();
    inicializarEventos();
    inicializarFiltros();
    
    console.log("✅ Sistema inicializado correctamente");
});

// ============================================
// 1. NAVEGACIÓN Y UI
// ============================================

function inicializarNavegacion() {
    console.log("🔧 Inicializando navegación...");
    
    // Botón de menú responsive
    const botonMenu = document.getElementById('botonMenu');
    const barraLateral = document.querySelector('.barra-lateral');
    const contenidoPrincipal = document.querySelector('.contenido-principal');
    
    if (botonMenu) {
        botonMenu.addEventListener('click', function() {
            barraLateral.classList.toggle('colapsada');
            contenidoPrincipal.classList.toggle('barra-colapsada');
        });
    }
    
    // Navegación por secciones
    const enlacesNav = document.querySelectorAll('.enlace-navegacion');
    enlacesNav.forEach(enlace => {
        enlace.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            if (href.startsWith('#')) {
                const seccion = href.substring(1);
                mostrarSeccion(seccion);
            }
        });
    });
    
    // Tarjetas interactivas
    const tarjetasInteractivas = document.querySelectorAll('.tarjeta-interactiva');
    tarjetasInteractivas.forEach(tarjeta => {
        tarjeta.addEventListener('click', function() {
            const seccion = this.getAttribute('data-abrir') || 'formulario';
            mostrarSeccion(seccion);
        });
    });
}

function mostrarSeccion(seccionId) {
    console.log(`📌 Mostrando sección: ${seccionId}`);
    
    const secciones = {
        'inicio': document.getElementById('sec-inicio'),
        'formulario': document.getElementById('sec-formulario'),
        'propiedades': document.getElementById('sec-propiedades'),
        'comentarios': document.getElementById('sec-comentarios'),
        'notificaciones': document.getElementById('sec-notificaciones')
    };
    
    const enlaces = {
        'inicio': document.getElementById('nav-inicio'),
        'formulario': document.getElementById('nav-formulario'),
        'propiedades': document.getElementById('nav-propiedades'),
        'comentarios': document.getElementById('nav-comentarios'),
        'notificaciones': document.getElementById('nav-notificaciones')
    };
    
    // Ocultar todas las secciones y remover estado activo
    Object.values(secciones).forEach(sec => {
        if (sec) {
            sec.classList.remove('activa');
            sec.classList.add('oculto');
        }
    });
    
    Object.values(enlaces).forEach(enlace => {
        if (enlace) {
            enlace.parentElement.classList.remove('activo');
        }
    });
    
    // Mostrar sección seleccionada
    if (secciones[seccionId]) {
        secciones[seccionId].classList.remove('oculto');
        secciones[seccionId].classList.add('activa');
        
        // Activar enlace en barra lateral
        if (enlaces[seccionId]) {
            enlaces[seccionId].parentElement.classList.add('activo');
        }
        
        // Actualizar título de página
        const tituloPagina = document.getElementById('tituloPagina');
        if (tituloPagina) {
            const titulos = {
                'inicio': 'Panel de Control',
                'formulario': 'Agregar Propiedad',
                'propiedades': 'Mis Propiedades',
                'comentarios': 'Comentarios',
                'notificaciones': 'Notificaciones'
            };
            tituloPagina.textContent = titulos[seccionId] || 'RentNono';
        }
        
        // Scroll al inicio de la sección
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// ============================================
// 2. INTERFAZ Y UTILIDADES
// ============================================

function inicializarInterfaz() {
    console.log("🎨 Inicializando interfaz...");
    
    // Toggle de precio no publicado
    const checkboxPrecio = document.getElementById('no-decirlo');
    const inputPrecio = document.getElementById('precio');
    
    if (checkboxPrecio && inputPrecio) {
        checkboxPrecio.addEventListener('change', function() {
            inputPrecio.disabled = this.checked;
            inputPrecio.placeholder = this.checked ? 'No publicado' : '120000';
            if (this.checked) {
                inputPrecio.value = '';
            }
        });
        
        // Estado inicial
        inputPrecio.disabled = checkboxPrecio.checked;
        inputPrecio.placeholder = checkboxPrecio.checked ? 'No publicado' : '120000';
    }
    
    // Tooltips
    const elementosTooltip = document.querySelectorAll('[data-tooltip]');
    elementosTooltip.forEach(elemento => {
        elemento.addEventListener('mouseenter', function() {
            const tooltip = this.getAttribute('data-tooltip');
            if (tooltip) {
                mostrarTooltip(this, tooltip);
            }
        });
        
        elemento.addEventListener('mouseleave', function() {
            ocultarTooltip();
        });
    });
}

function mostrarTooltip(elemento, texto) {
    // Eliminar tooltip anterior si existe
    ocultarTooltip();
    
    // Crear tooltip
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip-custom';
    tooltip.textContent = texto;
    tooltip.style.cssText = `
        position: absolute;
        background: #333;
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 10000;
        white-space: nowrap;
        pointer-events: none;
    `;
    
    document.body.appendChild(tooltip);
    
    // Posicionar tooltip
    const rect = elemento.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    
    tooltip.style.top = (rect.top - tooltipRect.height - 8) + 'px';
    tooltip.style.left = (rect.left + (rect.width - tooltipRect.width) / 2) + 'px';
}

function ocultarTooltip() {
    const tooltip = document.querySelector('.tooltip-custom');
    if (tooltip) {
        tooltip.remove();
    }
}

// ============================================
// 3. SUBIDA DE IMÁGENES MEJORADA
// ============================================

function inicializarSubidaImagenes() {
    console.log("🖼️ Inicializando subida de imágenes...");
    
    const areaSubida = document.getElementById('areaSubidaArchivos');
    const inputImagenes = document.getElementById('imagenes');
    const gridImagenes = document.getElementById('gridImagenes');
    const contadorSeleccionadas = document.getElementById('contadorSeleccionadas');
    
    if (!areaSubida || !inputImagenes) return;
    
    // Click en el área de subida
    areaSubida.addEventListener('click', function(e) {
        if (e.target !== inputImagenes) {
            inputImagenes.click();
        }
    });
    
    // Drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        areaSubida.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });
    
    areaSubida.addEventListener('dragenter', function() {
        this.classList.add('drag-over');
    });
    
    areaSubida.addEventListener('dragover', function() {
        this.classList.add('drag-over');
    });
    
    areaSubida.addEventListener('dragleave', function() {
        this.classList.remove('drag-over');
    });
    
    areaSubida.addEventListener('drop', function(e) {
        this.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            inputImagenes.files = files;
            procesarImagenes(files);
        }
    });
    
    // Cambio en el input de archivos
    inputImagenes.addEventListener('change', function() {
        procesarImagenes(this.files);
    });
    
    // Función para procesar imágenes
    window.procesarImagenes = function(files) {
        if (!files || files.length === 0) return;
        
        // Validar cantidad máxima
        if (files.length > 5) {
            mostrarToast('Máximo 5 imágenes permitidas', 'error');
            return;
        }
        
        // Limpiar grid anterior
        if (gridImagenes) {
            gridImagenes.innerHTML = '';
        }
        
        // Procesar cada archivo
        Array.from(files).forEach((file, index) => {
            // Validar tipo
            if (!file.type.startsWith('image/')) {
                mostrarToast(`El archivo "${file.name}" no es una imagen válida`, 'error');
                return;
            }
            
            // Validar tamaño (5MB)
            if (file.size > 5 * 1024 * 1024) {
                mostrarToast(`La imagen "${file.name}" es demasiado grande (máximo 5MB)`, 'error');
                return;
            }
            
            // Crear preview
            const reader = new FileReader();
            reader.onload = function(e) {
                const imagenPreview = document.createElement('div');
                imagenPreview.className = 'imagen-preview';
                imagenPreview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview" loading="lazy">
                    <div class="overlay-imagen">
                        <button type="button" class="btn-imagen" onclick="rotarImagen(${index})" title="Rotar">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                        <button type="button" class="btn-imagen" onclick="eliminarImagen(${index})" title="Eliminar">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                `;
                
                if (gridImagenes) {
                    gridImagenes.appendChild(imagenPreview);
                }
            };
            reader.readAsDataURL(file);
        });
        
        // Actualizar contador
        if (contadorSeleccionadas) {
            contadorSeleccionadas.textContent = files.length;
        }
        
        mostrarToast(`${files.length} imagen(es) cargada(s) correctamente`, 'success');
    };
    
    // Previsualización inicial si hay imágenes
    if (inputImagenes.files.length > 0) {
        procesarImagenes(inputImagenes.files);
    }
}

window.rotarImagen = function(index) {
    const gridImagenes = document.getElementById('gridImagenes');
    const imagenPreview = gridImagenes.children[index];
    const img = imagenPreview.querySelector('img');
    
    let rotacion = parseInt(img.style.transform.replace('rotate(', '').replace('deg)', '')) || 0;
    rotacion = (rotacion + 90) % 360;
    img.style.transform = `rotate(${rotacion}deg)`;
    img.style.transition = 'transform 0.3s ease';
};

window.eliminarImagen = function(index) {
    const inputImagenes = document.getElementById('imagenes');
    const gridImagenes = document.getElementById('gridImagenes');
    const contadorSeleccionadas = document.getElementById('contadorSeleccionadas');
    
    if (!inputImagenes || !gridImagenes) return;
    
    // Crear nuevo FileList
    const dt = new DataTransfer();
    const archivos = inputImagenes.files;
    
    for (let i = 0; i < archivos.length; i++) {
        if (i !== index) {
            dt.items.add(archivos[i]);
        }
    }
    
    // Actualizar input
    inputImagenes.files = dt.files;
    
    // Actualizar preview
    procesarImagenes(inputImagenes.files);
    
    // Actualizar contador
    if (contadorSeleccionadas) {
        contadorSeleccionadas.textContent = inputImagenes.files.length;
    }
    
    mostrarToast('Imagen eliminada', 'info');
};

// ============================================
// 4. BUSCADOR DE UBICACIONES - LA RIOJA
// ============================================

let mapaLaRioja = null;
let marcadorLaRioja = null;
let sugerenciasActuales = [];

// Base de datos de ubicaciones de La Rioja (Chilecito y Nonogasta)
const ubicacionesLaRioja = [
    // Nonogasta (FOCO PRINCIPAL)
    { nombre: "Nonogasta", tipo: "pueblo", lat: -29.2833, lon: -67.5000, destacado: true },
    { nombre: "Nonogasta Centro", tipo: "centro", lat: -29.2833, lon: -67.5000, destacado: true },
    { nombre: "Nonogasta Pueblo", tipo: "pueblo", lat: -29.2833, lon: -67.5000, destacado: true },
    { nombre: "Barrio Nonogasta", tipo: "barrio", lat: -29.2833, lon: -67.5000, destacado: true },
    
    // Chilecito
    { nombre: "Chilecito", tipo: "ciudad", lat: -29.1619, lon: -67.4974 },
    { nombre: "Chilecito Centro", tipo: "centro", lat: -29.1619, lon: -67.4974 },
    { nombre: "Chilecito Ciudad", tipo: "ciudad", lat: -29.1619, lon: -67.4974 },
    
    // Barrios de Chilecito
    { nombre: "Barrio El Molino", tipo: "barrio", lat: -29.1700, lon: -67.5000 },
    { nombre: "Barrio Estación", tipo: "barrio", lat: -29.1550, lon: -67.4950 },
    { nombre: "Barrio Jardín", tipo: "barrio", lat: -29.1650, lon: -67.5050 },
    { nombre: "Barrio Los Olivos", tipo: "barrio", lat: -29.1750, lon: -67.4900 },
    { nombre: "Barrio San Martín", tipo: "barrio", lat: -29.1600, lon: -67.5020 },
    
    // Localidades cercanas
    { nombre: "Anguinán", tipo: "localidad", lat: -29.2000, lon: -67.4833 },
    { nombre: "Los Sarmientos", tipo: "localidad", lat: -29.0833, lon: -67.5333 },
    { nombre: "La Puntilla", tipo: "localidad", lat: -29.2333, lon: -67.4667 },
    { nombre: "San Miguel", tipo: "localidad", lat: -29.1833, lon: -67.5167 },
    { nombre: "Sañogasta", tipo: "localidad", lat: -29.2833, lon: -67.6333 },
    
    // Calles principales
    { nombre: "Av. San Martín", tipo: "calle", lat: -29.1619, lon: -67.4974 },
    { nombre: "Calle 25 de Mayo", tipo: "calle", lat: -29.1625, lon: -67.4968 },
    { nombre: "Av. Belgrano", tipo: "calle", lat: -29.1630, lon: -67.4980 },
    { nombre: "Ruta Nacional 40", tipo: "ruta", lat: -29.1500, lon: -67.5000 },
    
    // Instituciones importantes
    { nombre: "Municipalidad de Chilecito", tipo: "institucion", lat: -29.1620, lon: -67.4970 },
    { nombre: "Hospital de Chilecito", tipo: "hospital", lat: -29.1650, lon: -67.4950 },
    { nombre: "Terminal de Ómnibus", tipo: "terminal", lat: -29.1580, lon: -67.4990 },
    { nombre: "Plaza Principal", tipo: "plaza", lat: -29.1615, lon: -67.4975 },
    
    // Zonas rurales
    { nombre: "Quebrada de los Sauces", tipo: "quebrada", lat: -29.1667, lon: -67.4333 },
    { nombre: "Cerro Famatina", tipo: "cerro", lat: -29.0000, lon: -67.8333 },
    { nombre: "Río Chilecito", tipo: "rio", lat: -29.1833, lon: -67.4667 }
];

function inicializarBuscadorLaRioja() {
    console.log("🔍 Inicializando buscador para La Rioja...");
    
    const inputBusqueda = document.getElementById('buscar-direccion');
    const listaSugerencias = document.getElementById('lista-sugerencias');
    
    if (!inputBusqueda) {
        console.error("❌ No se encuentra el campo de búsqueda");
        return;
    }
    
    // Evento de escritura
    inputBusqueda.addEventListener('input', function(e) {
        const termino = e.target.value.trim();
        
        if (termino.length === 0) {
            if (listaSugerencias) {
                listaSugerencias.style.display = 'none';
            }
            return;
        }
        
        // Mostrar sugerencias locales
        mostrarSugerenciasLocales(termino);
    });
    
    // Evento de teclado
    inputBusqueda.addEventListener('keydown', function(e) {
        const listaSugerencias = document.getElementById('lista-sugerencias');
        
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            navegarSugerencias(e.key);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            seleccionarSugerenciaActiva();
        } else if (e.key === 'Escape') {
            if (listaSugerencias) {
                listaSugerencias.style.display = 'none';
            }
        }
    });
    
    // Cerrar sugerencias al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (listaSugerencias && 
            !inputBusqueda.contains(e.target) && 
            !listaSugerencias.contains(e.target)) {
            listaSugerencias.style.display = 'none';
        }
    });
    
    console.log("✅ Buscador inicializado");
}

function mostrarSugerenciasLocales(termino) {
    const listaSugerencias = document.getElementById('lista-sugerencias');
    if (!listaSugerencias) return;
    
    // Filtrar ubicaciones
    const sugerencias = ubicacionesLaRioja.filter(ubicacion => {
        const nombreLower = ubicacion.nombre.toLowerCase();
        const terminoLower = termino.toLowerCase();
        
        // Priorizar coincidencias exactas o que empiecen con el término
        if (nombreLower.startsWith(terminoLower)) {
            return true;
        }
        
        // También incluir coincidencias parciales
        return nombreLower.includes(terminoLower);
    }).slice(0, 15); // Limitar a 15 resultados
    
    if (sugerencias.length === 0) {
        listaSugerencias.innerHTML = `
            <div class="sugerencia-vacia">
                <i class="fa-solid fa-map-marker-alt"></i>
                <p>No se encontraron resultados para "${termino}"</p>
                <small>Intenta con otra búsqueda</small>
            </div>
        `;
        listaSugerencias.style.display = 'block';
        sugerenciasActuales = [];
        return;
    }
    
    // Ordenar: destacados primero, luego por relevancia
    sugerencias.sort((a, b) => {
        if (a.destacado && !b.destacado) return -1;
        if (!a.destacado && b.destacado) return 1;
        
        const aStartsWith = a.nombre.toLowerCase().startsWith(termino.toLowerCase());
        const bStartsWith = b.nombre.toLowerCase().startsWith(termino.toLowerCase());
        
        if (aStartsWith && !bStartsWith) return -1;
        if (!aStartsWith && bStartsWith) return 1;
        
        return a.nombre.localeCompare(b.nombre);
    });
    
    // Generar HTML de sugerencias
    let html = '';
    sugerencias.forEach((ubicacion, index) => {
        const icono = obtenerIconoTipo(ubicacion.tipo);
        const color = obtenerColorTipo(ubicacion.tipo);
        const destacado = ubicacion.destacado ? 'data-destacado="true"' : '';
        
        html += `
            <div class="sugerencia-item" 
                 data-index="${index}"
                 data-lat="${ubicacion.lat}"
                 data-lon="${ubicacion.lon}"
                 data-nombre="${ubicacion.nombre}"
                 ${destacado}
                 onclick="seleccionarUbicacionLocal(this)">
                <div class="icono-sugerencia" style="background: ${color}20; color: ${color};">
                    <i class="fa-solid ${icono}"></i>
                </div>
                <div class="info-sugerencia">
                    <div class="nombre-lugar">
                        ${ubicacion.nombre}
                        ${ubicacion.destacado ? '<span class="badge-ubicacion badge-nono">NONOGASTA</span>' : 
                          ubicacion.nombre.includes('Chilecito') ? '<span class="badge-ubicacion badge-chilecito">CHILECITO</span>' : ''}
                    </div>
                    <div class="detalle-lugar">
                        <span class="tipo-lugar">${ubicacion.tipo}</span>
                        <small><i class="fa-solid fa-location-dot"></i> La Rioja, Argentina</small>
                    </div>
                </div>
            </div>
        `;
    });
    
    listaSugerencias.innerHTML = html;
    listaSugerencias.style.display = 'block';
    sugerenciasActuales = sugerencias;
}

function navegarSugerencias(direccion) {
    const sugerencias = document.querySelectorAll('.sugerencia-item');
    if (sugerencias.length === 0) return;
    
    let indexActivo = -1;
    for (let i = 0; i < sugerencias.length; i++) {
        if (sugerencias[i].classList.contains('activa')) {
            indexActivo = i;
            break;
        }
    }
    
    // Remover activo actual
    sugerencias.forEach(s => s.classList.remove('activa'));
    
    // Calcular nuevo índice
    let nuevoIndex;
    if (direccion === 'ArrowDown') {
        nuevoIndex = indexActivo === -1 ? 0 : (indexActivo + 1) % sugerencias.length;
    } else {
        nuevoIndex = indexActivo === -1 ? sugerencias.length - 1 : 
                    (indexActivo - 1 + sugerencias.length) % sugerencias.length;
    }
    
    // Activar nueva sugerencia
    sugerencias[nuevoIndex].classList.add('activa');
    sugerencias[nuevoIndex].scrollIntoView({ block: 'nearest' });
}

function seleccionarSugerenciaActiva() {
    const sugerenciaActiva = document.querySelector('.sugerencia-item.activa');
    if (sugerenciaActiva) {
        seleccionarUbicacionLocal(sugerenciaActiva);
    }
}

function obtenerIconoTipo(tipo) {
    const iconos = {
        'pueblo': 'fa-house-chimney',
        'ciudad': 'fa-city',
        'barrio': 'fa-map-pin',
        'centro': 'fa-map-marker-alt',
        'localidad': 'fa-location-dot',
        'calle': 'fa-road',
        'ruta': 'fa-route',
        'institucion': 'fa-building',
        'hospital': 'fa-hospital',
        'terminal': 'fa-bus',
        'plaza': 'fa-tree',
        'quebrada': 'fa-mountain',
        'cerro': 'fa-mountain',
        'rio': 'fa-water'
    };
    return iconos[tipo] || 'fa-map-marker-alt';
}

function obtenerColorTipo(tipo) {
    const colores = {
        'pueblo': '#ff9800',
        'ciudad': '#2196f3',
        'barrio': '#9c27b0',
        'centro': '#4caf50',
        'localidad': '#ff5722',
        'calle': '#795548',
        'ruta': '#f44336',
        'institucion': '#3f51b5',
        'hospital': '#e91e63',
        'terminal': '#ff9800',
        'plaza': '#2e7d32',
        'quebrada': '#795548',
        'cerro': '#607d8b',
        'rio': '#2196f3'
    };
    return colores[tipo] || '#82b16d';
}

// ============================================
// 5. MAPA INTERACTIVO
// ============================================

function inicializarMapaLaRioja() {
    console.log("🗺️ Inicializando mapa de La Rioja...");
    
    const contenedorMapa = document.getElementById('mapa-propiedad');
    if (!contenedorMapa) {
        console.error("❌ No se encuentra el contenedor del mapa");
        return;
    }
    
    // Verificar que Leaflet esté cargado
    if (typeof L === 'undefined') {
        console.error("❌ Leaflet no está cargado");
        mostrarErrorMapa('Leaflet no está cargado. Recarga la página.');
        return;
    }
    
    try {
        // Coordenadas centradas en La Rioja
        const centroLaRioja = [-29.1619, -67.4974]; // Chilecito como centro
        
        mapaLaRioja = L.map('mapa-propiedad', {
            center: centroLaRioja,
            zoom: 12,
            zoomControl: false, // Controlamos nosotros
            attributionControl: true,
            scrollWheelZoom: true,
            touchZoom: true,
            doubleClickZoom: true
        });
        
        // Capa base de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
            detectRetina: true
        }).addTo(mapaLaRioja);
        
        // Capa satelital
        const capaSatelital = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '© Esri',
            maxZoom: 19
        });
        
        // Capa de calles
        const capaCalles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
        
        // Control de capas
        const capasBase = {
            "Mapa": capaCalles,
            "Satélite": capaSatelital
        };
        L.control.layers(capasBase).addTo(mapaLaRioja);
        
        // Agregar marcadores de referencia
        agregarMarcadoresReferencia();
        
        // Evento para clic en el mapa
        mapaLaRioja.on('click', function(e) {
            if (!marcadorLaRioja) {
                crearMarcador(e.latlng.lat, e.latlng.lng);
            } else {
                marcadorLaRioja.setLatLng(e.latlng);
            }
            actualizarUbicacionDesdeClick(e.latlng.lat, e.latlng.lng);
        });
        
        // Hacer que el mapa sea responsivo
        setTimeout(() => {
            mapaLaRioja.invalidateSize();
        }, 100);
        
        console.log("✅ Mapa inicializado correctamente");
        
    } catch (error) {
        console.error('❌ Error inicializando mapa:', error);
        mostrarErrorMapa(error.message);
    }
}

function agregarMarcadoresReferencia() {
    if (!mapaLaRioja) return;
    
    // Marcador para Nonogasta (destacado)
    const iconoNonogasta = L.divIcon({
        className: 'marcador-referencia',
        html: `
            <div style="background: linear-gradient(135deg, #ff9800, #ff5722); 
                        color: white; width: 45px; height: 45px; border-radius: 50%; 
                        display: flex; align-items: center; justify-content: center; 
                        border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.3);
                        font-size: 10px; font-weight: bold;">
                NONO
            </div>
        `,
        iconSize: [45, 45],
        iconAnchor: [22.5, 45]
    });
    
    L.marker([-29.2833, -67.5000], {
        icon: iconoNonogasta
    }).addTo(mapaLaRioja)
    .bindPopup(`
        <div style="padding: 8px; min-width: 200px;">
            <h4 style="margin: 0 0 5px 0; color: #ff9800;">Nonogasta</h4>
            <p style="margin: 0; font-size: 12px;">Pueblo principal del sistema RentNono</p>
        </div>
    `);
    
    // Marcador para Chilecito
    const iconoChilecito = L.divIcon({
        className: 'marcador-referencia',
        html: `
            <div style="background: linear-gradient(135deg, #2196f3, #0d47a1); 
                        color: white; width: 45px; height: 45px; border-radius: 50%; 
                        display: flex; align-items: center; justify-content: center; 
                        border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.3);
                        font-size: 10px; font-weight: bold;">
                CHI
            </div>
        `,
        iconSize: [45, 45],
        iconAnchor: [22.5, 45]
    });
    
    L.marker([-29.1619, -67.4974], {
        icon: iconoChilecito
    }).addTo(mapaLaRioja)
    .bindPopup(`
        <div style="padding: 8px; min-width: 200px;">
            <h4 style="margin: 0 0 5px 0; color: #2196f3;">Chilecito</h4>
            <p style="margin: 0; font-size: 12px;">Ciudad principal del departamento</p>
        </div>
    `);
}

function crearMarcador(lat, lng) {
    if (!mapaLaRioja) return;
    
    // Remover marcador anterior si existe
    if (marcadorLaRioja) {
        mapaLaRioja.removeLayer(marcadorLaRioja);
    }
    
    // Icono personalizado para la propiedad
    const icono = L.divIcon({
        className: 'marcador-principal',
        html: `
            <div style="background: linear-gradient(135deg, #82b16d, #4a6fa5); 
                        color: white; width: 60px; height: 60px; border-radius: 50%; 
                        display: flex; align-items: center; justify-content: center; 
                        border: 4px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.3);
                        position: relative; animation: pulse 2s infinite; cursor: move;">
                <i class="fa-solid fa-home" style="font-size: 24px;"></i>
                <div style="position: absolute; bottom: -5px; right: -5px; background: #e74c3c; 
                            color: white; width: 24px; height: 24px; border-radius: 50%; 
                            display: flex; align-items: center; justify-content: center; 
                            font-size: 11px; border: 2px solid white; font-weight: bold;">
                    <i class="fa-solid fa-map-pin"></i>
                </div>
            </div>
        `,
        iconSize: [60, 60],
        iconAnchor: [30, 60],
        popupAnchor: [0, -60]
    });
    
    marcadorLaRioja = L.marker([lat, lng], {
        icon: icono,
        draggable: true,
        autoPan: true
    }).addTo(mapaLaRioja);
    
    // Popup informativo
    marcadorLaRioja.bindPopup(`
        <div style="padding: 12px; min-width: 280px;">
            <div style="color: #82b16d; font-weight: bold; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-map-marker-alt"></i>
                <span>Ubicación de la propiedad</span>
            </div>
            <div style="background: #f8f9fa; padding: 8px; border-radius: 4px; margin-bottom: 12px;">
                <div style="font-size: 11px; color: #666; margin-bottom: 4px;">
                    <i class="fa-solid fa-info-circle"></i> Arrastra el marcador para ajustar la ubicación exacta
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                <div style="background: #e3f2fd; padding: 6px; border-radius: 4px;">
                    <div style="color: #1565c0; font-weight: bold; margin-bottom: 2px;">Latitud</div>
                    <div style="font-family: monospace; color: #0d47a1;">${lat.toFixed(6)}</div>
                </div>
                <div style="background: #e8f5e9; padding: 6px; border-radius: 4px;">
                    <div style="color: #2e7d32; font-weight: bold; margin-bottom: 2px;">Longitud</div>
                    <div style="font-family: monospace; color: #1b5e20;">${lng.toFixed(6)}</div>
                </div>
            </div>
        </div>
    `).openPopup();
    
    // Evento al arrastrar
    marcadorLaRioja.on('dragend', function() {
        const pos = marcadorLaRioja.getLatLng();
        actualizarUbicacionDesdeClick(pos.lat, pos.lng);
        mapaLaRioja.panTo(pos);
    });
    
    // Centrar en el marcador
    mapaLaRioja.setView([lat, lng], 15);
    
    // Mostrar sección del mapa
    const seccionMapa = document.getElementById('seccion-mapa');
    const mensajeSinUbicacion = document.getElementById('mensaje-sin-ubicacion');
    
    if (seccionMapa) seccionMapa.style.display = 'block';
    if (mensajeSinUbicacion) mensajeSinUbicacion.style.display = 'none';
}

function seleccionarUbicacionLocal(elemento) {
    const lat = parseFloat(elemento.getAttribute('data-lat'));
    const lon = parseFloat(elemento.getAttribute('data-lon'));
    const nombre = elemento.getAttribute('data-nombre');
    
    // Actualizar campo de búsqueda
    const inputBusqueda = document.getElementById('buscar-direccion');
    if (inputBusqueda) {
        inputBusqueda.value = nombre;
    }
    
    // Ocultar sugerencias
    const listaSugerencias = document.getElementById('lista-sugerencias');
    if (listaSugerencias) {
        listaSugerencias.style.display = 'none';
    }
    
    // Mostrar en el mapa
    mostrarEnMapa(lat, lon, nombre);
}

function mostrarEnMapa(lat, lng, nombre) {
    if (!mapaLaRioja) {
        inicializarMapaLaRioja();
        setTimeout(() => {
            mostrarEnMapa(lat, lng, nombre);
        }, 500);
        return;
    }
    
    // Centrar en la ubicación
    const zoom = nombre.includes('Nonogasta') ? 14 : 15;
    mapaLaRioja.setView([lat, lng], zoom);
    
    // Crear o mover marcador
    if (!marcadorLaRioja) {
        crearMarcador(lat, lng);
    } else {
        marcadorLaRioja.setLatLng([lat, lng]);
        marcadorLaRioja.openPopup();
    }
    
    // Actualizar información
    actualizarUbicacion(lat, lng, nombre);
    
    // Mostrar mensaje de éxito
    const esNonogasta = nombre.includes('Nonogasta');
    mostrarToast(`Ubicación ${esNonogasta ? 'de Nonogasta' : ''} seleccionada correctamente`, 'success');
}

function actualizarUbicacionDesdeClick(lat, lng) {
    // Determinar el nombre basado en la ubicación
    let nombre = 'Ubicación personalizada';
    
    // Verificar distancia a puntos importantes
    const distanciaNonogasta = calcularDistancia(lat, lng, -29.2833, -67.5000);
    const distanciaChilecito = calcularDistancia(lat, lng, -29.1619, -67.4974);
    
    if (distanciaNonogasta < 2) { // Menos de 2km
        nombre = 'Nonogasta, La Rioja';
    } else if (distanciaChilecito < 5) { // Menos de 5km
        nombre = 'Chilecito, La Rioja';
    } else if (distanciaNonogasta < 10) {
        nombre = 'Cerca de Nonogasta, La Rioja';
    } else if (distanciaChilecito < 15) {
        nombre = 'Cerca de Chilecito, La Rioja';
    } else {
        nombre = `Ubicación (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
    }
    
    actualizarUbicacion(lat, lng, nombre);
}

function calcularDistancia(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radio de la Tierra en km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

function actualizarUbicacion(lat, lng, nombre) {
    console.log("📝 Actualizando ubicación:", nombre);
    
    // Campos ocultos del formulario
    const campos = {
        'direccion': nombre,
        'latitud': lat,
        'longitud': lng,
        'ciudad': extraerCiudad(nombre),
        'provincia': 'La Rioja'
    };
    
    Object.keys(campos).forEach(id => {
        const campo = document.getElementById(id);
        if (campo) {
            campo.value = campos[id];
        }
    });
    
    // Campos visibles en el mapa
    const textoUbicacion = document.getElementById('texto-ubicacion-mapa');
    const textoLat = document.getElementById('texto-latitud');
    const textoLon = document.getElementById('texto-longitud');
    
    if (textoUbicacion) textoUbicacion.textContent = nombre;
    if (textoLat) textoLat.textContent = lat.toFixed(6);
    if (textoLon) textoLon.textContent = lng.toFixed(6);
    
    // Validar que la ubicación esté completa
    validarUbicacion();
}

function extraerCiudad(direccion) {
    if (direccion.includes('Nonogasta')) return 'Nonogasta';
    if (direccion.includes('Chilecito')) return 'Chilecito';
    if (direccion.includes('Anguinán')) return 'Anguinán';
    if (direccion.includes('Los Sarmientos')) return 'Los Sarmientos';
    if (direccion.includes('La Puntilla')) return 'La Puntilla';
    return 'La Rioja';
}

function validarUbicacion() {
    const direccion = document.getElementById('direccion');
    const btnEnviar = document.getElementById('btnEnviarFormulario');
    
    if (direccion && direccion.value && btnEnviar) {
        btnEnviar.disabled = false;
        return true;
    }
    return false;
}

// ============================================
// 6. CONTROLES DEL MAPA
// ============================================

window.acercarMapa = function() {
    if (mapaLaRioja) {
        mapaLaRioja.zoomIn();
    }
};

window.alejarMapa = function() {
    if (mapaLaRioja) {
        mapaLaRioja.zoomOut();
    }
};

window.centrarMarcador = function() {
    if (mapaLaRioja && marcadorLaRioja) {
        const pos = marcadorLaRioja.getLatLng();
        mapaLaRioja.setView(pos, 16);
        marcadorLaRioja.openPopup();
    }
};

window.alternarVistaMapa = function() {
    if (mapaLaRioja) {
        // Cambiar entre vista normal y satélite
        const capas = mapaLaRioja._layers;
        let tieneSatelite = false;
        
        for (let key in capas) {
            if (capas[key]._url && capas[key]._url.includes('arcgisonline')) {
                tieneSatelite = true;
                break;
            }
        }
        
        if (tieneSatelite) {
            // Cambiar a mapa normal
            mapaLaRioja.eachLayer(function(layer) {
                if (layer._url && layer._url.includes('arcgisonline')) {
                    mapaLaRioja.removeLayer(layer);
                }
            });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapaLaRioja);
        } else {
            // Cambiar a satélite
            mapaLaRioja.eachLayer(function(layer) {
                if (layer._url && layer._url.includes('openstreetmap')) {
                    mapaLaRioja.removeLayer(layer);
                }
            });
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}').addTo(mapaLaRioja);
        }
    }
};

window.editarUbicacion = function() {
    // Limpiar campos
    const campos = ['direccion', 'latitud', 'longitud', 'ciudad', 'provincia'];
    campos.forEach(id => {
        const campo = document.getElementById(id);
        if (campo) campo.value = '';
    });
    
    const textos = ['texto-ubicacion-mapa', 'texto-latitud', 'texto-longitud'];
    textos.forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) elemento.textContent = '-';
    });
    
    const inputBusqueda = document.getElementById('buscar-direccion');
    if (inputBusqueda) inputBusqueda.value = '';
    
    // Remover marcador
    if (marcadorLaRioja && mapaLaRioja) {
        mapaLaRioja.removeLayer(marcadorLaRioja);
        marcadorLaRioja = null;
    }
    
    // Ocultar sección del mapa
    const seccionMapa = document.getElementById('seccion-mapa');
    const mensajeSinUbicacion = document.getElementById('mensaje-sin-ubicacion');
    
    if (seccionMapa) seccionMapa.style.display = 'none';
    if (mensajeSinUbicacion) mensajeSinUbicacion.style.display = 'block';
    
    // Deshabilitar botón de enviar
    const btnEnviar = document.getElementById('btnEnviarFormulario');
    if (btnEnviar) btnEnviar.disabled = true;
};

// ============================================
// 7. FILTROS Y TABLAS
// ============================================

function inicializarFiltros() {
    const filtroEstado = document.getElementById('filtroEstado');
    if (filtroEstado) {
        filtroEstado.addEventListener('change', filtrarPropiedades);
    }
}

function filtrarPropiedades() {
    const filtro = document.getElementById('filtroEstado').value;
    const filas = document.querySelectorAll('#tablaPropiedadesBody tr');
    
    filas.forEach(fila => {
        if (filtro === 'todas') {
            fila.style.display = '';
        } else {
            const estado = fila.getAttribute('data-estado');
            fila.style.display = estado === filtro ? '' : 'none';
        }
    });
}

// ============================================
// 8. FORMULARIO Y ENVÍO
// ============================================

function inicializarEventos() {
    const formulario = document.getElementById('formulario-propiedad');
    if (formulario) {
        formulario.addEventListener('submit', enviarFormulario);
    }
    
    // Botón para marcar todas las notificaciones como leídas
    const btnMarcarTodas = document.getElementById('btnMarcarTodasLeidas');
    if (btnMarcarTodas) {
        btnMarcarTodas.addEventListener('click', marcarTodasLeidas);
    }
}

async function enviarFormulario(e) {
    e.preventDefault();
    
    const formulario = e.target;
    const btnEnviar = document.getElementById('btnEnviarFormulario');
    
    if (!formulario || !btnEnviar) return;
    
    // Validaciones
    if (!validarFormulario()) {
        return false;
    }
    
    // Confirmación
    if (!confirm('¿Estás seguro de enviar la solicitud de propiedad?')) {
        return false;
    }
    
    // Mostrar loading
    const originalText = btnEnviar.innerHTML;
    btnEnviar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';
    btnEnviar.disabled = true;
    formulario.classList.add('formulario-loading');
    
    try {
        const formData = new FormData(formulario);
        
        // Enviar al servidor
        const response = await fetch('../database/guardar_propiedad.php', {
            method: 'POST',
            body: formData
        });
        
        const resultado = await response.json();
        
        if (resultado.success) {
            mostrarToast('✅ ' + resultado.message, 'success');
            
            // Resetear formulario
            formulario.reset();
            editarUbicacion();
            
            // Resetear imágenes
            const gridImagenes = document.getElementById('gridImagenes');
            if (gridImagenes) gridImagenes.innerHTML = '';
            
            const contadorSeleccionadas = document.getElementById('contadorSeleccionadas');
            if (contadorSeleccionadas) contadorSeleccionadas.textContent = '0';
            
            // Volver al inicio
            setTimeout(() => {
                mostrarSeccion('inicio');
            }, 1500);
            
        } else {
            mostrarToast('❌ Error: ' + (resultado.error || 'Error desconocido'), 'error');
        }
        
    } catch (error) {
        console.error('Error:', error);
        mostrarToast('❌ Error de conexión con el servidor', 'error');
        
    } finally {
        btnEnviar.innerHTML = originalText;
        btnEnviar.disabled = false;
        formulario.classList.remove('formulario-loading');
    }
}

function validarFormulario() {
    let errores = [];
    
    // Validar título
    const titulo = document.getElementById('titulo');
    if (!titulo.value.trim()) {
        errores.push('El título es obligatorio');
        titulo.classList.add('error');
    } else {
        titulo.classList.remove('error');
    }
    
    // Validar descripción
    const descripcion = document.getElementById('descripcion');
    if (!descripcion.value.trim()) {
        errores.push('La descripción es obligatoria');
        descripcion.classList.add('error');
    } else {
        descripcion.classList.remove('error');
    }
    
    // Validar ubicación
    const direccion = document.getElementById('direccion');
    if (!direccion.value) {
        errores.push('Debes seleccionar una ubicación');
    }
    
    // Validar imágenes
    const inputImagenes = document.getElementById('imagenes');
    if (!inputImagenes || inputImagenes.files.length === 0) {
        errores.push('Debes subir al menos una imagen');
    }
    
    // Mostrar errores si los hay
    if (errores.length > 0) {
        mostrarToast(errores.join('<br>'), 'error');
        return false;
    }
    
    return true;
}

// ============================================
// 9. NOTIFICACIONES TOAST
// ============================================

function mostrarToast(mensaje, tipo = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    // Crear toast
    const toast = document.createElement('div');
    toast.className = 'toast-content';
    
    // Icono según tipo
    let icono = 'fa-info-circle';
    if (tipo === 'success') icono = 'fa-check-circle';
    if (tipo === 'error') icono = 'fa-exclamation-circle';
    if (tipo === 'warning') icono = 'fa-exclamation-triangle';
    
    toast.innerHTML = `
        <div class="icono-toast" style="color: ${tipo === 'success' ? '#28a745' : tipo === 'error' ? '#dc3545' : '#ffc107'}">
            <i class="fa-solid ${icono}"></i>
        </div>
        <div class="texto-toast">${mensaje}</div>
        <button class="cerrar-toast" onclick="this.parentElement.remove()">
            <i class="fa-solid fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// ============================================
// 10. FUNCIONES AUXILIARES
// ============================================

function mostrarErrorMapa(mensaje) {
    const contenedorMapa = document.getElementById('mapa-propiedad');
    if (contenedorMapa) {
        contenedorMapa.innerHTML = `
            <div style="padding: 30px; text-align: center; background: #ffeaea; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; border-radius: 12px;">
                <div style="color: #d32f2f; font-size: 48px; margin-bottom: 20px;">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                </div>
                <h4 style="color: #b71c1c; margin-bottom: 10px;">Error al cargar el mapa</h4>
                <p style="color: #d32f2f; margin-bottom: 20px; max-width: 300px;">${mensaje || 'Error desconocido'}</p>
                <button onclick="inicializarMapaLaRioja()" 
                        style="background: #d32f2f; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-redo"></i> Reintentar
                </button>
            </div>
        `;
    }
}

// Funciones para probar el sistema
window.probarNonogasta = function() {
    mostrarSeccion('formulario');
    setTimeout(() => {
        const input = document.getElementById('buscar-direccion');
        if (input) {
            input.value = 'Nonogasta';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }, 500);
};

window.probarChilecito = function() {
    mostrarSeccion('formulario');
    setTimeout(() => {
        const input = document.getElementById('buscar-direccion');
        if (input) {
            input.value = 'Chilecito';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }, 500);
};

// ============================================
// INICIALIZACIÓN FINAL
// ============================================

// Agregar estilos dinámicos si no existen
if (!document.getElementById('estilos-dinamicos')) {
    const estilos = document.createElement('style');
    estilos.id = 'estilos-dinamicos';
    estilos.textContent = `
        .error {
            border-color: #dc3545 !important;
            background-color: #fff5f5 !important;
        }
        
        .marcador-principal {
            animation: pulse 2s infinite;
        }
        
        .icono-toast {
            font-size: 20px;
        }
        
        .texto-toast {
            flex: 1;
            font-size: 14px;
        }
        
        .cerrar-toast {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
        }
        
        .cerrar-toast:hover {
            background: rgba(0,0,0,0.1);
        }
        
        .ver-mapa {
            color: #3498db;
            cursor: pointer;
            font-size: 12px;
            margin-top: 4px;
            display: block;
        }
        
        .ver-mapa:hover {
            text-decoration: underline;
        }
        
        .modal-mapa {
            max-width: 800px;
            width: 90%;
        }
        
        .info-mapa-modal {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        #mapa-modal {
            margin-bottom: 15px;
        }
    `;
    document.head.appendChild(estilos);
}

// Verificación final
setTimeout(() => {
    console.log("=== ✅ SISTEMA RENTNONO LISTO ===");
    console.log("🎯 Enfoque en: Nonogasta y Chilecito, La Rioja");
    console.log("🗺️  Sistema de mapas: Listo");
    console.log("🖼️  Subida de imágenes: Listo");
    console.log("🔍  Buscador de ubicaciones: Listo");
    console.log("📊 " + ubicacionesLaRioja.length + " ubicaciones disponibles");
    console.log("💡 Usa probarNonogasta() o probarChilecito() para probar");
    console.log("=================================");
}, 1000);