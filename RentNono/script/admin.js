document.addEventListener("DOMContentLoaded", () => {
    // ----------------
    // Sistema de Notificaciones
    // ----------------
    function mostrarNotificacion(mensaje, tipo = 'exito', duracion = 4000) {
        const notificacion = document.createElement('div');
        notificacion.className = `notificacion ${tipo}`;
        notificacion.innerHTML = `
            <i class="fa-solid ${tipo === 'exito' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i>
            <span>${mensaje}</span>
        `;
        
        document.body.appendChild(notificacion);
        
        setTimeout(() => {
            notificacion.style.opacity = '0';
            setTimeout(() => {
                if (notificacion.parentNode) notificacion.remove();
            }, 300);
        }, duracion);
    }

    if (new URLSearchParams(window.location.search).has('edit')) {
        mostrarNotificacion('Usuario editado correctamente', 'exito');
    }

    // ----------------
    // Gestión de Secciones
    // ----------------
    function mostrarSeccion(id) {
        // Ocultar todas
        document.querySelectorAll(".seccion").forEach(s => {
            s.classList.remove("visible");
        });
        
        // Mostrar seleccionada
        const el = document.getElementById(id);
        if (el) el.classList.add("visible");
        
        // Actualizar botones
        document.querySelectorAll(".menu-btn").forEach(b => b.classList.remove("activo"));
        const btn = document.querySelector(`.menu-btn[data-seccion="${id}"]`);
        if (btn) btn.classList.add("activo");
        
        // Manejar menú usuarios
        if (id === 'usuarios') {
            const btnUsuarios = document.getElementById("btnUsuarios");
            if (btnUsuarios) btnUsuarios.classList.add("activo");
        } else {
            const btnUsuarios = document.getElementById("btnUsuarios");
            if (btnUsuarios) btnUsuarios.classList.remove("activo");
        }
        
        // Actualizar URL
        window.history.pushState({}, '', window.location.pathname + '?seccion=' + id);
    }

    document.querySelectorAll(".menu-btn[data-seccion]").forEach(btn => {
        if (btn.id !== 'btnUsuarios' && btn.id !== 'btnLogout') {
            btn.addEventListener("click", () => {
                mostrarSeccion(btn.dataset.seccion);
            });
        }
    });

    // ----------------
    // MENÚ DESPLEGABLE DE USUARIOS
    // ----------------
    function inicializarMenuUsuarios() {
        const btnUsuarios = document.getElementById("btnUsuarios");
        const submenu = document.getElementById("submenuUsuarios");
        
        if (!btnUsuarios || !submenu) return;
        
        let menuAbierto = false;
        
        // Abrir/cerrar menú
        function toggleMenu() {
            menuAbierto = !menuAbierto;
            
            if (menuAbierto) {
                submenu.classList.add("abierto");
                submenu.style.maxHeight = submenu.scrollHeight + "px";
                btnUsuarios.classList.add("activo");
            } else {
                submenu.classList.remove("abierto");
                submenu.style.maxHeight = "0";
                btnUsuarios.classList.remove("activo");
            }
        }
        
        // Botón principal
        btnUsuarios.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
            
            // Si no estamos en usuarios, ir a esa sección
            if (!window.location.href.includes('seccion=usuarios')) {
                mostrarSeccion('usuarios');
                // Mostrar tabla por defecto
                setTimeout(() => mostrarTablaUsuarios('admins'), 100);
            }
        });
        
        // Opciones del submenú
        document.querySelectorAll(".submenu-btn").forEach(btn => {
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                const tablaId = this.dataset.tabla;
                
                // Cerrar menú en móvil
                if (window.innerWidth <= 768) {
                    toggleMenu();
                }
                
                // Mostrar tabla
                mostrarTablaUsuarios(tablaId);
                
                // Actualizar URL
                const url = new URL(window.location);
                url.searchParams.set('tabla', tablaId);
                window.history.pushState({}, '', url.toString());
            });
        });
        
        // Cerrar al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!btnUsuarios.contains(e.target) && !submenu.contains(e.target)) {
                if (menuAbierto) {
                    menuAbierto = false;
                    submenu.classList.remove("abierto");
                    submenu.style.maxHeight = "0";
                    btnUsuarios.classList.remove("activo");
                }
            }
        });
        
        // Responsive
        function ajustarMenu() {
            if (window.innerWidth <= 768) {
                submenu.style.transition = 'max-height 0.3s ease';
                if (!menuAbierto) {
                    submenu.style.maxHeight = "0";
                }
            } else {
                submenu.style.transition = '';
                submenu.style.maxHeight = "";
            }
        }
        
        ajustarMenu();
        window.addEventListener('resize', ajustarMenu);
    }

    // ----------------
    // Gestión de Tablas de Usuarios
    // ----------------
    function mostrarTablaUsuarios(tablaId) {
        // Ocultar todas
        document.querySelectorAll('.contenedor-tabla-usuarios').forEach(contenedor => {
            contenedor.style.display = 'none';
        });
        
        // Mostrar seleccionada
        const contenedorId = 'contenedor' + tablaId.charAt(0).toUpperCase() + tablaId.slice(1);
        const contenedor = document.getElementById(contenedorId);
        
        if (contenedor) {
            contenedor.style.display = 'block';
            
            // Actualizar título
            const titulo = document.getElementById("tituloUsuarios");
            if (titulo) {
                const nombres = {
                    'admins': 'Administradores',
                    'propietarios': 'Propietarios',
                    'visitantes': 'Visitantes'
                };
                titulo.textContent = `Usuarios - ${nombres[tablaId] || tablaId}`;
            }
            
            // Marcar botón activo
            document.querySelectorAll(".submenu .submenu-btn").forEach(x => x.classList.remove("activo"));
            const btnActivo = document.querySelector(`.submenu-btn[data-tabla="${tablaId}"]`);
            if (btnActivo) btnActivo.classList.add("activo");
            
            // Inicializar buscador
            setTimeout(() => inicializarBuscadorParaTabla(tablaId), 50);
        }
    }

    // Mostrar tabla inicial
    const tablaActiva = new URLSearchParams(window.location.search).get('tabla') || 'admins';
    if (window.location.href.includes('seccion=usuarios')) {
        setTimeout(() => mostrarTablaUsuarios(tablaActiva), 100);
    }

    // ----------------
    // FILTROS DE LOGS (SIN NOTIFICACIÓN)
    // ----------------
    function inicializarFiltrosLogs() {
        const filterToday = document.getElementById('filterToday');
        const clearFilters = document.getElementById('clearFilters');
        const searchLogs = document.getElementById('searchLogs');
        const tablaLogs = document.getElementById('tablaLogs');
        
        if (!tablaLogs) return;
        
        const filasOriginales = Array.from(tablaLogs.querySelectorAll('tbody tr'));
        
        // Aplicar filtros
        function aplicarFiltros() {
            const hoy = new Date();
            const hoyMySQL = hoy.toISOString().split('T')[0];
            const hoyLegible = hoy.toLocaleDateString('es-ES');
            const textoBusqueda = searchLogs ? searchLogs.value.toLowerCase() : '';
            const mostrarSoloHoy = filterToday && filterToday.classList.contains('active');
            
            filasOriginales.forEach(fila => {
                let mostrar = true;
                
                // Filtro "hoy"
                if (mostrarSoloHoy) {
                    const fechaCelda = fila.cells[3].textContent.trim();
                    const esHoy = fechaCelda === hoyMySQL || fechaCelda === hoyLegible;
                    if (!esHoy) mostrar = false;
                }
                
                // Filtro búsqueda
                if (mostrar && textoBusqueda) {
                    const textoFila = fila.textContent.toLowerCase();
                    if (!textoFila.includes(textoBusqueda)) mostrar = false;
                }
                
                fila.style.display = mostrar ? '' : 'none';
            });
        }
        
        // Botón "Hoy" - SIN NOTIFICACIÓN
        if (filterToday) {
            filterToday.addEventListener('click', () => {
                filterToday.classList.toggle('active');
                aplicarFiltros(); // Solo aplicar, sin notificación
            });
        }
        
        // Botón "Limpiar"
        if (clearFilters) {
            clearFilters.addEventListener('click', () => {
                // Desactivar "hoy"
                if (filterToday) {
                    filterToday.classList.remove('active');
                }
                
                // Limpiar búsqueda
                if (searchLogs) {
                    searchLogs.value = '';
                }
                
                // Mostrar todas las filas
                filasOriginales.forEach(fila => {
                    fila.style.display = '';
                });
            });
        }
        
        // Buscador
        if (searchLogs) {
            searchLogs.addEventListener('input', () => {
                aplicarFiltros();
            });
        }
    }

    // ----------------
    // Modal Editar
    // ----------------
    const modalEditar = document.getElementById("modalEditar");
    if (modalEditar) {
        document.addEventListener('click', (e) => {
            if (e.target.closest('.editarBtn')) {
                const btn = e.target.closest('.editarBtn');
                const id = btn.dataset.id;
                const rol = btn.dataset.rol;
                const fila = btn.closest("tr");
                const nombre = fila.cells[0].innerText.trim();
                const correo = fila.cells[1].innerText.trim();

                const inputId = document.getElementById("editId");
                const inputRol = document.getElementById("editRol");
                const inputNombre = document.getElementById("editNombre");
                const inputCorreo = document.getElementById("editCorreo");
                const displayRol = document.getElementById("displayRol");

                if (inputId && inputRol && inputNombre && inputCorreo && displayRol) {
                    inputId.value = id;
                    inputRol.value = rol;
                    inputNombre.value = nombre;
                    inputCorreo.value = correo;
                    displayRol.textContent = rol === 'admin' ? 'Administrador' : 
                                           rol === 'propietario' ? 'Propietario' : 'Visitante';

                    modalEditar.style.display = "flex";
                }
            }
        });

        // Cerrar modal
        modalEditar.querySelectorAll('.cerrar, .btn-cancelar').forEach(btn => {
            btn.addEventListener('click', () => {
                modalEditar.style.display = "none";
            });
        });
        
        window.addEventListener("click", (e) => { 
            if (e.target === modalEditar) modalEditar.style.display = "none"; 
        });
    }

    // ----------------
    // Estado de Usuarios
    // ----------------
    document.querySelectorAll('.toggle-estado').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const id = this.dataset.id;
            const rol = this.dataset.rol;
            const estado = this.checked ? 1 : 0;
            const estadoTexto = this.parentElement.querySelector('.estado-texto');
            
            if (estadoTexto) {
                estadoTexto.textContent = estado ? 'Activo' : 'Inactivo';
            }
            
            const formData = new FormData();
            formData.append('accion', 'cambiar_estado');
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('id', id);
            formData.append('rol', rol);
            formData.append('estado', estado);
            
            fetch('indexadmin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarNotificacion(`Estado actualizado`, 'exito');
                } else {
                    mostrarNotificacion('Error al cambiar estado', 'error');
                    this.checked = !this.checked;
                    if (estadoTexto) {
                        estadoTexto.textContent = this.checked ? 'Activo' : 'Inactivo';
                    }
                }
            });
        });
    });

    // ----------------
    // Eliminación Visual
    // ----------------
    let filaAEliminar = null;
    let nombreAEliminar = '';
    
    document.querySelectorAll(".eliminarBtn").forEach(b => {
        b.addEventListener("click", (e) => {
            e.preventDefault();
            filaAEliminar = b.closest("tr");
            nombreAEliminar = b.dataset.nombre || filaAEliminar.cells[0].innerText.trim();
            
            const textoConfirmacion = document.getElementById("textoConfirmacion");
            if (textoConfirmacion) {
                textoConfirmacion.innerHTML = `¿Estás seguro de que quieres eliminar a <strong>"${nombreAEliminar}"</strong>?<br><small>Esta acción solo eliminará el usuario de la vista.</small>`;
            }
            
            const modalConfirmarEliminar = document.getElementById("modalConfirmarEliminar");
            if (modalConfirmarEliminar) {
                modalConfirmarEliminar.style.display = "flex";
            }
        });
    });

    const confirmarEliminar = document.getElementById("confirmarEliminar");
    const cancelarEliminar = document.getElementById("cancelarEliminar");
    
    if (confirmarEliminar) {
        confirmarEliminar.addEventListener("click", () => {
            if (filaAEliminar) {
                filaAEliminar.style.opacity = '0.5';
                filaAEliminar.style.transform = 'translateX(20px)';
                filaAEliminar.style.transition = 'all 0.4s ease';
                
                setTimeout(() => {
                    filaAEliminar.style.display = "none";
                    mostrarNotificacion(`Usuario "${nombreAEliminar}" eliminado de la vista`, 'exito');
                    
                    filaAEliminar = null;
                    nombreAEliminar = '';
                }, 400);
            }
            
            const modalConfirmarEliminar = document.getElementById("modalConfirmarEliminar");
            if (modalConfirmarEliminar) {
                modalConfirmarEliminar.style.display = "none";
            }
        });
    }

    if (cancelarEliminar) {
        cancelarEliminar.addEventListener("click", () => {
            const modalConfirmarEliminar = document.getElementById("modalConfirmarEliminar");
            if (modalConfirmarEliminar) {
                modalConfirmarEliminar.style.display = "none";
            }
            filaAEliminar = null;
            nombreAEliminar = '';
        });
    }

    // ----------------
    // Cerrar Sesión
    // ----------------
    const btnLogout = document.getElementById("btnLogout");
    if (btnLogout) {
        btnLogout.addEventListener("click", (e) => {
            e.preventDefault();
            const modalConfirmarLogout = document.getElementById("modalConfirmarLogout");
            if (modalConfirmarLogout) {
                modalConfirmarLogout.style.display = "flex";
            }
        });
    }

    const confirmarLogout = document.getElementById("confirmarLogout");
    const cancelarLogout = document.getElementById("cancelarLogout");
    
    if (confirmarLogout) {
        confirmarLogout.addEventListener("click", () => {
            window.location.href = '../database/logout.php';
        });
    }
    
    if (cancelarLogout) {
        cancelarLogout.addEventListener("click", () => {
            const modalConfirmarLogout = document.getElementById("modalConfirmarLogout");
            if (modalConfirmarLogout) {
                modalConfirmarLogout.style.display = "none";
            }
        });
    }

    // Cerrar modales al hacer click fuera
    window.addEventListener("click", (e) => { 
        if (e.target.classList.contains('modal')) {
            e.target.style.display = "none";
        }
    });

    // ----------------
    // Buscador en Tiempo Real
    // ----------------
    function inicializarBuscadorParaTabla(tablaId) {
        const contenedorId = 'contenedor' + tablaId.charAt(0).toUpperCase() + tablaId.slice(1);
        const contenedor = document.getElementById(contenedorId);
        if (!contenedor) return;
        
        const buscador = contenedor.querySelector('.buscador-tabla');
        if (!buscador) return;
        
        const tabla = contenedor.querySelector('table');
        const filas = tabla.querySelectorAll('tbody tr');
        
        buscador.addEventListener('input', (e) => {
            const texto = e.target.value.toLowerCase();
            
            filas.forEach(fila => {
                const textoFila = fila.textContent.toLowerCase();
                fila.style.display = textoFila.includes(texto) ? '' : 'none';
            });
        });
    }

    // Inicializar buscadores
    inicializarBuscadorParaTabla('admins');
    inicializarBuscadorParaTabla('propietarios');
    inicializarBuscadorParaTabla('visitantes');

    // ----------------
    // Actualizar hora en tiempo real
    // ----------------
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        const dateString = now.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = dateString + ' - ' + timeString;
        }
    }
    
    updateTime();
    setInterval(updateTime, 1000);

    // ----------------
    // INICIALIZACIÓN FINAL
    // ----------------
    inicializarMenuUsuarios();
    inicializarFiltrosLogs();
    
    console.log('Panel admin inicializado correctamente');
});

