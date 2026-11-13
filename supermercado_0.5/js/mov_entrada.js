// abrir y cerrar el menu lateral.
const abrir = document.getElementById("abrirMenu");
const cerrar = document.getElementById("cerrarMenu");
const fondo = document.getElementById("fondoMenu");

// escucha el clic del usuario
abrir.addEventListener("click", function () {
    document.body.classList.add("menu-abierto");
    abrir.setAttribute("aria-expanded", "true");
});

// esta funcion cierra el menu lateral
function ocultarMenu() {
    document.body.classList.remove("menu-abierto");
    abrir.setAttribute("aria-expanded", "false");
}

// escucha el clic del usuario
cerrar.addEventListener("click", ocultarMenu);
// escucha el clic del usuario
fondo.addEventListener("click", ocultarMenu);

// escucha las teclas que presiona el usuario
document.addEventListener("keydown", function (evento) {
    if (evento.key === "Escape") {
        ocultarMenu();
    }
});

// buscar proveedores y productos.
function crearAutocompletado(datos) {
    // elementos de la pagina que se usan en el codigo
    const input = document.getElementById(datos.campo);
    const lista = document.getElementById(datos.lista);
    const oculto = document.getElementById(datos.oculto);
    const estado = document.getElementById(datos.estado);

    // esta funcion pide al servidor las sugerencias que coinciden con el texto
    async function buscar(texto) {
        // pide los datos al servidor
        const respuesta = await fetch(
            `mov_entrada.php?${datos.parametro}=${encodeURIComponent(texto)}`
        );

        if (!respuesta.ok) {
            throw new Error("No se pudo buscar.");
        }

        return respuesta.json();
    }

    // escucha lo que el usuario escribe
    input.addEventListener("input", async function () {
        oculto.value = "";
        estado.textContent = "";

        const texto = input.value.trim();

        if (texto === "") {
            lista.replaceChildren();
            return;
        }

        try {
            const opciones = await buscar(texto);
            lista.replaceChildren();

            opciones.forEach(function (fila) {
                const opcion = document.createElement("option");
                opcion.value = `${fila.codigo} - ${fila.nombre}`;
                lista.appendChild(opcion);
            });
        } catch (error) {
            estado.textContent = `Error al buscar ${datos.nombre}.`;
        }
    });

    // esta funcion verifica que lo escrito corresponda a un registro existente
    async function resolver() {
        const texto = input.value.trim();

        if (texto === "") {
            oculto.value = "";
            estado.textContent = `Escriba el código o el nombre del ${datos.nombre}.`;
            return false;
        }

        try {
            const opciones = await buscar(texto);

            const encontrada = opciones.find(function (fila) {
                return texto === String(fila.codigo).trim() ||
                    texto.toLowerCase() === String(fila.nombre).trim().toLowerCase() ||
                    texto === `${fila.codigo} - ${fila.nombre}`;
            });

            if (!encontrada) {
                oculto.value = "";
                estado.textContent = `${datos.nombre} no encontrado. Seleccione una sugerencia.`;
                return false;
            }

            oculto.value = String(encontrada.codigo);
            input.value = `${encontrada.codigo} - ${encontrada.nombre}`;
            estado.textContent = "";
            return true;
        } catch (error) {
            oculto.value = "";
            estado.textContent = `No se pudo verificar el ${datos.nombre}.`;
            return false;
        }
    }

    // escucha cuando el usuario cambia el valor del campo
    input.addEventListener("change", resolver);
    return resolver;
}

// preparar la busqueda del proveedor y del producto.
const resolverProveedor = crearAutocompletado({
    campo: "proveedor_busqueda",
    lista: "lista_proveedores",
    oculto: "cod_proveedor",
    estado: "estado_proveedor",
    parametro: "buscar_proveedor",
    nombre: "proveedor"
});

