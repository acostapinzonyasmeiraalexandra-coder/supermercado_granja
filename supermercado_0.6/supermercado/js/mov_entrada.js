// menu lateral
const abrir = document.getElementById("abrirMenu");
const cerrar = document.getElementById("cerrarMenu");
const fondo = document.getElementById("fondoMenu");

function ocultarMenu() {
    document.body.classList.remove("menu-abierto");
    abrir.setAttribute("aria-expanded", "false");
}

abrir.addEventListener("click", function () {
    document.body.classList.add("menu-abierto");
    abrir.setAttribute("aria-expanded", "true");
});
cerrar.addEventListener("click", ocultarMenu);
fondo.addEventListener("click", ocultarMenu);

document.addEventListener("keydown", function (evento) {
    if (evento.key === "Escape") {
        ocultarMenu();
    }
});

// datos de los dos campos que buscan en el servidor
const campos = [
    {
        input: "proveedor_busqueda",
        lista: "lista_proveedores",
        oculto: "cod_proveedor",
        estado: "estado_proveedor",
        parametro: "buscar_proveedor",
        nombre: "proveedor"
    },
    {
        input: "producto_busqueda",
        lista: "lista_productos",
        oculto: "cod_producto",
        estado: "estado_producto",
        parametro: "buscar_producto",
        nombre: "producto"
    }
];

// pide al servidor los registros que coinciden con el texto
async function buscar(parametro, texto) {
    const respuesta = await fetch("mov_entrada.php?" + parametro + "=" + encodeURIComponent(texto));

    if (!respuesta.ok) {
        throw new Error("No se pudo buscar.");
    }

    return respuesta.json();
}

// revisa que lo escrito sea un registro que existe y llena el campo oculto
async function verificar(c) {
    const input = document.getElementById(c.input);
    const oculto = document.getElementById(c.oculto);
    const estado = document.getElementById(c.estado);
    const texto = input.value.trim();

    if (texto === "") {
        oculto.value = "";
        estado.textContent = "Escriba el código o el nombre del " + c.nombre + ".";
        return false;
    }

    try {
        const opciones = await buscar(c.parametro, texto);

        const encontrada = opciones.find(function (fila) {
            return texto === String(fila.codigo).trim() ||
                texto.toLowerCase() === String(fila.nombre).trim().toLowerCase() ||
                texto === fila.codigo + " - " + fila.nombre;
        });

        if (!encontrada) {
            oculto.value = "";
            estado.textContent = c.nombre + " no encontrado. Seleccione una sugerencia.";
            return false;
        }

        oculto.value = String(encontrada.codigo);
        input.value = encontrada.codigo + " - " + encontrada.nombre;
        estado.textContent = "";
        return true;
    } catch (error) {
        oculto.value = "";
        estado.textContent = "No se pudo verificar el " + c.nombre + ".";
        return false;
    }
}

// prepara las sugerencias del proveedor y del producto
campos.forEach(function (c) {
    const input = document.getElementById(c.input);
    const lista = document.getElementById(c.lista);
    const oculto = document.getElementById(c.oculto);
    const estado = document.getElementById(c.estado);

    // mientras escribe: llena las sugerencias
    input.addEventListener("input", async function () {
        oculto.value = "";
        estado.textContent = "";

        const texto = input.value.trim();
        lista.replaceChildren();

        if (texto === "") {
            return;
        }

        try {
            const opciones = await buscar(c.parametro, texto);

            opciones.forEach(function (fila) {
                const opcion = document.createElement("option");
                opcion.value = fila.codigo + " - " + fila.nombre;
                lista.appendChild(opcion);
            });
        } catch (error) {
            estado.textContent = "Error al buscar " + c.nombre + ".";
        }
    });

    // al terminar de escribir: verifica que exista
    input.addEventListener("change", function () {
        verificar(c);
    });
});

// revisa los datos antes de guardar
const formEntrada = document.getElementById("formularioEntrada");
let validado = false;

formEntrada.addEventListener("submit", async function (evento) {
    if (validado) {
        return;
    }

    evento.preventDefault();

    for (const c of campos) {
        if (!(await verificar(c))) {
            alert("Seleccione un " + c.nombre + " registrado de las sugerencias.");
            document.getElementById(c.input).focus();
            return;
        }
    }

    validado = true;
    formEntrada.requestSubmit();
});

// ventana para confirmar la eliminacion
const modal = document.getElementById("modalEliminar");

