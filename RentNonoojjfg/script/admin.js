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
        
        // Actualizar botones del menú principal
        document.querySelectorAll(".menu-btn[data-seccion]").forEach(b => {
            if (b.dataset.seccion) {
                b.classList.remove("activo");
                if (b.dataset.seccion === id) {
                    b.classList.add("activo");
                }
            }
        });
        
        // Manejar menú usuarios
        if (id === 'usuarios') {
            const btnUsuarios = document.getElementById("btnUsuarios");
            if (btnUsuarios) btnUsuarios.classList.add("activo");
        } else {
            const btnUsuarios = document.getElementById("btnUsuarios");
            if (btnUsuarios) btnUsuarios.classList.remove("activo");
        }
        
        // Actualizar URL
        const url = new URL(window.location);
        url.searchParams.set('seccion', id);
        window.history.pushState({}, '', url.toString());
    }

    // Inicializar botones del menú principal
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
                url.searchParams.set('seccion', 'usuarios');
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
        
        // Ocultar sección logs si está visible
        const seccionLogs = document.getElementById('logs');
        if (seccionLogs) {
            seccionLogs.classList.remove('visible');
        }
        
        // Mostrar sección usuarios
        const seccionUsuarios = document.getElementById('usuarios');
        if (seccionUsuarios) {
            seccionUsuarios.classList.add('visible');
        }
        
        // Si es logs, mostrar sección logs
        if (tablaId === 'logs') {
            if (seccionLogs) {
                seccionLogs.classList.add('visible');
            }
            if (seccionUsuarios) {
                seccionUsuarios.classList.remove('visible');
            }
            
            // Actualizar título
            const titulo = document.getElementById("tituloUsuarios");
            if (titulo) {
                titulo.textContent = 'Usuarios - Logs';
            }
            
            // Marcar botón activo
            document.querySelectorAll(".submenu .submenu-btn").forEach(x => x.classList.remove("activo"));
            const btnActivo = document.querySelector(`.submenu-btn[data-tabla="${tablaId}"]`);
            if (btnActivo) btnActivo.classList.add("activo");
            
            return;
        }
        
        // Mostrar tabla seleccionada
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
            
            // Mostrar/ocultar botón de agregar usuario
            const btnAgregar = document.getElementById('btnAgregarUsuario');
            if (btnAgregar) {
                btnAgregar.style.display = 'flex';
            }
            
            // Inicializar buscador
            setTimeout(() => inicializarBuscadorParaTabla(tablaId), 50);
        }
    }

    // Mostrar tabla inicial basada en URL
    const urlParams = new URLSearchParams(window.location.search);
    const seccionActual = urlParams.get('seccion') || 'inicio';
    const tablaActual = urlParams.get('tabla') || 'admins';
    
    // Mostrar sección inicial
    if (seccionActual === 'usuarios') {
        setTimeout(() => mostrarTablaUsuarios(tablaActual), 100);
    }

    // ----------------
    // FILTROS DE LOGS
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
        
        // Botón "Hoy"
        if (filterToday) {
            filterToday.addEventListener('click', () => {
                filterToday.classList.toggle('active');
                aplicarFiltros();
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
    // 1. MODAL PARA AGREGAR NUEVO USUARIO
    // ----------------
    function inicializarModalAgregarUsuario() {
        const btnAgregarUsuario = document.getElementById('btnAgregarUsuario');
        const modalAgregarUsuario = document.getElementById('modalAgregarUsuario');
        const cancelarAgregarUsuario = document.getElementById('cancelarAgregarUsuario');
        const cerrarModalAgregar = modalAgregarUsuario?.querySelector('.cerrar');
        const formAgregarUsuario = document.getElementById('formAgregarUsuario');
        
        if (!btnAgregarUsuario || !modalAgregarUsuario) return;
        
        // Abrir modal
        btnAgregarUsuario.addEventListener('click', () => {
            modalAgregarUsuario.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        });
        
        // Cerrar modal
        function cerrarModal() {
            modalAgregarUsuario.style.display = 'none';
            document.body.style.overflow = '';
            formAgregarUsuario.reset();
            limpiarErroresAgregar();
        }
        
        if (cancelarAgregarUsuario) {
            cancelarAgregarUsuario.addEventListener('click', cerrarModal);
        }
        
        if (cerrarModalAgregar) {
            cerrarModalAgregar.addEventListener('click', cerrarModal);
        }
        
        // Cerrar al hacer click fuera
        modalAgregarUsuario.addEventListener('click', (e) => {
            if (e.target === modalAgregarUsuario) {
                cerrarModal();
            }
        });
        
        // Limpiar mensajes de error
        function limpiarErroresAgregar() {
            document.querySelectorAll('#formAgregarUsuario .form-error').forEach(error => {
                error.textContent = '';
                error.classList.remove('active');
            });
        }
        
        // Mostrar errores específicos
        function mostrarErrorAgregar(campoId, mensaje) {
            const errorElement = document.getElementById(`errorAgregar${campoId}`);
            if (errorElement) {
                errorElement.textContent = mensaje;
                errorElement.classList.add('active');
            }
        }
        
        // Validar formulario
        function validarFormularioAgregar(formData) {
            let valido = true;
            limpiarErroresAgregar();
            
            // Validar nombre
            const nombre = formData.get('nombre')?.trim();
            if (!nombre || nombre.length < 2) {
                mostrarErrorAgregar('Nombre', 'El nombre debe tener al menos 2 caracteres');
                valido = false;
            }
            
            // Validar correo
            const correo = formData.get('correo')?.trim();
            if (!correo) {
                mostrarErrorAgregar('Correo', 'El correo es requerido');
                valido = false;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
                mostrarErrorAgregar('Correo', 'Correo electrónico inválido');
                valido = false;
            }
            
            // Validar rol
            if (!formData.get('rol')) {
                mostrarErrorAgregar('Rol', 'Selecciona un tipo de usuario');
                valido = false;
            }
            
            // Validar contraseña
            const password = formData.get('password');
            if (!password || password.length < 8) {
                mostrarErrorAgregar('Password', 'La contraseña debe tener al menos 8 caracteres');
                valido = false;
            }
            
            // Validar confirmación de contraseña
            const confirmPassword = formData.get('confirm_password');
            if (password !== confirmPassword) {
                mostrarErrorAgregar('ConfirmPassword', 'Las contraseñas no coinciden');
                valido = false;
            }
            
            return valido;
        }
        
        // Enviar formulario
        if (formAgregarUsuario) {
            formAgregarUsuario.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const formData = new FormData(formAgregarUsuario);
                
                if (!validarFormularioAgregar(formData)) {
                    return;
                }
                
                // Mostrar loading
                const submitBtn = document.getElementById('submitAgregarUsuario');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creando...';
                submitBtn.disabled = true;
                
                try {
                    const response = await fetch('indexadmin.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        mostrarNotificacion(data.message, 'exito');
                        cerrarModal();
                        
                        // Recargar la página después de 1 segundo para ver el nuevo usuario
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                        
                    } else {
                        if (data.errors && Array.isArray(data.errors)) {
                            data.errors.forEach(error => {
                                mostrarNotificacion(error, 'error');
                            });
                        } else if (data.error) {
                            mostrarNotificacion(data.error, 'error');
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                    mostrarNotificacion('Error al conectar con el servidor', 'error');
                } finally {
                    // Restaurar botón
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });
        }
        
        // Validación en tiempo real
        const camposValidacion = ['agregarNombre', 'agregarCorreo', 'agregarPassword', 'agregarConfirmPassword'];
        camposValidacion.forEach(campoId => {
            const campo = document.getElementById(campoId);
            if (campo) {
                campo.addEventListener('blur', () => {
                    const formData = new FormData(formAgregarUsuario);
                    validarFormularioAgregar(formData);
                });
            }
        });
    }

    // ----------------
    // 2. MODAL PARA VER DETALLES (BOTÓN DEL OJITO)
    // ----------------
    function inicializarModalDetalles() {
        const modalVerDetalles = document.getElementById('modalVerDetalles');
        const cerrarDetalles = document.getElementById('cerrarDetalles');
        const editarDesdeDetalles = document.getElementById('editarDesdeDetalles');
        const cerrarModalDetalles = modalVerDetalles?.querySelector('.cerrar');
        
        if (!modalVerDetalles) return;
        
        // Función para abrir modal con datos del usuario
        window.abrirModalDetalles = async function(usuarioId, usuarioRol, usuarioNombre, usuarioCorreo) {
            if (!usuarioId || !usuarioRol) return;
            
            // Mostrar loading
            modalVerDetalles.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Configurar datos básicos
            document.getElementById('detallesNombre').textContent = usuarioNombre || 'Cargando...';
            document.getElementById('detallesCorreo').textContent = usuarioCorreo || 'Cargando...';
            document.getElementById('detallesId').textContent = usuarioId;
            document.getElementById('detallesRol').textContent = usuarioRol.charAt(0).toUpperCase() + usuarioRol.slice(1);
            
            // Configurar icono según rol
            const iconoElement = document.getElementById('detallesIcono');
            if (iconoElement) {
                const iconos = {
                    'admin': 'fa-user-shield',
                    'propietario': 'fa-house-user',
                    'visitante': 'fa-user'
                };
                iconoElement.className = `fa-solid ${iconos[usuarioRol] || 'fa-user'}`;
            }
            
            // Obtener detalles adicionales del servidor
            try {
                const formData = new FormData();
                formData.append('accion', 'obtener_detalles_usuario');
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                formData.append('id', usuarioId);
                formData.append('rol', usuarioRol);
                
                const response = await fetch('indexadmin.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success && data.usuario) {
                    const usuario = data.usuario;
                    
                    // Actualizar detalles
                    document.getElementById('detallesNombre').textContent = usuario.nombre || usuarioNombre;
                    document.getElementById('detallesCorreo').textContent = usuario.correo || usuarioCorreo;
                    
                    // Estado
                    const estadoElement = document.getElementById('detallesEstado');
                    if (estadoElement) {
                        estadoElement.textContent = usuario.estado == 1 ? 'Activo' : 'Inactivo';
                        estadoElement.className = `info-value ${usuario.estado == 1 ? 'estado-activo' : 'estado-inactivo'}`;
                    }
                    
                    // Fechas
                    if (usuario.fecha_creacion) {
                        const fechaCreacion = new Date(usuario.fecha_creacion);
                        document.getElementById('detallesFechaCreacion').textContent = 
                            fechaCreacion.toLocaleDateString('es-ES') + ' ' + 
                            fechaCreacion.toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'});
                    }
                    
                    if (usuario.fecha_actualizacion) {
                        const fechaActualizacion = new Date(usuario.fecha_actualizacion);
                        document.getElementById('detallesFechaActualizacion').textContent = 
                            fechaActualizacion.toLocaleDateString('es-ES') + ' ' + 
                            fechaActualizacion.toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'});
                    }
                    
                    // Guardar datos para editar
                    modalVerDetalles.dataset.userId = usuarioId;
                    modalVerDetalles.dataset.userRol = usuarioRol;
                    
                } else {
                    mostrarNotificacion('Error al cargar detalles del usuario', 'error');
                }
            } catch (error) {
                console.error('Error cargando detalles:', error);
                mostrarNotificacion('Error al cargar detalles', 'error');
            }
        };
        
        // Cerrar modal
        function cerrarModalDetallesFunc() {
            modalVerDetalles.style.display = 'none';
            document.body.style.overflow = '';
        }
        
        if (cerrarDetalles) {
            cerrarDetalles.addEventListener('click', cerrarModalDetallesFunc);
        }
        
        if (cerrarModalDetalles) {
            cerrarModalDetalles.addEventListener('click', cerrarModalDetallesFunc);
        }
        
        // Cerrar al hacer click fuera
        modalVerDetalles.addEventListener('click', (e) => {
            if (e.target === modalVerDetalles) {
                cerrarModalDetallesFunc();
            }
        });
        
        // Botón editar desde detalles
        if (editarDesdeDetalles) {
            editarDesdeDetalles.addEventListener('click', () => {
                const userId = modalVerDetalles.dataset.userId;
                const userRol = modalVerDetalles.dataset.userRol;
                
                if (userId && userRol) {
                    // Buscar y hacer click en el botón de editar correspondiente
                    const fila = document.querySelector(`tr[data-id="${userId}"][data-rol="${userRol}"]`);
                    if (fila) {
                        const btnEditar = fila.querySelector('.editarBtn');
                        if (btnEditar) {
                            btnEditar.click();
                        }
                    }
                }
                
                cerrarModalDetallesFunc();
            });
        }
    }

    // ----------------
    // Modal Editar (existente)
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
                    
                    // Actualizar ícono según rol
                    const icono = displayRol.querySelector('i');
                    if (icono) {
                        const iconos = {
                            'admin': 'fa-user-shield',
                            'propietario': 'fa-house-user',
                            'visitante': 'fa-user'
                        };
                        icono.className = `fa-solid ${iconos[rol] || 'fa-user-shield'}`;
                    }
                    
                    displayRol.querySelector('span').textContent = rol === 'admin' ? 'Administrador' : 
                                           rol === 'propietario' ? 'Propietario' : 'Visitante';

                    modalEditar.style.display = "flex";
                    document.body.style.overflow = 'hidden';
                }
            }
        });

        // Cerrar modal
        function cerrarModalEditar() {
            modalEditar.style.display = "none";
            document.body.style.overflow = '';
        }
        
        modalEditar.querySelectorAll('.cerrar, .btn-cancelar').forEach(btn => {
            btn.addEventListener('click', cerrarModalEditar);
        });
        
        window.addEventListener("click", (e) => { 
            if (e.target === modalEditar) cerrarModalEditar(); 
        });
    }

    // ----------------
    // Estado de Usuarios (existente)
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
    // Eliminación Visual (existente)
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
                document.body.style.overflow = 'hidden';
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
                document.body.style.overflow = '';
            }
        });
    }

    if (cancelarEliminar) {
        cancelarEliminar.addEventListener("click", () => {
            const modalConfirmarEliminar = document.getElementById("modalConfirmarEliminar");
            if (modalConfirmarEliminar) {
                modalConfirmarEliminar.style.display = "none";
                document.body.style.overflow = '';
            }
            filaAEliminar = null;
            nombreAEliminar = '';
        });
    }

    // ----------------
    // Cerrar Sesión (existente)
    // ----------------
    const btnLogout = document.getElementById("btnLogout");
    if (btnLogout) {
        btnLogout.addEventListener("click", (e) => {
            e.preventDefault();
            const modalConfirmarLogout = document.getElementById("modalConfirmarLogout");
            if (modalConfirmarLogout) {
                modalConfirmarLogout.style.display = "flex";
                document.body.style.overflow = 'hidden';
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
                document.body.style.overflow = '';
            }
        });
    }

    // Cerrar modales al hacer click fuera (existente)
    window.addEventListener("click", (e) => { 
        if (e.target.classList.contains('modal')) {
            e.target.style.display = "none";
            document.body.style.overflow = '';
        }
    });

    // ----------------
    // Buscador en Tiempo Real (existente)
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

    // Inicializar buscadores para todas las tablas
    function inicializarTodosBuscadores() {
        ['admins', 'propietarios', 'visitantes'].forEach(tablaId => {
            inicializarBuscadorParaTabla(tablaId);
        });
    }

    // ----------------
    // Eventos para botones del ojito (ver detalles)
    // ----------------
    document.addEventListener('click', (e) => {
        if (e.target.closest('.verDetallesBtn')) {
            const btn = e.target.closest('.verDetallesBtn');
            const id = btn.dataset.id;
            const rol = btn.dataset.rol;
            const nombre = btn.dataset.nombre || 'Usuario';
            const correo = btn.dataset.correo || 'Sin correo';
            
            if (typeof abrirModalDetalles === 'function') {
                abrirModalDetalles(id, rol, nombre, correo);
            }
        }
    });

    // ----------------
    // Actualizar hora en tiempo real (existente)
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
    // Botón de actualizar estadísticas
    // ----------------
    const refreshStats = document.getElementById('refreshStats');
    if (refreshStats) {
        refreshStats.addEventListener('click', () => {
            mostrarNotificacion('Estadísticas actualizadas', 'exito');
            // Aquí podrías agregar lógica para actualizar estadísticas en tiempo real
        });
    }

    // ----------------
    // INICIALIZACIÓN FINAL
    // ----------------
    inicializarMenuUsuarios();
    inicializarFiltrosLogs();
    inicializarModalAgregarUsuario();
    inicializarModalDetalles();
    inicializarTodosBuscadores();
    
    // Mostrar sección inicial si no hay una activa
    if (!document.querySelector('.seccion.visible')) {
        mostrarSeccion('inicio');
    }
    
    console.log('Panel admin inicializado correctamente con nuevas funcionalidades');
});

