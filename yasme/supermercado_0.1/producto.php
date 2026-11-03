<?php
include("conexion.php");

// conexion
// revisa que haya conexion a la base de datos
if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("No se encontró una conexión válida en conexion.php.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// usuario y cargo
// cerrar sesion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["salir"])) {
    header("Location: index.html");
    exit();
}

// usuario y rol que llegan del menu
$usuario = trim((string)($_POST["usuario"] ?? $_GET["usuario"] ?? ""));
$rol = (string)($_POST["rol"] ?? $_GET["rol"] ?? "");

if ($usuario === "" || ($rol !== "1" && $rol !== "2")) {
    header("Location: index.html");
    exit();
}

$esAdmin = ($rol === "1");
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
$inicial = strtoupper(substr(trim($usuario), 0, 1));
$parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($rol);

// paginas del menu (admin true = solo administrador)
$paginas = [
    "inicio"      => ["archivo" => "menu.php",         "texto" => "Inicio",                "icono" => "inicio.png",     "admin" => false],
    "cliente"     => ["archivo" => "cliente.php",      "texto" => "Cliente",               "icono" => "cliente.png",    "admin" => false],
    "mov_salida"  => ["archivo" => "mov_salida.php",   "texto" => "Movimiento de salida",  "icono" => "salida.png",     "admin" => false],
    "producto"    => ["archivo" => "producto.php",     "texto" => "Productos",             "icono" => "producto.png",   "admin" => true],
    "mov_entrada" => ["archivo" => "mov_entrada.php",  "texto" => "Movimiento de entrada", "icono" => "entrada.png",    "admin" => true],
    "proveedor"   => ["archivo" => "proveedor.php",    "texto" => "Proveedor",             "icono" => "proveedor.png",  "admin" => true],
    "inventario"  => ["archivo" => "inventario.php",   "texto" => "Inventario",            "icono" => "inventario.png", "admin" => true],
    "reportes"    => ["archivo" => "reportes.php",     "texto" => "Reportes",              "icono" => "reportes.png",   "admin" => true],
    "usuario"     => ["archivo" => "edita_usuario.php","texto" => "Modificar usuario",     "icono" => "usuario.png",    "admin" => true],
];

// categorias del supermercado (valor guardado => texto que se ve)
$categorias = [
    "abarrotes"          => "Abarrotes",
    "granos y cereales"  => "Granos y Cereales",
    "enlatados"          => "Enlatados",
    "aceites y salsas"   => "Aceites y Salsas",
    "frutas"             => "Frutas",
    "verduras"           => "Verduras",
    "carnes"             => "Carnes",
    "pollo"              => "Pollo",
    "pescados y mariscos" => "Pescados y Mariscos",
    "embutidos"          => "Embutidos",
    "lacteos"            => "Lácteos",
    "huevos"             => "Huevos",
    "panaderia"          => "Panadería",
    "bebidas"            => "Bebidas",
    "licores"            => "Licores",
    "snacks"             => "Snacks",
    "dulces"             => "Dulces",
    "congelados"         => "Congelados",
    "aseo personal"      => "Aseo Personal",
    "aseo del hogar"     => "Aseo del Hogar",
    "mascotas"           => "Mascotas",
    "bebes"              => "Bebés",
    "hogar y cocina"     => "Hogar y Cocina",
    "papeleria"          => "Papelería",
];

// variables de la pagina
$mensaje = "";
$tipoMensaje = "";
$productoEditar = null;
$busqueda = trim((string)($_GET["buscar"] ?? ""));
$productos = null;
$proveedorTexto = "";
$proveedorCodigo = "";

