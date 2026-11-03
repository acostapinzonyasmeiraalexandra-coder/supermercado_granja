<?php

/* 

   - EMPLEADO (cargo 2): solo el formulario para registrar

     un producto.

   - ADMINISTRADOR (cargo 1): solo la búsqueda y la tabla de

     productos. El formulario solo le aparece cuando pulsa

     "Editar". El administrador también puede eliminar.

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

    // compatibilidad con la variable de conexion

    if (!isset($conn) && isset($conexion)) {

        $conn = $conexion;

    }

    $mensaje = "";

    $producto_editar = null;

    $busqueda = "";

    // Texto y código que se muestran en el campo de proveedor

    $proveedorTexto = "";

    $proveedorCodigo = "";

    // Activar el modo de reporte de excepciones

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    /*

       AUTOCOMPLETAR PROVEEDOR

       El formulario pregunta aquí: "¿qué proveedor coincide

       con lo que escribió el usuario?" y esta parte responde

       con una lista (máximo 10) en formato JSON.

       */

    if (isset($_GET["buscar_proveedor"])) {

        // Avisamos que la respuesta será JSON (datos para JavaScript)

        header("Content-Type: application/json; charset=utf-8");

        try {

            $textoBuscado = trim($_GET["buscar_proveedor"]);

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

            $stmt_prov = $conn->prepare(

                "SELECT cod_proveedor, nombre

                 FROM proveedor

                 WHERE CAST(cod_proveedor AS CHAR) LIKE ?

                    OR nombre LIKE ?

                 ORDER BY

                    CASE

                        WHEN CAST(cod_proveedor AS CHAR) = ? THEN 0

                        WHEN nombre = ? THEN 0

                        ELSE 1

                    END,

                    nombre ASC

                 LIMIT 10"

            );

            $stmt_prov->bind_param("ssss", $filtroCodigo, $filtro, $parteCodigo, $textoBuscado);

            $stmt_prov->execute();

            $res_prov = $stmt_prov->get_result();

            $lista = [];

            while ($fila_prov = $res_prov->fetch_assoc()) {

                $lista[] = [

                    "codigo" => $fila_prov["cod_proveedor"],

                    "nombre" => $fila_prov["nombre"]

                ];

            }

            $stmt_prov->close();

            echo json_encode($lista, JSON_UNESCAPED_UNICODE);

        } catch (Throwable $ex) {

            http_response_code(500);

            echo json_encode([]);

        }

        exit; // Terminamos aquí: no hay que dibujar la página

    }

    // 1. Accion eliminar producto

    // El empleado no puede editar ni eliminar productos

    if (isset($_GET['accion']) && !$esAdmin) {

        $mensaje = "<p style='color:red; font-weight:bold;'>No tiene permiso para editar ni eliminar productos.</p>";

    }

    if ($esAdmin && isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['cod_producto'])) {

        $cod_eliminar = $_GET['cod_producto'];

        try {

            $sql_del = "DELETE FROM producto WHERE cod_producto = ?";

            $stmt_del = $conn->prepare($sql_del);

            $stmt_del->bind_param("i", $cod_eliminar);

            $stmt_del->execute();

            $mensaje = "<p style='color:green; font-weight:bold;'>Producto eliminado correctamente.</p>";

            $stmt_del->close();

        } catch (mysqli_sql_exception $e) {

            if ($e->getCode() == 1451) {

                $mensaje = "<p style='color:red; font-weight:bold;'>No se puede eliminar el producto porque tiene movimientos registrados.</p>";

            } else {

                $mensaje = "<p style='color:red; font-weight:bold;'>Error al eliminar: "

                . htmlspecialchars($e->getMessage()) . "</p>";

            }

        }

    }

    // 2. Accion editar producto

    if ($esAdmin && isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['cod_producto'])) {

        $cod_buscar = $_GET['cod_producto'];

        try {

            $sql_buscar = "SELECT * FROM producto WHERE cod_producto = ?";

            $stmt_buscar = $conn->prepare($sql_buscar);

            $stmt_buscar->bind_param("i", $cod_buscar);

            $stmt_buscar->execute();

            $res = $stmt_buscar->get_result();

            $producto_editar = $res->fetch_assoc();

            // Para mostrar "código - nombre" en el campo de proveedor

            if ($producto_editar) {

                $proveedorCodigo = $producto_editar['cod_proveedor'];

                $stmt_nombre = $conn->prepare("SELECT nombre FROM proveedor WHERE cod_proveedor = ?");

                $stmt_nombre->bind_param("i", $proveedorCodigo);

                $stmt_nombre->execute();

                $fila_nombre = $stmt_nombre->get_result()->fetch_assoc();

                $stmt_nombre->close();

                if ($fila_nombre) {

                    $proveedorTexto = $proveedorCodigo . " - " . $fila_nombre['nombre'];

                }

            }

            $stmt_buscar->close();

        } catch (mysqli_sql_exception $e) {

            $mensaje = "<p style='color:red; font-weight:bold;'>Error al cargar los datos: "

            . htmlspecialchars($e->getMessage()) . "</p>";

        }

    }

    // 3. Formulario guardar y modificar POST

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        // Reglas de permisos:

        // - Solo el empleado registra productos nuevos.

        // - Solo el administrador actualiza.

        if (isset($_POST["guardar"]) && $esAdmin) {

            $mensaje = "<p style='color:red; font-weight:bold;'>El administrador no registra productos nuevos.</p>";

        }

        if (isset($_POST["actualizar"]) && !$esAdmin) {

            $mensaje = "<p style='color:red; font-weight:bold;'>No tiene permiso para actualizar productos.</p>";

        }

        // Registrar producto

        if (isset($_POST["guardar"]) && !$esAdmin) {

            $cod_producto = $_POST["cod_producto"];

            $nombre = $_POST["nombre"];

            $precio = $_POST["precio"];

            $cod_proveedor = $_POST["cod_proveedor"];

            $categoria = $_POST["categoria"];

            $descripcion = $_POST["descripcion"];

            try {

                $sql = "INSERT INTO producto (cod_producto, nombre, precio, categoria, descripcion, cod_proveedor)VALUES (?, ?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param("isdssi",$cod_producto,$nombre,$precio,$categoria,$descripcion,$cod_proveedor);

                $stmt->execute();

                $mensaje = "<p style='color:green; font-weight:bold;'>Producto registrado correctamente.</p>";

                $stmt->close();

            } catch (mysqli_sql_exception $e) {

                if ($e->getCode() == 1062) {

                    $mensaje = "<p style='color:red; font-weight:bold;'>Error: El codigo del producto ya esta registrado.</p>";

                } else {

                    $mensaje = "<p style='color:red; font-weight:bold;'>Error al registrar: "

                    . htmlspecialchars($e->getMessage()) . "</p>";

                }

            }

        }

        // Actualizar producto

        if (isset($_POST["actualizar"]) && $esAdmin) {

            $cod_producto = $_POST["cod_producto"];

            $nombre = $_POST["nombre"];

            $precio = $_POST["precio"];

            $cod_proveedor = $_POST["cod_proveedor"];

            $categoria = $_POST["categoria"];

            $descripcion = $_POST["descripcion"];

            try {

                $sql_up = "UPDATE producto SET nombre = ?, precio = ?, categoria = ?, descripcion = ?, cod_proveedor = ? WHERE cod_producto = ?";

                $stmt_up = $conn->prepare($sql_up);

                $stmt_up->bind_param("sdssii", $nombre,$precio,$categoria,$descripcion,$cod_proveedor,$cod_producto);

                $stmt_up->execute();

                $mensaje = "<p style='color:green; font-weight:bold;'>Producto actualizado correctamente.</p>";

                $stmt_up->close();

            } catch (mysqli_sql_exception $e) {

                $mensaje = "<p style='color:red; font-weight:bold;'>Error al actualizar: "

                . htmlspecialchars($e->getMessage()) . "</p>";

            }

        }

    }

    // 4. Buscar y listar productos (solo administrador)

    $productos = false;

    if ($esAdmin) {

    if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {

        $busqueda = trim($_GET['buscar']);

        $param_busqueda = "%" . $busqueda . "%";

        $sql_listar = "SELECT producto.*, proveedor.nombre AS nombre_proveedor FROM producto INNER JOIN proveedor ON producto.cod_proveedor = proveedor.cod_proveedor WHERE producto.cod_producto LIKE ? OR producto.nombre LIKE ?  OR producto.categoria LIKE ? OR producto.descripcion LIKE ?";        $stmt_list = $conn->prepare($sql_listar);

        $stmt_list->bind_param("ssss", $param_busqueda, $param_busqueda, $param_busqueda, $param_busqueda);

        $stmt_list->execute();

        $productos = $stmt_list->get_result();

    } else {

        $sql_listar = "SELECT producto.*, proveedor.nombre AS nombre_proveedor

               FROM producto

               INNER JOIN proveedor ON producto.cod_proveedor = proveedor.cod_proveedor

               ORDER BY producto.cod_producto ASC";

        $productos = $conn->query($sql_listar);

    }

    }

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Productos</title>