// =====================================================
// FUNCIONES GLOBALES PARA PAGINACIÓN - VERSIÓN ORIGINAL
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
        } else {
            btnAnterior.classList.remove('disabled');
            btnAnterior.disabled = false;
            btnAnterior.onclick = () => cambiarPagina(tipo, paginaActual - 1);
        }
    }
    
    // Botón siguiente (último)
    const btnSiguiente = contenedor.querySelector('.pagina-btn.small:last-child');
    if (btnSiguiente) {
        if (paginaActual >= totalPaginas) {
            btnSiguiente.classList.add('disabled');
            btnSiguiente.disabled = true;
            btnSiguiente.onclick = null;
        } else {
            btnSiguiente.classList.remove('disabled');
            btnSiguiente.disabled = false;
            btnSiguiente.onclick = () => cambiarPagina(tipo, paginaActual + 1);
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
                    mostrarNotificacion('Estado actualizado correctamente', 'exito');
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
                
                // Actualizar ícono según rol
                const icono = displayRol.querySelector('i');
                if (icono) {
                    const iconos = {
                        'admin': 'fa-user-shield',
                        'propietario': 'fa-house-user',
                        'visitante': 'fa-user'
                    };
                    icono.className = `fa-solid ${iconos[rol] || 'fa-user-shield'}`;
                }
                
                displayRol.querySelector('span').textContent = rol === 'admin' ? 'Administrador' : 
                                           rol === 'propietario' ? 'Propietario' : 'Visitante';

                const modalEditar = document.getElementById("modalEditar");
                if (modalEditar) {
                    modalEditar.style.display = "flex";
                    document.body.style.overflow = 'hidden';
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
                document.body.style.overflow = 'hidden';
            }
        });
    });
    
    // Re-inicializar botones de ver detalles (ojito)
    tbody.querySelectorAll('.verDetallesBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const rol = this.dataset.rol;
            const nombre = this.dataset.nombre || 'Usuario';
            const correo = this.dataset.correo || 'Sin correo';
            
            if (typeof abrirModalDetalles === 'function') {
                abrirModalDetalles(id, rol, nombre, correo);
            }
        });
    });
}

// Función global para mostrar secciones
window.mostrarSeccion = function(id) {
    // Ocultar todas las secciones
    document.querySelectorAll('.seccion').forEach(seccion => {
        seccion.classList.remove('visible');
    });
    
    // Mostrar la sección solicitada
    const seccion = document.getElementById(id);
    if (seccion) {
        seccion.classList.add('visible');
    }
    
    // Actualizar botones del menú principal
    document.querySelectorAll('.menu-btn[data-seccion]').forEach(btn => {
        btn.classList.remove('activo');
        if (btn.dataset.seccion === id) {
            btn.classList.add('activo');
        }
    });
    
    // Si es la sección de usuarios, mostrar la tabla por defecto
    if (id === 'usuarios') {
        const tablaActual = new URLSearchParams(window.location.search).get('tabla') || 'admins';
        mostrarTablaUsuarios(tablaActual);
    }
    
    // Actualizar URL
    const url = new URL(window.location);
    url.searchParams.set('seccion', id);
    window.history.pushState({}, '', url.toString());
};