if (modal) {
    const textoModal = document.getElementById("textoEliminar");
    const botonConfirmar = document.getElementById("confirmarEliminar");
    const botonCancelar = document.getElementById("cancelarEliminar");
    let formularioPendiente = null;

    function cerrarModal() {
        modal.hidden = true;
        formularioPendiente = null;
    }

    // al enviar un formulario de eliminar, abre la ventana en vez de enviarlo
    document.addEventListener("submit", function (evento) {
        if (evento.target.classList.contains("form-eliminar")) {
            evento.preventDefault();
            formularioPendiente = evento.target;

            const fila = formularioPendiente.closest("tr");
            const codigo = fila ? fila.children[0].textContent.trim() : "";

            textoModal.textContent = codigo
                ? "¿Seguro que deseas eliminar el movimiento de entrada " + codigo + "? Esta acción no se puede deshacer."
                : "¿Seguro que deseas eliminar este registro? Esta acción no se puede deshacer.";

            modal.hidden = false;
            botonCancelar.focus();
        }
    });

    // confirmar: envia el formulario
    botonConfirmar.addEventListener("click", function () {
        if (!formularioPendiente) {
            return;
        }
        const oculto = document.createElement("input");
        oculto.type = "hidden";
        oculto.name = "eliminar_movimiento";
        oculto.value = "1";
        formularioPendiente.appendChild(oculto);
        formularioPendiente.submit();
    });

    // cancelar: con el boton, con clic en el fondo oscuro o con Escape
    botonCancelar.addEventListener("click", cerrarModal);
    modal.addEventListener("click", function (evento) {
        if (evento.target === modal) {
            cerrarModal();
        }
    });
    document.addEventListener("keydown", function (evento) {
        if (evento.key === "Escape" && !modal.hidden) {
            cerrarModal();
        }
    });
}

// busqueda en vivo de movimientos
const campoBuscar = document.getElementById("campoBuscar");
const cuerpoTabla = document.getElementById("cuerpoTabla");
const formBuscador = document.getElementById("formBuscador");
const botonLimpiar = document.getElementById("limpiarBusqueda");

if (campoBuscar && cuerpoTabla && formBuscador) {
    let temporizador = null;
    let controlador = null;

    // pide la tabla filtrada al servidor y reemplaza las filas
    async function buscarMovimientos() {
        const texto = campoBuscar.value.trim();
        const url = "mov_entrada.php" + (texto !== "" ? "?buscar=" + encodeURIComponent(texto) : "");

        // cancela la busqueda anterior si todavia no termina
        if (controlador) {
            controlador.abort();
        }
        controlador = new AbortController();

        try {
            const respuesta = await fetch(url, { signal: controlador.signal, credentials: "same-origin" });
            const html = await respuesta.text();
            const documento = new DOMParser().parseFromString(html, "text/html");
            const nueva = documento.getElementById("cuerpoTabla");

            if (nueva) {
                cuerpoTabla.innerHTML = nueva.innerHTML;
                history.replaceState(null, "", url);
            }
        } catch (error) {
            if (error.name !== "AbortError") {
                console.error(error);
            }
        }

        // muestra u oculta el boton mostrar todos
        botonLimpiar.hidden = campoBuscar.value.trim() === "";
    }

    // busca 300 milisegundos despues de dejar de escribir
    campoBuscar.addEventListener("input", function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(buscarMovimientos, 300);
        botonLimpiar.hidden = campoBuscar.value.trim() === "";
    });

    // si presiona Enter, busca sin recargar la pagina
    formBuscador.addEventListener("submit", function (evento) {
        evento.preventDefault();
        clearTimeout(temporizador);
        buscarMovimientos();
    });

    // mostrar todos: borra el texto y muestra todo
    botonLimpiar.addEventListener("click", function (evento) {
        evento.preventDefault();
        campoBuscar.value = "";
        buscarMovimientos();
        campoBuscar.focus();
    });
}

// solo letras, solo numeros o decimales (segun data-tipo)
document.querySelectorAll("[data-tipo]").forEach(function (campo) {
    campo.addEventListener("input", function () {
        if (campo.dataset.tipo === "letras") {
            campo.value = campo.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s]/g, "");
        } else if (campo.dataset.tipo === "numeros") {
            campo.value = campo.value.replace(/\D/g, "");
        } else if (campo.dataset.tipo === "decimal") {
            let valor = campo.value.replace(/,/g, ".").replace(/[^0-9.]/g, "");
            const punto = valor.indexOf(".");
            if (punto !== -1) {
                valor = valor.slice(0, punto + 1) + valor.slice(punto + 1).replace(/\./g, "");
            }
            campo.value = valor;
        }
    });
});

// mensajes de validacion en español
document.querySelectorAll(".formulario input, .formulario select").forEach(function (campo) {
    campo.addEventListener("invalid", function () {
        campo.setCustomValidity("");

        if (campo.validity.valueMissing) {
            campo.setCustomValidity(campo.tagName === "SELECT" ? "Por favor, selecciona una opción." : "Por favor, llena este campo.");
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