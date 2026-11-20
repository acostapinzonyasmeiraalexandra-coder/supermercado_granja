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

// pide datos al servidor y devuelve la lista (o vacia si falla)
async function pedirLista(url) {
    try {
        const respuesta = await fetch(url);
        return respuesta.ok ? await respuesta.json() : [];
    } catch (error) {
        return [];
    }
}

// da formato de pesos a un numero
function formato(valor) {
    return valor.toLocaleString("es-CO", { style: "currency", currency: "COP", maximumFractionDigits: 2 });
}

// elementos del formulario (solo existen si se muestra el formulario)
const formulario = document.getElementById("formularioFactura");
const tbody = document.getElementById("productos");
const campoCliente = document.getElementById("cliente_busqueda");
const campoCedula = document.getElementById("cedula_cliente");
const listaClientes = document.getElementById("lista_clientes");
const estadoCliente = document.getElementById("estado_cliente");
const listaProductos = document.getElementById("lista_productos");

// calcula el subtotal de cada fila y el total
function calcularTotal() {
    let total = 0;

    document.querySelectorAll("#productos tr").forEach(function (fila) {
        const precio = parseFloat(fila.querySelector(".precio").value) || 0;
        const cantidad = parseInt(fila.querySelector(".cantidad").value) || 0;

        fila.querySelector(".subtotal").textContent = formato(precio * cantidad);
        total += precio * cantidad;
    });

    document.getElementById("totalFactura").textContent = formato(total);
}

// agrega una fila vacia para otro producto
function agregarProducto() {
    const fila = document.createElement("tr");
    fila.innerHTML = `
        <td><input class="codigo" type="number" name="cod_producto[]" min="1"></td>
        <td><input class="nombre-producto" type="text" name="nombre_producto[]" list="lista_productos" autocomplete="off" placeholder="Escriba código o nombre..."></td>
        <td><input class="precio" type="number" name="precio[]" min="0" step="0.01" readonly></td>
        <td><input class="cantidad" type="number" name="cantidad[]" min="1" step="1" value="1"></td>
        <td class="subtotal derecha">$0</td>
        <td><button type="button" class="boton boton-chico boton-eliminar quitar">Quitar</button></td>
    `;
    tbody.appendChild(fila);
    calcularTotal();
}

// busca el producto escrito (por codigo o nombre) y llena la fila
async function llenarFila(fila, texto) {
    const campoCodigo = fila.querySelector(".codigo");
    const campoNombre = fila.querySelector(".nombre-producto");
    const campoPrecio = fila.querySelector(".precio");

    texto = texto.trim();
    campoCodigo.value = "";
    campoNombre.value = "";
    campoPrecio.value = "";

    if (texto !== "") {
        const productos = await pedirLista("mov_salida.php?buscar_producto=" + encodeURIComponent(texto));

        const producto = productos.find(function (p) {
            return texto === String(p.codigo).trim() ||
                texto.toLowerCase() === String(p.nombre).trim().toLowerCase() ||
                texto === p.codigo + " - " + p.nombre;
        });

        if (producto) {
            campoCodigo.value = producto.codigo;
            campoNombre.value = producto.nombre;
            campoPrecio.value = producto.precio;
        } else {
            alert("No se encontró ese producto. Elige una opción de las sugerencias.");
        }
    }

    calcularTotal();
}

