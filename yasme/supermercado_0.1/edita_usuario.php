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

// solo el admin puede entrar a esta pagina
if (!$esAdmin) {
    header("Location: menu.php?" . $parametros);
    exit();
}

// usuario que tiene la sesion abierta (no se puede eliminar)
$usuarioSesion = $usuario;

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

// evita que el texto rompa el html
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

// variables de la pagina
$mensaje = "";
$tipoMensaje = "";
$usuarioEditar = null;
$busqueda = trim((string)($_GET["buscar"] ?? ""));
$usuarios = null;

// eliminar usuario (no deja eliminar al que tiene la sesion abierta)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_usuario"])) {
    $cedulaEliminar = trim((string)($_POST["cedula"] ?? ""));

    try {
        if (!ctype_digit($cedulaEliminar)) {
            throw new InvalidArgumentException("Cédula no válida.");
        }

        $cedulaNumero = (int)$cedulaEliminar;
        $stmt = $conn->prepare("DELETE FROM usuario WHERE cedula = ? AND usuario <> ?");
        $stmt->bind_param("is", $cedulaNumero, $usuarioSesion);
        $stmt->execute();
        $eliminados = $stmt->affected_rows;
        $stmt->close();

        if ($eliminados > 0) {
            header("Location: edita_usuario.php?" . $parametros . "&mensaje=eliminado");
            exit();
        }

        $mensaje = "No se eliminó: el usuario no existe o es tu propio usuario.";
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // 1451 = el usuario tiene movimientos registrados
        $mensaje = ((int)$e->getCode() === 1451)
            ? "No se puede eliminar el usuario porque tiene movimientos registrados."
            : "Ocurrió un error al eliminar el usuario.";
        $tipoMensaje = "error";
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    }
}

// cargar usuario para editar
if (isset($_GET["accion"]) && $_GET["accion"] === "editar") {
    $cedulaBuscar = trim((string)($_GET["cedula"] ?? ""));

    try {
        if (!ctype_digit($cedulaBuscar)) {
            throw new InvalidArgumentException("Cédula no válida.");
        }

        $cedulaNumero = (int)$cedulaBuscar;
        $stmt = $conn->prepare(
            "SELECT cedula, nombre, apellido, telefono, email, direccion, usuario, id_tipo_usuario
             FROM usuario
             WHERE cedula = ?"
        );
        $stmt->bind_param("i", $cedulaNumero);
        $stmt->execute();
        $usuarioEditar = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$usuarioEditar) {
            $mensaje = "No se encontró el usuario seleccionado.";
            $tipoMensaje = "error";
        }
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        $mensaje = "No se pudieron cargar los datos del usuario.";
        $tipoMensaje = "error";
    }
}