const resolverProducto = crearAutocompletado({
    campo: "producto_busqueda",
    lista: "lista_productos",
    oculto: "cod_producto",
    estado: "estado_producto",
    parametro: "buscar_producto",
    nombre: "producto"
});

// revisar los datos antes de guardar.
const formEntrada = document.getElementById("formularioEntrada");
let validado = false;

// escucha el envio del formulario
formEntrada.addEventListener("submit", async function (evento) {
    if (validado) {
        return;
    }

    evento.preventDefault();

    if (!await resolverProveedor()) {
        alert("Seleccione un proveedor registrado de las sugerencias.");
        document.getElementById("proveedor_busqueda").focus();
        return;
    }

    if (!await resolverProducto()) {
        alert("Seleccione un producto registrado de las sugerencias.");
        document.getElementById("producto_busqueda").focus();
        return;
    }

    validado = true;
    formEntrada.requestSubmit();
});
// ========== ventana para confirmar la eliminacion ==========
(() => {
    const modal = document.getElementById("modalEliminar");
    // si no hay ventana, no hace nada
    if (!modal) return;

    const texto = document.getElementById("textoEliminar");
    const botonConfirmar = document.getElementById("confirmarEliminar");
    const botonCancelar = document.getElementById("cancelarEliminar");
    // formulario que se va a enviar si la persona confirma
    let formularioPendiente = null;

    // abre la ventana con el nombre del registro
    function abrirModal(formulario) {
        formularioPendiente = formulario;
        const fila = formulario.closest("tr");
        const nombre = fila ? fila.children[0].textContent.trim() : "";
        texto.textContent = nombre
            ? "¿Seguro que deseas eliminar el movimiento de entrada " + nombre + "? Esta acción no se puede deshacer."
            : "¿Seguro que deseas eliminar este registro? Esta acción no se puede deshacer.";
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

    // confirmar: envia el formulario
    botonConfirmar.addEventListener("click", function () {
        if (!formularioPendiente) return;
        // agrega el dato que espera php
        const oculto = document.createElement("input");
        oculto.type = "hidden";
        oculto.name = "eliminar_movimiento";
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
    const entrada = document.getElementById("campoBuscar");
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
        const url = "mov_entrada.php" + (texto !== "" ? "?buscar=" + encodeURIComponent(texto) : "");

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
        } else if (campo.dataset.tipo === "decimal") {
            // deja solo numeros y un punto decimal
            let valor = campo.value.replace(/,/g, ".").replace(/[^0-9.]/g, "");
            const punto = valor.indexOf(".");
            if (punto !== -1) valor = valor.slice(0, punto + 1) + valor.slice(punto + 1).replace(/\./g, "");
            campo.value = valor;
        }
    });
});

// ========== mensajes de validacion en español ==========
document.querySelectorAll(".formulario input, .formulario select").forEach(function (campo) {
    // cuando el campo no es valido, muestra el mensaje en español
    campo.addEventListener("invalid", function () {
        // respeta los mensajes propios de la pagina
        if (campo.validity.customError && campo.validationMessage) return;
        campo.setCustomValidity("");

        if (campo.validity.valueMissing) {
            campo.setCustomValidity(campo.tagName === "SELECT" ? "Por favor, selecciona una opción." : "Por favor, llena este campo.");
        } else if (campo.validity.typeMismatch) {
            campo.setCustomValidity("Escribe un correo válido. Ejemplo: maria.gomez@correo.com");
        } else if (campo.validity.patternMismatch) {
            campo.setCustomValidity(campo.dataset.error || "El formato no es válido.");
        } else if (campo.validity.tooShort) {
            campo.setCustomValidity("Debe tener al menos " + campo.minLength + " caracteres.");
        }
    });

    // borra el mensaje cuando la persona corrige el campo
    campo.addEventListener("input", function () {
        campo.setCustomValidity("");
    });
    campo.addEventListener("change", function () {
        campo.setCustomValidity("");
    });
});