<?php
session_start();

// si no ha iniciado sesion, volver al ingreso
if (!isset($_SESSION["usuario"])) {
    header("Location: ../index.php");
    exit();
}

// datos del usuario que inicio sesion
$usuario = $_SESSION["usuario"];
$rol = (string) ($_SESSION["rol"] ?? ""); // 1 administrador | 2 empleado
$esAdmin = ($rol === "1");
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
$inicial = strtoupper(substr($usuario, 0, 1));

// esta pagina es solo para el administrador
if (!$esAdmin) {
    header("Location: menu.php");
    exit();
}

// conexion a la base de datos
include("conexion.php");

// proteger los datos que se muestran en la pagina.
function escapar($dato): string {
    return htmlspecialchars((string) $dato, ENT_QUOTES, "UTF-8");
}

// mostrar los iconos de la pagina.
function icono(string $nombre): string {
    return '<img src="../img/' . escapar($nombre) . '.png" alt="" class="icono">';
}

// dar formato al precio.
function formatoMoneda($valor): string {
    return "$" . number_format((float) $valor, 2, ",", ".");
}

// esta variable almacena el mensaje que se muestra al usuario
$mensaje = "";
// esta variable almacena el tipo de mensaje (ok o error)
$tipoMensaje = "";
// esta variable almacena los datos del registro que se va a editar
$movimientoEditar = null;
// esta variable almacena el texto que se escribe en el buscador
$busqueda = "";
$resultado = null;

// estas variables almacenan el texto y el codigo del proveedor y del producto
$proveedorTexto = "";
$proveedorCodigo = "";
$productoTexto = "";
$productoCodigo = "";

// buscar proveedores y productos para el autocompletado.
function responderBusqueda(mysqli $conn, string $tabla, string $campoCodigo, string $textoBuscado): void {
    header("Content-Type: application/json; charset=utf-8");

    // intenta ejecutar el codigo y atrapa los errores
    try {
        $textoBuscado = trim($textoBuscado);

        if ($textoBuscado === "") {
            echo json_encode([]);
            exit;
        }

        $parteCodigo = trim(explode(" - ", $textoBuscado)[0]);
        // esta variable almacena el texto buscado con comodines para usar like
        $filtro = "%" . $textoBuscado . "%";
        $filtroCodigo = "%" . $parteCodigo . "%";

        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare(
            "SELECT $campoCodigo AS codigo, nombre
             FROM $tabla
             WHERE CAST($campoCodigo AS CHAR) LIKE ? OR nombre LIKE ?
             ORDER BY
                CASE WHEN CAST($campoCodigo AS CHAR) = ? OR nombre = ? THEN 0 ELSE 1 END,
                nombre ASC
             LIMIT 10"
        );

        // envia los datos a la consulta
        $stmt->bind_param("ssss", $filtroCodigo, $filtro, $parteCodigo, $textoBuscado);
        // ejecuta la consulta
        $stmt->execute();

        // obtiene el resultado de la consulta
        $lista = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        // cierra la consulta
        $stmt->close();

        echo json_encode($lista, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        // si ocurre cualquier error responde con una lista vacia
        http_response_code(500);
        echo json_encode([]);
    }

    exit;
}

// consultar el nombre de un proveedor o producto.
function textoRelacion(mysqli $conn, string $tabla, string $campoCodigo, int $codigo): string {
    // prepara la consulta sql de forma segura
    $stmt = $conn->prepare("SELECT nombre FROM $tabla WHERE $campoCodigo = ?");
    // envia los datos a la consulta
    $stmt->bind_param("i", $codigo);
    // ejecuta la consulta
    $stmt->execute();

    // obtiene el resultado de la consulta
    $fila = $stmt->get_result()->fetch_assoc();
    // cierra la consulta
    $stmt->close();

    return $fila ? $codigo . " - " . $fila["nombre"] : "";
}

// buscar proveedores y productos.
if (isset($_GET["buscar_proveedor"])) {
    responderBusqueda($conn, "proveedor", "cod_proveedor", (string) $_GET["buscar_proveedor"]);
}

if (isset($_GET["buscar_producto"])) {
    responderBusqueda($conn, "producto", "cod_producto", (string) $_GET["buscar_producto"]);
}

// eliminar movimientos solamente como administrador.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_movimiento"])) {
    // intenta ejecutar el codigo y atrapa los errores
    try {
        // bloquea la accion si el usuario no es administrador
        if (!$esAdmin) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("No tienes permiso para eliminar movimientos de entrada.");
        }

        // datos que llegan del formulario
        $codigo = trim((string) ($_POST["cod_movimiento"] ?? ""));

        if (!ctype_digit($codigo)) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Código de movimiento no válido.");
        }

        $codNum = (int) $codigo;
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare("DELETE FROM mov_entrada WHERE cod_movimiento = ?");
        // envia los datos a la consulta
        $stmt->bind_param("i", $codNum);
        // ejecuta la consulta
        $stmt->execute();

        $eliminados = $stmt->affected_rows;
        // cierra la consulta
        $stmt->close();

        if ($eliminados > 0) {
            // redirige a mov_entrada.php
            header("Location: mov_entrada.php?mensaje=eliminado");
            exit();
        }

        // lanza un error para avisar que el dato no es valido
        throw new InvalidArgumentException("No se encontró el movimiento de entrada que deseas eliminar.");
    } catch (InvalidArgumentException $e) {
        // si algun dato no es valido guarda el mensaje de validacion
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // si falla la base de datos guarda el mensaje de error
        if ($e->getCode() === 1451) {
            $mensaje = "No se puede eliminar el movimiento porque está relacionado con otros registros.";
        } else {
            $mensaje = "Error al eliminar el movimiento de entrada.";
        }

        $tipoMensaje = "error";
    }
}