if (formulario) {
    // cliente: sugerencias mientras escribe
    campoCliente.addEventListener("input", async function () {
        campoCedula.value = "";
        estadoCliente.textContent = "";
        listaClientes.replaceChildren();

        const texto = campoCliente.value.trim();
        if (texto === "") {
            return;
        }

        const clientes = await pedirLista("mov_salida.php?buscar_cliente=" + encodeURIComponent(texto));

        clientes.forEach(function (c) {
            const opcion = document.createElement("option");
            opcion.value = c.cedula + " - " + c.nombre;
            listaClientes.appendChild(opcion);
        });
    });

    // cliente: verifica lo escrito cuando termina de elegir
    campoCliente.addEventListener("change", async function () {
        const texto = campoCliente.value.trim();
        if (texto === "") {
            return;
        }

        const clientes = await pedirLista("mov_salida.php?buscar_cliente=" + encodeURIComponent(texto));

        const cliente = clientes.find(function (c) {
            return texto === String(c.cedula).trim() ||
                texto.toLowerCase() === String(c.nombre).trim().toLowerCase() ||
                texto === c.cedula + " - " + c.nombre;
        });

        if (cliente) {
            campoCedula.value = String(cliente.cedula);
            campoCliente.value = cliente.cedula + " - " + cliente.nombre;
            estadoCliente.textContent = "Cliente verificado: " + cliente.nombre;
        } else {
            campoCedula.value = "";
            estadoCliente.textContent = "Cliente no válido. Elige una opción de las sugerencias.";
        }
    });

    // productos: mientras escribe en una fila
    tbody.addEventListener("input", async function (evento) {
        const campo = evento.target;

        // sugerencias de productos
        if (campo.classList.contains("nombre-producto")) {
            listaProductos.replaceChildren();

            if (campo.value.trim() !== "") {
                const productos = await pedirLista("mov_salida.php?buscar_producto=" + encodeURIComponent(campo.value.trim()));

                productos.forEach(function (p) {
                    const opcion = document.createElement("option");
                    opcion.value = p.codigo + " - " + p.nombre;
                    listaProductos.appendChild(opcion);
                });
            }
        }

        // recalcula el total si cambia el precio o la cantidad
        if (campo.classList.contains("cantidad") || campo.classList.contains("precio")) {
            calcularTotal();
        }
    });

    // productos: cuando termina de elegir el codigo o el nombre
    tbody.addEventListener("change", function (evento) {
        if (evento.target.classList.contains("codigo") || evento.target.classList.contains("nombre-producto")) {
            llenarFila(evento.target.closest("tr"), evento.target.value);
        }
    });

    // productos: con enter en el codigo busca el producto y no envia el formulario
    tbody.addEventListener("keydown", function (evento) {
        if (evento.key === "Enter" && evento.target.classList.contains("codigo")) {
            evento.preventDefault();
            llenarFila(evento.target.closest("tr"), evento.target.value);
        }
    });

    // productos: boton quitar
    tbody.addEventListener("click", function (evento) {
        if (evento.target.classList.contains("quitar")) {
            evento.target.closest("tr").remove();
            calcularTotal();
        }
    });

    // boton agregar producto
    document.getElementById("btnAgregarProducto").addEventListener("click", agregarProducto);

    // revisa los datos antes de guardar
    formulario.addEventListener("submit", function (evento) {
        if (campoCedula.value === "") {
            evento.preventDefault();
            alert("Selecciona un cliente registrado de las sugerencias.");
            campoCliente.focus();
            return;
        }

        const filas = document.querySelectorAll("#productos tr");

        if (filas.length === 0) {
            evento.preventDefault();
            alert("Agrega al menos un producto.");
            return;
        }

        for (const fila of filas) {
            const cantidad = parseInt(fila.querySelector(".cantidad").value) || 0;

            if (fila.querySelector(".codigo").value === "" || fila.querySelector(".precio").value === "" || cantidad < 1) {
                evento.preventDefault();
                alert("Revisa los productos: elige un producto de las sugerencias y una cantidad mayor a 0.");
                return;
            }
        }
    });

    // si no hay filas (salida nueva), crea la primera
    if (tbody.querySelectorAll("tr").length === 0) {
        agregarProducto();
    }

    calcularTotal();
}

// botones de factura: cargan la factura en un marco invisible y abren la ventana de imprimir
const marcoFactura = document.createElement("iframe");
marcoFactura.style.cssText = "position:fixed;width:0;height:0;border:0;";
document.body.appendChild(marcoFactura);

// cuando la factura termina de cargar, la manda a imprimir
marcoFactura.addEventListener("load", function () {
    if (marcoFactura.src.includes("factura=")) {
        marcoFactura.contentWindow.focus();
        marcoFactura.contentWindow.print();
    }
});

document.querySelectorAll(".btn-ver-factura").forEach(function (boton) {
    boton.addEventListener("click", function () {
        marcoFactura.src = "mov_salida.php?factura=" + encodeURIComponent(boton.dataset.codigo) + "&t=" + Date.now();
    });
});

// ventana para confirmar la eliminacion (solo administrador)
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
                ? "¿Seguro que deseas eliminar el movimiento #" + codigo + "? Esta acción no se puede deshacer."
                : "¿Seguro que deseas eliminar este movimiento? Esta acción no se puede deshacer.";

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

    // cancelar: con el boton, con clic en el fondo oscuro o con escape
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

// busqueda en vivo en las tablas (solo administrador)
document.querySelectorAll(".buscar-tabla").forEach(function (campo) {
    campo.addEventListener("input", function () {
        const tabla = document.getElementById(campo.dataset.tabla);
        const texto = campo.value.toLocaleLowerCase("es").trim();
        let encontrados = 0;

        // muestra u oculta cada fila segun coincida con el texto
        tabla.querySelectorAll("tbody tr").forEach(function (fila) {
            if (fila.classList.contains("fila-sin-resultados")) {
                return;
            }
            const coincide = fila.textContent.toLocaleLowerCase("es").includes(texto);
            fila.style.display = coincide ? "" : "none";
            if (coincide) {
                encontrados++;
            }
        });

        // aviso cuando no hay coincidencias
        let aviso = tabla.querySelector(".fila-sin-resultados");

        if (encontrados === 0 && texto !== "") {
            if (!aviso) {
                aviso = document.createElement("tr");
                aviso.className = "fila-sin-resultados";
                aviso.innerHTML = '<td colspan="' + tabla.tHead.rows[0].cells.length + '" class="tabla-vacia">No se encontraron resultados.</td>';
                tabla.tBodies[0].appendChild(aviso);
            }
        } else if (aviso) {
            aviso.remove();
        }
    });
});