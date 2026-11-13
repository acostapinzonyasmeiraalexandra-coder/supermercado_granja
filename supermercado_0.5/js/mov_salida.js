// funcion que se ejecuta sola al cargar la pagina
(() => {
    // abrir y cerrar el menu lateral
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

    if (abrir) abrir.addEventListener("click", mostrarMenu);
    if (cerrar) cerrar.addEventListener("click", ocultarMenu);
    if (fondo) fondo.addEventListener("click", ocultarMenu);
    // escucha las teclas que presiona el usuario
    document.addEventListener("keydown", e => { if (e.key === "Escape") ocultarMenu(); });
})();

// busqueda de clientes, productos y total
const campoCliente = document.getElementById("cliente_busqueda");
const campoCedula = document.getElementById("cedula_cliente");
const listaClientes = document.getElementById("lista_clientes");
const estadoCliente = document.getElementById("estado_cliente");
let consultaClientes = 0;

// esta funcion da formato de pesos a un valor
function formato(valor) {
    return valor.toLocaleString("es-CO", { style: "currency", currency: "COP", maximumFractionDigits: 2 });
}

// esta funcion carga la factura en el marco para imprimirla
function generarFactura(codigo) {
    // elementos de la pagina que se usan en el codigo
    const marco = document.getElementById("marcoFactura");
    marco.src = "mov_salida.php?factura=" + encodeURIComponent(codigo) + "&t=" + Date.now();
}

if (campoCliente) {
    // escucha lo que el usuario escribe
    campoCliente.addEventListener("input", async function () {
        campoCedula.value = "";
        if (estadoCliente) estadoCliente.textContent = "";
        const texto = campoCliente.value.trim();
        consultaClientes++;
        const actual = consultaClientes;

        if (texto === "") {
            listaClientes.replaceChildren();
            return;
        }

        try {
            // pide los datos al servidor
            const resp = await fetch("mov_salida.php?buscar_cliente=" + encodeURIComponent(texto));
            if (!resp.ok) return;
            const clientes = await resp.json();
            if (actual !== consultaClientes) return;

            listaClientes.replaceChildren();
            clientes.forEach(c => {
                const opt = document.createElement("option");
                opt.value = c.cedula + " - " + c.nombre;
                listaClientes.appendChild(opt);
            });
        } catch (e) { console.error(e); }
    });

    // escucha cuando el usuario cambia el valor del campo
    campoCliente.addEventListener("change", async function () {
        const texto = campoCliente.value.trim();
        if (texto === "") return;

        try {
            // pide los datos al servidor
            const resp = await fetch("mov_salida.php?buscar_cliente=" + encodeURIComponent(texto));
            const clientes = await resp.json();
            const cliente = clientes.find(c => {
                const cd = String(c.cedula).trim();
                const nm = String(c.nombre).trim();
                return texto === cd || texto.toLowerCase() === nm.toLowerCase() || texto === cd + " - " + nm;
            });

            if (cliente) {
                campoCedula.value = String(cliente.cedula);
                campoCliente.value = cliente.cedula + " - " + cliente.nombre;
                if (estadoCliente) estadoCliente.textContent = "Cliente verificado: " + cliente.nombre;
            } else {
                campoCedula.value = "";
                if (estadoCliente) estadoCliente.textContent = "Cliente no válido. Elige una opción de las sugerencias.";
            }
        } catch (e) { console.error(e); }
    });
}

// esta funcion conecta los eventos de una fila de la tabla de productos
function conectarFila(fila) {
    const codigo = fila.querySelector(".codigo");
    const nombre = fila.querySelector(".nombre-producto");
    const precio = fila.querySelector(".precio");
    const cantidad = fila.querySelector(".cantidad");
    const quitar = fila.querySelector(".quitar");

    // escucha cuando el usuario cambia el valor del campo
    codigo.addEventListener("change", () => buscarProducto(fila));
    // escucha las teclas que presiona el usuario
    codigo.addEventListener("keydown", (e) => {
        if (e.key === "Enter") { e.preventDefault(); buscarProducto(fila); }
    });

    // escucha lo que el usuario escribe para sugerir productos por nombre o codigo
    nombre.addEventListener("input", () => sugerirProductos(nombre));
    // escucha cuando el usuario elige un producto de las sugerencias
    nombre.addEventListener("change", () => elegirProducto(fila));
    // escucha lo que el usuario escribe
    precio.addEventListener("input", calcularTotal);
    // escucha lo que el usuario escribe
    cantidad.addEventListener("input", calcularTotal);

    // escucha el clic del usuario
    quitar.addEventListener("click", () => {
        fila.remove();
        calcularTotal();
    });
}

