<?php
include_once(__DIR__ . "/sesion.php");

// 1. SESIÓN Y PERMISOS (solo el administrador entra)
if (
    !isset($_SESSION["usuario"], $_SESSION["rol"]) ||
    trim((string)$_SESSION["usuario"]) === "" ||
    !in_array((string)$_SESSION["rol"], ["1", "2"], true)
) {
    header("Location: ../index.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

$usuario = $_SESSION["usuario"];
$rol = (string)$_SESSION["rol"]; // 1 administrador | 2 empleado
$esAdmin = ($rol === "1");
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
$inicial = strtoupper(substr($usuario, 0, 1));

if (!$esAdmin) {
    header("Location: menu.php");
    exit();
}

include("conexion.php");

// productos con poco stock (notificaciones)
$totalStockBajo = 0;
try {
    $consultaStockBajo = $conn->query("SELECT COUNT(*) AS total FROM producto WHERE stock <= stock_minimo");
    $totalStockBajo = (int)($consultaStockBajo->fetch_assoc()["total"] ?? 0);
} catch (Throwable $error) {
    $totalStockBajo = 0;
}


// 2. FUNCIONES

// Convierte un texto a algo seguro para mostrar en HTML
function escapar($dato)
{
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

// actualiza el inventario de los productos
function actualizarInventario($conn, $codigos)
{
    foreach (array_unique($codigos) as $cod) {
        $cod = (int)$cod;
        if ($cod <= 0) {
            continue;
        }

        // total de entradas y de salidas del producto
        $stmt = $conn->prepare(
            "SELECT
                (SELECT COALESCE(SUM(cantidad), 0) FROM mov_entrada WHERE cod_producto = ?) AS entradas,
                (SELECT COALESCE(SUM(cantidad), 0) FROM mov_salida WHERE cod_producto = ?) AS salidas"
        );
        $stmt->bind_param("ii", $cod, $cod);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $entradas = (int)$total["entradas"];
        $salidas = (int)$total["salidas"];
        $stock = max(0, $entradas - $salidas);

        // mira si el producto ya tiene fila en inventario
        $stmt = $conn->prepare("SELECT cod_inventario FROM inventario WHERE cod_producto = ?");
        $stmt->bind_param("i", $cod);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existe) {
            // ya existe: solo actualiza los números
            $stmt = $conn->prepare("UPDATE inventario SET entradas = ?, salidas = ?, stock_actual = ? WHERE cod_producto = ?");
            $stmt->bind_param("iiii", $entradas, $salidas, $stock, $cod);
            $stmt->execute();
            $stmt->close();
        } else {
            // no existe: crea la fila del producto
            $stmt = $conn->prepare("SELECT nombre, precio FROM producto WHERE cod_producto = ?");
            $stmt->bind_param("i", $cod);
            $stmt->execute();
            $producto = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare(
                "SELECT CONCAT(p.cod_proveedor, ' - ', p.nombre) AS proveedor
                 FROM mov_entrada m
                 INNER JOIN proveedor p ON p.cod_proveedor = m.cod_proveedor
                 WHERE m.cod_producto = ?
                 ORDER BY m.cod_movimiento DESC
                 LIMIT 1"
            );
            $stmt->bind_param("i", $cod);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $proveedor = $fila ? $fila["proveedor"] : "";

            if ($producto) {
                $nombre = $producto["nombre"];
                $precio = (float)$producto["precio"];
                $stmt = $conn->prepare(
                    "INSERT INTO inventario (cod_producto, nombre_producto, precio_unitario, proveedor, entradas, salidas, stock_actual)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("isdsiii", $cod, $nombre, $precio, $proveedor, $entradas, $salidas, $stock);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// 3. VARIABLES QUE USA EL HTML
$mensaje = "";
$tipoMensaje = "";
$movimientoEditar = null;
$resultado = null;
$busqueda = "";

$proveedorTexto = "";
$proveedorCodigo = "";
$productoTexto = "";
$productoCodigo = "";

// 4. BUSCADORES DE PROVEEDOR Y PRODUCTO (responden JSON al JS)
$tablaBuscar = "";
$campoBuscar = "";
$textoBuscar = "";

if (isset($_GET["buscar_proveedor"])) {
    $tablaBuscar = "proveedor";
    $campoBuscar = "cod_proveedor";
    $textoBuscar = trim((string)$_GET["buscar_proveedor"]);
} elseif (isset($_GET["buscar_producto"])) {
    $tablaBuscar = "producto";
    $campoBuscar = "cod_producto";
    $textoBuscar = trim((string)$_GET["buscar_producto"]);
}

if ($tablaBuscar !== "") {
    header("Content-Type: application/json; charset=utf-8");
    $lista = [];

    if ($textoBuscar !== "") {
        $parteCodigo = trim(explode(" - ", $textoBuscar)[0]);
        $filtro = "%" . $textoBuscar . "%";
        $filtroCodigo = "%" . $parteCodigo . "%";

        $stmt = $conn->prepare(
            "SELECT $campoBuscar AS codigo, nombre
             FROM $tablaBuscar
             WHERE CAST($campoBuscar AS CHAR) LIKE ? OR nombre LIKE ?
             ORDER BY nombre ASC
             LIMIT 10"
        );
        $stmt->bind_param("ss", $filtroCodigo, $filtro);
        $stmt->execute();
        $lista = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    echo json_encode($lista, JSON_UNESCAPED_UNICODE);
    exit();
}

// 5. ELIMINAR UN MOVIMIENTO
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_movimiento"])) {
    try {
        $codigo = trim((string)($_POST["cod_movimiento"] ?? ""));
        if (!ctype_digit($codigo)) {
            throw new Exception("Código de movimiento no válido.");
        }
        $codigo = (int)$codigo;

        // guarda el producto para actualizar su stock
        $stmt = $conn->prepare("SELECT cod_producto FROM mov_entrada WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM mov_entrada WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $eliminados = $stmt->affected_rows;
        $stmt->close();

        if ($eliminados < 1) {
            throw new Exception("No se encontró el movimiento de entrada que deseas eliminar.");
        }

        actualizarInventario($conn, [(int)$fila["cod_producto"]]);

        header("Location: mov_entrada.php?mensaje=eliminado");
        exit();
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        if ($e->getCode() == 1451) {
            $mensaje = "No se puede eliminar el movimiento porque está relacionado con otros registros.";
        }
        $tipoMensaje = "error";
    }
}

// 6. GUARDAR UN MOVIMIENTO NUEVO O ACTUALIZAR UNO EXISTENTE
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST["guardar"]) || isset($_POST["actualizar"]))) {
    $esActualizar = isset($_POST["actualizar"]);

    $codigo = trim((string)($_POST["cod_movimiento"] ?? ""));
    $fecha = trim((string)($_POST["fecha"] ?? ""));
    $precio = str_replace(",", ".", trim((string)($_POST["precio"] ?? "")));
    $cantidad = trim((string)($_POST["cantidad"] ?? ""));
    $nota = trim((string)($_POST["nota"] ?? ""));
    $codProveedor = trim((string)($_POST["cod_proveedor"] ?? ""));
    $codProducto = trim((string)($_POST["cod_producto"] ?? ""));

    try {
        if (
            !ctype_digit($codigo) || $fecha === "" ||
            !is_numeric($precio) || (float)$precio < 0 ||
            !ctype_digit($cantidad) || (int)$cantidad <= 0 ||
            !ctype_digit($codProveedor) || !ctype_digit($codProducto)
        ) {
            throw new Exception("Completa todos los campos obligatorios con valores válidos.");
        }

        $codigo = (int)$codigo;
        $precio = (float)$precio;
        $cantidad = (int)$cantidad;
        $codProveedor = (int)$codProveedor;
        $codProducto = (int)$codProducto;

        // productos a los que hay que recalcular el stock
        $productosAfectados = [$codProducto];

        if ($esActualizar) {
            // el producto anterior también cambia si el usuario eligió otro
            $stmt = $conn->prepare("SELECT cod_producto FROM mov_entrada WHERE cod_movimiento = ?");
            $stmt->bind_param("i", $codigo);
            $stmt->execute();
            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($anterior) {
                $productosAfectados[] = (int)$anterior["cod_producto"];
            }

            $stmt = $conn->prepare(
                "UPDATE mov_entrada
                 SET fecha = ?, precio = ?, cantidad = ?, nota = ?, cod_proveedor = ?, cod_producto = ?
                 WHERE cod_movimiento = ?"
            );
            $stmt->bind_param("sdisiii", $fecha, $precio, $cantidad, $nota, $codProveedor, $codProducto, $codigo);
            $destino = "actualizado";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO mov_entrada (cod_movimiento, fecha, precio, cantidad, nota, cod_proveedor, cod_producto)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isdisii", $codigo, $fecha, $precio, $cantidad, $nota, $codProveedor, $codProducto);
            $destino = "registrado";
        }

        $stmt->execute();
        $stmt->close();

        actualizarInventario($conn, $productosAfectados);

        header("Location: mov_entrada.php?mensaje=" . $destino);
        exit();
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        if ($e->getCode() == 1062) {
            $mensaje = "El código del movimiento ya está registrado.";
        } elseif ($e->getCode() == 1452) {
            $mensaje = "El proveedor o producto seleccionado no existe.";
        }
        $tipoMensaje = "error";
    }
}

// 7. CARGAR EL MOVIMIENTO QUE SE VA A EDITAR
if (($_GET["accion"] ?? "") === "editar") {
    try {
        $codigo = trim((string)($_GET["cod_movimiento"] ?? ""));
        if (!ctype_digit($codigo)) {
            throw new Exception("Código de movimiento no válido.");
        }
        $codigo = (int)$codigo;

        $stmt = $conn->prepare(
            "SELECT m.*, p.nombre AS nombre_proveedor, pr.nombre AS nombre_producto
             FROM mov_entrada m
             LEFT JOIN proveedor p ON m.cod_proveedor = p.cod_proveedor
             LEFT JOIN producto pr ON m.cod_producto = pr.cod_producto
             WHERE m.cod_movimiento = ?"
        );
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $movimientoEditar = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$movimientoEditar) {
            throw new Exception("No se encontró el movimiento seleccionado.");
        }

        $proveedorCodigo = $movimientoEditar["cod_proveedor"];
        $productoCodigo = $movimientoEditar["cod_producto"];
        $proveedorTexto = $proveedorCodigo . " - " . $movimientoEditar["nombre_proveedor"];
        $productoTexto = $productoCodigo . " - " . $movimientoEditar["nombre_producto"];
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    }
}

// 8. MENSAJES DE ÉXITO (vienen en la URL después de redirigir)
$mensajesOk = [
    "registrado" => "Movimiento de entrada registrado correctamente.",
    "actualizado" => "Movimiento de entrada actualizado correctamente.",
    "eliminado" => "Movimiento de entrada eliminado correctamente."
];

$clave = (string)($_GET["mensaje"] ?? "");
if (isset($mensajesOk[$clave])) {
    $mensaje = $mensajesOk[$clave];
    $tipoMensaje = ($clave === "eliminado") ? "eliminado" : "ok";
}

// 9. LISTADO DE MOVIMIENTOS (con búsqueda)
try {
    $busqueda = trim((string)($_GET["buscar"] ?? ""));
    $filtro = "%" . $busqueda . "%";

    $stmt = $conn->prepare(
        "SELECT m.cod_movimiento, m.fecha, m.precio, m.cantidad, m.nota,
                m.cod_proveedor, p.nombre AS nombre_proveedor,
                m.cod_producto, pr.nombre AS nombre_producto
         FROM mov_entrada m
         LEFT JOIN proveedor p ON m.cod_proveedor = p.cod_proveedor
         LEFT JOIN producto pr ON m.cod_producto = pr.cod_producto
         WHERE CAST(m.cod_movimiento AS CHAR) LIKE ?
            OR m.fecha LIKE ?
            OR CAST(m.cod_proveedor AS CHAR) LIKE ?
            OR p.nombre LIKE ?
            OR CAST(m.cod_producto AS CHAR) LIKE ?
            OR pr.nombre LIKE ?
         ORDER BY m.cod_movimiento DESC"
    );
    $stmt->bind_param("ssssss", $filtro, $filtro, $filtro, $filtro, $filtro, $filtro);
    $stmt->execute();
    $resultado = $stmt->get_result();
} catch (Exception $e) {
    $mensaje = "No se pudo cargar el listado de movimientos de entrada.";
    $tipoMensaje = "error";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- el celular ve la pagina como en el computador -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Entrada | La Granja</title>
    <link rel="stylesheet" href="../css/mov_entrada.css?v=20261120">
</head>
<body class="pagina-entrada">

<header class="encabezado">

    <div class="encabezado-marca">
        <button type="button" class="boton-menu" id="abrirMenu"
            aria-label="Abrir menú lateral" aria-controls="menuLateral"
            aria-expanded="false">
            <img src="../img/menu.png" alt="" class="icono">
        </button>

        <div class="encabezado-logo">
            <img src="../img/logo.png" alt="Logo de La Granja">
        </div>

        <div class="encabezado-texto">
            <h1>Supermercado La Granja</h1>
            <p>Sistema de gestión de inventario</p>
        </div>
    </div>

    <div class="encabezado-herramientas">
        <div class="encabezado-fecha">
            <img src="../img/calendario.png" alt="" class="icono-herramienta">
            <span><?= date("d/m/Y") ?></span>
        </div>

        <a href="ayuda.php#inventario"
            class="boton-herramienta boton-notificaciones" aria-label="Productos con pocas existencias">
            <img src="../img/notificaciones.png" alt="" class="icono-herramienta">
            <?php if ($totalStockBajo > 0): ?>
                <span class="numero-notificaciones"><?= $totalStockBajo ?></span>
            <?php endif; ?>
        </a>

        <a href="ayuda.php" class="boton-herramienta" aria-label="Centro de ayuda">
            <img src="../img/ayuda.png" alt="" class="icono-herramienta">
        </a>

        <div class="encabezado-usuario">
            <span class="usuario-inicial"><?= htmlspecialchars($inicial) ?></span>
            <div>
                <strong><?= htmlspecialchars($usuario) ?></strong>
                <p><?= htmlspecialchars($nombreCargo) ?></p>
            </div>
        </div>
    </div>

    <!-- ola verde del encabezado -->
    <img src="../img/ola.png" alt="" class="encabezado-ola">

</header>

<div class="fondo-menu" id="fondoMenu"></div>

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
            <img src="../img/cerrar.png" alt="" class="icono">
        </button>
    </div>

    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= escapar($inicial) ?></div>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>

    <nav class="navegacion">
        <p class="titulo-navegacion">MENÚ PRINCIPAL</p>

        <div class="lista-navegacion">
            <a href="menu.php" class="enlace-menu">
                <span class="caja-icono"><img src="../img/inicio.png" alt="" class="icono"></span>
                <span>Inicio</span>
            </a>

            <a href="cliente.php" class="enlace-menu">
                <span class="caja-icono"><img src="../img/cliente.png" alt="" class="icono"></span>
                <span>Cliente</span>
            </a>

            <a href="mov_salida.php" class="enlace-menu">
                <span class="caja-icono"><img src="../img/salida.png" alt="" class="icono"></span>
                <span>Movimiento de salida</span>
            </a>

            <?php if ($esAdmin): ?>
                <a href="producto.php" class="enlace-menu">
                    <span class="caja-icono"><img src="../img/producto.png" alt="" class="icono"></span>
                    <span>Productos</span>
                </a>

                <a href="mov_entrada.php" class="enlace-menu activo" aria-current="page">
                    <span class="caja-icono"><img src="../img/entrada.png" alt="" class="icono"></span>
                    <span>Movimiento de entrada</span>
                </a>

                <a href="proveedor.php" class="enlace-menu">
                    <span class="caja-icono"><img src="../img/proveedor.png" alt="" class="icono"></span>
                    <span>Proveedor</span>
                </a>

                <a href="inventario.php" class="enlace-menu">
                    <span class="caja-icono"><img src="../img/inventario.png" alt="" class="icono"></span>
                    <span>Inventario</span>
                </a>

                <a href="reportes.php" class="enlace-menu">
                    <span class="caja-icono"><img src="../img/reportes.png" alt="" class="icono"></span>
                    <span>Reportes</span>
                </a>

                <a href="edita_usuario.php" class="enlace-menu">
                    <span class="caja-icono"><img src="../img/usuario.png" alt="" class="icono"></span>
                    <span>Modificar usuario</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <form action="cerrar_sesion.php" method="POST" class="form-salir">
        <button type="submit" name="salir" value="1" class="boton-salir">
            <span class="caja-icono caja-icono-roja"><img src="../img/salir.png" alt="" class="icono"></span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<main class="contenido">
    <section class="titulo-pagina">
        <span class="titulo-icono"><img src="../img/entrada.png" alt="" class="icono"></span>
        <div>
            <h2>Movimiento de Entrada</h2>
            <p>
                <?= $esAdmin
                    ? "Registra, busca, edita o elimina los movimientos de entrada."
                    : "Registra una nueva entrada de productos en el inventario." ?>
            </p>
        </div>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h3 class="panel-titulo">
            <?= $movimientoEditar ? "Editar movimiento de entrada" : "Ingresar movimiento de entrada" ?>
        </h3>

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

            <div class="campo">
                <label for="proveedor_busqueda">Proveedor <span class="obligatorio">*</span></label>
                <input type="text" id="proveedor_busqueda" list="lista_proveedores" autocomplete="off"
                    placeholder="Escriba código o nombre" value="<?= escapar($proveedorTexto) ?>" required>
                <datalist id="lista_proveedores"></datalist>
                <input type="hidden" name="cod_proveedor" id="cod_proveedor" value="<?= escapar($proveedorCodigo) ?>">
                <p id="estado_proveedor" class="texto-campo-error"></p>
            </div>

            <div class="campo">
                <label for="producto_busqueda">Producto <span class="obligatorio">*</span></label>
                <input type="text" id="producto_busqueda" list="lista_productos" autocomplete="off"
                    placeholder="Escriba código o nombre" value="<?= escapar($productoTexto) ?>" required>
                <datalist id="lista_productos"></datalist>
                <input type="hidden" name="cod_producto" id="cod_producto" value="<?= escapar($productoCodigo) ?>">
                <p id="estado_producto" class="texto-campo-error"></p>
            </div>

            <div class="campo campo-ancho">
                <label for="nota">Nota u observación</label>
                <textarea id="nota" name="nota" placeholder="Ingrese nota u observación"><?= escapar($movimientoEditar["nota"] ?? "") ?></textarea>
            </div>

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

    <?php if ($esAdmin): ?>
        <section class="panel">
            <h3 class="panel-titulo">Buscar movimiento de entrada</h3>

            <form action="mov_entrada.php" method="GET" class="buscador" id="formBuscador">
                <input type="text" name="buscar" id="campoBuscar" autocomplete="off" placeholder="Código, fecha, proveedor o producto..."
                    value="<?= escapar($busqueda) ?>">

                <a href="mov_entrada.php" class="boton boton-secundario" id="limpiarBusqueda"
                    <?= $busqueda === "" ? "hidden" : "" ?>>Mostrar todos</a>
            </form>
        </section>

        <section class="panel">
            <h3 class="panel-titulo">Listado de movimientos de entrada</h3>

            <div class="tabla-contenedor tabla-scroll">
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
                        <?php if ($resultado &&$resultado->num_rows > 0): ?>
                            <?php while ($fila =$resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?= escapar($fila["cod_movimiento"]) ?></td>
                                    <td><?= escapar($fila["fecha"]) ?></td>
                                    <td><?= "$" . number_format((float)$fila["precio"], 2, ",", ".") ?></td>
                                    <td><?= escapar($fila["cantidad"]) ?></td>
                                    <td><?= escapar($fila["nota"] ?? "") ?></td>
                                    <td><?= escapar($fila["cod_proveedor"] . " - " . ($fila["nombre_proveedor"] ?? "Sin nombre")) ?></td>
                                    <td><?= escapar($fila["cod_producto"] . " - " . ($fila["nombre_producto"] ?? "Sin nombre")) ?></td>
                                    <td class="tabla-acciones">
                                        <a class="boton boton-chico boton-editar"
                                            href="mov_entrada.php?accion=editar&amp;cod_movimiento=<?= urlencode((string) $fila["cod_movimiento"]) ?>">
                                            Editar
                                        </a>

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

        <div class="modal" id="modalEliminar" data-modulo="entrada" hidden>
            <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="tituloEliminar">
                <div class="modal__icono">
                    <img src="../img/error.png" alt="Error" width="80" height="80">
                </div>
                <h3 class="modal__titulo" id="tituloEliminar">Eliminar movimiento de entrada</h3>
                <p class="modal__texto" id="textoEliminar">¿Seguro que deseas eliminar este movimiento de entrada? Esta acción no se puede deshacer.</p>
                <div class="modal__botones">
                    <button type="button" class="boton boton-secundario" id="cancelarEliminar">Cancelar</button>
                    <button type="button" class="boton boton-eliminar" id="confirmarEliminar">Eliminar</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<footer class="pie-pagina">
    <div class="footer-contenido">
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

    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<script src="../js/mov_entrada.js?v=2"></script>
</body>
</html>