// cargar los datos del movimiento que se va a editar.
if (($_GET["accion"] ?? "") === "editar") {
    // intenta ejecutar el codigo y atrapa los errores
    try {
        // bloquea la accion si el usuario no es administrador
        if (!$esAdmin) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("No tienes permiso para editar movimientos de entrada.");
        }

        // datos que llegan por la url
        $codigo = trim((string) ($_GET["cod_movimiento"] ?? ""));

        if (!ctype_digit($codigo)) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Código de movimiento no válido.");
        }

        $codNum = (int) $codigo;
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare("SELECT * FROM mov_entrada WHERE cod_movimiento = ?");
        // envia los datos a la consulta
        $stmt->bind_param("i", $codNum);
        // ejecuta la consulta
        $stmt->execute();

        // obtiene el resultado de la consulta
        $movimientoEditar = $stmt->get_result()->fetch_assoc();
        // cierra la consulta
        $stmt->close();

        if (!$movimientoEditar) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("No se encontró el movimiento seleccionado.");
        }

        $proveedorCodigo = $movimientoEditar["cod_proveedor"];
        $productoCodigo = $movimientoEditar["cod_producto"];

        $proveedorTexto = textoRelacion($conn, "proveedor", "cod_proveedor", (int) $proveedorCodigo);
        $productoTexto = textoRelacion($conn, "producto", "cod_producto", (int) $productoCodigo);
    } catch (InvalidArgumentException $e) {
        // si algun dato no es valido guarda el mensaje de validacion
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // si falla la base de datos guarda el mensaje de error
        $mensaje = "Error al cargar el movimiento de entrada.";
        $tipoMensaje = "error";
    }
}

