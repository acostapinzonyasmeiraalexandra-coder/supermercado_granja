// menu lateral
const abrir = document.getElementById("abrirMenu");
const cerrar = document.getElementById("cerrarMenu");
const fondo = document.getElementById("fondoMenu");

if (abrir) {
    abrir.addEventListener("click", function () {
        document.body.classList.add("menu-abierto");
        abrir.setAttribute("aria-expanded", "true");
    });
}

function ocultarMenu() {
    document.body.classList.remove("menu-abierto");
    if (abrir) {
        abrir.setAttribute("aria-expanded", "false");
    }
}

if (cerrar) cerrar.addEventListener("click", ocultarMenu);
if (fondo) fondo.addEventListener("click", ocultarMenu);

document.addEventListener("keydown", function (evento) {
    if (evento.key === "Escape") {
        ocultarMenu();
    }
});

// variables del formulario
const formularioProducto = document.getElementById("formularioProducto");
const campoCodigoProd = document.getElementById("cod_producto");
const errorCodigo = document.getElementById("error_codigo");
const campoNombre = document.getElementById("nombre");
const errorNombre = document.getElementById("error_nombre");
const campoProveedor = document.getElementById("proveedor_busqueda");
const campoCodigoProv = document.getElementById("cod_proveedor");
const listaProveedores = document.getElementById("lista_proveedores");
const estadoProveedor = document.getElementById("estado_proveedor");

// revisar en php si el codigo ya existe
async function revisarCodigo() {
    if (!campoCodigoProd || !errorCodigo) return false;

    const valor = campoCodigoProd.value.trim();
    if (valor === "") {
        errorCodigo.textContent = "";
        return false;
    }

    const res = await fetch("producto.php?verificar_producto=codigo&valor=" + encodeURIComponent(valor));
    const datos = await res.json();

    if (datos.existe) {
        errorCodigo.textContent = "Este código de producto ya está registrado. Sugerido: " + datos.sugerido;
        return true;
    } else {
        errorCodigo.textContent = "";
        return false;
    }
}

// revisar en php si el nombre ya existe
async function revisarNombre() {
    if (!campoNombre || !errorNombre) return false;

    const valor = campoNombre.value.trim();
    const codigoActual = formularioProducto.getAttribute("data-codigo-actual") || "";

    if (valor === "") {
        errorNombre.textContent = "";
        return false;
    }

    const url = "producto.php?verificar_producto=nombre&valor=" + encodeURIComponent(valor) + "&excluir=" + encodeURIComponent(codigoActual);
    const res = await fetch(url);
    const datos = await res.json();

    if (datos.existe) {
        errorNombre.textContent = "Ya existe un producto con este nombre (código " + datos.codigo + ").";
        return true;
    } else {
        errorNombre.textContent = "";
        return false;
    }
}

if (campoCodigoProd) {
    campoCodigoProd.addEventListener("input", revisarCodigo);
    campoCodigoProd.addEventListener("change", revisarCodigo);
    campoCodigoProd.addEventListener("blur", revisarCodigo);
}

if (campoNombre) {
    campoNombre.addEventListener("input", revisarNombre);
    campoNombre.addEventListener("change", revisarNombre);
    campoNombre.addEventListener("blur", revisarNombre);
}

// buscar y validar proveedor
async function buscarProveedores(texto) {
    const res = await fetch("producto.php?buscar_proveedor=" + encodeURIComponent(texto));
    return await res.json();
}

if (campoProveedor) {
    campoProveedor.addEventListener("input", async function () {
        campoCodigoProv.value = "";
        estadoProveedor.textContent = "";
        const texto = campoProveedor.value.trim();

        if (texto === "") {
            listaProveedores.innerHTML = "";
            return;
        }

        const proveedores = await buscarProveedores(texto);
        listaProveedores.innerHTML = "";

        proveedores.forEach(function (prov) {
            const opcion = document.createElement("option");
            opcion.value = prov.codigo + " - " + prov.nombre;
            listaProveedores.appendChild(opcion);
        });
    });

    campoProveedor.addEventListener("change", validarProveedor);
}

async function validarProveedor() {
    const texto = campoProveedor.value.trim();
    campoCodigoProv.value = "";

    if (texto === "") {
        estadoProveedor.textContent = "Escribe y selecciona un proveedor.";
        return false;
    }

    const proveedores = await buscarProveedores(texto);
    const textoMinuscula = texto.toLowerCase();

    const encontrado = proveedores.find(function (p) {
        return texto === p.codigo ||
               textoMinuscula === p.nombre.toLowerCase() ||
               texto === p.codigo + " - " + p.nombre;
    });

    if (!encontrado) {
        estadoProveedor.textContent = "Proveedor no válido. Elige uno de la lista.";
        return false;
    }

    campoCodigoProv.value = encontrado.codigo;
    campoProveedor.value = encontrado.codigo + " - " + encontrado.nombre;
    estadoProveedor.textContent = "";
    return true;
}