// =====================================================
// FUNCIONES GLOBALES PARA PAGINACIÓN - VERSIÓN CORREGIDA
// =====================================================

function cambiarPagina(tipo, nuevaPagina) {
    const tbody = document.getElementById(`tbody${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
    const paginaActualElement = document.getElementById(`paginaActual${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
    
    if (!tbody || !paginaActualElement) return;
    
    const paginaActual = parseInt(paginaActualElement.textContent);
    if (nuevaPagina === paginaActual) return;
    
    // Guardar HTML original
    const oldHTML = tbody.innerHTML;
    
    // Mostrar loading
    tbody.innerHTML = `
        <tr>
            <td colspan="5" style="text-align: center; padding: 40px;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.5em; color: #82b16d;"></i>
                <p style="margin-top: 10px; color: #666;">Cargando página ${nuevaPagina}...</p>
            </td>
        </tr>
    `;
    
    // Deshabilitar botones temporalmente
    const contenedorId = 'contenedor' + tipo.charAt(0).toUpperCase() + tipo.slice(1);
    const contenedor = document.getElementById(contenedorId);
    if (contenedor) {
        const botones = contenedor.querySelectorAll('.pagina-btn');
        botones.forEach(btn => {
            const oldOnclick = btn.onclick;
            btn.setAttribute('data-old-onclick', btn.onclick ? 'has' : 'none');
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'wait';
        });
    }
    
    // Realizar petición AJAX
    const formData = new FormData();
    formData.append('accion', 'cambiar_pagina');
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    formData.append('tipo', tipo);
    formData.append('pagina', nuevaPagina);
    
    fetch('indexadmin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Error en la respuesta');
        return response.text();
    })
    .then(html => {
        if (html.trim()) {
            tbody.innerHTML = html;
            paginaActualElement.textContent = nuevaPagina;
            
            // Actualizar estado de botones
            actualizarEstadoBotonesPaginacion(tipo, nuevaPagina);
            
            // Re-inicializar eventos
            reinicializarEventosTabla(tbody);
        } else {
            throw new Error('Respuesta vacía');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        tbody.innerHTML = oldHTML;
    })
    .finally(() => {
        // Rehabilitar botones
        const contenedor = document.getElementById(contenedorId);
        if (contenedor) {
            const botones = contenedor.querySelectorAll('.pagina-btn');
            botones.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '';
                btn.style.cursor = '';
            });
        }
    });
}

