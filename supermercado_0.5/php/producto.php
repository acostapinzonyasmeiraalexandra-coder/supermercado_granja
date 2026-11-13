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
// esta variable almacena el tipo de mensaje (ok o error)
$tipoMensaje = "";
// esta variable almacena los datos del registro que se va a editar
$productoEditar = null;
// esta variable almacena el texto que se escribe en el buscador
$busqueda = trim((string)($_GET["buscar"] ?? ""));
$productos = null;
// estas variables almacenan el texto y el codigo del proveedor y del producto
$proveedorTexto = "";
$proveedorCodigo = "";

// acciones de productos
// busca proveedores para el autocompletado
if (isset($_GET["buscar_proveedor"])) {
    header("Content-Type: application/json; charset=utf-8");

    // intenta ejecutar el codigo y atrapa los errores
    try {
        // datos que llegan por la url
        $texto = trim((string)$_GET["buscar_proveedor"]);
        if ($texto === "") {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $parteCodigo = trim(explode(" - ", $texto)[0]);
        // esta variable almacena el texto buscado con comodines para usar like
        $filtro = "%" . $texto . "%";
        $filtroCodigo = "%" . $parteCodigo . "%";

        // prepara la consulta sql de forma segura
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
        // envia los datos a la consulta
        $stmt->bind_param("ssss", $filtroCodigo, $filtro, $parteCodigo, $texto);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $resultado = $stmt->get_result();

        // esta variable almacena una lista que se llena mas adelante
        $lista = [];
        // recorre cada fila del resultado
        while ($fila = $resultado->fetch_assoc()) {
            $lista[] = [
                "codigo" => (string)$fila["cod_proveedor"],
                "nombre" => (string)$fila["nombre"]
            ];
        }
        // cierra la consulta
        $stmt->close();

        echo json_encode($lista, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        // si ocurre cualquier error responde con una lista vacia
        http_response_code(500);
        echo json_encode([]);
    }
    exit();
}

// eliminar producto (solo admin)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_producto"])) {
    // bloquea la accion si el usuario no es administrador
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para eliminar productos.";
        $tipoMensaje = "error";
    } else {
        // datos que llegan del formulario
        $codigoEliminar = trim((string)($_POST["cod_producto"] ?? ""));

        // intenta ejecutar el codigo y atrapa los errores
        try {
            if (!ctype_digit($codigoEliminar)) {
                // lanza un error para avisar que el dato no es valido
                throw new InvalidArgumentException("Código de producto no válido.");
            }

            $codigoNumero = (int)$codigoEliminar;
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("DELETE FROM producto WHERE cod_producto = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $codigoNumero);
            // ejecuta la consulta
            $stmt->execute();
            $eliminados = $stmt->affected_rows;
            // cierra la consulta
            $stmt->close();

            if ($eliminados > 0) {
                // redirige a producto.php
                header("Location: producto.php?mensaje=eliminado");
                exit();
            }

            $mensaje = "No se encontró el producto que deseas eliminar.";
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            // si falla la base de datos guarda el mensaje de error
            $mensaje = ((int)$e->getCode() === 1451)
                ? "No se puede eliminar el producto porque tiene movimientos registrados."
                : "Ocurrió un error al eliminar el producto.";
            $tipoMensaje = "error";
        } catch (InvalidArgumentException $e) {
            // si algun dato no es valido guarda el mensaje de validacion
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}

// cargar producto para editar (solo admin)
if (isset($_GET["accion"]) && $_GET["accion"] === "editar") {
    // bloquea la accion si el usuario no es administrador
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para editar productos.";
        $tipoMensaje = "error";
    } else {
        // datos que llegan por la url
        $codigoBuscar = trim((string)($_GET["cod_producto"] ?? ""));

        // intenta ejecutar el codigo y atrapa los errores
        try {
            if (!ctype_digit($codigoBuscar)) {
                // lanza un error para avisar que el dato no es valido
                throw new InvalidArgumentException("Código de producto no válido.");
            }

            $codigoNumero = (int)$codigoBuscar;
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("SELECT * FROM producto WHERE cod_producto = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $codigoNumero);
            // ejecuta la consulta
            $stmt->execute();
            // obtiene el resultado de la consulta
            $productoEditar = $stmt->get_result()->fetch_assoc();
            // cierra la consulta
            $stmt->close();

            if (!$productoEditar) {
                $mensaje = "No se encontró el producto seleccionado.";
                $tipoMensaje = "error";
            } else {
                $proveedorCodigo = (string)$productoEditar["cod_proveedor"];
                $codigoNumeroProveedor = (int)$proveedorCodigo;
                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("SELECT nombre FROM proveedor WHERE cod_proveedor = ?");
                // envia los datos a la consulta
                $stmt->bind_param("i", $codigoNumeroProveedor);
                // ejecuta la consulta
                $stmt->execute();
                // obtiene el resultado de la consulta
                $filaProveedor = $stmt->get_result()->fetch_assoc();
                // cierra la consulta
                $stmt->close();

                if ($filaProveedor) {
                    $proveedorTexto = $proveedorCodigo . " - " . $filaProveedor["nombre"];
                }
            }
        } catch (InvalidArgumentException $e) {
            // si algun dato no es valido guarda el mensaje de validacion
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            // si falla la base de datos guarda el mensaje de error
            $mensaje = "No se pudieron cargar los datos del producto.";
            $tipoMensaje = "error";
        }
    }
}

// actualizar producto (solo admin)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar"])) {
    // bloquea la accion si el usuario no es administrador
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para actualizar productos.";
        $tipoMensaje = "error";
    } else {
        // datos que llegan del formulario
        $codigo = trim((string)($_POST["cod_producto"] ?? ""));
        $nombre = trim((string)($_POST["nombre"] ?? ""));
        $precio = str_replace(",", ".", trim((string)($_POST["precio"] ?? "")));
        $codProveedor = trim((string)($_POST["cod_proveedor"] ?? ""));
        $categoria = trim((string)($_POST["categoria"] ?? ""));
        $descripcion = trim((string)($_POST["descripcion"] ?? ""));

        // intenta ejecutar el codigo y atrapa los errores
        try {
            if (
                !ctype_digit($codigo) || $nombre === "" || !preg_match('/^[\p{L}\s]+$/u', $nombre) ||
                !is_numeric($precio) || (float)$precio < 0 ||
                !ctype_digit($codProveedor) || !array_key_exists($categoria, $categorias)
            ) {
                // lanza un error para avisar que el dato no es valido
                throw new InvalidArgumentException("Completa los campos obligatorios correctamente. El nombre solo puede tener letras y el precio solo números.");
            }

            $codProveedorNumero = (int)$codProveedor;
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("SELECT cod_proveedor FROM proveedor WHERE cod_proveedor = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $codProveedorNumero);
            // ejecuta la consulta
            $stmt->execute();
            // obtiene el resultado de la consulta
            $existeProveedor = $stmt->get_result()->num_rows > 0;
            // cierra la consulta
            $stmt->close();

            if (!$existeProveedor) {
                // lanza un error para avisar que el dato no es valido
                throw new InvalidArgumentException("Selecciona un proveedor válido.");
            }

            $precioNumero = (float)$precio;
            $codigoNumero = (int)$codigo;
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare(
                "UPDATE producto
                 SET nombre = ?, precio = ?, categoria = ?, descripcion = ?, cod_proveedor = ?
                 WHERE cod_producto = ?"
            );
            // envia los datos a la consulta
            $stmt->bind_param(
                "sdssii",
                $nombre, $precioNumero, $categoria, $descripcion,
                $codProveedorNumero, $codigoNumero
            );
            // ejecuta la consulta
            $stmt->execute();
            // cierra la consulta
            $stmt->close();

            // redirige a producto.php
            header("Location: producto.php?mensaje=actualizado");
            exit();
        } catch (InvalidArgumentException $e) {
            // si algun dato no es valido guarda el mensaje de validacion
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            // si falla la base de datos guarda el mensaje de error
            $mensaje = "Error al actualizar el producto. Verifica los datos.";
            $tipoMensaje = "error";
        }
    }
}

// registrar producto
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar"])) {
    // datos que llegan del formulario
    $codigo = trim((string)($_POST["cod_producto"] ?? ""));
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $precio = str_replace(",", ".", trim((string)($_POST["precio"] ?? "")));
    $codProveedor = trim((string)($_POST["cod_proveedor"] ?? ""));
    $categoria = trim((string)($_POST["categoria"] ?? ""));
    $descripcion = trim((string)($_POST["descripcion"] ?? ""));

    // intenta ejecutar el codigo y atrapa los errores
    try {
        if (
            !ctype_digit($codigo) || (int)$codigo < 1 ||
            $nombre === "" || !preg_match('/^[\p{L}\s]+$/u', $nombre) || !is_numeric($precio) || (float)$precio < 0 ||
            !ctype_digit($codProveedor) ||
            !array_key_exists($categoria, $categorias)
        ) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Completa los campos obligatorios correctamente. El nombre solo puede tener letras y el precio solo números.");
        }

        $codProveedorNumero = (int)$codProveedor;
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare("SELECT cod_proveedor FROM proveedor WHERE cod_proveedor = ?");
        // envia los datos a la consulta
        $stmt->bind_param("i", $codProveedorNumero);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $existeProveedor = $stmt->get_result()->num_rows > 0;
        // cierra la consulta
        $stmt->close();

        if (!$existeProveedor) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Debes seleccionar un proveedor válido.");
        }

        $codigoNumero = (int)$codigo;
        $precioNumero = (float)$precio;
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare(
            "INSERT INTO producto
                (cod_producto, nombre, precio, categoria, descripcion, cod_proveedor)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        // envia los datos a la consulta
        $stmt->bind_param(
            "isdssi",
            $codigoNumero, $nombre, $precioNumero, $categoria, $descripcion, $codProveedorNumero
        );
        // ejecuta la consulta
        $stmt->execute();
        // cierra la consulta
        $stmt->close();

        // redirige a producto.php
        header("Location: producto.php?mensaje=registrado");
        exit();
    } catch (InvalidArgumentException $e) {
        // si algun dato no es valido guarda el mensaje de validacion
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // si falla la base de datos guarda el mensaje de error
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
    // intenta ejecutar el codigo y atrapa los errores
    try {
        if ($busqueda !== "") {
            // esta variable almacena el texto buscado con comodines para usar like
            $filtro = "%" . $busqueda . "%";
            // prepara la consulta sql de forma segura
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
            // envia los datos a la consulta
            $stmt->bind_param("sssss", $filtro, $filtro, $filtro, $filtro, $filtro);
            // ejecuta la consulta
            $stmt->execute();
            // obtiene el resultado de la consulta
            $productos = $stmt->get_result();
        } else {
            // ejecuta la consulta sql
            $productos = $conn->query(
                "SELECT p.*, pr.nombre AS nombre_proveedor
                 FROM producto p
                 INNER JOIN proveedor pr ON p.cod_proveedor = pr.cod_proveedor
                 ORDER BY p.cod_producto ASC"
            );
        }
    } catch (mysqli_sql_exception $e) {
        // si falla la base de datos guarda el mensaje de error
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
    <!-- hoja de estilos de la pagina -->
    <link rel="stylesheet" href="../css/producto.css?v=3">
</head>
<!-- cuerpo de la pagina -->
<body class="pagina-producto">

<!-- encabezado -->
<header class="encabezado">
    <button type="button" class="boton-menu" id="abrirMenu" aria-label="Abrir menú lateral" aria-controls="menuLateral" aria-expanded="false">
        <img src="../img/menu.png" alt="" class="icono">
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
        <span class="usuario-inicial"><?= htmlspecialchars($inicial, ENT_QUOTES, "UTF-8") ?></span>
        <div>
            <strong><?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?></strong>
            <p><?= htmlspecialchars($nombreCargo, ENT_QUOTES, "UTF-8") ?></p>
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
            <img src="../img/cerrar.png" alt="" class="icono">
        </button>
    </div>

    <!-- datos del usuario en el menu lateral -->
    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= htmlspecialchars($inicial, ENT_QUOTES, "UTF-8") ?></div>
        <div>
            <strong><?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?></strong>
            <p><?= htmlspecialchars($nombreCargo, ENT_QUOTES, "UTF-8") ?></p>
        </div>
    </div>

    <!-- enlaces de navegacion -->
    <nav class="navegacion">
        <p class="titulo-navegacion">MENÚ PRINCIPAL</p>
        <div class="lista-navegacion">
            <?php foreach ($paginas as $clave => $pagina): ?>
                <?php if ($pagina["admin"] && !$esAdmin) { continue; } ?>
                <?php $esActual = ($clave === "producto"); ?>
                <a href="<?= htmlspecialchars($pagina["archivo"], ENT_QUOTES, "UTF-8") ?>" class="enlace-menu<?= $esActual ? " activo" : "" ?>"<?= $esActual ? ' aria-current="page"' : "" ?>>
                    <span class="caja-icono"><img src="../img/<?= $pagina["icono"] ?>" alt="" class="icono"></span>
                    <span><?= htmlspecialchars($pagina["texto"], ENT_QUOTES, "UTF-8") ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- formulario para cerrar sesion -->
    <form action="cerrar_sesion.php" method="POST" class="form-salir">
        <button type="submit" name="salir" value="1" class="boton-salir">
            <span class="caja-icono caja-icono-roja"><img src="../img/salir.png" alt="" class="icono"></span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<!-- contenido principal -->
<main class="contenido">
    <!-- titulo de la pagina -->
    <section class="titulo-pagina">
        <span class="titulo-icono"><img src="../img/producto.png" alt="" class="icono"></span>
        <div>
            <h2>Productos</h2>
            <p><?= $esAdmin ? "Registra, busca, edita o elimina los productos registrados." : "Registra un producto nuevo en el inventario." ?></p>
        </div>
    </section>

    <!-- mensaje de aviso -->
    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= htmlspecialchars($tipoMensaje, ENT_QUOTES, "UTF-8") ?>" role="status">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <!-- formulario de producto -->
    <section class="panel">
        <h3 class="panel-titulo"><?= $productoEditar ? "Editar producto" : "Ingresar producto" ?></h3>
        <!-- formulario -->
        <form action="producto.php" method="POST" id="formularioProducto" class="formulario">

            <?php if ($productoEditar): ?>
                <input type="hidden" name="cod_producto" value="<?= htmlspecialchars((string)$productoEditar["cod_producto"], ENT_QUOTES, "UTF-8") ?>">
                <div class="campo">
                    <label for="codigo_visible">Código del producto <span class="obligatorio">*</span></label>
                    <input id="codigo_visible" type="number" value="<?= htmlspecialchars((string)$productoEditar["cod_producto"], ENT_QUOTES, "UTF-8") ?>" disabled>
                </div>
            <?php else: ?>
                <div class="campo">
                    <label for="cod_producto">Código del producto <span class="obligatorio">*</span></label>
                    <input id="cod_producto" type="text" name="cod_producto" required inputmode="numeric" pattern="[0-9]+" maxlength="10" data-tipo="numeros" data-error="Escribe solo números." placeholder="Ingrese código">
                </div>
            <?php endif; ?>

            <div class="campo">
                <label for="nombre">Nombre <span class="obligatorio">*</span></label>
                <input id="nombre" type="text" name="nombre" required pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" data-tipo="letras" data-error="Escribe solo letras, sin números." value="<?= htmlspecialchars((string)($productoEditar["nombre"] ?? ""), ENT_QUOTES, "UTF-8") ?>" placeholder="Ingrese nombre">
            </div>
            <div class="campo">
                <label for="precio">Precio <span class="obligatorio">*</span></label>
                <input id="precio" type="text" name="precio" required inputmode="decimal" pattern="[0-9]+(\.[0-9]+)?" data-tipo="decimal" data-error="Escribe solo números (usa punto para los decimales)." value="<?= htmlspecialchars((string)($productoEditar["precio"] ?? ""), ENT_QUOTES, "UTF-8") ?>" placeholder="Ingrese precio">
            </div>
            <div class="campo">
                <label for="proveedor_busqueda">Proveedor <span class="obligatorio">*</span></label>
                <input type="text" id="proveedor_busqueda" list="lista_proveedores" autocomplete="off" placeholder="Escriba el código o el nombre" value="<?= htmlspecialchars($proveedorTexto, ENT_QUOTES, "UTF-8") ?>" required>
                <datalist id="lista_proveedores"></datalist>
                <input type="hidden" name="cod_proveedor" id="cod_proveedor" value="<?= htmlspecialchars($proveedorCodigo, ENT_QUOTES, "UTF-8") ?>">
                <p id="estado_proveedor" class="texto-campo-error" aria-live="polite"></p>
            </div>
            <div class="campo">
                <label for="categoria">Categoría <span class="obligatorio">*</span></label>
                <select id="categoria" name="categoria" required>
                    <option value="">Seleccione una categoría</option>
                    <?php foreach ($categorias as $valor => $texto): ?>
                        <option value="<?= htmlspecialchars($valor, ENT_QUOTES, "UTF-8") ?>" <?= (($productoEditar["categoria"] ?? "") === $valor) ? "selected" : "" ?>><?= htmlspecialchars($texto, ENT_QUOTES, "UTF-8") ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo campo-ancho">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" placeholder="Ingrese descripción"><?= htmlspecialchars((string)($productoEditar["descripcion"] ?? ""), ENT_QUOTES, "UTF-8") ?></textarea>
            </div>
            <!-- botones del formulario -->
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

    <?php if ($esAdmin): ?>
        <!-- buscador -->
        <section class="panel">
            <h3 class="panel-titulo">Buscar producto</h3>
            <!-- formulario de busqueda -->
            <form action="producto.php" method="GET" class="buscador" id="formBuscador">
                <input type="text" name="buscar" id="campoBuscar" autocomplete="off" placeholder="Código, nombre, categoría, proveedor o descripción..." value="<?= htmlspecialchars($busqueda, ENT_QUOTES, "UTF-8") ?>">
                <a href="producto.php" class="boton boton-secundario" id="limpiarBusqueda"
                    <?= $busqueda === "" ? "hidden" : "" ?>>Mostrar todos</a>
            </form>
        </section>

        <!-- tabla de productos -->
        <section class="panel">
            <h3 class="panel-titulo">Listado de productos</h3>
            <div class="tabla-contenedor tabla-scroll">
                <!-- tabla con los registros -->
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
                                <td><?= htmlspecialchars((string)$fila["cod_producto"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars((string)$fila["nombre"], ENT_QUOTES, "UTF-8") ?></td>
                                <td>$<?= number_format((float)$fila["precio"], 2, ",", ".") ?></td>
                                <td><?= htmlspecialchars($fila["cod_proveedor"] . " - " . $fila["nombre_proveedor"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars((string)$fila["categoria"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars((string)$fila["descripcion"], ENT_QUOTES, "UTF-8") ?></td>
                                <td class="tabla-acciones">
                                    <a class="boton boton-chico boton-editar" href="producto.php?accion=editar&amp;cod_producto=<?= urlencode((string)$fila["cod_producto"]) ?>">Editar</a>
                                    <!-- formulario para eliminar el registro -->
                                    <form action="producto.php" method="POST" class="form-eliminar">
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

        <!-- ventana para confirmar la eliminacion -->
        <div class="modal" id="modalEliminar" hidden>
            <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="tituloEliminar">
                <div class="modal__icono modal__icono--aviso">!</div>
                <h3 class="modal__titulo" id="tituloEliminar">Eliminar producto</h3>
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
            <p class="footer-descripcion">Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.</p>
            <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">ⓘ</span>Centro de ayuda</a>
            <a href="ayuda.php#privacidad" class="footer-enlace"><span class="footer-icono">♧</span>Privacidad y seguridad</a>
            <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">▤</span>Manual de uso del sistema</a>
            <a href="ayuda.php#soporte" class="footer-enlace"><span class="footer-icono">?</span>Asistencia técnica</a>
            <a href="ayuda.php#inventario" class="footer-enlace"><span class="footer-icono">▣</span>Gestión y control de inventario</a>
            <a href="ayuda.php#movimientos" class="footer-enlace"><span class="footer-icono">↔</span>Movimientos de entrada y salida</a>
        </section>
        <!-- informacion del sistema -->
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
    <!-- parte inferior del pie de pagina -->
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<script src = "../js/producto.js"></script>

</body>
</html>