// validar todo antes de enviar el formulario
if (formularioProducto) {
    formularioProducto.addEventListener("submit", async function (evento) {
        evento.preventDefault();

        const botonPulsado = evento.submitter;
        const codMal = await revisarCodigo();
        const nomMal = await revisarNombre();
        const provBien = await validarProveedor();

        if (codMal) {
            campoCodigoProd.focus();
            return;
        }

        if (nomMal) {
            campoNombre.focus();
            return;
        }

        if (!provBien) {
            campoProveedor.focus();
            return;
        }

        if (botonPulsado && botonPulsado.name) {
            const inputBoton = document.createElement("input");
            inputBoton.type = "hidden";
            inputBoton.name = botonPulsado.name;
            inputBoton.value = "1";
            formularioProducto.appendChild(inputBoton);
        }

        formularioProducto.submit();
    });
}

// modal para confirmar eliminacion
const modal = document.getElementById("modalEliminar");
if (modal) {
    const textoModal = document.getElementById("textoEliminar");
    const botonConfirmar = document.getElementById("confirmarEliminar");
    const botonCancelar = document.getElementById("cancelarEliminar");
    let formularioEliminar = null;

    document.addEventListener("submit", function (e) {
        if (e.target.classList.contains("form-eliminar")) {
            e.preventDefault();
            formularioEliminar = e.target;
            const fila = formularioEliminar.closest("tr");
            const nombreProd = fila ? fila.children[1].textContent.trim() : "";

            textoModal.textContent = "¿Seguro que deseas eliminar el producto " + nombreProd + "? Esta acción no se puede deshacer.";
            modal.hidden = false;
        }
    });

    botonConfirmar.addEventListener("click", function () {
        if (!formularioEliminar) return;
        const oculto = document.createElement("input");
        oculto.type = "hidden";
        oculto.name = "eliminar_producto";
        oculto.value = "1";
        formularioEliminar.appendChild(oculto);
        formularioEliminar.submit();
    });

    botonCancelar.addEventListener("click", function () {
        modal.hidden = true;
        formularioEliminar = null;
    });
}

// busqueda en vivo en la tabla
const entradaBuscar = document.getElementById("campoBuscar");
const cuerpoTabla = document.getElementById("cuerpoTabla");
const formBuscador = document.getElementById("formBuscador");
const botonLimpiar = document.getElementById("limpiarBusqueda");

if (entradaBuscar && cuerpoTabla) {
    let tiempoEspera = null;

    async function buscarProductos() {
        const texto = entradaBuscar.value.trim();
        const url = "producto.php" + (texto !== "" ? "?buscar=" + encodeURIComponent(texto) : "");

        const res = await fetch(url);
        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, "text/html");
        const nuevaTabla = doc.getElementById("cuerpoTabla");

        if (nuevaTabla) {
            cuerpoTabla.innerHTML = nuevaTabla.innerHTML;
        }

        if (botonLimpiar) {
            botonLimpiar.hidden = (texto === "");
        }
    }

    entradaBuscar.addEventListener("input", function () {
        clearTimeout(tiempoEspera);
        tiempoEspera = setTimeout(buscarProductos, 300);
    });

    if (formBuscador) {
        formBuscador.addEventListener("submit", function (e) {
            e.preventDefault();
            buscarProductos();
        });
    }

    if (botonLimpiar) {
        botonLimpiar.addEventListener("click", function (e) {
            e.preventDefault();
            entradaBuscar.value = "";
            buscarProductos();
        });
    }
}

// filtrar caracteres mientras se escribe
document.querySelectorAll("[data-tipo]").forEach(function (campo) {
    campo.addEventListener("input", function () {
        if (campo.dataset.tipo === "letras") {
            campo.value = campo.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s]/g, "");
        } else if (campo.dataset.tipo === "numeros") {
            campo.value = campo.value.replace(/\D/g, "");
        } else if (campo.dataset.tipo === "decimal") {
            let val = campo.value.replace(/,/g, ".").replace(/[^0-9.]/g, "");
            const punto = val.indexOf(".");
            if (punto !== -1) {
                val = val.slice(0, punto + 1) + val.slice(punto + 1).replace(/\./g, "");
            }
            campo.value = val;
        }
    });
});