// Función para actualizar estado de botones de paginación
function actualizarEstadoBotonesPaginacion(tipo, paginaActual) {
    const contenedorId = 'contenedor' + tipo.charAt(0).toUpperCase() + tipo.slice(1);
    const contenedor = document.getElementById(contenedorId);
    if (!contenedor) return;
    
    // Encontrar el total de páginas
    const infoPagina = contenedor.querySelector('.info-pagina');
    if (!infoPagina) return;
    
    const texto = infoPagina.textContent;
    const match = texto.match(/de (\d+)/);
    const totalPaginas = match ? parseInt(match[1]) : 1;
    
    // Botón anterior (primero)
    const btnAnterior = contenedor.querySelector('.pagina-btn.small:first-child');
    if (btnAnterior) {
        if (paginaActual <= 1) {
            btnAnterior.classList.add('disabled');
            btnAnterior.disabled = true;
            btnAnterior.onclick = null;
            btnAnterior.style.cursor = 'not-allowed';
        } else {
            btnAnterior.classList.remove('disabled');
            btnAnterior.disabled = false;
            btnAnterior.onclick = () => cambiarPagina(tipo, paginaActual - 1);
            btnAnterior.style.cursor = 'pointer';
        }
    }
    
    // Botón siguiente (último)
    const btnSiguiente = contenedor.querySelector('.pagina-btn.small:last-child');
    if (btnSiguiente) {
        if (paginaActual >= totalPaginas) {
            btnSiguiente.classList.add('disabled');
            btnSiguiente.disabled = true;
            btnSiguiente.onclick = null;
            btnSiguiente.style.cursor = 'not-allowed';
        } else {
            btnSiguiente.classList.remove('disabled');
            btnSiguiente.disabled = false;
            btnSiguiente.onclick = () => cambiarPagina(tipo, paginaActual + 1);
            btnSiguiente.style.cursor = 'pointer';
        }
    }
}

