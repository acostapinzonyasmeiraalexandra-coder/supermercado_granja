<?php
include_once(__DIR__ . "/sesion.php");

// verifica que exista una sesion valida
if (!isset($_SESSION["usuario"]) || !isset($_SESSION["rol"]) || trim($_SESSION["usuario"]) == "" || ($_SESSION["rol"] != "1" && $_SESSION["rol"] != "2")) {
    $_SESSION = [];
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// evita mostrar informacion guardada despues de cerrar sesion
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// datos del usuario que inicio sesion
$usuario = $_SESSION["usuario"];
$rol = (string)$_SESSION["rol"]; // 1 administrador | 2 empleado
$esAdmin = ($rol == "1");
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
$inicial = strtoupper(substr($usuario, 0, 1));

// esta pagina es solo para el administrador
if (!$esAdmin) {
    header("Location: menu.php");
    exit();
}

// conexion a la base de datos
include("conexion.php");

// productos con poco stock (notificaciones)
$totalStockBajo = 0;
try {
    $consultaStockBajo = $conn->query("SELECT COUNT(*) AS total FROM producto WHERE stock <= stock_minimo");
    $totalStockBajo = (int)($consultaStockBajo->fetch_assoc()["total"] ?? 0);
} catch (Throwable $error) {
    $totalStockBajo = 0;
}


// evita que el texto rompa el html
function escapar($dato) {
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

// paginas del menu
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

// categorias del supermercado
$categorias = [
    "abarrotes"           => "Abarrotes",
    "granos y cereales"   => "Granos y Cereales",
    "enlatados"           => "Enlatados",
    "aceites y salsas"    => "Aceites y Salsas",
    "frutas"              => "Frutas",
    "verduras"            => "Verduras",
    "carnes"              => "Carnes",
    "pollo"               => "Pollo",
    "pescados y mariscos" => "Pescados y Mariscos",
    "embutidos"           => "Embutidos",
    "lacteos"             => "Lácteos",
    "huevos"              => "Huevos",
    "panaderia"           => "Panadería",
    "bebidas"             => "Bebidas",
    "licores"             => "Licores",
    "snacks"              => "Snacks",
    "dulces"              => "Dulces",
    "congelados"          => "Congelados",
    "aseo personal"       => "Aseo Personal",
    "aseo del hogar"      => "Aseo del Hogar",
    "mascotas"            => "Mascotas",
    "bebes"               => "Bebés",
    "hogar y cocina"      => "Hogar y Cocina",
    "papeleria"           => "Papelería",
];

$mensaje = "";
$tipoMensaje = "";
$productoEditar = null;
$busqueda = trim($_GET["buscar"] ?? "");
$productos = null;
$proveedorTexto = "";
$proveedorCodigo = "";

// busca proveedores para el autocompletado
if (isset($_GET["buscar_proveedor"])) {
    header("Content-Type: application/json; charset=utf-8");
    $texto = trim($_GET["buscar_proveedor"]);

    if ($texto == "") {
        echo json_encode([]);
        exit();
    }

    $parteCodigo = trim(explode(" - ", $texto)[0]);
    $filtro = "%" . $texto . "%";
    $filtroCodigo = "%" . $parteCodigo . "%";

    $stmt = $conn->prepare("SELECT cod_proveedor, nombre FROM proveedor WHERE CAST(cod_proveedor AS CHAR) LIKE ? OR nombre LIKE ? ORDER BY nombre ASC LIMIT 10");
    $stmt->bind_param("ss", $filtroCodigo, $filtro);
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

    echo json_encode($lista);
    exit();
}

// verifica si el codigo o nombre ya existen mientras se escribe
if (isset($_GET["verificar_producto"])) {
    header("Content-Type: application/json; charset=utf-8");
    $tipo = $_GET["verificar_producto"];
    $valor = trim($_GET["valor"] ?? "");
    $excluir = (int)($_GET["excluir"] ?? 0);
    $respuesta = ["existe" => false];

    if ($tipo == "codigo" && ctype_digit($valor)) {
        $codigoNum = (int)$valor;
        $stmt = $conn->prepare("SELECT nombre FROM producto WHERE cod_producto = ? LIMIT 1");
        $stmt->bind_param("i", $codigoNum);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($fila) {
            $sig = $conn->query("SELECT COALESCE(MAX(cod_producto), 0) + 1 AS siguiente FROM producto")->fetch_assoc();
            $respuesta = [
                "existe" => true,
                "sugerido" => (string)$sig["siguiente"]
            ];
        }
    } elseif ($tipo == "nombre" && $valor != "") {
        $stmt = $conn->prepare("SELECT cod_producto FROM producto WHERE nombre = ? AND cod_producto <> ? LIMIT 1");
        $stmt->bind_param("si", $valor, $excluir);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($fila) {
            $respuesta = [
                "existe" => true,
                "codigo" => (string)$fila["cod_producto"]
            ];
        }
    }

    echo json_encode($respuesta);
    exit();
}

// eliminar producto
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["eliminar_producto"])) {
    $codigoEliminar = trim($_POST["cod_producto"] ?? "");

    if (ctype_digit($codigoEliminar)) {
        try {
            $codigoNum = (int)$codigoEliminar;
            $stmt = $conn->prepare("DELETE FROM producto WHERE cod_producto = ?");
            $stmt->bind_param("i", $codigoNum);
            $stmt->execute();
            $eliminados = $stmt->affected_rows;
            $stmt->close();

            if ($eliminados > 0) {
                header("Location: producto.php?mensaje=eliminado");
                exit();
            } else {
                $mensaje = "No se encontró el producto que deseas eliminar.";
                $tipoMensaje = "error";
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) {
                $mensaje = "No se puede eliminar el producto porque tiene movimientos registrados.";
            } else {
                $mensaje = "Ocurrió un error al eliminar el producto.";
            }
            $tipoMensaje = "error";
        }
    }
}