// acciones de productos
// busca proveedores para el autocompletado
if (isset($_GET["buscar_proveedor"])) {
    header("Content-Type: application/json; charset=utf-8");

    try {
        $texto = trim((string)$_GET["buscar_proveedor"]);
        if ($texto === "") {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $parteCodigo = trim(explode(" - ", $texto)[0]);
        $filtro = "%" . $texto . "%";
        $filtroCodigo = "%" . $parteCodigo . "%";

        $stmt = $conn->prepare(
            "SELECT cod_proveedor, nombre
             FROM proveedor
             WHERE CAST(cod_proveedor AS CHAR) LIKE ? OR nombre LIKE ?
             ORDER BY
               CASE
                 WHEN CAST(cod_proveedor AS CHAR) = ? THEN 0
                 WHEN nombre = ? THEN 0
                 ELSE 1
               END,
               nombre ASC
             LIMIT 10"
        );
        $stmt->bind_param("ssss", $filtroCodigo, $filtro, $parteCodigo, $texto);
        $stmt->execute();
        $resultado = $stmt->get_result();

        $lista = [];
        while ($fila = $resultado->fetch_assoc()) {
            $lista[] = [
                "codigo" => (string)$fila["cod_proveedor"],
                "nombre" => (string)$fila["nombre"]
            ];
        }
        $stmt->close();

        echo json_encode($lista, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([]);
    }
    exit();
}

// eliminar producto (solo admin)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_producto"])) {
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para eliminar productos.";
        $tipoMensaje = "error";
    } else {
        $codigoEliminar = trim((string)($_POST["cod_producto"] ?? ""));

        try {
            if (!ctype_digit($codigoEliminar)) {
                throw new InvalidArgumentException("Código de producto no válido.");
            }

            $codigoNumero = (int)$codigoEliminar;
            $stmt = $conn->prepare("DELETE FROM producto WHERE cod_producto = ?");
            $stmt->bind_param("i", $codigoNumero);
            $stmt->execute();
            $eliminados = $stmt->affected_rows;
            $stmt->close();

            if ($eliminados > 0) {
                header("Location: producto.php?" . $parametros . "&mensaje=eliminado");
                exit();
            }

            $mensaje = "No se encontró el producto que deseas eliminar.";
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            $mensaje = ((int)$e->getCode() === 1451)
                ? "No se puede eliminar el producto porque tiene movimientos registrados."
                : "Ocurrió un error al eliminar el producto.";
            $tipoMensaje = "error";
        } catch (InvalidArgumentException $e) {
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}

// cargar producto para editar (solo admin)
if (isset($_GET["accion"]) && $_GET["accion"] === "editar") {
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para editar productos.";
        $tipoMensaje = "error";
    } else {
        $codigoBuscar = trim((string)($_GET["cod_producto"] ?? ""));

        try {
            if (!ctype_digit($codigoBuscar)) {
                throw new InvalidArgumentException("Código de producto no válido.");
            }

            $codigoNumero = (int)$codigoBuscar;
            $stmt = $conn->prepare("SELECT * FROM producto WHERE cod_producto = ?");
            $stmt->bind_param("i", $codigoNumero);
            $stmt->execute();
            $productoEditar = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$productoEditar) {
                $mensaje = "No se encontró el producto seleccionado.";
                $tipoMensaje = "error";
            } else {
                $proveedorCodigo = (string)$productoEditar["cod_proveedor"];
                $codigoNumeroProveedor = (int)$proveedorCodigo;
                $stmt = $conn->prepare("SELECT nombre FROM proveedor WHERE cod_proveedor = ?");
                $stmt->bind_param("i", $codigoNumeroProveedor);
                $stmt->execute();
                $filaProveedor = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($filaProveedor) {
                    $proveedorTexto = $proveedorCodigo . " - " . $filaProveedor["nombre"];
                }
            }
        } catch (InvalidArgumentException $e) {
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            $mensaje = "No se pudieron cargar los datos del producto.";
            $tipoMensaje = "error";
        }
    }
}

