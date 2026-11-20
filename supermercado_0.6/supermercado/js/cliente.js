// ========== menu lateral ==========
(() => {
    // elementos de la pagina que se usan en el codigo
    const abrir = document.getElementById("abrirMenu");
    const cerrar = document.getElementById("cerrarMenu");
    const fondo = document.getElementById("fondoMenu");

    // esta funcion abre el menu lateral
    function mostrarMenu() {
        document.body.classList.add("menu-abierto");
        abrir.setAttribute("aria-expanded", "true");
    }

    // esta funcion cierra el menu lateral
    function ocultarMenu() {
        document.body.classList.remove("menu-abierto");
        abrir.setAttribute("aria-expanded", "false");
    }

    // escucha el clic del usuario
    abrir.addEventListener("click", mostrarMenu);
    cerrar.addEventListener("click", ocultarMenu);
    fondo.addEventListener("click", ocultarMenu);

    // escucha las teclas que presiona el usuario
    document.addEventListener("keydown", evento => {
        if (evento.key === "Escape") ocultarMenu();
    });
})();

// ========== solo letras o solo numeros ==========
// quita en vivo lo que no corresponde mientras la persona escribe
document.querySelectorAll("[data-tipo]").forEach(function (campo) {
    campo.addEventListener("input", function () {
        if (campo.dataset.tipo === "letras") {
            // deja solo letras (con tildes y ñ) y espacios
            campo.value = campo.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s]/g, "");
        } else if (campo.dataset.tipo === "numeros") {
            // deja solo numeros
            campo.value = campo.value.replace(/\D/g, "");
        }
    });
});

// ========== mensajes de validacion en español ==========
document.querySelectorAll(".formulario input").forEach(function (campo) {
    // cuando el campo no es valido, muestra el mensaje en español
    campo.addEventListener("invalid", function () {
        // limpia el mensaje anterior para recalcular el estado
        campo.setCustomValidity("");

        if (campo.validity.valueMissing) {
            // campo vacio
            campo.setCustomValidity("Por favor, llena este campo.");
        } else if (campo.validity.typeMismatch) {
            // correo mal escrito
            campo.setCustomValidity("Escribe un correo válido. Ejemplo: maria.gomez@correo.com");
        } else if (campo.validity.patternMismatch) {
            // formato incorrecto (letras o numeros)
            campo.setCustomValidity(campo.dataset.error || "El formato no es válido.");
        } else if (campo.validity.tooShort) {
            // muy corto
            campo.setCustomValidity("Debe tener al menos " + campo.minLength + " caracteres.");
        }
    });

    // borra el mensaje cuando la persona corrige el campo
    campo.addEventListener("input", function () {
        campo.setCustomValidity("");
    });
});

// ========== ventana para confirmar la eliminacion ==========
(() => {
    const modal = document.getElementById("modalEliminar");
    // si no hay ventana (empleado), no hace nada
    if (!modal) return;

    const texto = document.getElementById("textoEliminar");
    const botonConfirmar = document.getElementById("confirmarEliminar");
    const botonCancelar = document.getElementById("cancelarEliminar");
    // formulario que se va a enviar si la persona confirma
    let formularioPendiente = null;

    // abre la ventana con el nombre del cliente
    function abrirModal(formulario) {
        formularioPendiente = formulario;
        const fila = formulario.closest("tr");
        const nombre = fila ? fila.children[1].textContent.trim() : "";
        texto.textContent = nombre
            ? "¿Seguro que deseas eliminar al cliente " + nombre + "? Esta acción no se puede deshacer."
            : "¿Seguro que deseas eliminar este cliente? Esta acción no se puede deshacer.";
        modal.hidden = false;
        botonCancelar.focus();
    }

    // cierra la ventana sin eliminar
    function cerrarModal() {
        modal.hidden = true;
        formularioPendiente = null;
    }

    // intercepta el envio de cualquier formulario de eliminar (tambien los de la busqueda en vivo)
    document.addEventListener("submit", function (e) {
        if (e.target.classList.contains("form-eliminar")) {
            e.preventDefault();
            abrirModal(e.target);
        }
    });

    // confirmar: agrega el dato que espera php y envia el formulario
    botonConfirmar.addEventListener("click", function () {
        if (!formularioPendiente) return;
        const oculto = document.createElement("input");
        oculto.type = "hidden";
        oculto.name = "eliminar_cliente";
        oculto.value = "1";
        formularioPendiente.appendChild(oculto);
        formularioPendiente.submit();
    });

    // cancelar con el boton
    botonCancelar.addEventListener("click", cerrarModal);

    // cancelar al hacer clic en el fondo oscuro
    modal.addEventListener("click", function (e) {
        if (e.target === modal) cerrarModal();
    });

    // cancelar con la tecla Escape
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && !modal.hidden) cerrarModal();
    });
})();

// ========== busqueda en vivo ==========
(() => {
    const entrada = document.getElementById("buscador");
    const tabla = document.getElementById("tablaClientes");
    const formulario = document.getElementById("formBuscador");
    const botonLimpiar = document.getElementById("limpiarBusqueda");
    // si no hay buscador (empleado), no hace nada
    if (!entrada || !tabla) return;

    let temporizador = null;
    let controlador = null;

    // pide la tabla filtrada al servidor y la reemplaza
    async function buscar() {
        const texto = entrada.value.trim();
        const url = "cliente.php" + (texto !== "" ? "?buscar=" + encodeURIComponent(texto) : "");

        // cancela la busqueda anterior si todavia no termina
        if (controlador) controlador.abort();
        controlador = new AbortController();

        try {
            const respuesta = await fetch(url, { signal: controlador.signal, credentials: "same-origin" });
            const html = await respuesta.text();
            const documento = new DOMParser().parseFromString(html, "text/html");
            const nueva = documento.getElementById("tablaClientes");

            // solo reemplaza si llego la tabla
            if (nueva) {
                tabla.innerHTML = nueva.innerHTML;
                history.replaceState(null, "", url);
            }
        } catch (error) {
            if (error.name !== "AbortError") console.error(error);
        }
    }

    // busca 300 milisegundos despues de dejar de escribir
    entrada.addEventListener("input", function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(buscar, 300);
    });

    // el boton buscar tambien funciona (sin recargar la pagina)
    formulario.addEventListener("submit", function (e) {
        e.preventDefault();
        clearTimeout(temporizador);
        buscar();
    });

    // el boton limpiar borra el texto y muestra todos (si existe)
    if (botonLimpiar) {
        botonLimpiar.addEventListener("click", function () {
            entrada.value = "";
            buscar();
            entrada.focus();
        });
    }
})();