// cargar producto para editar
if (isset($_GET["accion"]) && $_GET["accion"] == "editar") {
    $codigoBuscar = trim($_GET["cod_producto"] ?? "");

    if (ctype_digit($codigoBuscar)) {
        $codigoNum = (int)$codigoBuscar;
        $stmt = $conn->prepare("SELECT * FROM producto WHERE cod_producto = ?");
        $stmt->bind_param("i", $codigoNum);
        $stmt->execute();
        $productoEditar = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($productoEditar) {
            $proveedorCodigo = (string)$productoEditar["cod_proveedor"];
            $codProvNum = (int)$proveedorCodigo;
            $stmt = $conn->prepare("SELECT nombre FROM proveedor WHERE cod_proveedor = ?");
            $stmt->bind_param("i", $codProvNum);
            $stmt->execute();
            $filaProv = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($filaProv) {
                $proveedorTexto = $proveedorCodigo . " - " . $filaProv["nombre"];
            }
        } else {
            $mensaje = "No se encontró el producto seleccionado.";
            $tipoMensaje = "error";
        }
    }
}

// actualizar producto
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["actualizar"])) {
    $codigo = trim($_POST["cod_producto"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");
    $precio = str_replace(",", ".", trim($_POST["precio"] ?? ""));
    $codProveedor = trim($_POST["cod_proveedor"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");

    if (!ctype_digit($codigo) || $nombre == "" || !preg_match('/^[\p{L}\s]+$/u', $nombre) || !is_numeric($precio) || (float)$precio < 0 || !ctype_digit($codProveedor) || !array_key_exists($categoria, $categorias)) {
        $mensaje = "Completa los campos obligatorios correctamente. El nombre solo lleva letras y el precio solo números.";
        $tipoMensaje = "error";
    } else {
        $codigoNum = (int)$codigo;
        $codProvNum = (int)$codProveedor;
        $precioNum = (float)$precio;

        $stmt = $conn->prepare("SELECT cod_producto FROM producto WHERE nombre = ? AND cod_producto <> ? LIMIT 1");
        $stmt->bind_param("si", $nombre, $codigoNum);
        $stmt->execute();
        $existeNombre = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($existeNombre) {
            $mensaje = "Ya existe otro producto con ese nombre.";
            $tipoMensaje = "error";
        } else {
            $stmt = $conn->prepare("UPDATE producto SET nombre = ?, precio = ?, categoria = ?, descripcion = ?, cod_proveedor = ? WHERE cod_producto = ?");
            $stmt->bind_param("sdssii", $nombre, $precioNum, $categoria, $descripcion, $codProvNum, $codigoNum);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: producto.php?mensaje=actualizado");
                exit();
            } else {
                $mensaje = "Error al actualizar el producto.";
                $tipoMensaje = "error";
                $stmt->close();
            }
        }
    }
}

// registrar producto nuevo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["guardar"])) {
    $codigo = trim($_POST["cod_producto"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");
    $precio = str_replace(",", ".", trim($_POST["precio"] ?? ""));
    $codProveedor = trim($_POST["cod_proveedor"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");

    if (!ctype_digit($codigo) || (int)$codigo < 1 || $nombre == "" || !preg_match('/^[\p{L}\s]+$/u', $nombre) || !is_numeric($precio) || (float)$precio < 0 || !ctype_digit($codProveedor) || !array_key_exists($categoria, $categorias)) {
        $mensaje = "Completa los campos obligatorios correctamente. El nombre solo lleva letras y el precio solo números.";
        $tipoMensaje = "error";
    } else {
        $codigoNum = (int)$codigo;
        $codProvNum = (int)$codProveedor;
        $precioNum = (float)$precio;

        $stmt = $conn->prepare("SELECT cod_producto FROM producto WHERE cod_producto = ? LIMIT 1");
        $stmt->bind_param("i", $codigoNum);
        $stmt->execute();
        $existeCodigo = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        $stmt = $conn->prepare("SELECT cod_producto FROM producto WHERE nombre = ? LIMIT 1");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $existeNombre = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($existeCodigo) {
            $mensaje = "No se puede registrar: el código $codigoNum ya está en uso.";
            $tipoMensaje = "error";
        } elseif ($existeNombre) {
            $mensaje = "No se puede registrar: ya existe un producto con ese nombre.";
            $tipoMensaje = "error";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO producto (cod_producto, nombre, precio, categoria, descripcion, cod_proveedor) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isdssi", $codigoNum, $nombre, $precioNum, $categoria, $descripcion, $codProvNum);
                $stmt->execute();
                $stmt->close();

                header("Location: producto.php?mensaje=registrado");
                exit();
            } catch (mysqli_sql_exception $e) {
                $mensaje = "Error al registrar el producto. Verifica los datos.";
                $tipoMensaje = "error";
            }
        }
    }
}

// mensajes de confirmacion
if (isset($_GET["mensaje"])) {
    if ($_GET["mensaje"] == "registrado") {
        $mensaje = "Producto registrado correctamente.";
        $tipoMensaje = "ok";
    } elseif ($_GET["mensaje"] == "actualizado") {
        $mensaje = "Producto actualizado correctamente.";
        $tipoMensaje = "ok";
    } elseif ($_GET["mensaje"] == "eliminado") {
        $mensaje = "Producto eliminado correctamente.";
        $tipoMensaje = "error";
    }
}

// listado y busqueda de productos
if ($busqueda != "") {
    $filtro = "%" . $busqueda . "%";
    $stmt = $conn->prepare("SELECT p.*, pr.nombre AS nombre_proveedor FROM producto p INNER JOIN proveedor pr ON p.cod_proveedor = pr.cod_proveedor WHERE CAST(p.cod_producto AS CHAR) LIKE ? OR p.nombre LIKE ? OR p.categoria LIKE ? OR p.descripcion LIKE ? OR pr.nombre LIKE ? ORDER BY p.cod_producto ASC");
    $stmt->bind_param("sssss", $filtro, $filtro, $filtro, $filtro, $filtro);
    $stmt->execute();
    $productos = $stmt->get_result();
} else {
    $productos = $conn->query("SELECT p.*, pr.nombre AS nombre_proveedor FROM producto p INNER JOIN proveedor pr ON p.cod_proveedor = pr.cod_proveedor ORDER BY p.cod_producto ASC");
}

// codigo sugerido para producto nuevo
$codigoSugerido = "";
if (!$productoEditar) {
    $filaSiguiente = $conn->query("SELECT COALESCE(MAX(cod_producto), 0) + 1 AS siguiente FROM producto")->fetch_assoc();
    if ($filaSiguiente) {
        $codigoSugerido = (string)$filaSiguiente["siguiente"];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- el celular ve la pagina como en el computador -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos | La Granja</title>
    <link rel="stylesheet" href="../css/producto.css?v=20261120">
</head>
<body class="pagina-producto">

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
            <?php foreach ($paginas as $clave => $pagina): ?>
                <?php if ($pagina["admin"] && !$esAdmin) { continue; } ?>
                <?php $esActual = ($clave == "producto"); ?>
                <a href="<?= escapar($pagina["archivo"]) ?>" class="enlace-menu<?= $esActual ? " activo" : "" ?>"<?= $esActual ? ' aria-current="page"' : "" ?>>
                    <span class="caja-icono"><img src="../img/<?= $pagina["icono"] ?>" alt="" class="icono"></span>
                    <span><?= escapar($pagina["texto"]) ?></span>
                </a>
            <?php endforeach; ?>
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
        <span class="titulo-icono"><img src="../img/producto.png" alt="" class="icono"></span>
        <div>
            <h2>Productos</h2>
            <p>Registra, busca, edita o elimina los productos registrados.</p>
        </div>
    </section>

    <?php if ($mensaje != ""): ?>
        <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h3 class="panel-titulo"><?= $productoEditar ? "Editar producto" : "Ingresar producto" ?></h3>
        <form action="producto.php" method="POST" id="formularioProducto" class="formulario" data-codigo-actual="<?= escapar($productoEditar["cod_producto"] ?? "") ?>">

            <?php if ($productoEditar): ?>
                <input type="hidden" name="cod_producto" value="<?= escapar($productoEditar["cod_producto"]) ?>">
                <div class="campo">
                    <label for="codigo_visible">Código del producto <span class="obligatorio">*</span></label>
                    <input id="codigo_visible" type="number" value="<?= escapar($productoEditar["cod_producto"]) ?>" disabled>
                </div>
            <?php else: ?>
                <div class="campo">
                    <label for="cod_producto">Código del producto <span class="obligatorio">*</span></label>
                    <input id="cod_producto" type="text" name="cod_producto" required inputmode="numeric" pattern="[0-9]+" maxlength="10" data-tipo="numeros" data-error="Escribe solo números." autocomplete="off" list="lista_codigos" placeholder="<?= $codigoSugerido != "" ? "Sugerido: " . escapar($codigoSugerido) : "Ingrese código" ?>">
                    <datalist id="lista_codigos">
                        <?php if ($codigoSugerido != ""): ?>
                            <option value="<?= escapar($codigoSugerido) ?>">Código sugerido</option>
                        <?php endif; ?>
                    </datalist>
                    <p id="error_codigo" class="texto-campo-error" aria-live="polite" style="color: #dc2626; font-weight: 600; margin-top: 4px;"></p>
                </div>
            <?php endif; ?>

            <div class="campo">
                <label for="nombre">Nombre <span class="obligatorio">*</span></label>
                <input id="nombre" type="text" name="nombre" required pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" data-tipo="letras" data-error="Escribe solo letras, sin números." value="<?= escapar($productoEditar["nombre"] ?? "") ?>" placeholder="Ingrese nombre">
                <p id="error_nombre" class="texto-campo-error" aria-live="polite" style="color: #dc2626; font-weight: 600; margin-top: 4px;"></p>
            </div>

            <div class="campo">
                <label for="precio">Precio <span class="obligatorio">*</span></label>
                <input id="precio" type="text" name="precio" required inputmode="decimal" pattern="[0-9]+(\.[0-9]+)?" data-tipo="decimal" data-error="Escribe solo números (usa punto para los decimales)." value="<?= escapar($productoEditar["precio"] ?? "") ?>" placeholder="Ingrese precio">
            </div>

            <div class="campo">
                <label for="proveedor_busqueda">Proveedor <span class="obligatorio">*</span></label>
                <input type="text" id="proveedor_busqueda" list="lista_proveedores" autocomplete="off" placeholder="Escriba el código o el nombre" value="<?= escapar($proveedorTexto) ?>" required>
                <datalist id="lista_proveedores"></datalist>
                <input type="hidden" name="cod_proveedor" id="cod_proveedor" value="<?= escapar($proveedorCodigo) ?>">
                <p id="estado_proveedor" class="texto-campo-error" aria-live="polite"></p>
            </div>

            <div class="campo">
                <label for="categoria">Categoría <span class="obligatorio">*</span></label>
                <select id="categoria" name="categoria" required>
                    <option value="">Seleccione una categoría</option>
                    <?php foreach ($categorias as $valor => $texto): ?>
                        <option value="<?= escapar($valor) ?>" <?= (($productoEditar["categoria"] ?? "") == $valor) ? "selected" : "" ?>><?= escapar($texto) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo campo-ancho">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" placeholder="Ingrese descripción"><?= escapar($productoEditar["descripcion"] ?? "") ?></textarea>
            </div>

            <div class="botones campo-ancho">
                <?php if ($productoEditar): ?>
                    <button type="submit" name="actualizar" value="1" class="boton boton-principal">Actualizar producto</button>
                    <a href="producto.php" class="boton boton-secundario">Cancelar</a>
                <?php else: ?>
                    <button type="submit" name="guardar" value="1" class="boton boton-principal">Registrar producto</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3 class="panel-titulo">Buscar producto</h3>
        <form action="producto.php" method="GET" class="buscador" id="formBuscador">
            <input type="text" name="buscar" id="campoBuscar" autocomplete="off" placeholder="Código, nombre, categoría, proveedor o descripción..." value="<?= escapar($busqueda) ?>">
            <a href="producto.php" class="boton boton-secundario" id="limpiarBusqueda" <?= $busqueda == "" ? "hidden" : "" ?>>Mostrar todos</a>
        </form>
    </section>

    <section class="panel">
        <h3 class="panel-titulo">Listado de productos</h3>
        <div class="tabla-contenedor tabla-scroll">
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
                <tbody id="cuerpoTabla">
                <?php if ($productos && $productos->num_rows > 0): ?>
                    <?php while ($fila = $productos->fetch_assoc()): ?>
                        <tr>
                            <td><?= escapar($fila["cod_producto"]) ?></td>
                            <td><?= escapar($fila["nombre"]) ?></td>
                            <td>$<?= number_format((float)$fila["precio"], 2, ",", ".") ?></td>
                            <td><?= escapar($fila["cod_proveedor"] . " - " . $fila["nombre_proveedor"]) ?></td>
                            <td><?= escapar($fila["categoria"]) ?></td>
                            <td><?= escapar($fila["descripcion"]) ?></td>
                            <td class="tabla-acciones">
                                <a class="boton boton-chico boton-editar" href="producto.php?accion=editar&amp;cod_producto=<?= urlencode($fila["cod_producto"]) ?>">Editar</a>
                                <form action="producto.php" method="POST" class="form-eliminar">
                                    <input type="hidden" name="cod_producto" value="<?= escapar($fila["cod_producto"]) ?>">
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

    <div class="modal" id="modalEliminar" data-modulo="producto" hidden>
        <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="tituloEliminar">
            <div class="modal__icono">
                <img src="../img/error.png" alt="Error" width="80" height="80">
            </div>
            <h3 class="modal__titulo" id="tituloEliminar">Eliminar producto</h3>
            <p class="modal__texto" id="textoEliminar">¿Seguro que deseas eliminar este producto? Esta acción no se puede deshacer.</p>
            <div class="modal__botones">
                <button type="button" class="boton boton-secundario" id="cancelarEliminar">Cancelar</button>
                <button type="button" class="boton boton-eliminar" id="confirmarEliminar">Eliminar</button>
            </div>
        </div>
    </div>
</main>

<footer class="pie-pagina">
    <div class="footer-contenido">
        <section class="footer-ayuda">
            <h4>AYUDA Y SOPORTE</h4>
            <p class="footer-descripcion">Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.</p>
            <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">ⓘ</span>Centro de ayuda</a>
            <a href="ayuda.php#privacidad" class="footer-enlace"><span class="footer-icono">♧</span>Privacidad y seguridad</a>
            <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">▤</span>Manual de uso del sistema</a>
            <a href="ayuda.php#soporte" class="footer-enlace"><span class="footer-icono">?</span>Asistencia técnica</a>
            <a href="ayuda.php#inventario" class="footer-enlace"><span class="footer-icono">▣</span>Gestión y control de inventario</a>
            <a href="ayuda.php#movimientos" class="footer-enlace"><span class="footer-icono">↔</span>Movimientos de entrada y salida</a>
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

<script src="../js/producto.js?v=3"></script>
</body>
</html>