// actualizar producto (solo admin)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar"])) {
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para actualizar productos.";
        $tipoMensaje = "error";
    } else {
        $codigo = trim((string)($_POST["cod_producto"] ?? ""));
        $nombre = trim((string)($_POST["nombre"] ?? ""));
        $precio = trim((string)($_POST["precio"] ?? ""));
        $codProveedor = trim((string)($_POST["cod_proveedor"] ?? ""));
        $categoria = trim((string)($_POST["categoria"] ?? ""));
        $descripcion = trim((string)($_POST["descripcion"] ?? ""));

        try {
            if (
                !ctype_digit($codigo) || $nombre === "" ||
                !is_numeric($precio) || (float)$precio < 0 ||
                !ctype_digit($codProveedor) || !array_key_exists($categoria, $categorias) ||
                $descripcion === ""
            ) {
                throw new InvalidArgumentException("Completa todos los campos correctamente.");
            }

            $codProveedorNumero = (int)$codProveedor;
            $stmt = $conn->prepare("SELECT cod_proveedor FROM proveedor WHERE cod_proveedor = ?");
            $stmt->bind_param("i", $codProveedorNumero);
            $stmt->execute();
            $existeProveedor = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$existeProveedor) {
                throw new InvalidArgumentException("Selecciona un proveedor válido.");
            }

            $precioNumero = (float)$precio;
            $codigoNumero = (int)$codigo;
            $stmt = $conn->prepare(
                "UPDATE producto
                 SET nombre = ?, precio = ?, categoria = ?, descripcion = ?, cod_proveedor = ?
                 WHERE cod_producto = ?"
            );
            $stmt->bind_param(
                "sdssii",
                $nombre, $precioNumero, $categoria, $descripcion,
                $codProveedorNumero, $codigoNumero
            );
            $stmt->execute();
            $stmt->close();

            header("Location: producto.php?" . $parametros . "&mensaje=actualizado");
            exit();
        } catch (InvalidArgumentException $e) {
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            $mensaje = "Error al actualizar el producto. Verifica los datos.";
            $tipoMensaje = "error";
        }
    }
}

// registrar producto
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar"])) {
    $codigo = trim((string)($_POST["cod_producto"] ?? ""));
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $precio = trim((string)($_POST["precio"] ?? ""));
    $codProveedor = trim((string)($_POST["cod_proveedor"] ?? ""));
    $categoria = trim((string)($_POST["categoria"] ?? ""));
    $descripcion = trim((string)($_POST["descripcion"] ?? ""));

    try {
        if (
            !ctype_digit($codigo) || (int)$codigo < 1 ||
            $nombre === "" || !is_numeric($precio) || (float)$precio < 0 ||
            !ctype_digit($codProveedor) ||
            !array_key_exists($categoria, $categorias) ||
            $descripcion === ""
        ) {
            throw new InvalidArgumentException("Completa todos los campos correctamente.");
        }

        $codProveedorNumero = (int)$codProveedor;
        $stmt = $conn->prepare("SELECT cod_proveedor FROM proveedor WHERE cod_proveedor = ?");
        $stmt->bind_param("i", $codProveedorNumero);
        $stmt->execute();
        $existeProveedor = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$existeProveedor) {
            throw new InvalidArgumentException("Debes seleccionar un proveedor válido.");
        }

        $codigoNumero = (int)$codigo;
        $precioNumero = (float)$precio;
        $stmt = $conn->prepare(
            "INSERT INTO producto
                (cod_producto, nombre, precio, categoria, descripcion, cod_proveedor)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "isdssi",
            $codigoNumero, $nombre, $precioNumero, $categoria, $descripcion, $codProveedorNumero
        );
        $stmt->execute();
        $stmt->close();

        header("Location: producto.php?" . $parametros . "&mensaje=registrado");
        exit();
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        $mensaje = ((int)$e->getCode() === 1062)
            ? "El código del producto ya está registrado."
            : "Error al registrar el producto. Verifica los datos.";
        $tipoMensaje = "error";
    }
}

// mensajes despues de registrar, editar o eliminar
if (isset($_GET["mensaje"])) {
    if ($_GET["mensaje"] === "registrado") {
        $mensaje = "Producto registrado correctamente.";
        $tipoMensaje = "ok";
    } elseif ($_GET["mensaje"] === "actualizado") {
        $mensaje = "Producto actualizado correctamente.";
        $tipoMensaje = "ok";
    } elseif ($_GET["mensaje"] === "eliminado") {
        $mensaje = "Producto eliminado correctamente.";
        $tipoMensaje = "error";
    }
}