// actualizar usuario
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar"])) {
    $cedula = trim((string)($_POST["cedula"] ?? ""));
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $apellido = trim((string)($_POST["apellido"] ?? ""));
    $telefono = trim((string)($_POST["telefono"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $direccion = trim((string)($_POST["direccion"] ?? ""));
    $usuarioNuevo = trim((string)($_POST["usuario_login"] ?? ""));
    $contra = (string)($_POST["contra"] ?? "");
    $idTipo = (int)($_POST["id_tipo_usuario"] ?? 0);

    try {
        if (
            !ctype_digit($cedula) || $nombre === "" || $apellido === "" ||
            $telefono === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $direccion === "" || $usuarioNuevo === "" || !in_array($idTipo, [1, 2], true)
        ) {
            throw new InvalidArgumentException("Completa todos los campos correctamente.");
        }

        $cedulaNumero = (int)$cedula;

        // busca el usuario que tenia antes para saber si se edita a si mismo
        $stmt = $conn->prepare("SELECT usuario FROM usuario WHERE cedula = ?");
        $stmt->bind_param("i", $cedulaNumero);
        $stmt->execute();
        $anterior = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$anterior) {
            throw new InvalidArgumentException("El usuario que intentas actualizar no existe.");
        }

        if ($contra !== "") {
            // si escribio una contraseña nueva, se cambia
            $stmt = $conn->prepare(
                "UPDATE usuario
                 SET nombre = ?, apellido = ?, telefono = ?, direccion = ?, email = ?,
                     usuario = ?, contra = ?, id_tipo_usuario = ?
                 WHERE cedula = ?"
            );
            $stmt->bind_param(
                "sssssssii",
                $nombre, $apellido, $telefono, $direccion, $email,
                $usuarioNuevo, $contra, $idTipo, $cedulaNumero
            );
        } else {
            // si la dejo vacia, se conserva la contraseña actual
            $stmt = $conn->prepare(
                "UPDATE usuario
                 SET nombre = ?, apellido = ?, telefono = ?, direccion = ?, email = ?,
                     usuario = ?, id_tipo_usuario = ?
                 WHERE cedula = ?"
            );
            $stmt->bind_param(
                "ssssssii",
                $nombre, $apellido, $telefono, $direccion, $email,
                $usuarioNuevo, $idTipo, $cedulaNumero
            );
        }

        $stmt->execute();
        $stmt->close();

        // si el admin se edito a si mismo, cambian los datos de la url
        if ($anterior["usuario"] === $usuarioSesion) {
            $usuario = $usuarioNuevo;
            $rol = (string)$idTipo;
            $parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($rol);
        }

        header("Location: edita_usuario.php?" . $parametros . "&mensaje=actualizado");
        exit();
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // 1062 = el usuario ya existe
        $mensaje = ((int)$e->getCode() === 1062)
            ? "Ese nombre de usuario ya está en uso."
            : "Error al actualizar el usuario. Verifica los datos.";
        $tipoMensaje = "error";
    }

    // si hubo error, vuelve a mostrar el formulario con lo escrito
    if ($tipoMensaje === "error" && ctype_digit($cedula)) {
        $usuarioEditar = [
            "cedula" => $cedula,
            "nombre" => $nombre,
            "apellido" => $apellido,
            "telefono" => $telefono,
            "email" => $email,
            "direccion" => $direccion,
            "usuario" => $usuarioNuevo,
            "id_tipo_usuario" => $idTipo
        ];
    }
}

// mensajes despues de actualizar o eliminar
if (isset($_GET["mensaje"])) {
    if ($_GET["mensaje"] === "actualizado") {
        $mensaje = "Usuario actualizado correctamente.";
        $tipoMensaje = "ok";
    } elseif ($_GET["mensaje"] === "eliminado") {
        $mensaje = "Usuario eliminado correctamente.";
        $tipoMensaje = "error";
    }
}

// listado y busqueda (sin mostrar la contraseña)
try {
    $sql = "SELECT cedula, nombre, apellido, telefono, email, direccion, usuario, id_tipo_usuario
            FROM usuario";

    if ($busqueda !== "") {
        $sql .= " WHERE CAST(cedula AS CHAR) LIKE ?
                     OR nombre LIKE ?
                     OR apellido LIKE ?
                     OR telefono LIKE ?
                     OR email LIKE ?
                     OR direccion LIKE ?
                     OR usuario LIKE ?";
    }
    $sql .= " ORDER BY cedula ASC";

    $stmt = $conn->prepare($sql);
    if ($busqueda !== "") {
        $filtro = "%" . $busqueda . "%";
        $stmt->bind_param("sssssss", $filtro, $filtro, $filtro, $filtro, $filtro, $filtro, $filtro);
    }
    $stmt->execute();
    $usuarios = $stmt->get_result();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    $mensaje = "No se pudo cargar el listado de usuarios.";
    $tipoMensaje = "error";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | La Granja</title>
    <link rel="stylesheet" href="css/edita_usuario.css">
</head>
<body class="pagina-usuario">

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
        <span class="usuario-inicial"><?= e($inicial) ?></span>
        <div>
            <strong><?= e($usuario) ?></strong>
            <p><?= e($nombreCargo) ?></p>
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
        <div class="usuario-inicial usuario-grande"><?= e($inicial) ?></div>
        <div>
            <strong><?= e($usuario) ?></strong>
            <p><?= e($nombreCargo) ?></p>
        </div>
    </div>

    <nav class="navegacion">
        <p class="titulo-navegacion">MENÚ PRINCIPAL</p>
        <div class="lista-navegacion">
            <?php foreach ($paginas as $clave => $pagina): ?>
                <?php if ($pagina["admin"] && !$esAdmin) { continue; } ?>
                <?php $esActual = ($clave === "usuario"); ?>
                <a href="<?= e($pagina["archivo"] . "?" . $parametros) ?>" class="enlace-menu<?= $esActual ? " activo" : "" ?>"<?= $esActual ? ' aria-current="page"' : "" ?>>
                    <span class="caja-icono"><img src="img/<?= $pagina["icono"] ?>" alt="" class="icono"></span>
                    <span><?= e($pagina["texto"]) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <form action="menu.php" method="POST" class="form-salir">
        <input type="hidden" name="usuario" value="<?= e($usuario) ?>">
        <input type="hidden" name="rol" value="<?= e($rol) ?>">
        <button type="submit" name="salir" value="1" class="boton-salir">
            <span class="caja-icono caja-icono-roja"><img src="img/salir.png" alt="" class="icono"></span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<!-- contenido principal -->
<main class="contenido">
    <section class="titulo-pagina">
        <span class="titulo-icono"><img src="img/usuario.png" alt="" class="icono"></span>
        <div>
            <h2>Modificar Usuarios</h2>
            <p>Busca, edita o elimina los usuarios del sistema.</p>
        </div>
    </section>

    <!-- mensaje de aviso -->
    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= e($tipoMensaje) ?>" role="status">
            <?= e($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- formulario (solo aparece al editar un usuario) -->
    <?php if ($usuarioEditar): ?>
        <section class="panel">
            <h3 class="panel-titulo">Modificar usuario</h3>
            <form action="edita_usuario.php?<?= e($parametros) ?>" method="POST" class="formulario">
                <input type="hidden" name="usuario" value="<?= e($usuario) ?>">
                <input type="hidden" name="rol" value="<?= e($rol) ?>">
                <input type="hidden" name="cedula" value="<?= e($usuarioEditar["cedula"]) ?>">

                <div class="campo">
                    <label for="cedula_visible">Cédula</label>
                    <input id="cedula_visible" type="number" value="<?= e($usuarioEditar["cedula"]) ?>" disabled>
                </div>
                <div class="campo">
                    <label for="nombre">Nombre</label>
                    <input id="nombre" type="text" name="nombre" required value="<?= e($usuarioEditar["nombre"] ?? "") ?>" placeholder="Ingrese nombre">
                </div>
                <div class="campo">
                    <label for="apellido">Apellido</label>
                    <input id="apellido" type="text" name="apellido" required value="<?= e($usuarioEditar["apellido"] ?? "") ?>" placeholder="Ingrese apellido">
                </div>
                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input id="telefono" type="text" name="telefono" required value="<?= e($usuarioEditar["telefono"] ?? "") ?>" placeholder="Ingrese teléfono">
                </div>
                <div class="campo">
                    <label for="email">Correo electrónico</label>
                    <input id="email" type="email" name="email" required value="<?= e($usuarioEditar["email"] ?? "") ?>" placeholder="Ingrese correo">
                </div>
                <div class="campo">
                    <label for="direccion">Dirección</label>
                    <input id="direccion" type="text" name="direccion" required value="<?= e($usuarioEditar["direccion"] ?? "") ?>" placeholder="Ingrese dirección">
                </div>
                <div class="campo">
                    <label for="usuario_login">Usuario (login)</label>
                    <input id="usuario_login" type="text" name="usuario_login" required value="<?= e($usuarioEditar["usuario"] ?? "") ?>" placeholder="Ingrese usuario">
                </div>
                <div class="campo">
                    <label for="contra">Contraseña nueva (vacía = no cambiarla)</label>
                    <input id="contra" type="password" name="contra" autocomplete="new-password" placeholder="Dejar vacía para conservar la actual">
                </div>
                <div class="campo">
                    <label for="id_tipo_usuario">Cargo</label>
                    <select id="id_tipo_usuario" name="id_tipo_usuario" required>
                        <option value="1" <?= ((int)$usuarioEditar["id_tipo_usuario"] === 1) ? "selected" : "" ?>>Administrador</option>
                        <option value="2" <?= ((int)$usuarioEditar["id_tipo_usuario"] === 2) ? "selected" : "" ?>>Empleado</option>
                    </select>
                </div>

                <div class="botones campo-ancho">
                    <button type="submit" name="actualizar" value="1" class="boton boton-principal">Actualizar usuario</button>
                    <a href="edita_usuario.php?<?= e($parametros) ?>" class="boton boton-secundario">Cancelar</a>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- buscador -->
    <section class="panel">
        <h3 class="panel-titulo">Buscar usuario</h3>
        <form action="edita_usuario.php" method="GET" class="buscador">
            <input type="hidden" name="usuario" value="<?= e($usuario) ?>">
            <input type="hidden" name="rol" value="<?= e($rol) ?>">
            <input type="text" name="buscar" placeholder="Cédula, nombre, apellido, teléfono, correo, dirección o usuario..." value="<?= e($busqueda) ?>">
            <button type="submit" class="boton boton-principal">Buscar</button>
            <?php if ($busqueda !== ""): ?>
                <a href="edita_usuario.php?<?= e($parametros) ?>" class="boton boton-secundario">Mostrar todos</a>
            <?php endif; ?>
        </form>
    </section>

    <!-- tabla de usuarios -->
    <section class="panel">
        <h3 class="panel-titulo">Listado de usuarios</h3>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Nombre</th>
                        <th>Apellido</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Dirección</th>
                        <th>Usuario</th>
                        <th>Cargo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($usuarios && $usuarios->num_rows > 0): ?>
                    <?php while ($fila = $usuarios->fetch_assoc()): ?>
                        <?php
                        // texto del cargo segun el tipo de usuario
                        if ((int)$fila["id_tipo_usuario"] === 1) {
                            $cargoTexto = "Administrador";
                        } elseif ((int)$fila["id_tipo_usuario"] === 2) {
                            $cargoTexto = "Empleado";
                        } else {
                            $cargoTexto = "Sin cargo";
                        }
                        ?>
                        <tr>
                            <td><?= e($fila["cedula"]) ?></td>
                            <td><?= e($fila["nombre"] ?? "") ?></td>
                            <td><?= e($fila["apellido"] ?? "") ?></td>
                            <td><?= e($fila["telefono"] ?? "") ?></td>
                            <td><?= e($fila["email"] ?? "") ?></td>
                            <td><?= e($fila["direccion"] ?? "") ?></td>
                            <td><?= e($fila["usuario"] ?? "") ?></td>
                            <td><?= e($cargoTexto) ?></td>
                            <td class="tabla-acciones">
                                <a class="boton boton-chico boton-editar" href="edita_usuario.php?<?= e($parametros) ?>&amp;accion=editar&amp;cedula=<?= urlencode((string)$fila["cedula"]) ?>">Actualizar</a>
                                <form action="edita_usuario.php?<?= e($parametros) ?>" method="POST" class="form-eliminar" onsubmit="return confirm('¿Desea eliminar este usuario?');">
                                    <input type="hidden" name="usuario" value="<?= e($usuario) ?>">
                                    <input type="hidden" name="rol" value="<?= e($rol) ?>">
                                    <input type="hidden" name="cedula" value="<?= e($fila["cedula"]) ?>">
                                    <button type="submit" name="eliminar_usuario" value="1" class="boton boton-chico boton-eliminar">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="tabla-vacia">No se encontraron usuarios.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <section class="footer-ayuda">
            <h4>AYUDA Y SOPORTE</h4>
            <p class="footer-descripcion">Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.</p>
            <a href="ayuda.php?<?= e($parametros) ?>#uso" class="footer-enlace"><span class="footer-icono">ⓘ</span>Centro de ayuda</a>
            <a href="ayuda.php?<?= e($parametros) ?>#privacidad" class="footer-enlace"><span class="footer-icono">♧</span>Privacidad y seguridad</a>
            <a href="ayuda.php?<?= e($parametros) ?>#uso" class="footer-enlace"><span class="footer-icono">▤</span>Manual de uso del sistema</a>
            <a href="ayuda.php?<?= e($parametros) ?>#soporte" class="footer-enlace"><span class="footer-icono">?</span>Asistencia técnica</a>
            <a href="ayuda.php?<?= e($parametros) ?>#inventario" class="footer-enlace"><span class="footer-icono">▣</span>Gestión y control de inventario</a>
            <a href="ayuda.php?<?= e($parametros) ?>#movimientos" class="footer-enlace"><span class="footer-icono">↔</span>Movimientos de entrada y salida</a>
        </section>
        <section class="footer-informacion">
            <h4>INFORMACIÓN DEL SISTEMA</h4>
            <p><strong>Plataforma administrativa</strong><br>Herramienta para organizar y controlar los productos del supermercado.</p>
            <p><strong>Gestión de usuarios</strong><br>Consulta, actualización y eliminación de los usuarios del sistema.</p>
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
</script>

</body>
</html>
<?php
$conn->close();
?>
