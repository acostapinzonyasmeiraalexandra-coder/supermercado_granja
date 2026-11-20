 // obtener los elementos del menu
    const abrirMenu = document.getElementById("abrirMenu");
    const cerrarMenu = document.getElementById("cerrarMenu");
    const fondoMenu = document.getElementById("fondoMenu");

    // cambiar el estado del menu
    function cambiarMenu(abierto) {
        document.body.classList.toggle("menu-abierto", abierto);
        abrirMenu.setAttribute("aria-expanded", abierto);
    }

    // abrir y cerrar el menu
    abrirMenu.addEventListener("click", function () {
        cambiarMenu(true);
    });

    // escucha el clic del usuario
    cerrarMenu.addEventListener("click", function () {
        cambiarMenu(false);
    });

    // escucha el clic del usuario
    fondoMenu.addEventListener("click", function () {
        cambiarMenu(false);
    });

    // cerrar el menu con escape
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            cambiarMenu(false);
        }
    });
// ========== busqueda en vivo ==========
(() => {
    const entrada = document.getElementById("buscar_texto");
    const tabla = document.getElementById("cuerpoTabla");
    const formulario = document.getElementById("formBuscador");
    const botonLimpiar = document.getElementById("limpiarBusqueda");
    // si no hay buscador, no hace nada
    if (!entrada || !tabla || !formulario) return;

    let temporizador = null;
    let controlador = null;

    // muestra u oculta el boton "mostrar todos"
    function actualizarBoton() {
        if (botonLimpiar) botonLimpiar.hidden = entrada.value.trim() === "";
    }

    // pide la tabla filtrada al servidor y la reemplaza
    async function buscar() {
        const texto = entrada.value.trim();
        const url = "inventario.php" + (texto !== "" ? "?buscar_texto=" + encodeURIComponent(texto) : "");

        // cancela la busqueda anterior si todavia no termina
        if (controlador) controlador.abort();
        controlador = new AbortController();

        try {
            const respuesta = await fetch(url, { signal: controlador.signal, credentials: "same-origin" });
            const html = await respuesta.text();
            const documento = new DOMParser().parseFromString(html, "text/html");
            const nueva = documento.getElementById("cuerpoTabla");

            // solo reemplaza si llego la tabla
            if (nueva) {
                tabla.innerHTML = nueva.innerHTML;
                // actualiza tambien: .resumen-inventario
                const viejo0 = document.querySelector(".resumen-inventario");
                const nuevo0 = documento.querySelector(".resumen-inventario");
                if (viejo0 && nuevo0) viejo0.innerHTML = nuevo0.innerHTML;
                // actualiza tambien: .contador
                const viejo1 = document.querySelector(".contador");
                const nuevo1 = documento.querySelector(".contador");
                if (viejo1 && nuevo1) viejo1.innerHTML = nuevo1.innerHTML;
                history.replaceState(null, "", url);
            }
        } catch (error) {
            if (error.name !== "AbortError") console.error(error);
        }
        actualizarBoton();
    }

    // busca 300 milisegundos despues de dejar de escribir
    entrada.addEventListener("input", function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(buscar, 300);
        actualizarBoton();
    });

    // el boton buscar tambien funciona (sin recargar la pagina)
    formulario.addEventListener("submit", function (e) {
        e.preventDefault();
        clearTimeout(temporizador);
        buscar();
    });

    // el boton mostrar todos borra el texto y muestra todos
    if (botonLimpiar) {
        botonLimpiar.addEventListener("click", function (e) {
            e.preventDefault();
            entrada.value = "";
            buscar();
            entrada.focus();
        });
    }
})();