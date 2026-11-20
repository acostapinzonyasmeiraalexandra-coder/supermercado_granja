<?php
// inicia la sesion
include_once(__DIR__ . "/sesion.php");

// verifica el usuario y el rol
if (
    !isset($_SESSION["usuario"], $_SESSION["rol"]) ||
    trim((string)$_SESSION["usuario"]) === "" ||
    !in_array((string)$_SESSION["rol"], ["1", "2"], true)
) {
    header("Location: ../index.php");
    exit();
}

// evita mostrar datos guardados
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// datos del usuario
$usuario = $_SESSION["usuario"];
$rol = (string)$_SESSION["rol"];
$esAdmin = ($rol === "1");
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
$inicial = strtoupper(substr($usuario, 0, 1));

// solo permite entrar al administrador
if (!$esAdmin) {
    header("Location: menu.php");
    exit();
}

// conexion a la base de datos
include("conexion.php");

// cuenta los productos con pocas existencias para las notificaciones del encabezado
$totalStockBajo = 0;
try {
    $consultaStockBajo = $conn->query("SELECT COUNT(*) AS total FROM producto WHERE stock <= stock_minimo");
    $totalStockBajo = (int)($consultaStockBajo->fetch_assoc()["total"] ?? 0);
} catch (Throwable $error) {
    $totalStockBajo = 0;
}


// variables de la pagina
$mensaje = "";
$tipoMensaje = "";
$usuarioEditar = null;
$usuarios = null;

// elimina usuario sin permitir borrar la sesion actual
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_usuario"])) {
    $cedula = trim((string)($_POST["cedula"] ?? ""));

    try {
        if (!ctype_digit($cedula)) {
            throw new InvalidArgumentException("Cédula no válida.");
        }

        $cedula = (int)$cedula;
        $stmt = $conn->prepare("DELETE FROM usuario WHERE cedula = ? AND usuario <> ?");
        $stmt->bind_param("is", $cedula, $usuario);
        $stmt->execute();
        $eliminados = $stmt->affected_rows;
        $stmt->close();

        if ($eliminados > 0) {
            header("Location: edita_usuario.php?mensaje=eliminado");
            exit();
        }

        $mensaje = "No se eliminó: el usuario no existe o es tu propio usuario.";
        $tipoMensaje = "error";
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        $mensaje = ((int)$e->getCode() === 1451)
            ? "No se puede eliminar el usuario porque tiene movimientos registrados."
            : "Ocurrió un error al eliminar el usuario.";
        $tipoMensaje = "error";
    }
}

