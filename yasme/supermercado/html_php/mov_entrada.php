<?php
/* 
   - Empleado (cargo 2): .
   - Administrador (cargo 1)
    */

session_start();

// Si no hay usuario o cargo en la sesión, lo mandamos al inicio
if (!isset($_SESSION["usuario"]) || !isset($_SESSION["cargo"])) {
    header("Location: index.html");
    exit();
}

// "1" = administrador, "2" = empleado
$cargo = (string)$_SESSION["cargo"];

// Si el cargo no es 1 ni 2, lo mandamos al menú
if ($cargo !== "1" && $cargo !== "2") {
    header("Location: menu.php");
    exit();
}

// true si es administrador, false si es empleado
$esAdmin = ($cargo === "1");

include("conexion.php");

if (isset($conexion) && !isset($conn)) {
    $conn = $conexion;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mensaje = "";
$movimiento_editar = null;
$busqueda = "";

// Textos y códigos que se muestran en los campos de proveedor y producto
$proveedorTexto = "";
$proveedorCodigo = "";
$productoTexto = "";
$productoCodigo = "";


/* 
   Autocompletar
    */

function responderBusqueda($conn, $tabla, $campoCodigo, $textoBuscado)
{
    // Avisamos que la respuesta será JSON (datos para JavaScript)
    header("Content-Type: application/json; charset=utf-8");

    try {

        $textoBuscado = trim($textoBuscado);

        // Si no escribió nada, devolvemos una lista vacía
        if ($textoBuscado === "") {
            echo json_encode([]);
            exit;
        }

        // Si el texto es "3 - Lácteos", el código es lo que está antes del " - "
        $parteCodigo = trim(explode(" - ", $textoBuscado)[0]);

        // Los % significan "que contenga este texto"
        $filtro = "%" . $textoBuscado . "%";
        $filtroCodigo = "%" . $parteCodigo . "%";

        // $tabla y $campoCodigo los escribimos nosotros más abajo (no vienen del usuario)
        $stmt = $conn->prepare(
            "SELECT $campoCodigo AS codigo, nombre
             FROM $tabla
             WHERE CAST($campoCodigo AS CHAR) LIKE ?
                OR nombre LIKE ?
             ORDER BY
                CASE
                    WHEN CAST($campoCodigo AS CHAR) = ? THEN 0
                    WHEN nombre = ? THEN 0
                    ELSE 1
                END,
                nombre ASC
             LIMIT 10"
        );
        $stmt->bind_param("ssss", $filtroCodigo, $filtro, $parteCodigo, $textoBuscado);
        $stmt->execute();
        $res = $stmt->get_result();

        $lista = [];
        while ($fila = $res->fetch_assoc()) {
            $lista[] = [
                "codigo" => $fila["codigo"],
                "nombre" => $fila["nombre"]
            ];
        }
        $stmt->close();

        echo json_encode($lista, JSON_UNESCAPED_UNICODE);

    } catch (Throwable $ex) {

        http_response_code(500);
        echo json_encode([]);
    }

    exit; // Terminamos aquí: no hay que dibujar la página
}

if (isset($_GET["buscar_proveedor"])) {
    responderBusqueda($conn, "proveedor", "cod_proveedor", $_GET["buscar_proveedor"]);
}

if (isset($_GET["buscar_producto"])) {
    responderBusqueda($conn, "producto", "cod_producto", $_GET["buscar_producto"]);
}




if ((isset($_GET['borrar']) || isset($_GET['editar'])) && !$esAdmin) {

    $mensaje = "<p style='color: red; font-weight: bold;'>
    No tiene permiso para editar ni eliminar movimientos.
    </p>";
}


/* Borrar (solo administrador) */

if (isset($_GET['borrar']) && $esAdmin) {

    $codigo = intval($_GET['borrar']);

    try {

        $stmt = $conn->prepare("DELETE FROM mov_entrada WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $mensaje = "Movimiento eliminado correctamente.";
        $stmt->close();

    } catch (mysqli_sql_exception $e) {

        if ($e->getCode() == 1451) {

            $mensaje = "<p style='color: red; font-weight: bold;'>
            Error: No se puede eliminar el movimiento porque está relacionado con otros registros.
            </p>";

        } else {

            $mensaje = "<p style='color: red; font-weight: bold;'>
            Error en BD: " . htmlspecialchars($e->getMessage()) . "
            </p>";
        }
    }
}


/* Editar (solo administrador) */

if (isset($_GET['editar']) && $esAdmin) {

    $codigo = intval($_GET['editar']);

    try {

        $stmt = $conn->prepare("SELECT * FROM mov_entrada WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $resultado = $stmt->get_result();
        if ($resultado->num_rows > 0) {

            $movimiento_editar = $resultado->fetch_assoc();

            // Para mostrar "código - nombre" en los campos de proveedor y producto
            $proveedorCodigo = $movimiento_editar['cod_proveedor'];
            $productoCodigo = $movimiento_editar['cod_producto'];

            $stmtNombre = $conn->prepare("SELECT nombre FROM proveedor WHERE cod_proveedor = ?");
            $stmtNombre->bind_param("i", $proveedorCodigo);
            $stmtNombre->execute();
            $filaNombre = $stmtNombre->get_result()->fetch_assoc();
            $stmtNombre->close();

            if ($filaNombre) {
                $proveedorTexto = $proveedorCodigo . " - " . $filaNombre['nombre'];
            }

            $stmtNombre = $conn->prepare("SELECT nombre FROM producto WHERE cod_producto = ?");
            $stmtNombre->bind_param("i", $productoCodigo);
            $stmtNombre->execute();
            $filaNombre = $stmtNombre->get_result()->fetch_assoc();
            $stmtNombre->close();

            if ($filaNombre) {
                $productoTexto = $productoCodigo . " - " . $filaNombre['nombre'];
            }

        }
        $stmt->close();

    } catch (mysqli_sql_exception $e) {

        $mensaje = "<p style='color: red; font-weight: bold;'>
        Error en BD: " . htmlspecialchars($e->getMessage()) . "
        </p>";
    }
}


/* Guardar y actualizar */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST['accion'] ?? "";

    $codigo = intval($_POST['cod_movimiento'] ?? 0);
    $fecha = $_POST['fecha'] ?? "";
    $precio = floatval($_POST['precio'] ?? 0);
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $nota = $_POST['nota'] ?? "";
    $cod_proveedor = intval($_POST['cod_proveedor'] ?? 0);
    $cod_producto = intval($_POST['cod_producto'] ?? 0);


    /* Reglas de permisos:
       - Solo el empleado registra entradas nuevas.
       - Solo el administrador actualiza. */

    if ($accion === "guardar" && $esAdmin) {

        $mensaje = "<p style='color: red; font-weight: bold;'>
        El administrador no registra entradas nuevas.
        </p>";
    }

    if ($accion === "actualizar" && !$esAdmin) {

        $mensaje = "<p style='color: red; font-weight: bold;'>
        No tiene permiso para actualizar movimientos.
        </p>";
    }


    /* GUARDAR */

    if ($accion === "guardar" && !$esAdmin) {

        try {

            $stmt = $conn->prepare("INSERT INTO mov_entrada(cod_movimiento, fecha, precio, cantidad, nota, cod_proveedor, cod_producto)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param("isdisii",$codigo,$fecha,$precio,$cantidad,$nota,$cod_proveedor,$cod_producto);
            $stmt->execute();
            $mensaje = "Movimiento guardado correctamente.";
            $stmt->close();

        } catch (mysqli_sql_exception $e) {

            if ($e->getCode() == 1062) {

                $mensaje = "<p style='color: red; font-weight: bold;'>
                Error: El código del movimiento (" . htmlspecialchars($codigo) . ") ya existe.
                </p>";

            } elseif ($e->getCode() == 1452) {

                $mensaje = "<p style='color: red; font-weight: bold;'>
                Error: El código del proveedor o el código del producto seleccionado no existe.
                </p>";

            } else {

                $mensaje = "<p style='color: red; font-weight: bold;'>
                Error en BD: " . htmlspecialchars($e->getMessage()) . "
                </p>";
            }
        }
    }


    /* Actualizar*/

    if ($accion === "actualizar" && $esAdmin) {

        try {

            $stmt = $conn->prepare("UPDATE mov_entrada SET fecha = ?,precio = ?,cantidad = ?,nota = ?,cod_proveedor = ?,cod_producto = ?
                 WHERE cod_movimiento = ?"
            );

            $stmt->bind_param("sdisiii",$fecha,$precio,$cantidad,$nota,$cod_proveedor,$cod_producto,$codigo);
            $stmt->execute();
            $mensaje = "Movimiento actualizado correctamente.";
            $stmt->close();

        } catch (mysqli_sql_exception $e) {

            if ($e->getCode() == 1452) {

                $mensaje = "<p style='color: red; font-weight: bold;'>
                Error: El código del proveedor o el código del producto seleccionado no existe.
                </p>";

            } else {

                $mensaje = "<p style='color: red; font-weight: bold;'>
                Error en BD: " . htmlspecialchars($e->getMessage()) . "
                </p>";
            }
        }
    }
}


/* buscar*/

if (isset($_GET['buscar'])) {

    $busqueda = trim($_GET['buscar']);

}


/* Lista de movimiento(solo administrador) */

if ($esAdmin) {

$sql = "SELECT m.cod_movimiento,m.fecha,m.precio,m.cantidad,m.nota,m.cod_proveedor,p.nombre AS nombre_proveedor,
            m.cod_producto,
            pr.nombre AS nombre_producto
        FROM mov_entrada m
        LEFT JOIN proveedor p 
            ON m.cod_proveedor = p.cod_proveedor
        LEFT JOIN producto pr 
            ON m.cod_producto = pr.cod_producto";


if ($busqueda !== "") {

    $sql .= " WHERE m.cod_movimiento LIKE ? OR m.fecha LIKE ? OR m.cod_proveedor LIKE ? OR p.nombre LIKE ? OR m.cod_producto LIKE ?
              OR pr.nombre LIKE ?";
}


$sql .= " ORDER BY m.cod_movimiento ASC";


$stmt = $conn->prepare($sql);


if ($busqueda !== "") {

    $texto = "%" . $busqueda . "%";

    $stmt->bind_param("ssssss",$texto,$texto,$texto,$texto,$texto,$texto);
}
$stmt->execute();
$resultado = $stmt->get_result();

}




?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Movimiento Entrada</title>

</head>

<body>


<h2>Movimiento Entrada</h2>

<!-- Botón para volver al menú -->
<form action="menu.php" method="GET">
    <button type="submit">Menu Principal</button>
</form>


<?php if ($mensaje != ""): ?>

    <?php echo $mensaje; ?>

<?php endif; ?>


<!-- FORMULARIO -->

<?php if ($movimiento_editar): ?>


    <h4>Modificar Movimiento</h4>


    <form method="POST" id="formularioEntrada">

        <input
            type="hidden"
            name="accion"
            value="actualizar"
        >


        <input
            type="hidden"
            name="cod_movimiento"
            value="<?php echo $movimiento_editar['cod_movimiento']; ?>"
        >


        <label>Codigo Movimiento:</label><br>
        <input type="number" value="<?php echo $movimiento_editar['cod_movimiento']; ?>" disabled>

        <br><br>

        <label>Fecha:</label><br>
        <input type="date" name="fecha" value="<?php echo $movimiento_editar['fecha']; ?>" required>

        <br><br>
        <label>Precio:</label><br>
        <input type="number" name="precio" step="0.01" value="<?php echo $movimiento_editar['precio']; ?>" required >

        <br><br>

        <label>Cantidad:</label><br>
        <input type="number" name="cantidad" value="<?php echo $movimiento_editar['cantidad']; ?>" required >

        <br><br>

        <label>Nota:</label><br>
        <textarea name="nota"><?php echo htmlspecialchars($movimiento_editar['nota'] ?? ''); ?></textarea>

        <br><br>

        <label>Proveedor:</label><br>
        <input type="text" id="proveedor_busqueda"  list="lista_proveedores" autocomplete="off"  placeholder="Escriba el código o el nombre"  value="<?php echo htmlspecialchars($proveedorTexto); ?>"  required >

        <!-- Aquí JavaScript pone las sugerencias -->
        <datalist id="lista_proveedores"></datalist>

        <!-- Campo oculto: aquí queda el código que realmente se envía -->
        <input type="hidden" name="cod_proveedor" id="cod_proveedor" value="<?php echo htmlspecialchars($proveedorCodigo); ?>" >

        <p id="estado_proveedor"></p>

        <br><br>

        <label>Producto:</label><br>
        <input type="text" id="producto_busqueda" list="lista_productos" autocomplete="off" placeholder="Escriba el código o el nombre" value="<?php echo htmlspecialchars($productoTexto); ?>" required >

        <!-- Aquí JavaScript pone las sugerencias -->
        <datalist id="lista_productos"></datalist>

        <!-- Campo oculto: aquí queda el código que realmente se envía -->
        <input
            type="hidden"
            name="cod_producto"
            id="cod_producto"
            value="<?php echo htmlspecialchars($productoCodigo); ?>"
        >

        <p id="estado_producto"></p>

        <br><br>


        <button type="submit">
            Actualizar
        </button>


        <a href="mov_entrada.php">
            Cancelar
        </a>

    </form>


<?php elseif (!$esAdmin): ?>


    <form method="POST" id="formularioEntrada">

        <input
            type="hidden"
            name="accion"
            value="guardar"
        >


        <label>Codigo Movimiento:</label><br>

        <input
            type="number"
            name="cod_movimiento"
            required
            placeholder="Codigo"
        >

        <br><br>


        <label>Fecha:</label><br>

        <input
            type="date"
            name="fecha"
            required
        >

        <br><br>


        <label>Precio:</label><br>

        <input
            type="number"
            name="precio"
            step="0.01"
            required
            placeholder="Precio"
        >

        <br><br>


        <label>Cantidad:</label><br>

        <input
            type="number"
            name="cantidad"
            required
            placeholder="Cantidad"
        >

        <br><br>


        <label>Nota:</label><br>

        <textarea
            name="nota"
            placeholder="Nota"
        ></textarea>

        <br><br>


        <label>Proveedor:</label><br>

        <input
            type="text"
            id="proveedor_busqueda"
            list="lista_proveedores"
            autocomplete="off"
            placeholder="Escriba el código o el nombre"
            value="<?php echo htmlspecialchars($proveedorTexto); ?>"
            required
        >

        <!-- Aquí JavaScript pone las sugerencias -->
        <datalist id="lista_proveedores"></datalist>

        <!-- Campo oculto: aquí queda el código que realmente se envía -->
        <input
            type="hidden"
            name="cod_proveedor"
            id="cod_proveedor"
            value="<?php echo htmlspecialchars($proveedorCodigo); ?>"
        >

        <p id="estado_proveedor"></p>

        <br>


        <label>Producto:</label><br>

        <input
            type="text"
            id="producto_busqueda"
            list="lista_productos"
            autocomplete="off"
            placeholder="Escriba el código o el nombre"
            value="<?php echo htmlspecialchars($productoTexto); ?>"
            required
        >

        <!-- Aquí JavaScript pone las sugerencias -->
        <datalist id="lista_productos"></datalist>

        <!-- Campo oculto: aquí queda el código que realmente se envía -->
        <input
            type="hidden"
            name="cod_producto"
            id="cod_producto"
            value="<?php echo htmlspecialchars($productoCodigo); ?>"
        >

        <p id="estado_producto"></p>

        <br><br>


        <button type="submit">
            Registrar
        </button>

    </form>


    <br>


    <form action="menu.php">

        <button type="submit">
            Menu Principal
        </button>

    </form>


<?php endif; ?>


<br>


<?php if ($movimiento_editar || !$esAdmin): ?>

<script>

// Activa el autocompletado en un campo (sirve para proveedor y producto).
// Devuelve una función que confirma que lo escrito sí existe.
function crearAutocompletado(config) {

    const campo  = document.getElementById(config.campo);   // Lo que escribe el usuario
    const lista  = document.getElementById(config.lista);   // Sugerencias
    const oculto = document.getElementById(config.oculto);  // Código que se envía
    const estado = document.getElementById(config.estado);  // Mensaje bajo el campo

    // Contador para ignorar respuestas viejas si el usuario sigue escribiendo.
    let consulta = 0;

    // ---------- Sugerencias mientras se escribe ----------
    campo.addEventListener("input", async function () {

        // Si cambia el texto, el código elegido ya no es válido.
        oculto.value = "";
        estado.textContent = "";

        const texto = campo.value.trim();
        const consultaActual = ++consulta;

        // Si borró todo, vaciamos las sugerencias.
        if (texto === "") {
            lista.replaceChildren();
            return;
        }

        try {
            // Le preguntamos a PHP qué opciones coinciden.
            const respuesta = await fetch(
                "mov_entrada.php?" + config.parametro + "=" + encodeURIComponent(texto)
            );

            if (!respuesta.ok) {
                throw new Error("No se pudo buscar.");
            }

            const opciones = await respuesta.json();

            // Si ya escribió otra cosa, ignoramos esta respuesta.
            if (consultaActual !== consulta) {
                return;
            }

            // Llenamos la lista de sugerencias.
            lista.replaceChildren();
            opciones.forEach(function (opcion) {
                const elemento = document.createElement("option");
                elemento.value = opcion.codigo + " - " + opcion.nombre;
                lista.appendChild(elemento);
            });

        } catch (error) {
            estado.textContent = "Error al buscar " + config.nombre + ".";
            console.error(error);
        }
    });

    // ---------- Confirmar que lo escrito sí existe ----------
    // Devuelve true si es válido y false si no.
    async function resolver() {
        const texto = campo.value.trim();

        if (texto === "") {
            oculto.value = "";
            estado.textContent = "Escriba el código o el nombre del " + config.nombre + ".";
            return false;
        }

        try {
            const respuesta = await fetch(
                "mov_entrada.php?" + config.parametro + "=" + encodeURIComponent(texto)
            );

            if (!respuesta.ok) {
                throw new Error("Error al consultar.");
            }

            const opciones = await respuesta.json();

            // Buscamos la opción que coincida exactamente con lo escrito.
            const encontrada = opciones.find(function (o) {
                const codigo = String(o.codigo).trim();
                const nombre = String(o.nombre).trim();

                return texto === codigo ||
                       texto.toLowerCase() === nombre.toLowerCase() ||
                       texto === codigo + " - " + nombre;
            });

            if (!encontrada) {
                oculto.value = "";
                estado.textContent =
                    config.nombre + " no encontrado. Seleccione uno de las sugerencias.";
                return false;
            }

            // Es válido: guardamos el código en el campo oculto.
            oculto.value = String(encontrada.codigo);
            campo.value = encontrada.codigo + " - " + encontrada.nombre;
            estado.textContent = "";
            return true;

        } catch (error) {
            oculto.value = "";
            estado.textContent = "No se pudo verificar el " + config.nombre + ".";
            console.error(error);
            return false;
        }
    }

    // Cuando el usuario termina de escribir, lo verificamos.
    campo.addEventListener("change", resolver);

    return resolver;
}

// Autocompletado del proveedor.
const resolverProveedor = crearAutocompletado({
    campo: "proveedor_busqueda",
    lista: "lista_proveedores",
    oculto: "cod_proveedor",
    estado: "estado_proveedor",
    parametro: "buscar_proveedor",
    nombre: "proveedor"
});

// Autocompletado del producto.
const resolverProducto = crearAutocompletado({
    campo: "producto_busqueda",
    lista: "lista_productos",
    oculto: "cod_producto",
    estado: "estado_producto",
    parametro: "buscar_producto",
    nombre: "producto"
});

// ---------- Validar antes de enviar el formulario ----------
document.getElementById("formularioEntrada").addEventListener("submit", async function (evento) {

    // Detenemos el envío para revisar primero.
    evento.preventDefault();

    // 1. El proveedor debe existir.
    const proveedorValido = await resolverProveedor();
    if (!proveedorValido) {
        alert("Seleccione un proveedor registrado de las sugerencias.");
        document.getElementById("proveedor_busqueda").focus();
        return;
    }

    // 2. El producto debe existir.
    const productoValido = await resolverProducto();
    if (!productoValido) {
        alert("Seleccione un producto registrado de las sugerencias.");
        document.getElementById("producto_busqueda").focus();
        return;
    }

    // Todo bien: enviamos el formulario.
    this.submit();
});
</script>

<?php endif; // fin del JavaScript del formulario ?>


<?php if ($esAdmin): ?>



<h3>Buscar Movimiento</h3>


<form method="GET">

    <input
        type="text"
        name="buscar"
        value="<?php echo htmlspecialchars($busqueda); ?>"
        placeholder="Buscar"
    >

    <button type="submit">
        Buscar
    </button>

    <a href="mov_entrada.php">
        Mostrar todos
    </a>

</form>


<br>


<!-- TABLA -->

<table border="1" cellpadding="5" cellspacing="0">

    <tr>

        <th>Codigo Movimiento</th>
        <th>Fecha</th>
        <th>Precio</th>
        <th>Cantidad</th>
        <th>Nota</th>
        <th>Proveedor</th>
        <th>Producto</th>
        <th>Acciones</th>

    </tr>


    <?php while ($fila = $resultado->fetch_assoc()): ?>


        <tr>

            <td><?php echo $fila['cod_movimiento']; ?></td>
            <td><?php echo $fila['fecha']; ?></td>
            <td><?php echo $fila['precio']; ?></td>
            <td><?php echo $fila['cantidad']; ?></td>
            <td><?php echo htmlspecialchars($fila['nota'] ?? ''); ?></td>
            <td><?php echo $fila['cod_proveedor'] . " - " . $fila['nombre_proveedor']; ?></td>
            <td><?php echo $fila['cod_producto'] ." - " . $fila['nombre_producto']; ?></td>
            <td>
                <a href="?editar=<?php echo $fila['cod_movimiento']; ?>">Editar</a>|
                <a href="?borrar=<?php echo $fila['cod_movimiento']; ?>"
                    onclick="return confirm('¿Desea borrar este movimiento?');"
                > Eliminar</a>

            </td>

        </tr>


    <?php endwhile; ?>


</table>

<br>



<?php endif; // fin de la tabla del administrador ?>




<br>


</body>
</html>


<?php

if ($esAdmin && isset($stmt)) {
    $stmt->close();
}
$conn->close();

?>