// listado y busqueda de productos (solo admin)
if ($esAdmin) {
    try {
        if ($busqueda !== "") {
            $filtro = "%" . $busqueda . "%";
            $stmt = $conn->prepare(
                "SELECT p.*, pr.nombre AS nombre_proveedor
                 FROM producto p
                 INNER JOIN proveedor pr ON p.cod_proveedor = pr.cod_proveedor
                 WHERE CAST(p.cod_producto AS CHAR) LIKE ?
                    OR p.nombre LIKE ?
                    OR p.categoria LIKE ?
                    OR p.descripcion LIKE ?
                    OR pr.nombre LIKE ?
                 ORDER BY p.cod_producto ASC"
            );
            $stmt->bind_param("sssss", $filtro, $filtro, $filtro, $filtro, $filtro);
            $stmt->execute();
            $productos = $stmt->get_result();
        } else {
            $productos = $conn->query(
                "SELECT p.*, pr.nombre AS nombre_proveedor
                 FROM producto p
                 INNER JOIN proveedor pr ON p.cod_proveedor = pr.cod_proveedor
                 ORDER BY p.cod_producto ASC"
            );
        }
    } catch (mysqli_sql_exception $e) {
        $mensaje = "No se pudo cargar el listado de productos.";
        $tipoMensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos | La Granja</title>
    <link rel="stylesheet" href="css/producto.css">
</head>
<body class="pagina-producto">

<!-- encabezado -->
<header class="encabezado">
    <button type="button" class="boton-menu" id="abrirMenu" aria-label="Abrir menú lateral" aria-controls="menuLateral" aria-expanded="false">
        <img src="img/menu.png" alt="" class="icono">
    </button>
    <div class="encabezado-logo">
        <img src="img/logo.png" alt="Logo de La Granja">
    </div>
    <div class="encabezado-texto">
        <h1>Supermercado La Granja</h1>
        <p>Sistema de gestión de inventario</p>
    </div>
    <div class="encabezado-usuario">
        <span class="usuario-inicial"><?= htmlspecialchars($inicial, ENT_QUOTES, "UTF-8") ?></span>
        <div>
            <strong><?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?></strong>
            <p><?= htmlspecialchars($nombreCargo, ENT_QUOTES, "UTF-8") ?></p>
        </div>
    </div>
</header>

<div class="fondo-menu" id="fondoMenu"></div>

<!-- menu lateral -->
<aside class="menu-lateral" id="menuLateral" aria-label="Menú principal">
    <div class="menu-lateral-encabezado">
        <div class="menu-lateral-marca">
            <img src="img/logo.png" alt="Logo de Supermercado La Granja">
            <div>
                <h2>Supermercado</h2>
                <p>La Granja</p>
            </div>
        </div>
        <button type="button" class="boton-cerrar" id="cerrarMenu" aria-label="Cerrar menú lateral">
            <img src="img/cerrar.png" alt="" class="icono">
        </button>
    </div>

    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= htmlspecialchars($inicial, ENT_QUOTES, "UTF-8") ?></div>
        <div>
            <strong><?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?></strong>
            <p><?= htmlspecialchars($nombreCargo, ENT_QUOTES, "UTF-8") ?></p>
        </div>
    </div>

    <nav class="navegacion">
        <p class="titulo-navegacion">MENÚ PRINCIPAL</p>
        <div class="lista-navegacion">
            <?php foreach ($paginas as $clave => $pagina): ?>
                <?php if ($pagina["admin"] && !$esAdmin) { continue; } ?>
                <?php $esActual = ($clave === "producto"); ?>
                <a href="<?= htmlspecialchars($pagina["archivo"] . "?" . $parametros, ENT_QUOTES, "UTF-8") ?>" class="enlace-menu<?= $esActual ? " activo" : "" ?>"<?= $esActual ? ' aria-current="page"' : "" ?>>
                    <span class="caja-icono"><img src="img/<?= $pagina["icono"] ?>" alt="" class="icono"></span>
                    <span><?= htmlspecialchars($pagina["texto"], ENT_QUOTES, "UTF-8") ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <form action="menu.php" method="POST" class="form-salir">
        <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?>">
        <input type="hidden" name="rol" value="<?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>">
        <button type="submit" name="salir" value="1" class="boton-salir">
            <span class="caja-icono caja-icono-roja"><img src="img/salir.png" alt="" class="icono"></span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<!-- contenido principal -->
<main class="contenido">
    <section class="titulo-pagina">
        <span class="titulo-icono"><img src="img/producto.png" alt="" class="icono"></span>
        <div>
            <h2>Productos</h2>
            <p><?= $esAdmin ? "Registra, busca, edita o elimina los productos registrados." : "Registra un producto nuevo en el inventario." ?></p>
        </div>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= htmlspecialchars($tipoMensaje, ENT_QUOTES, "UTF-8") ?>" role="status">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <!-- formulario de producto -->
    <section class="panel">
        <h3 class="panel-titulo"><?= $productoEditar ? "Editar producto" : "Ingresar producto" ?></h3>
        <form action="producto.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>" method="POST" id="formularioProducto" class="formulario">
            <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?>">
            <input type="hidden" name="rol" value="<?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>">

            <?php if ($productoEditar): ?>
                <input type="hidden" name="cod_producto" value="<?= htmlspecialchars((string)$productoEditar["cod_producto"], ENT_QUOTES, "UTF-8") ?>">
                <div class="campo">
                    <label for="codigo_visible">Código del producto</label>
                    <input id="codigo_visible" type="number" value="<?= htmlspecialchars((string)$productoEditar["cod_producto"], ENT_QUOTES, "UTF-8") ?>" disabled>
                </div>
            <?php else: ?>
                <div class="campo">
                    <label for="cod_producto">Código del producto</label>
                    <input id="cod_producto" type="number" name="cod_producto" min="1" required placeholder="Ingrese código">
                </div>
            <?php endif; ?>

            <div class="campo">
                <label for="nombre">Nombre</label>
                <input id="nombre" type="text" name="nombre" required value="<?= htmlspecialchars((string)($productoEditar["nombre"] ?? ""), ENT_QUOTES, "UTF-8") ?>" placeholder="Ingrese nombre">
            </div>
            <div class="campo">
                <label for="precio">Precio</label>
                <input id="precio" type="number" name="precio" min="0" step="0.01" required value="<?= htmlspecialchars((string)($productoEditar["precio"] ?? ""), ENT_QUOTES, "UTF-8") ?>" placeholder="Ingrese precio">
            </div>
            <div class="campo">
                <label for="proveedor_busqueda">Proveedor</label>
                <input type="text" id="proveedor_busqueda" list="lista_proveedores" autocomplete="off" placeholder="Escriba el código o el nombre" value="<?= htmlspecialchars($proveedorTexto, ENT_QUOTES, "UTF-8") ?>" required>
                <datalist id="lista_proveedores"></datalist>
                <input type="hidden" name="cod_proveedor" id="cod_proveedor" value="<?= htmlspecialchars($proveedorCodigo, ENT_QUOTES, "UTF-8") ?>">
                <p id="estado_proveedor" class="texto-campo-error" aria-live="polite"></p>
            </div>
            <div class="campo">
                <label for="categoria">Categoría</label>
                <select id="categoria" name="categoria" required>
                    <option value="">Seleccione una categoría</option>
                    <?php foreach ($categorias as $valor => $texto): ?>
                        <option value="<?= htmlspecialchars($valor, ENT_QUOTES, "UTF-8") ?>" <?= (($productoEditar["categoria"] ?? "") === $valor) ? "selected" : "" ?>><?= htmlspecialchars($texto, ENT_QUOTES, "UTF-8") ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo campo-ancho">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" required placeholder="Ingrese descripción"><?= htmlspecialchars((string)($productoEditar["descripcion"] ?? ""), ENT_QUOTES, "UTF-8") ?></textarea>
            </div>
            <div class="botones campo-ancho">
                <?php if ($productoEditar): ?>
                    <button type="submit" name="actualizar" value="1" class="boton boton-principal">Actualizar producto</button>
                    <a href="producto.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>" class="boton boton-secundario">Cancelar</a>
                <?php else: ?>
                    <button type="submit" name="guardar" value="1" class="boton boton-principal">Registrar producto</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if ($esAdmin): ?>
        <!-- buscador -->
        <section class="panel">
            <h3 class="panel-titulo">Buscar producto</h3>
            <form action="producto.php" method="GET" class="buscador">
                <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?>">
                <input type="hidden" name="rol" value="<?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>">
                <input type="text" name="buscar" placeholder="Código, nombre, categoría, proveedor o descripción..." value="<?= htmlspecialchars($busqueda, ENT_QUOTES, "UTF-8") ?>">
                <button type="submit" class="boton boton-principal">Buscar</button>
                <?php if ($busqueda !== ""): ?>
                    <a href="producto.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>" class="boton boton-secundario">Mostrar todos</a>
                <?php endif; ?>
            </form>
        </section>

        <!-- tabla de productos -->
        <section class="panel">
            <h3 class="panel-titulo">Listado de productos</h3>
            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Precio</th>
                            <th>Proveedor</th>
                            <th>Categoría</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($productos && $productos->num_rows > 0): ?>
                        <?php while ($fila = $productos->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$fila["cod_producto"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars((string)$fila["nombre"], ENT_QUOTES, "UTF-8") ?></td>
                                <td>$<?= number_format((float)$fila["precio"], 2, ",", ".") ?></td>
                                <td><?= htmlspecialchars($fila["cod_proveedor"] . " - " . $fila["nombre_proveedor"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars((string)$fila["categoria"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars((string)$fila["descripcion"], ENT_QUOTES, "UTF-8") ?></td>
                                <td class="tabla-acciones">
                                    <a class="boton boton-chico boton-editar" href="producto.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>&amp;accion=editar&amp;cod_producto=<?= urlencode((string)$fila["cod_producto"]) ?>">Editar</a>
                                    <form action="producto.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>" method="POST" class="form-eliminar" onsubmit="return confirm('¿Seguro que deseas eliminar este producto?');">
                                        <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?>">
                                        <input type="hidden" name="rol" value="<?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>">
                                        <input type="hidden" name="cod_producto" value="<?= htmlspecialchars((string)$fila["cod_producto"], ENT_QUOTES, "UTF-8") ?>">
                                        <button type="submit" name="eliminar_producto" value="1" class="boton boton-chico boton-eliminar">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="tabla-vacia">No se encontraron productos.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <section class="footer-ayuda">
            <h4>AYUDA Y SOPORTE</h4>
            <p class="footer-descripcion">Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.</p>
            <a href="ayuda.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>#uso" class="footer-enlace"><span class="footer-icono">ⓘ</span>Centro de ayuda</a>
            <a href="ayuda.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>#privacidad" class="footer-enlace"><span class="footer-icono">♧</span>Privacidad y seguridad</a>
            <a href="ayuda.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>#uso" class="footer-enlace"><span class="footer-icono">▤</span>Manual de uso del sistema</a>
            <a href="ayuda.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>#soporte" class="footer-enlace"><span class="footer-icono">?</span>Asistencia técnica</a>
            <a href="ayuda.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>#inventario" class="footer-enlace"><span class="footer-icono">▣</span>Gestión y control de inventario</a>
            <a href="ayuda.php?<?= htmlspecialchars($parametros, ENT_QUOTES, "UTF-8") ?>#movimientos" class="footer-enlace"><span class="footer-icono">↔</span>Movimientos de entrada y salida</a>
        </section>
        <section class="footer-informacion">
            <h4>INFORMACIÓN DEL SISTEMA</h4>
            <p><strong>Plataforma administrativa</strong><br>Herramienta para organizar y controlar los productos del supermercado.</p>
            <p><strong>Gestión de productos</strong><br>Registro, consulta y actualización de información de los productos.</p>
            <p><strong>Control de movimientos</strong><br>Seguimiento de las entradas y salidas de mercancía.</p>
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

<script>
// menu lateral
const abrir = document.getElementById("abrirMenu");
const cerrar = document.getElementById("cerrarMenu");
const fondo = document.getElementById("fondoMenu");

function mostrarMenu() {
    document.body.classList.add("menu-abierto");
    abrir.setAttribute("aria-expanded", "true");
}

function ocultarMenu() {
    document.body.classList.remove("menu-abierto");
    abrir.setAttribute("aria-expanded", "false");
}

abrir.addEventListener("click", mostrarMenu);
cerrar.addEventListener("click", ocultarMenu);
fondo.addEventListener("click", ocultarMenu);

document.addEventListener("keydown", function(evento) {
    if (evento.key === "Escape") {
        ocultarMenu();
    }
});

// autocompletar y comprobar el proveedor
const campoProveedor = document.getElementById("proveedor_busqueda");
const campoCodigo = document.getElementById("cod_proveedor");
const lista = document.getElementById("lista_proveedores");
const estado = document.getElementById("estado_proveedor");
const formulario = document.getElementById("formularioProducto");
const datosUsuario = new URLSearchParams({
    usuario: <?= json_encode($usuario, JSON_UNESCAPED_UNICODE) ?>,
    rol: <?= json_encode($rol) ?>
});

let numeroConsulta = 0;

async function buscarProveedores(texto) {
    const parametros = new URLSearchParams(datosUsuario);
    parametros.set("buscar_proveedor", texto);
    const respuesta = await fetch("producto.php?" + parametros.toString(), {
        headers: { "Accept": "application/json" }
    });

    if (!respuesta.ok) {
        throw new Error("No se pudo consultar el proveedor.");
    }

    return await respuesta.json();
}

campoProveedor.addEventListener("input", async function() {
    campoCodigo.value = "";
    estado.textContent = "";
    const texto = campoProveedor.value.trim();
    const consulta = ++numeroConsulta;

    if (texto === "") {
        lista.replaceChildren();
        return;
    }

    try {
        const proveedores = await buscarProveedores(texto);
        if (consulta !== numeroConsulta) return;

        lista.replaceChildren();
        proveedores.forEach(function(proveedor) {
            const opcion = document.createElement("option");
            opcion.value = proveedor.codigo + " - " + proveedor.nombre;
            lista.appendChild(opcion);
        });
    } catch (error) {
        estado.textContent = "Error al buscar proveedores.";
        console.error(error);
    }
});

async function resolverProveedor() {
    const texto = campoProveedor.value.trim();
    campoCodigo.value = "";

    if (texto === "") {
        estado.textContent = "Escribe y selecciona un proveedor.";
        return false;
    }

    try {
        const proveedores = await buscarProveedores(texto);
        const normalizado = texto.toLocaleLowerCase("es");

        const proveedor = proveedores.find(function(p) {
            const codigo = String(p.codigo).trim();
            const nombre = String(p.nombre).trim();

            return texto === codigo ||
                normalizado === nombre.toLocaleLowerCase("es") ||
                texto === codigo + " - " + nombre;
        });

        if (!proveedor) {
            estado.textContent = "Proveedor no encontrado. Selecciona una sugerencia válida.";
            return false;
        }

        campoCodigo.value = String(proveedor.codigo);
        campoProveedor.value = proveedor.codigo + " - " + proveedor.nombre;
        estado.textContent = "";
        return true;
    } catch (error) {
        estado.textContent = "No se pudo verificar el proveedor.";
        console.error(error);
        return false;
    }
}

campoProveedor.addEventListener("change", resolverProveedor);

formulario.addEventListener("submit", async function(evento) {
    evento.preventDefault();

    const botonPulsado = evento.submitter;
    const valido = await resolverProveedor();

    if (!valido) {
        campoProveedor.focus();
        return;
    }

    if (!botonPulsado || !botonPulsado.name) {
        estado.textContent = "No se pudo identificar la acción del formulario.";
        return;
    }

    let accion = formulario.querySelector('input[data-accion-formulario="1"]');

    if (!accion) {
        accion = document.createElement("input");
        accion.type = "hidden";
        accion.dataset.accionFormulario = "1";
        formulario.appendChild(accion);
    }

    accion.name = botonPulsado.name;
    accion.value = botonPulsado.value || "1";
    formulario.submit();
});
</script>
</body>
</html>