// busca el usuario que se va a editar
if (isset($_GET["accion"]) && $_GET["accion"] === "editar") {
    $cedula = trim((string)($_GET["cedula"] ?? ""));

    try {
        if (!ctype_digit($cedula)) {
            throw new InvalidArgumentException("Cédula no válida.");
        }

        $cedula = (int)$cedula;
        $stmt = $conn->prepare(
            "SELECT cedula, nombre, apellido, telefono, email, direccion, usuario, id_tipo_usuario
             FROM usuario WHERE cedula = ?"
        );
        $stmt->bind_param("i", $cedula);
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

// actualiza los datos sin consultar ni modificar la contraseña
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar"])) {
    $cedula = trim((string)($_POST["cedula"] ?? ""));
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $apellido = trim((string)($_POST["apellido"] ?? ""));
    $telefono = trim((string)($_POST["telefono"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $direccion = trim((string)($_POST["direccion"] ?? ""));
    $usuarioNuevo = trim((string)($_POST["usuario_login"] ?? ""));
    $idTipo = (int)($_POST["id_tipo_usuario"] ?? 0);

    try {
        if (
            !ctype_digit($cedula) || $nombre === "" || $apellido === "" ||
            $telefono === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $direccion === "" || $usuarioNuevo === "" || !in_array($idTipo, [1, 2], true)
        ) {
            throw new InvalidArgumentException("Completa todos los campos correctamente.");
        }

        if (
            !preg_match('/^[\p{L}\s]+$/u', $nombre) ||
            !preg_match('/^[\p{L}\s]+$/u', $apellido)
        ) {
            throw new InvalidArgumentException("El nombre y el apellido solo pueden tener letras.");
        }

        if (!ctype_digit($telefono) || strlen($telefono) < 7 || strlen($telefono) > 10) {
            throw new InvalidArgumentException("El teléfono debe tener entre 7 y 10 números.");
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $usuarioNuevo)) {
            throw new InvalidArgumentException("El usuario contiene caracteres no permitidos.");
        }

        $cedula = (int)$cedula;

        // comprueba que el usuario exista
        $stmt = $conn->prepare("SELECT usuario FROM usuario WHERE cedula = ?");
        $stmt->bind_param("i", $cedula);
        $stmt->execute();
        $anterior = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$anterior) {
            throw new InvalidArgumentException("El usuario que intentas actualizar no existe.");
        }

        // actualiza solo los datos personales y el cargo
        $stmt = $conn->prepare(
            "UPDATE usuario
             SET nombre = ?, apellido = ?, telefono = ?, direccion = ?, email = ?,
                 usuario = ?, id_tipo_usuario = ?
             WHERE cedula = ?"
        );
        $stmt->bind_param(
            "ssssssii",
            $nombre, $apellido, $telefono, $direccion, $email,
            $usuarioNuevo, $idTipo, $cedula
        );
        $stmt->execute();
        $stmt->close();

        // actualiza la sesion si se modifico el usuario actual
        if ($anterior["usuario"] === $usuario) {
            $_SESSION["usuario"] = $usuarioNuevo;
            $_SESSION["rol"] = (string)$idTipo;

            if ($idTipo === 2) {
                header("Location: menu.php");
                exit();
            }
        }

        header("Location: edita_usuario.php?mensaje=actualizado");
        exit();
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        $mensaje = ((int)$e->getCode() === 1062)
            ? "Ese nombre de usuario ya está en uso."
            : "Error al actualizar el usuario. Verifica los datos.";
        $tipoMensaje = "error";
    }

    // conserva los datos si ocurre un error
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

// muestra mensajes
if (isset($_GET["mensaje"])) {
    if ($_GET["mensaje"] === "actualizado") {
        $mensaje = "Usuario actualizado correctamente.";
        $tipoMensaje = "ok";
    } elseif ($_GET["mensaje"] === "eliminado") {
        $mensaje = "Usuario eliminado correctamente.";
        $tipoMensaje = "ok";
    }
}

// carga todos los usuarios para filtrarlos mientras se escribe
try {
    $sql = "SELECT cedula, nombre, apellido, telefono, email, direccion, usuario, id_tipo_usuario
            FROM usuario ORDER BY cedula ASC";
    $stmt = $conn->prepare($sql);
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
    <!-- configuracion de la pagina -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | La Granja</title>
    <link rel="stylesheet" href="../css/edita_usuario.css?v=20261119">
    <link rel="stylesheet" href="../css/menu.css?v=20261119">
</head>

<body class="pagina-usuario">

<!-- encabezado del sistema -->
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

</header>

<!-- fondo del menu -->
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

    <!-- usuario conectado -->
    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= htmlspecialchars($inicial, ENT_QUOTES, "UTF-8") ?></div>
        <div>
            <strong><?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?></strong>
            <p><?= htmlspecialchars($nombreCargo, ENT_QUOTES, "UTF-8") ?></p>
        </div>
    </div>

    <!-- enlaces del menu -->
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
            <a href="producto.php" class="enlace-menu">
                <span class="caja-icono"><img src="../img/producto.png" alt="" class="icono"></span>
                <span>Productos</span>
            </a>
            <a href="mov_entrada.php" class="enlace-menu">
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
            <a href="edita_usuario.php" class="enlace-menu activo" aria-current="page">
                <span class="caja-icono"><img src="../img/usuario.png" alt="" class="icono"></span>
                <span>Modificar usuario</span>
            </a>
        </div>
    </nav>

    <!-- cerrar sesion -->
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
        <span class="titulo-icono"><img src="../img/usuario.png" alt="" class="icono"></span>
        <div>
            <h2>Modificar Usuarios</h2>
            <p>Busca, edita o elimina los usuarios del sistema.</p>
        </div>
    </section>

    <!-- mensajes -->
    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= htmlspecialchars($tipoMensaje, ENT_QUOTES, "UTF-8") ?>" role="status">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <!-- formulario para editar -->
    <?php if ($usuarioEditar): ?>
        <section class="panel">
            <h3 class="panel-titulo">Modificar usuario</h3>

            <form action="edita_usuario.php" method="POST" class="formulario">
                <input type="hidden" name="cedula" value="<?= htmlspecialchars($usuarioEditar["cedula"], ENT_QUOTES, "UTF-8") ?>">

                <div class="campo">
                    <label for="cedula_visible">Cédula</label>
                    <input id="cedula_visible" type="number" value="<?= htmlspecialchars($usuarioEditar["cedula"], ENT_QUOTES, "UTF-8") ?>" disabled>
                </div>

                <div class="campo">
                    <label for="nombre">Nombre</label>
                    <input id="nombre" type="text" name="nombre" required pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" data-tipo="letras" data-error="Escribe solo letras." value="<?= htmlspecialchars($usuarioEditar["nombre"] ?? "", ENT_QUOTES, "UTF-8") ?>" placeholder="Ingrese nombre">
                </div>

                <div class="campo">
                    <label for="apellido">Apellido</label>
                    <input id="apellido" type="text" name="apellido" required pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" data-tipo="letras" data-error="Escribe solo letras." value="<?= htmlspecialchars($usuarioEditar["apellido"] ?? "", ENT_QUOTES, "UTF-8") ?>" placeholder="Ingrese apellido">
                </div>

                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input id="telefono" type="text" name="telefono" required inputmode="numeric" pattern="[0-9]{7,10}" minlength="7" maxlength="10" data-tipo="numeros" data-error="Escribe entre 7 y 10 números." value="<?= htmlspecialchars($usuarioEditar["telefono"] ?? "", ENT_QUOTES, "UTF-8") ?>" placeholder="Ingrese teléfono">
                </div>

                <div class="campo">
                    <label for="email">Correo electrónico</label>
                    <input id="email" type="email" name="email" required value="<?= htmlspecialchars($usuarioEditar["email"] ?? "", ENT_QUOTES, "UTF-8") ?>" placeholder="Ingrese correo">
                </div>

                <div class="campo">
                    <label for="direccion">Dirección</label>
                    <input id="direccion" type="text" name="direccion" required value="<?= htmlspecialchars($usuarioEditar["direccion"] ?? "", ENT_QUOTES, "UTF-8") ?>" placeholder="Ingrese dirección">
                </div>

                <div class="campo">
                    <label for="usuario_login">Usuario (login)</label>
                    <input id="usuario_login" type="text" name="usuario_login" required pattern="[A-Za-z0-9._-]+" data-error="Usa letras, números, punto, guion o guion bajo." value="<?= htmlspecialchars($usuarioEditar["usuario"] ?? "", ENT_QUOTES, "UTF-8") ?>" placeholder="Ingrese usuario">
                </div>

                <!-- seleccion del cargo -->
                <div class="campo">
                    <label for="id_tipo_usuario">Cargo</label>
                    <select id="id_tipo_usuario" name="id_tipo_usuario" required>
                        <option value="1" <?= ((int)$usuarioEditar["id_tipo_usuario"] === 1) ? "selected" : "" ?>>Administrador</option>
                        <option value="2" <?= ((int)$usuarioEditar["id_tipo_usuario"] === 2) ? "selected" : "" ?>>Empleado</option>
                    </select>
                </div>

                <!-- botones del formulario -->
                <div class="botones campo-ancho">
                    <button type="submit" name="actualizar" value="1" class="boton boton-principal">Actualizar usuario</button>
                    <a href="edita_usuario.php" class="boton boton-secundario">Cancelar</a>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- buscador en tiempo real -->
    <section class="panel">
        <h3 class="panel-titulo">Buscar usuario</h3>
        <div class="buscador">
            <input
                type="text"
                id="buscarUsuario"
                placeholder="Cédula, nombre, apellido, teléfono, correo, dirección o usuario..."
                autocomplete="off"
            >
        </div>
    </section>

    <!-- listado de usuarios -->
    <section class="panel">
        <h3 class="panel-titulo">Listado de usuarios</h3>
        <div class="tabla-contenedor tabla-scroll">
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

                <tbody id="listaUsuarios">
                <?php if ($usuarios && $usuarios->num_rows > 0): ?>
                    <?php while ($fila = $usuarios->fetch_assoc()): ?>
                        <?php $cargoTexto = ((int)$fila["id_tipo_usuario"] === 1) ? "Administrador" : "Empleado"; ?>
                        <tr class="fila-usuario">
                            <td><?= htmlspecialchars($fila["cedula"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($fila["nombre"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($fila["apellido"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($fila["telefono"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($fila["email"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($fila["direccion"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($fila["usuario"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= $cargoTexto ?></td>
                            <td class="tabla-acciones">
                                <a class="boton boton-chico boton-editar" href="edita_usuario.php?accion=editar&amp;cedula=<?= urlencode((string)$fila["cedula"]) ?>">Actualizar</a>

                                <!-- formulario para eliminar -->
                                <form action="edita_usuario.php" method="POST" class="form-eliminar">
                                    <input type="hidden" name="cedula" value="<?= htmlspecialchars($fila["cedula"], ENT_QUOTES, "UTF-8") ?>">
                                    <button type="submit" name="eliminar_usuario" value="1" class="boton boton-chico boton-eliminar">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                    <tr id="filaSinResultados" hidden>
                        <td colspan="9" class="tabla-vacia">No se encontraron usuarios.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<!-- ventana para confirmar la eliminacion -->
<div class="modal" id="modalEliminar" data-modulo="usuario" hidden>
    <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="tituloEliminar">
        <div class="modal__icono">
            <img src="../img/error.png" alt="Error" width="80" height="80">
        </div>
        <h3 class="modal__titulo" id="tituloEliminar">Eliminar usuario</h3>
        <p class="modal__texto" id="textoEliminar">¿Seguro que deseas eliminar este usuario? Esta acción no se puede deshacer.</p>
        <div class="modal__botones">
            <button type="button" class="boton boton-secundario" id="cancelarEliminar">Cancelar</button>
            <button type="button" class="boton boton-eliminar" id="confirmarEliminar">Eliminar</button>
        </div>
    </div>
</div>

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
            <p><strong>Gestión de usuarios</strong><br>Consulta, actualización y eliminación de los usuarios del sistema.</p>
            <p><strong>Control de movimientos</strong><br>Seguimiento de las entradas y salidas de mercancía.</p>
            <div class="footer-nota">
                <strong>Uso del sistema</strong>
                <p>Acceso destinado a empleados y administradores autorizados.</p>
            </div>
        </section>
    </div>

    <!-- derechos reservados -->
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<!-- archivos de JavaScript -->
<script src="../js/edita_usuario.js"></script>

<!-- busqueda en tiempo real -->
<script>
    const buscarUsuario = document.getElementById("buscarUsuario");
    const filasUsuarios = document.querySelectorAll(".fila-usuario");
    const filaSinResultados = document.getElementById("filaSinResultados");

    buscarUsuario.addEventListener("input", function () {
        const texto = this.value.toLowerCase().trim();
        let encontrados = 0;

        filasUsuarios.forEach(function (fila) {
            const contenido = fila.textContent.toLowerCase();

            if (contenido.includes(texto)) {
                fila.hidden = false;
                encontrados++;
            } else {
                fila.hidden = true;
            }
        });

        filaSinResultados.hidden = encontrados > 0;
    });

    // muestra el aviso si no hay usuarios registrados
    if (filasUsuarios.length === 0) {
        filaSinResultados.hidden = false;
    }
</script>
</body>
</html>
<?php
// cierra la conexion
$conn->close();
?>