</head>

<body>

    <?php echo $mensaje; ?>

    <!-- Formulario para editar producto -->

    <?php if ($producto_editar) : ?>

        <h2>Editar Producto</h2>

        <form action="" method="POST" id="formularioProducto">

            <input type="hidden" name="cod_producto"

            value="<?php echo htmlspecialchars($producto_editar['cod_producto']); ?>">

            <label>Codigo Producto:</label><br>

            <input type="number"

            value="<?php echo htmlspecialchars($producto_editar['cod_producto']); ?>"

            disabled><br><br>

            <label>Nombre:</label><br>

            <input type="text" name="nombre"

            value="<?php echo htmlspecialchars($producto_editar['nombre']); ?>"

            required><br><br>

            <label>Precio:</label><br>

            <input type="number" step="0.01" name="precio"

            value="<?php echo htmlspecialchars($producto_editar['precio']); ?>"

            required><br><br>

           <label>Proveedor:</label><br>

            <input type="text"

            id="proveedor_busqueda"

            list="lista_proveedores"

            autocomplete="off"

            placeholder="Escriba el código o el nombre"

            value="<?php echo htmlspecialchars($proveedorTexto); ?>"

            required>

            <!-- Aquí JavaScript pone las sugerencias -->

            <datalist id="lista_proveedores"></datalist>

            <!-- Campo oculto: aquí queda el código que realmente se envía -->

            <input type="hidden" name="cod_proveedor" id="cod_proveedor"

            value="<?php echo htmlspecialchars($proveedorCodigo); ?>">

            <p id="estado_proveedor"></p>

            <label>Categoria:</label><br>

            <select name="categoria" required>

                <option value="abarrotes" <?php if ($producto_editar['categoria'] == 'abarrotes') echo 'selected'; ?>>Abarrotes</option>

                <option value="carnes" <?php if ($producto_editar['categoria'] == 'carnes') echo 'selected'; ?>>Carnes</option>

                <option value="frutas" <?php if ($producto_editar['categoria'] == 'frutas') echo 'selected'; ?>>Frutas</option>

                <option value="aseo personal" <?php if ($producto_editar['categoria'] == 'aseo personal') echo 'selected'; ?>>Aseo Personal</option>

            </select><br><br>

            <label>Descripcion:</label><br>

            <textarea name="descripcion" required><?php echo htmlspecialchars($producto_editar['descripcion']); ?></textarea><br><br>

            <button type="submit" name="actualizar">Actualizar Producto</button>

            <a href="?">Cancelar</a>

        </form>

    <?php elseif (!$esAdmin): ?>

        <!-- Formulario para registrar producto -->

        <h2>Ingresar Producto</h2>

        <form action="" method="POST" id="formularioProducto">

            <label>Codigo Producto:</label><br>

            <input type="number" name="cod_producto" required placeholder="Ingrese codigo"><br><br>

            <label>Nombre:</label><br>

            <input type="text" name="nombre" required placeholder="Ingrese nombre"><br><br>

            <label>Precio:</label><br>

            <input type="number" name="precio" step="0.01" required placeholder="Ingrese precio"><br><br>

            <label>Proveedor:</label><br>

            <input type="text"

            id="proveedor_busqueda"

            list="lista_proveedores"

            autocomplete="off"

            placeholder="Escriba el código o el nombre"

            value="<?php echo htmlspecialchars($proveedorTexto); ?>"

            required>

            <!-- Aquí JavaScript pone las sugerencias -->

            <datalist id="lista_proveedores"></datalist>

            <!-- Campo oculto: aquí queda el código que realmente se envía -->

            <input type="hidden" name="cod_proveedor" id="cod_proveedor"

            value="<?php echo htmlspecialchars($proveedorCodigo); ?>">

            <p id="estado_proveedor"></p>

            <label>Categoria:</label><br>

            <select name="categoria" required>

                <option value="">Seleccione una categoria</option>

                <option value="abarrotes">Abarrotes</option>

                <option value="carnes">Carnes</option>

                <option value="frutas">Frutas</option>

                <option value="aseo personal">Aseo Personal</option>

            </select><br><br>

            <label>Descripcion:</label><br>

            <textarea name="descripcion" required placeholder="Ingrese descripcion"></textarea><br><br>

            <button type="submit" name="guardar">Registrar</button>

        </form>

    <?php endif; ?>

    <?php if ($producto_editar || !$esAdmin): ?>

    <script>

    // =====================================================

    // JAVASCRIPT DEL FORMULARIO

    // (todo esto corre en el navegador, no en el servidor)

    // =====================================================

    // ---------- Elementos de la página que vamos a usar ----------

    const campoProveedor  = document.getElementById("proveedor_busqueda"); // Lo que escribe el usuario

    const campoCodigo     = document.getElementById("cod_proveedor");      // Código oculto que se envía

    const listaProveedores = document.getElementById("lista_proveedores"); // Sugerencias

    const estadoProveedor = document.getElementById("estado_proveedor");   // Mensaje bajo el campo

    // Contador para ignorar respuestas viejas si el usuario sigue escribiendo.

    let consultaProveedores = 0;

    // ---------- Sugerencias mientras se escribe ----------

    campoProveedor.addEventListener("input", async function () {

        // Si cambia el texto, el código elegido ya no es válido.

        campoCodigo.value = "";

        estadoProveedor.textContent = "";

        const texto = campoProveedor.value.trim();

        const consultaActual = ++consultaProveedores;

        // Si borró todo, vaciamos las sugerencias.

        if (texto === "") {

            listaProveedores.replaceChildren();

            return;

        }

        try {

            // Le preguntamos a PHP qué proveedores coinciden.

            const respuesta = await fetch(

                "producto.php?buscar_proveedor=" + encodeURIComponent(texto)

            );

            if (!respuesta.ok) {

                throw new Error("No se pudo buscar el proveedor.");

            }

            const proveedores = await respuesta.json();

            // Si ya escribió otra cosa, ignoramos esta respuesta.

            if (consultaActual !== consultaProveedores) {

                return;

            }

            // Llenamos la lista de sugerencias.

            listaProveedores.replaceChildren();

            proveedores.forEach(function (proveedor) {

                const opcion = document.createElement("option");

                opcion.value = proveedor.codigo + " - " + proveedor.nombre;

                listaProveedores.appendChild(opcion);

            });

        } catch (error) {

            estadoProveedor.textContent = "Error al buscar proveedores.";

            console.error(error);

        }

    });

    // ---------- Confirmar que el proveedor escrito sí existe ----------

    // Devuelve true si es válido y false si no.

    async function resolverProveedor() {

        const texto = campoProveedor.value.trim();

        if (texto === "") {

            campoCodigo.value = "";

            estadoProveedor.textContent = "Escriba el código o el nombre del proveedor.";

            return false;

        }

        try {

            const respuesta = await fetch(

                "producto.php?buscar_proveedor=" + encodeURIComponent(texto)

            );

            if (!respuesta.ok) {

                throw new Error("Error al consultar el proveedor.");

            }

            const proveedores = await respuesta.json();

            // Buscamos el proveedor que coincida exactamente con lo escrito.

            const proveedor = proveedores.find(function (p) {

                const codigo = String(p.codigo).trim();

                const nombre = String(p.nombre).trim();

                return texto === codigo ||

                       texto.toLowerCase() === nombre.toLowerCase() ||

                       texto === codigo + " - " + nombre;

            });

            if (!proveedor) {

                campoCodigo.value = "";

                estadoProveedor.textContent =

                    "Proveedor no encontrado. Seleccione uno de las sugerencias.";

                return false;

            }

            // Proveedor válido: guardamos su código en el campo oculto.

            campoCodigo.value = String(proveedor.codigo);

            campoProveedor.value = proveedor.codigo + " - " + proveedor.nombre;

            estadoProveedor.textContent = "";

            return true;

        } catch (error) {

            campoCodigo.value = "";

            estadoProveedor.textContent = "No se pudo verificar el proveedor.";

            console.error(error);

            return false;

        }

    }

    // Cuando el usuario termina de escribir, lo verificamos.

    campoProveedor.addEventListener("change", resolverProveedor);

    // ---------- Validar antes de enviar el formulario ----------

    document.getElementById("formularioProducto").addEventListener("submit", async function (evento) {

        // Detenemos el envío para revisar primero.

        evento.preventDefault();

        const proveedorValido = await resolverProveedor();

        if (!proveedorValido) {

            alert("Seleccione un proveedor registrado de las sugerencias.");

            campoProveedor.focus();

            return;

        }

        // Todo bien: enviamos el formulario.

        // (el botón no se llama "submit", así que this.submit() funciona)

        this.submit();

    });

    </script>

    <?php endif; // fin del JavaScript del formulario ?>

    <br>

    <form action="menu.php" method="POST" style="display:inline;">

        <button type="submit">Menú Principal</button>

    </form>

    <?php if ($esAdmin): ?>

    <hr>

    <hr>

    <!-- Buscar productos -->

    <h3>Buscar Producto</h3>

    <form action="" method="GET">

        <input type="text" name="buscar"

        placeholder="Ingrese codigo, nombre o categoria..."

        value="<?php echo htmlspecialchars($busqueda); ?>">

        <button type="submit">Buscar</button>

        <?php if (!empty($busqueda)): ?>

            <a href="?">Mostrar todos</a>

        <?php endif; ?>

    </form>

    <br>

    <!-- Tabla de productos -->

    <h3>Listado de Productos</h3>

    <table border="1" cellpadding="5" cellspacing="0">

        <thead>

            <tr>

                <th>Codigo</th>

                <th>Nombre</th>

                <th>Precio</th>

                <th>Proveedor</th>

                <th>Categoria</th>

                <th>Descripcion</th>

                <th>Acciones</th>

            </tr>

        </thead>

        <tbody>

            <?php if ($productos && $productos->num_rows > 0) : ?>

                <?php while ($fila = $productos->fetch_assoc()) : ?>

                    <tr>

                        <td><?php echo htmlspecialchars($fila['cod_producto']); ?></td>

                        <td><?php echo htmlspecialchars($fila['nombre']); ?></td>

                        <td><?php echo htmlspecialchars($fila['precio']); ?></td>

                        <td><?php echo htmlspecialchars($fila['cod_proveedor'] . " - " . $fila['nombre_proveedor']); ?></td>

                        <td><?php echo htmlspecialchars($fila['categoria']); ?></td>

                        <td><?php echo htmlspecialchars($fila['descripcion']); ?></td>

                        <td>

                            <a href="?accion=editar&cod_producto=<?php echo urlencode($fila['cod_producto']); ?>">Editar</a>

                            <a href="?accion=eliminar&cod_producto=<?php echo urlencode($fila['cod_producto']); ?>"

                            onclick="return confirm('¿Seguro que deseas eliminar este producto?');">Eliminar</a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="7">No se encontraron productos.</td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

    <?php endif; // fin de buscador y tabla del administrador ?>

</body>

</html>

<?php

$conn->close();

?>