// guardar un movimiento nuevo o actualizar uno existente.
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST["guardar"]) || isset($_POST["actualizar"]))) {
    $esActualizar = isset($_POST["actualizar"]);

    // datos que llegan del formulario
    $codigo = trim((string) ($_POST["cod_movimiento"] ?? ""));
    $fecha = trim((string) ($_POST["fecha"] ?? ""));
    $precio = str_replace(",", ".", trim((string) ($_POST["precio"] ?? "")));
    $cantidad = trim((string) ($_POST["cantidad"] ?? ""));
    $nota = trim((string) ($_POST["nota"] ?? ""));
    $codProveedor = trim((string) ($_POST["cod_proveedor"] ?? ""));
    $codProducto = trim((string) ($_POST["cod_producto"] ?? ""));

    // intenta ejecutar el codigo y atrapa los errores
    try {
        if ($esActualizar && !$esAdmin) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("No tienes permiso para actualizar movimientos de entrada.");
        }

        if (
            !ctype_digit($codigo) || $fecha === "" ||
            !is_numeric($precio) || (float) $precio < 0 ||
            !ctype_digit($cantidad) || (int) $cantidad <= 0 ||
            !ctype_digit($codProveedor) || !ctype_digit($codProducto)
        ) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Completa todos los campos obligatorios con valores válidos.");
        }

        $codNum = (int) $codigo;
        $precioNum = (float) $precio;
        $cantidadNum = (int) $cantidad;
        $provNum = (int) $codProveedor;
        $prodNum = (int) $codProducto;

        if ($esActualizar) {
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare(
                "UPDATE mov_entrada
                 SET fecha = ?, precio = ?, cantidad = ?, nota = ?, cod_proveedor = ?, cod_producto = ?
                 WHERE cod_movimiento = ?"
            );

            // envia los datos a la consulta
            $stmt->bind_param("sdisiii", $fecha, $precioNum, $cantidadNum, $nota, $provNum, $prodNum, $codNum);
            $destino = "actualizado";
        } else {
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare(
                "INSERT INTO mov_entrada
                 (cod_movimiento, fecha, precio, cantidad, nota, cod_proveedor, cod_producto)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            // envia los datos a la consulta
            $stmt->bind_param("isdisii", $codNum, $fecha, $precioNum, $cantidadNum, $nota, $provNum, $prodNum);
            $destino = "registrado";
        }

        // ejecuta la consulta
        $stmt->execute();
        // cierra la consulta
        $stmt->close();

        // redirige a mov_entrada.php
        header("Location: mov_entrada.php?mensaje=" . $destino);
        exit();
    } catch (InvalidArgumentException $e) {
        // si algun dato no es valido guarda el mensaje de validacion
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // si falla la base de datos guarda el mensaje de error
        if ($e->getCode() === 1062) {
            $mensaje = "El código del movimiento ya está registrado.";
        } elseif ($e->getCode() === 1452) {
            $mensaje = "El proveedor o producto seleccionado no existe.";
        } else {
            $mensaje = "Error al procesar la solicitud.";
        }

        $tipoMensaje = "error";
    }
}

// mostrar el mensaje de cada accion.
$mensajesOk = [
    "registrado" => "Movimiento de entrada registrado correctamente.",
    "actualizado" => "Movimiento de entrada actualizado correctamente.",
    "eliminado" => "Movimiento de entrada eliminado correctamente."
];

// datos que llegan por la url
$clave = (string) ($_GET["mensaje"] ?? "");

if (isset($mensajesOk[$clave])) {
    $mensaje = $mensajesOk[$clave];

    if ($clave === "eliminado") {
        $tipoMensaje = "eliminado";
    } else {
        $tipoMensaje = "ok";
    }
}