// Función para re-inicializar eventos después de cargar nueva página
function reinicializarEventosTabla(tbody) {
    // Re-inicializar toggles de estado
    tbody.querySelectorAll('.toggle-estado').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const id = this.dataset.id;
            const rol = this.dataset.rol;
            const estado = this.checked ? 1 : 0;
            const estadoTexto = this.parentElement.querySelector('.estado-texto');
            
            if (estadoTexto) {
                estadoTexto.textContent = estado ? 'Activo' : 'Inactivo';
            }
            
            const formData = new FormData();
            formData.append('accion', 'cambiar_estado');
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('id', id);
            formData.append('rol', rol);
            formData.append('estado', estado);
            
            fetch('indexadmin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Notificación opcional
                }
            });
        });
    });
    
    // Re-inicializar botones de editar
    tbody.querySelectorAll('.editarBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const rol = this.dataset.rol;
            const fila = this.closest("tr");
            const nombre = fila.cells[0].innerText.trim();
            const correo = fila.cells[1].innerText.trim();

            const inputId = document.getElementById("editId");
            const inputRol = document.getElementById("editRol");
            const inputNombre = document.getElementById("editNombre");
            const inputCorreo = document.getElementById("editCorreo");
            const displayRol = document.getElementById("displayRol");

            if (inputId && inputRol && inputNombre && inputCorreo && displayRol) {
                inputId.value = id;
                inputRol.value = rol;
                inputNombre.value = nombre;
                inputCorreo.value = correo;
                displayRol.textContent = rol === 'admin' ? 'Administrador' : 
                                       rol === 'propietario' ? 'Propietario' : 'Visitante';

                const modalEditar = document.getElementById("modalEditar");
                if (modalEditar) {
                    modalEditar.style.display = "flex";
                }
            }
        });
    });
    
    // Re-inicializar botones de eliminar
    tbody.querySelectorAll('.eliminarBtn').forEach(b => {
        b.addEventListener("click", (e) => {
            e.preventDefault();
            const filaAEliminar = b.closest("tr");
            const nombreAEliminar = b.dataset.nombre || filaAEliminar.cells[0].innerText.trim();
            
            const textoConfirmacion = document.getElementById("textoConfirmacion");
            if (textoConfirmacion) {
                textoConfirmacion.innerHTML = `¿Estás seguro de que quieres eliminar a <strong>"${nombreAEliminar}"</strong>?<br><small>Esta acción solo eliminará el usuario de la vista.</small>`;
            }
            
            const modalConfirmarEliminar = document.getElementById("modalConfirmarEliminar");
            if (modalConfirmarEliminar) {
                modalConfirmarEliminar.style.display = "flex";
            }
        });
    });
}