// lista de sugerencias de productos
const listaProductos = document.getElementById("lista_productos");
let consultaProductos = 0;

// esta funcion llena las sugerencias con los productos que coinciden por codigo o nombre
async function sugerirProductos(campo) {
    const texto = campo.value.trim();
    consultaProductos++;
    const actual = consultaProductos;

    if (texto === "") {
        listaProductos.replaceChildren();
        return;
    }

    try {
        // pide los datos al servidor
        const resp = await fetch("mov_salida.php?buscar_producto=" + encodeURIComponent(texto));
        if (!resp.ok) return;
        const productos = await resp.json();
        if (actual !== consultaProductos) return;

        listaProductos.replaceChildren();
        productos.forEach(p => {
            const opt = document.createElement("option");
            opt.value = p.codigo + " - " + p.nombre;
            listaProductos.appendChild(opt);
        });
    } catch (e) { console.error(e); }
}

// esta funcion llena el codigo, el nombre y el precio con el producto elegido
async function elegirProducto(fila) {
    const campoCod = fila.querySelector(".codigo");
    const campoNom = fila.querySelector(".nombre-producto");
    const campoPre = fila.querySelector(".precio");
    const texto = campoNom.value.trim();

    if (texto === "") {
        campoCod.value = ""; campoPre.value = ""; calcularTotal(); return;
    }

    try {
        // pide los datos al servidor
        const resp = await fetch("mov_salida.php?buscar_producto=" + encodeURIComponent(texto));
        const productos = await resp.json();
        const prod = productos.find(p => {
            const cd = String(p.codigo).trim();
            const nm = String(p.nombre).trim();
            return texto === cd || texto.toLowerCase() === nm.toLowerCase() || texto === cd + " - " + nm;
        });

        if (prod) {
            campoCod.value = prod.codigo;
            campoNom.value = prod.nombre;
            campoPre.value = prod.precio;
        } else {
            campoCod.value = ""; campoNom.value = ""; campoPre.value = "";
            alert("No se encontró ese producto. Elige una opción de las sugerencias.");
        }
        calcularTotal();
    } catch (e) { console.error(e); }
}

// esta funcion agrega una fila nueva para otro producto
function agregarProducto() {
    const fila = document.createElement("tr");
    fila.innerHTML = `
        <td><input class="codigo" type="number" name="cod_producto[]" min="1" required></td>
        <td><input class="nombre-producto" type="text" name="nombre_producto[]" list="lista_productos" autocomplete="off" placeholder="Escriba código o nombre..." required></td>
        <td><input class="precio" type="number" name="precio[]" min="0" step="0.01" readonly required></td>
        <td><input class="cantidad" type="number" name="cantidad[]" min="1" step="1" value="1" required></td>
        <td class="subtotal derecha">$0</td>
        <td><button type="button" class="boton boton-chico boton-eliminar quitar">Quitar</button></td>
    `;
    document.getElementById("productos").appendChild(fila);
    conectarFila(fila);
    calcularTotal();
}

// esta funcion busca el producto por codigo y llena el nombre y el precio
async function buscarProducto(fila) {
    const campoCod = fila.querySelector(".codigo");
    const campoNom = fila.querySelector(".nombre-producto");
    const campoPre = fila.querySelector(".precio");
    const cod = campoCod.value.trim();

    if (cod === "") {
        campoNom.value = ""; campoPre.value = ""; calcularTotal(); return;
    }

    try {
        // pide los datos al servidor
        const resp = await fetch("mov_salida.php?producto_codigo=" + encodeURIComponent(cod));
        const prod = await resp.json();

        if (prod.existe) {
            campoCod.value = prod.codigo;
            campoNom.value = prod.nombre;
            campoPre.value = prod.precio;
            calcularTotal();
        } else {
            campoNom.value = ""; campoPre.value = ""; calcularTotal();
            alert("No se encontró producto con ese código.");
        }
    } catch (e) { console.error(e); }
}

