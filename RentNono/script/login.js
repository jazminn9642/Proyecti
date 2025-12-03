//LOGICA DE VENTANAS FLOTANTES (INICIO DE SESION Y REGISTRO DE USUARIOS)

    const abrirLogin = document.getElementById('abrirLogin');
    const cerrarLogin = document.getElementById('cerrarLogin');
    const modalFondoLogin = document.getElementById('modalFondoLogin');

    const abrirRegistroPropietario = document.getElementById('abrirRegistroPropietario');
    const cerrarRegistroPropietario = document.getElementById('cerrarRegistroPropietario');
    const modalFondoRegistroPropietario = document.getElementById('modalFondoRegistroPropietario');

    const abrirRegistroVisitante = document.getElementById('abrirRegistroVisitante');
    const cerrarRegistroVisitante = document.getElementById('cerrarRegistroVisitante');
    const modalFondoRegistroVisitante = document.getElementById('modalFondoRegistroVisitante');

    // Abrir y cerrar Login
    abrirLogin.onclick = () => modalFondoLogin.style.display = 'flex';
    cerrarLogin.onclick = () => modalFondoLogin.style.display = 'none';

    // Abrir Registro Propietario y cerrar Login
    abrirRegistroPropietario.onclick = () => {
        modalFondoLogin.style.display = 'none';   // Cierra Login
        modalFondoRegistroPropietario.style.display = 'flex'; // Abre Registro
    };
    cerrarRegistroPropietario.onclick = () => modalFondoRegistroPropietario.style.display = 'none';

    // Abrir Registro Visitante y cerrar Login
    abrirRegistroVisitante.onclick = () => {
        modalFondoLogin.style.display = 'none';   // Cierra Login
        modalFondoRegistroVisitante.style.display = 'flex'; // Abre Registro
    };
    cerrarRegistroVisitante.onclick = () => modalFondoRegistroVisitante.style.display = 'none';

    // Cerrar modales al hacer click fuera
    window.addEventListener('click', (e) => {
        if(e.target === modalFondoLogin) modalFondoLogin.style.display = 'none';
        if(e.target === modalFondoRegistroPropietario) modalFondoRegistroPropietario.style.display = 'none';
        if(e.target === modalFondoRegistroVisitante) modalFondoRegistroVisitante.style.display = 'none';
    });
    fetch("publicaciones.php?ajax=1&" + query)
