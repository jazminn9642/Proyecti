document.addEventListener("DOMContentLoaded", () => {

    // ----------------
    // Sistema de Notificaciones
    // ----------------
    function mostrarNotificacion(mensaje, tipo = 'exito') {
        const notificacion = document.createElement('div');
        notificacion.className = `notificacion ${tipo}`;
        notificacion.innerHTML = `
            <i class="fa-solid ${tipo === 'exito' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i>
            <span>${mensaje}</span>
        `;
        
        document.body.appendChild(notificacion);
        
        setTimeout(() => {
            notificacion.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(() => {
                notificacion.remove();
            }, 300);
        }, 4000);
    }

    // Mostrar notificación si hay parámetros en URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('edit') && urlParams.get('edit') === 'ok') {
        mostrarNotificacion('Usuario editado correctamente', 'exito');
    }

    // ----------------
    // Gestión de Secciones - SOLO UN BOTÓN ACTIVO
    // ----------------
    const menuBtns = document.querySelectorAll(".menu-btn");
    const secciones = document.querySelectorAll(".seccion");

    function mostrarSeccion(id) {
        // Ocultar todas las secciones
        secciones.forEach(s => s.classList.remove("visible"));
        
        // Mostrar solo la sección seleccionada
        const el = document.getElementById(id);
        if (el) el.classList.add("visible");
        
        // Remover activo de TODOS los botones primero
        menuBtns.forEach(b => b.classList.remove("activo"));
        
        // Activar solo el botón clickeado
        const btn = document.querySelector(`.menu-btn[data-seccion="${id}"]`);
        if (btn) btn.classList.add("activo");
        
        // Cerrar submenú si está abierto (excepto cuando se clickea Usuarios)
        if (id !== 'usuarios') {
            const submenu = document.getElementById("submenuUsuarios");
            if (submenu) submenu.classList.remove("abierto");
        }
    }

    // Inicializar event listeners para botones del menú
    menuBtns.forEach(btn => {
        btn.addEventListener("click", () => {
            const id = btn.dataset.seccion;
            if (id) mostrarSeccion(id);
        });
    });

    // ----------------
    // Submenu Usuarios (desplegable)
    // ----------------
    const btnUsuarios = document.getElementById("btnUsuarios");
    const submenu = document.getElementById("submenuUsuarios");

    if (btnUsuarios && submenu) {
        btnUsuarios.addEventListener("click", (e) => {
            e.stopPropagation();
            submenu.classList.toggle("abierto");
            btnUsuarios.classList.toggle("activo");
        });

        // Cerrar submenu al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!btnUsuarios.contains(e.target) && !submenu.contains(e.target)) {
                submenu.classList.remove("abierto");
                btnUsuarios.classList.remove("activo");
            }
        });
    }

    // ----------------
    // Gestión de Tablas de Usuarios
    // ----------------
    const tablas = {
        admins: document.getElementById("tablaAdmins"),
        propietarios: document.getElementById("tablaPropietarios"),
        visitantes: document.getElementById("tablaVisitantes")
    };

    document.querySelectorAll(".submenu-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            // Mostrar sección usuarios y activar botón principal
            mostrarSeccion('usuarios');
            btnUsuarios.classList.add("activo");

            // Ocultar todas las tablas y mostrar la correspondiente
            Object.values(tablas).forEach(t => {
                if (t) t.style.display = 'none';
            });
            const target = tablas[btn.dataset.tabla];
            if (target) target.style.display = 'table';

            // Actualizar título
            const titulo = document.getElementById("tituloUsuarios");
            if (titulo) {
                const texto = btn.textContent;
                titulo.textContent = `Usuarios - ${texto}`;
            }

            // Marcar submenu option activo (visual)
            document.querySelectorAll(".submenu .submenu-btn").forEach(x => x.classList.remove("activo"));
            btn.classList.add("activo");
            
            // Inicializar buscador para esta tabla
            inicializarBuscadorParaTabla(btn.dataset.tabla);
        });
    });

    // ----------------
    // Modal Editar Mejorado
    // ----------------
    const modalEditar = document.getElementById("modalEditar");
    const modalConfirmarEliminar = document.getElementById("modalConfirmarEliminar");
    const cerrarModal = modalEditar ? modalEditar.querySelector(".cerrar") : null;
    const inputId = document.getElementById("editId");
    const inputRol = document.getElementById("editRol");
    const inputNombre = document.getElementById("editNombre");
    const inputCorreo = document.getElementById("editCorreo");
    const displayRol = document.getElementById("displayRol");
    const formEditar = document.getElementById("formEditar");
    const btnCancelar = document.querySelector(".btn-cancelar");

    // Mapeo de nombres de roles para mostrar
    const nombresRoles = {
        'admin': 'Administrador',
        'propietario': 'Propietario', 
        'visitante': 'Visitante'
    };

    if (modalEditar) {
        // Abrir modal editar
        document.querySelectorAll(".editarBtn").forEach(btn => {
            if (!btn.closest('.modal-contenido')) {
                btn.addEventListener("click", () => {
                    const id = btn.dataset.id;
                    const rol = btn.dataset.rol;
                    const fila = btn.closest("tr");
                    const nombre = fila.cells[0].innerText.trim();
                    const correo = fila.cells[1].innerText.trim();

                    if (inputId && inputRol && inputNombre && inputCorreo && displayRol) {
                        inputId.value = id;
                        inputRol.value = rol;
                        inputNombre.value = nombre;
                        inputCorreo.value = correo;
                        displayRol.textContent = nombresRoles[rol] || rol;

                        validarFormulario();
                        modalEditar.style.display = "flex";
                    }
                });
            }
        });

        // Cerrar modal editar
        if (cerrarModal) {
            cerrarModal.addEventListener("click", () => modalEditar.style.display = "none");
        }

        if (btnCancelar) {
            btnCancelar.addEventListener("click", () => modalEditar.style.display = "none");
        }
        
        window.addEventListener("click", (e) => { 
            if (e.target === modalEditar) modalEditar.style.display = "none"; 
        });
    }

    // ----------------
    // Validación Formulario Editar
    // ----------------
    function validarFormulario() {
        if (!inputNombre || !inputCorreo) return false;
        
        const nombreValido = inputNombre.value.trim().length >= 2;
        const correoValido = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(inputCorreo.value);
        
        if (inputNombre) {
            inputNombre.style.borderColor = nombreValido ? '#82b16d' : '#ef4444';
            inputNombre.style.boxShadow = nombreValido ? '0 0 0 3px rgba(130, 177, 109, 0.1)' : '0 0 0 3px rgba(239, 68, 68, 0.1)';
        }
        if (inputCorreo) {
            inputCorreo.style.borderColor = correoValido ? '#82b16d' : '#ef4444';
            inputCorreo.style.boxShadow = correoValido ? '0 0 0 3px rgba(130, 177, 109, 0.1)' : '0 0 0 3px rgba(239, 68, 68, 0.1)';
        }
        
        return nombreValido && correoValido;
    }

    if (inputNombre && inputCorreo) {
        inputNombre.addEventListener('input', validarFormulario);
        inputCorreo.addEventListener('input', validarFormulario);
    }

    if (formEditar) {
        formEditar.addEventListener('submit', function(e) {
            if (!validarFormulario()) {
                e.preventDefault();
                mostrarNotificacion('Por favor, corrige los errores en el formulario', 'error');
            }
        });
    }

    // ----------------
    // Sistema de Eliminación con Confirmación (SOLO VISUAL)
    // ----------------
    let filaAEliminar = null;
    let nombreAEliminar = '';

    // Modal de confirmación
    const textoConfirmacion = document.getElementById("textoConfirmacion");
    const cancelarEliminar = document.getElementById("cancelarEliminar");
    const confirmarEliminar = document.getElementById("confirmarEliminar");

    // Abrir modal de confirmación
    document.querySelectorAll(".eliminarBtn").forEach(b => {
        b.addEventListener("click", (e) => {
            e.preventDefault();
            filaAEliminar = b.closest("tr");
            nombreAEliminar = b.dataset.nombre || filaAEliminar.cells[0].innerText.trim();
            
            // Actualizar texto de confirmación
            if (textoConfirmacion) {
                textoConfirmacion.innerHTML = `¿Estás seguro de que quieres eliminar a <strong>"${nombreAEliminar}"</strong>?<br><small>Esta acción solo eliminará el usuario de la vista, no de la base de datos.</small>`;
            }
            
            modalConfirmarEliminar.style.display = "flex";
        });
    });

    // Confirmar eliminación (solo visual)
    if (confirmarEliminar) {
        confirmarEliminar.addEventListener("click", () => {
            if (filaAEliminar) {
                // Efecto visual de eliminación
                filaAEliminar.style.opacity = '0.5';
                filaAEliminar.style.transform = 'translateX(20px)';
                filaAEliminar.style.transition = 'all 0.4s ease';
                
                setTimeout(() => {
                    filaAEliminar.style.display = "none";
                    mostrarNotificacion(`Usuario "${nombreAEliminar}" eliminado de la vista`, 'exito');
                    
                    // Resetear variables
                    filaAEliminar = null;
                    nombreAEliminar = '';
                }, 400);
            }
            
            modalConfirmarEliminar.style.display = "none";
        });
    }

    // Cancelar eliminación
    if (cancelarEliminar) {
        cancelarEliminar.addEventListener("click", () => {
            modalConfirmarEliminar.style.display = "none";
            filaAEliminar = null;
            nombreAEliminar = '';
        });
    }

    // Cerrar modal de confirmación al hacer click fuera
    window.addEventListener("click", (e) => { 
        if (e.target === modalConfirmarEliminar) {
            modalConfirmarEliminar.style.display = "none";
            filaAEliminar = null;
            nombreAEliminar = '';
        }
    });

    // ----------------
    // Cerrar Sesión
    // ----------------
    const btnLogout = document.getElementById("btnLogout");
    if (btnLogout) {
        btnLogout.addEventListener("click", (e) => {
            e.preventDefault();
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = '../database/logout.php';
            }
        });
    }

    // ----------------
    // Buscador en Tiempo Real
    // ----------------
    function inicializarBuscadorParaTabla(tablaId) {
        const tablaNombre = 'tabla' + tablaId.charAt(0).toUpperCase() + tablaId.slice(1);
        const tabla = document.getElementById(tablaNombre);
        if (!tabla) return;
        
        const thead = tabla.querySelector('thead');
        const filas = tabla.querySelectorAll('tbody tr');
        
        // Verificar si ya existe un buscador
        let buscador = thead.previousElementSibling;
        if (!buscador || !buscador.classList.contains('buscador')) {
            buscador = document.createElement('input');
            buscador.type = 'text';
            buscador.placeholder = `Buscar en ${tablaId}...`;
            buscador.className = 'buscador';
            thead.parentNode.insertBefore(buscador, thead);
        }
        
        buscador.addEventListener('input', (e) => {
            const texto = e.target.value.toLowerCase();
            let filasVisibles = 0;
            
            filas.forEach(fila => {
                const textoFila = fila.textContent.toLowerCase();
                const mostrar = textoFila.includes(texto);
                fila.style.display = mostrar ? '' : 'none';
                if (mostrar) filasVisibles++;
            });
            
            // Mostrar mensaje si no hay resultados
            let mensajeNoResultados = tabla.parentNode.querySelector('.no-resultados');
            if (filasVisibles === 0 && texto !== '') {
                if (!mensajeNoResultados) {
                    mensajeNoResultados = document.createElement('div');
                    mensajeNoResultados.className = 'no-resultados';
                    mensajeNoResultados.style.cssText = 'text-align: center; padding: 20px; color: #6b7280; font-style: italic;';
                    mensajeNoResultados.textContent = 'No se encontraron resultados';
                    tabla.parentNode.appendChild(mensajeNoResultados);
                }
            } else if (mensajeNoResultados) {
                mensajeNoResultados.remove();
            }
        });
    }

    // Inicializar todos los buscadores
    function inicializarTodosBuscadores() {
        inicializarBuscadorParaTabla('admins');
        inicializarBuscadorParaTabla('propietarios');
        inicializarBuscadorParaTabla('visitantes');
        
        // Buscador para logs
        const tablaLogs = document.getElementById('tablaLogs');
        if (tablaLogs) {
            const thead = tablaLogs.querySelector('thead');
            const filas = tablaLogs.querySelectorAll('tbody tr');
            
            let buscador = thead.previousElementSibling;
            if (!buscador || !buscador.classList.contains('buscador')) {
                buscador = document.createElement('input');
                buscador.type = 'text';
                buscador.placeholder = 'Buscar en logs...';
                buscador.className = 'buscador';
                thead.parentNode.insertBefore(buscador, thead);
            }
            
            buscador.addEventListener('input', (e) => {
                const texto = e.target.value.toLowerCase();
                filas.forEach(fila => {
                    const textoFila = fila.textContent.toLowerCase();
                    fila.style.display = textoFila.includes(texto) ? '' : 'none';
                });
            });
        }
    }

    // ----------------
    // Ordenamiento de Tablas
    // ----------------
    function inicializarOrdenamiento() {
        document.querySelectorAll('th[data-ordenable="true"]').forEach(th => {
            th.style.cursor = 'pointer';
            th.title = 'Click para ordenar';
            
            th.addEventListener('click', () => {
                const tabla = th.closest('table');
                const tbody = tabla.querySelector('tbody');
                const indice = Array.from(th.parentNode.children).indexOf(th);
                const filas = Array.from(tbody.querySelectorAll('tr'));
                
                const ordenActual = th.dataset.orden || 'asc';
                const nuevoOrden = ordenActual === 'asc' ? 'desc' : 'asc';
                
                filas.sort((a, b) => {
                    const textoA = a.cells[indice].textContent.trim();
                    const textoB = b.cells[indice].textContent.trim();
                    
                    const numA = parseFloat(textoA.replace(/[^\d.-]/g, ''));
                    const numB = parseFloat(textoB.replace(/[^\d.-]/g, ''));
                    
                    if (!isNaN(numA) && !isNaN(numB)) {
                        return nuevoOrden === 'asc' ? numA - numB : numB - numA;
                    } else {
                        return nuevoOrden === 'asc' 
                            ? textoA.localeCompare(textoB) 
                            : textoB.localeCompare(textoA);
                    }
                });
                
                filas.forEach(fila => tbody.appendChild(fila));
                th.dataset.orden = nuevoOrden;
                
                // Actualizar indicadores visuales
                tabla.querySelectorAll('th[data-ordenable="true"]').forEach(header => {
                    header.innerHTML = header.innerHTML.replace(' ↑', '').replace(' ↓', '');
                    if (header === th) {
                        header.innerHTML += nuevoOrden === 'asc' ? ' ↑' : ' ↓';
                    }
                });
            });
        });
    }

    // ----------------
    // Inicialización Completa
    // ----------------
    function inicializarTodo() {
        inicializarTodosBuscadores();
        inicializarOrdenamiento();
        
        // Inicializar tabla de admins por defecto
        const tablaAdmins = document.getElementById('tablaAdmins');
        if (tablaAdmins && tablaAdmins.style.display !== 'none') {
            inicializarBuscadorParaTabla('admins');
        }
    }

    // Inicializar cuando el DOM esté listo
    inicializarTodo();

    // ----------------
    // Mejoras de UX Adicionales
    // ----------------
    
    // Prevenir envío múltiple de formularios
    if (formEditar) {
        formEditar.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
                
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar Cambios';
                }, 3000);
            }
        });
    }

    // Cerrar modales con ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (modalEditar && modalEditar.style.display === 'flex') {
                modalEditar.style.display = 'none';
            }
            if (modalConfirmarEliminar && modalConfirmarEliminar.style.display === 'flex') {
                modalConfirmarEliminar.style.display = 'none';
                filaAEliminar = null;
                nombreAEliminar = '';
            }
        }
    });

    // Smooth scroll para mejor experiencia
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Efectos hover mejorados para tarjetas
    document.querySelectorAll('.resumen-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

});