// buscar y mostrar movimientos para el administrador.
if ($esAdmin) {
    // intenta ejecutar el codigo y atrapa los errores
    try {
        // esta variable almacena el texto que se escribe en el buscador
        $busqueda = trim((string) ($_GET["buscar"] ?? ""));

        // esta variable almacena la consulta sql
        $sql = "SELECT m.cod_movimiento, m.fecha, m.precio, m.cantidad, m.nota,
                       m.cod_proveedor, p.nombre AS nombre_proveedor,
                       m.cod_producto, pr.nombre AS nombre_producto
                FROM mov_entrada m
                LEFT JOIN proveedor p ON m.cod_proveedor = p.cod_proveedor
                LEFT JOIN producto pr ON m.cod_producto = pr.cod_producto";

        if ($busqueda !== "") {
            // esta variable almacena el texto buscado con comodines para usar like
            $filtro = "%" . $busqueda . "%";

            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare(
                $sql . " WHERE CAST(m.cod_movimiento AS CHAR) LIKE ?
                            OR m.fecha LIKE ?
                            OR CAST(m.cod_proveedor AS CHAR) LIKE ?
                            OR p.nombre LIKE ?
                            OR CAST(m.cod_producto AS CHAR) LIKE ?
                            OR pr.nombre LIKE ?
                         ORDER BY m.cod_movimiento DESC"
            );

            // envia los datos a la consulta
            $stmt->bind_param("ssssss", $filtro, $filtro, $filtro, $filtro, $filtro, $filtro);
            // ejecuta la consulta
            $stmt->execute();
            // obtiene el resultado de la consulta
            $resultado = $stmt->get_result();
        } else {
            // ejecuta la consulta sql
            $resultado = $conn->query($sql . " ORDER BY m.cod_movimiento DESC");
        }
    } catch (mysqli_sql_exception $e) {
        // si falla la base de datos guarda el mensaje de error
        $mensaje = "No se pudo cargar el listado de movimientos de entrada.";
        $tipoMensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Entrada | La Granja</title>
    <!-- hoja de estilos de la pagina -->
    <link rel="stylesheet" href="../css/mov_entrada.css?v=3">
</head>
<!-- cuerpo de la pagina -->
<body class="pagina-entrada">

<!-- encabezado de la pagina -->
<header class="encabezado">
    <button type="button" class="boton-menu" id="abrirMenu"
        aria-label="Abrir menú lateral" aria-controls="menuLateral" aria-expanded="false">
        <?= icono("menu") ?>
    </button>

    <div class="encabezado-logo">
        <img src="../img/logo.png" alt="Logo de La Granja">
    </div>

    <div class="encabezado-texto">
        <h1>Supermercado La Granja</h1>
        <p>Sistema de gestión de inventario</p>
    </div>

    <!-- datos del usuario que inicio sesion -->
    <div class="encabezado-usuario">
        <span class="usuario-inicial"><?= escapar($inicial) ?></span>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>
</header>

<!-- fondo que oscurece la pagina cuando el menu esta abierto -->
<div class="fondo-menu" id="fondoMenu"></div>

<!-- menu lateral -->
<aside class="menu-lateral" id="menuLateral" aria-label="Menú principal">
    <div class="menu-lateral-encabezado">
        <div class="menu-lateral-marca">
            <img src="../img/logo.png" alt="Logo de Supermercado La Granja">
            <div>
                <h2>Supermercado</h2>
                <p>La Granja</p>
            </div>
        </div>

        <button type="button" class="boton-cerrar" id="cerrarMenu" aria-label="Cerrar menú lateral">
            <?= icono("cerrar") ?>
        </button>
    </div>

    <!-- datos del usuario en el menu lateral -->
    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= escapar($inicial) ?></div>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>

    <!-- enlaces de navegacion -->
    <nav class="navegacion">
        <p class="titulo-navegacion">MENÚ PRINCIPAL</p>

        <div class="lista-navegacion">
            <a href="menu.php" class="enlace-menu">
                <span class="caja-icono"><?= icono("inicio") ?></span>
                <span>Inicio</span>
            </a>

            <a href="cliente.php" class="enlace-menu">
                <span class="caja-icono"><?= icono("cliente") ?></span>
                <span>Cliente</span>
            </a>

            <a href="mov_salida.php" class="enlace-menu">
                <span class="caja-icono"><?= icono("salida") ?></span>
                <span>Movimiento de salida</span>
            </a>

            <?php if ($esAdmin): ?>
                <a href="producto.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("producto") ?></span>
                    <span>Productos</span>
                </a>

                <a href="mov_entrada.php" class="enlace-menu activo" aria-current="page">
                    <span class="caja-icono"><?= icono("entrada") ?></span>
                    <span>Movimiento de entrada</span>
                </a>

                <a href="proveedor.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("proveedor") ?></span>
                    <span>Proveedor</span>
                </a>

                <a href="inventario.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("inventario") ?></span>
                    <span>Inventario</span>
                </a>

                <a href="reportes.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("reportes") ?></span>
                    <span>Reportes</span>
                </a>

                <a href="edita_usuario.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("usuario") ?></span>
                    <span>Modificar usuario</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- formulario para cerrar sesion -->
    <form action="cerrar_sesion.php" method="POST" class="form-salir">
        <button type="submit" name="salir" value="1" class="boton-salir">
            <span class="caja-icono caja-icono-roja"><?= icono("salir") ?></span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<!-- contenido principal -->
<main class="contenido">
    <!-- titulo de la pagina -->
    <section class="titulo-pagina">
        <span class="titulo-icono"><?= icono("entrada") ?></span>
        <div>
            <h2>Movimiento de Entrada</h2>
            <p>
                <?= $esAdmin
                    ? "Registra, busca, edita o elimina los movimientos de entrada."
                    : "Registra una nueva entrada de productos en el inventario." ?>
            </p>
        </div>
    </section>

    <!-- mensaje de aviso -->
    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- formulario de entrada para los usuarios -->
    <section class="panel">
        <h3 class="panel-titulo">
            <?= $movimientoEditar ? "Editar movimiento de entrada" : "Ingresar movimiento de entrada" ?>
        </h3>

        <!-- formulario -->
        <form action="mov_entrada.php" method="POST" id="formularioEntrada" class="formulario">
            <input type="hidden" name="<?= $movimientoEditar ? "actualizar" : "guardar" ?>" value="1">

            <?php if ($movimientoEditar): ?>
                <input type="hidden" name="cod_movimiento" value="<?= escapar($movimientoEditar["cod_movimiento"]) ?>">
                <div class="campo">
                    <label for="codigo_visible">Código de movimiento <span class="obligatorio">*</span></label>
                    <input id="codigo_visible" type="number" value="<?= escapar($movimientoEditar["cod_movimiento"]) ?>" disabled>
                </div>
            <?php else: ?>
                <div class="campo">
                    <label for="cod_movimiento">Código de movimiento <span class="obligatorio">*</span></label>
                    <input id="cod_movimiento" type="text" name="cod_movimiento" required inputmode="numeric" pattern="[0-9]+" maxlength="10" data-tipo="numeros" data-error="Escribe solo números." placeholder="Ingrese código">
                </div>
            <?php endif; ?>

            <div class="campo">
                <label for="fecha">Fecha <span class="obligatorio">*</span></label>
                <input id="fecha" type="date" name="fecha" required
                    value="<?= escapar($movimientoEditar["fecha"] ?? date("Y-m-d")) ?>">
            </div>

            <div class="campo">
                <label for="precio">Precio unitario <span class="obligatorio">*</span></label>
                <input id="precio" type="text" inputmode="decimal" pattern="[0-9]+(\.[0-9]+)?" data-tipo="decimal" data-error="Escribe solo números (usa punto para los decimales)." name="precio" required
                    value="<?= escapar($movimientoEditar["precio"] ?? "") ?>" placeholder="Ingrese precio">
            </div>

            <div class="campo">
                <label for="cantidad">Cantidad <span class="obligatorio">*</span></label>
                <input id="cantidad" type="text" inputmode="numeric" pattern="[1-9][0-9]*" maxlength="6" data-tipo="numeros" data-error="Escribe un número entero mayor que 0." name="cantidad" required
                    value="<?= escapar($movimientoEditar["cantidad"] ?? "") ?>" placeholder="Ingrese cantidad">
            </div>

            <!-- seleccionar el proveedor -->
            <div class="campo">
                <label for="proveedor_busqueda">Proveedor <span class="obligatorio">*</span></label>
                <input type="text" id="proveedor_busqueda" list="lista_proveedores" autocomplete="off"
                    placeholder="Escriba código o nombre" value="<?= escapar($proveedorTexto) ?>" required>
                <datalist id="lista_proveedores"></datalist>
                <input type="hidden" name="cod_proveedor" id="cod_proveedor" value="<?= escapar($proveedorCodigo) ?>">
                <p id="estado_proveedor" class="texto-campo-error"></p>
            </div>

            <!-- seleccionar el producto -->
            <div class="campo">
                <label for="producto_busqueda">Producto <span class="obligatorio">*</span></label>
                <input type="text" id="producto_busqueda" list="lista_productos" autocomplete="off"
                    placeholder="Escriba código o nombre" value="<?= escapar($productoTexto) ?>" required>
                <datalist id="lista_productos"></datalist>
                <input type="hidden" name="cod_producto" id="cod_producto" value="<?= escapar($productoCodigo) ?>">
                <p id="estado_producto" class="texto-campo-error"></p>
            </div>

            <!-- escribir una observacion -->
            <div class="campo campo-ancho">
                <label for="nota">Nota u observación</label>
                <textarea id="nota" name="nota" placeholder="Ingrese nota u observación"><?= escapar($movimientoEditar["nota"] ?? "") ?></textarea>
            </div>

            <!-- botones del formulario -->
            <div class="botones campo-ancho">
                <?php if ($movimientoEditar): ?>
                    <button type="submit" class="boton boton-principal">Actualizar movimiento</button>
                    <a href="mov_entrada.php" class="boton boton-secundario">Cancelar</a>
                <?php else: ?>
                    <button type="submit" class="boton boton-principal">Registrar movimiento</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- buscador y tabla del administrador -->
    <?php if ($esAdmin): ?>
        <!-- panel de contenido -->
        <section class="panel">
            <h3 class="panel-titulo">Buscar movimiento de entrada</h3>

            <!-- formulario de busqueda -->
            <form action="mov_entrada.php" method="GET" class="buscador" id="formBuscador">

                <input type="text" name="buscar" id="campoBuscar" autocomplete="off" placeholder="Código, fecha, proveedor o producto..."
                    value="<?= escapar($busqueda) ?>">

                <a href="mov_entrada.php" class="boton boton-secundario" id="limpiarBusqueda"
                    <?= $busqueda === "" ? "hidden" : "" ?>>Mostrar todos</a>
            </form>
        </section>

        <!-- panel de contenido -->
        <section class="panel">
            <h3 class="panel-titulo">Listado de movimientos de entrada</h3>

            <div class="tabla-contenedor tabla-scroll">
                <!-- tabla con los registros -->
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Precio</th>
                            <th>Cantidad</th>
                            <th>Nota</th>
                            <th>Proveedor</th>
                            <th>Producto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody id="cuerpoTabla">
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while ($fila = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?= escapar($fila["cod_movimiento"]) ?></td>
                                    <td><?= escapar($fila["fecha"]) ?></td>
                                    <td><?= formatoMoneda($fila["precio"]) ?></td>
                                    <td><?= escapar($fila["cantidad"]) ?></td>
                                    <td><?= escapar($fila["nota"] ?? "") ?></td>
                                    <td><?= escapar($fila["cod_proveedor"] . " - " . ($fila["nombre_proveedor"] ?? "Sin nombre")) ?></td>
                                    <td><?= escapar($fila["cod_producto"] . " - " . ($fila["nombre_producto"] ?? "Sin nombre")) ?></td>
                                    <td class="tabla-acciones">
                                        <a class="boton boton-chico boton-editar"
                                            href="mov_entrada.php?accion=editar&amp;cod_movimiento=<?= urlencode((string) $fila["cod_movimiento"]) ?>">
                                            Editar
                                        </a>

                                        <!-- formulario para eliminar el registro -->
                                        <form action="mov_entrada.php" method="POST" class="form-eliminar">
                                            <input type="hidden" name="cod_movimiento" value="<?= escapar($fila["cod_movimiento"]) ?>">
                                            <button type="submit" name="eliminar_movimiento" value="1"
                                                class="boton boton-chico boton-eliminar">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="tabla-vacia">No se encontraron movimientos de entrada.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ventana para confirmar la eliminacion -->
        <div class="modal" id="modalEliminar" hidden>
            <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="tituloEliminar">
                <div class="modal__icono modal__icono--aviso">!</div>
                <h3 class="modal__titulo" id="tituloEliminar">Eliminar movimiento</h3>
                <p class="modal__texto" id="textoEliminar">¿Seguro que deseas eliminar este registro?</p>
                <div class="modal__botones">
                    <button type="button" class="boton boton-secundario" id="cancelarEliminar">Cancelar</button>
                    <button type="button" class="boton boton-eliminar" id="confirmarEliminar">Eliminar</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <!-- enlaces de ayuda -->
        <section class="footer-ayuda">
            <h4>AYUDA Y SOPORTE</h4>
            <p class="footer-descripcion">
                Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.
            </p>

            <a href="ayuda.php" class="footer-enlace"><span class="footer-icono">ⓘ</span>Centro de ayuda</a>
            <a href="ayuda.php#privacidad" class="footer-enlace"><span class="footer-icono">♧</span>Privacidad y seguridad</a>
            <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">▤</span>Manual de uso del sistema</a>
            <a href="ayuda.php#soporte" class="footer-enlace"><span class="footer-icono">?</span>Asistencia técnica</a>
            <a href="ayuda.php#inventario" class="footer-enlace"><span class="footer-icono">▣</span>Gestión y control de inventario</a>
            <a href="ayuda.php#movimientos" class="footer-enlace"><span class="footer-icono">↔</span>Movimientos de entrada y salida</a>
        </section>

        <!-- informacion del sistema -->
        <section class="footer-informacion">
            <h4>INFORMACIÓN DEL SISTEMA</h4>

            <p><strong>Plataforma administrativa</strong><br>
                Herramienta para organizar y controlar los productos del supermercado.</p>

            <p><strong>Gestión de clientes</strong><br>
                Registro, consulta y actualización de la información de los clientes.</p>

            <p><strong>Control de movimientos</strong><br>
                Seguimiento de las entradas y salidas de mercancía.</p>

            <div class="footer-nota">
                <strong>Uso del sistema</strong>
                <p>Acceso destinado a empleados y administradores autorizados.</p>
            </div>
        </section>
    </div>

    <!-- parte inferior del pie de pagina -->
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

    
<script src = "../js/mov_entrada.js"></script>

</body>
</html>