// esta funcion calcula el subtotal de cada fila y el total de la factura
function calcularTotal() {
    let total = 0;
    document.querySelectorAll("#productos tr").forEach(fila => {
        const precio = parseFloat(fila.querySelector(".precio").value) || 0;
        const cantidad = parseInt(fila.querySelector(".cantidad").value) || 0;
        const subtotal = precio * cantidad;

        fila.querySelector(".subtotal").textContent = formato(subtotal);
        total += subtotal;
    });
    // elementos de la pagina que se usan en el codigo
    const elemTotal = document.getElementById("totalFactura");
    if (elemTotal) elemTotal.textContent = formato(total);
}

// ventana para confirmar la eliminacion, con el mismo funcionamiento de clientes
(() => {
    const modal = document.getElementById("modalEliminar");
    if (!modal) return;

    const texto = document.getElementById("textoEliminar");
    const botonConfirmar = document.getElementById("confirmarEliminar");
    const botonCancelar = document.getElementById("cancelarEliminar");
    let formularioPendiente = null;

    function abrirModal(formulario) {
        formularioPendiente = formulario;
        const fila = formulario.closest("tr");
        const codigo = fila ? fila.children[0].textContent.trim() : "";
        texto.textContent = codigo
            ? "¿Seguro que deseas eliminar el movimiento #" + codigo + "? Esta acción no se puede deshacer."
            : "¿Seguro que deseas eliminar este movimiento? Esta acción no se puede deshacer.";
        modal.hidden = false;
        botonCancelar.focus();
    }

    function cerrarModal() {
        modal.hidden = true;
        formularioPendiente = null;
    }

    document.addEventListener("submit", function (evento) {
        if (evento.target.classList.contains("form-eliminar")) {
            evento.preventDefault();
            abrirModal(evento.target);
        }
    });

    botonConfirmar.addEventListener("click", function () {
        if (!formularioPendiente) return;
        const oculto = document.createElement("input");
        oculto.type = "hidden";
        oculto.name = "eliminar_movimiento";
        oculto.value = "1";
        formularioPendiente.appendChild(oculto);
        formularioPendiente.submit();
    });

    botonCancelar.addEventListener("click", cerrarModal);
    modal.addEventListener("click", function (evento) {
        if (evento.target === modal) cerrarModal();
    });
    document.addEventListener("keydown", function (evento) {
        if (evento.key === "Escape" && !modal.hidden) cerrarModal();
    });
})();

// busqueda en vivo en cada tabla y aviso cuando no hay coincidencias
 document.querySelectorAll(".buscar-tabla").forEach(campo => {
    campo.addEventListener("input", function () {
        const tabla = document.getElementById(this.dataset.tabla);
        if (!tabla) return;

        const texto = this.value.toLocaleLowerCase("es").trim();
        const filas = Array.from(tabla.querySelectorAll("tbody tr"));
        let encontrados = 0;

        filas.forEach(fila => {
            if (fila.classList.contains("fila-sin-resultados")) return;
            const coincide = fila.textContent.toLocaleLowerCase("es").includes(texto);
            fila.style.display = coincide ? "" : "none";
            if (coincide) encontrados++;
        });

        let aviso = tabla.querySelector(".fila-sin-resultados");
        if (encontrados === 0 && texto !== "") {
            if (!aviso) {
                aviso = document.createElement("tr");
                aviso.className = "fila-sin-resultados";
                const celda = document.createElement("td");
                celda.colSpan = tabla.tHead ? tabla.tHead.rows[0].cells.length : 1;
                celda.className = "tabla-vacia";
                celda.textContent = "No se encontraron resultados.";
                aviso.appendChild(celda);
                tabla.tBodies[0].appendChild(aviso);
            }
            aviso.style.display = "";
        } else if (aviso) {
            aviso.remove();
        }
    });
});

document.querySelectorAll("#productos tr").forEach(conectarFila);
if (document.querySelectorAll("#productos tr").length === 0 && document.getElementById("productos")) {
    agregarProducto();
}
calcularTotal();