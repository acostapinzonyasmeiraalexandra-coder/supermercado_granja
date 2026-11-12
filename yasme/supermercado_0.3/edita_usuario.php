<?php
// carga el archivo que hace la conexion con la base de datos
include("conexion.php");

// conexion
// revisa que haya conexion a la base de datos
if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}
// si no hay una conexion valida detiene la pagina
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("No se encontró una conexión válida en conexion.php.");
}

// hace que los errores de mysql se manejen como excepciones
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// define la codificacion de los caracteres de la base de datos
$conn->set_charset("utf8mb4");

// usuario y cargo
// cerrar sesion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["salir"])) {
    // redirige a index.html
    header("Location: index.html");
    exit();
}

// usuario y rol que llegan del menu
$usuario = trim((string)($_POST["usuario"] ?? $_GET["usuario"] ?? ""));
$rol = (string)($_POST["rol"] ?? $_GET["rol"] ?? "");

// si faltan los datos de acceso devuelve al inicio de sesion
if ($usuario === "" || ($rol !== "1" && $rol !== "2")) {
    // redirige a index.html
    header("Location: index.html");
    exit();
}

// esta variable indica si el usuario es administrador (cargo 1)
$esAdmin = ($rol === "1");
// esta variable almacena el texto del cargo para mostrarlo en pantalla
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
// esta variable almacena la primera letra del usuario para el encabezado
$inicial = strtoupper(substr(trim($usuario), 0, 1));
// esta variable almacena el usuario y el cargo para no perderlos en los enlaces
$parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($rol);

// solo el admin puede entrar a esta pagina
if (!$esAdmin) {
    // redirige a menu.php
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
// esta variable almacena el tipo de mensaje (ok o error)
$tipoMensaje = "";
// esta variable almacena los datos del registro que se va a editar
$usuarioEditar = null;

// eliminar usuario (no deja eliminar al que tiene la sesion abierta)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_usuario"])) {
    // datos que llegan del formulario
    $cedulaEliminar = trim((string)($_POST["cedula"] ?? ""));

    // intenta ejecutar el codigo y atrapa los errores
    try {
        if (!ctype_digit($cedulaEliminar)) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Cédula no válida.");
        }

        // convierte la cedula a numero entero
        $cedulaNumero = (int)$cedulaEliminar;
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare("DELETE FROM usuario WHERE cedula = ? AND usuario <> ?");
        // envia los datos a la consulta
        $stmt->bind_param("is", $cedulaNumero, $usuarioSesion);
        // ejecuta la consulta
        $stmt->execute();
        $eliminados = $stmt->affected_rows;
        // cierra la consulta
        $stmt->close();

        if ($eliminados > 0) {
            // redirige a edita_usuario.php
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
        // si algun dato no es valido guarda el mensaje de validacion
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    }
}

// cargar usuario para editar
if (isset($_GET["accion"]) && $_GET["accion"] === "editar") {
    // datos que llegan por la url
    $cedulaBuscar = trim((string)($_GET["cedula"] ?? ""));

    // intenta ejecutar el codigo y atrapa los errores
    try {
        if (!ctype_digit($cedulaBuscar)) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Cédula no válida.");
        }

        // convierte la cedula a numero entero
        $cedulaNumero = (int)$cedulaBuscar;
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare(
            "SELECT cedula, nombre, apellido, telefono, email, direccion, usuario, id_tipo_usuario
             FROM usuario
             WHERE cedula = ?"
        );
        // envia los datos a la consulta
        $stmt->bind_param("i", $cedulaNumero);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $usuarioEditar = $stmt->get_result()->fetch_assoc();
        // cierra la consulta
        $stmt->close();

        if (!$usuarioEditar) {
            $mensaje = "No se encontró el usuario seleccionado.";
            $tipoMensaje = "error";
        }
    } catch (InvalidArgumentException $e) {
        // si algun dato no es valido guarda el mensaje de validacion
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // si falla la base de datos guarda el mensaje de error
        $mensaje = "No se pudieron cargar los datos del usuario.";
        $tipoMensaje = "error";
    }
}

// actualizar usuario
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar"])) {
    // datos que llegan del formulario
    $cedula = trim((string)($_POST["cedula"] ?? ""));
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $apellido = trim((string)($_POST["apellido"] ?? ""));
    $telefono = trim((string)($_POST["telefono"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $direccion = trim((string)($_POST["direccion"] ?? ""));
    $usuarioNuevo = trim((string)($_POST["usuario_login"] ?? ""));
    $contra = (string)($_POST["contra"] ?? "");
    $idTipo = (int)($_POST["id_tipo_usuario"] ?? 0);

    // intenta ejecutar el codigo y atrapa los errores
    try {
        if (
            !ctype_digit($cedula) || $nombre === "" || $apellido === "" ||
            $telefono === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $direccion === "" || $usuarioNuevo === "" || !in_array($idTipo, [1, 2], true)
        ) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Completa todos los campos correctamente.");
        }

        // convierte la cedula a numero entero
        $cedulaNumero = (int)$cedula;

        // busca el usuario que tenia antes para saber si se edita a si mismo
        $stmt = $conn->prepare("SELECT usuario FROM usuario WHERE cedula = ?");
        // envia los datos a la consulta
        $stmt->bind_param("i", $cedulaNumero);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $anterior = $stmt->get_result()->fetch_assoc();
        // cierra la consulta
        $stmt->close();

        if (!$anterior) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("El usuario que intentas actualizar no existe.");
        }

        if ($contra !== "") {
            // si escribio una contrasena nueva, se cambia
            $stmt = $conn->prepare(
                "UPDATE usuario
                 SET nombre = ?, apellido = ?, telefono = ?, direccion = ?, email = ?,
                     usuario = ?, contra = ?, id_tipo_usuario = ?
                 WHERE cedula = ?"
            );
            // envia los datos a la consulta
            $stmt->bind_param(
                "sssssssii",
                $nombre, $apellido, $telefono, $direccion, $email,
                $usuarioNuevo, $contra, $idTipo, $cedulaNumero
            );
        } else {
            // si la dejo vacia, se conserva la contrasena actual
            $stmt = $conn->prepare(
                "UPDATE usuario
                 SET nombre = ?, apellido = ?, telefono = ?, direccion = ?, email = ?,
                     usuario = ?, id_tipo_usuario = ?
                 WHERE cedula = ?"
            );
            // envia los datos a la consulta
            $stmt->bind_param(
                "ssssssii",
                $nombre, $apellido, $telefono, $direccion, $email,
                $usuarioNuevo, $idTipo, $cedulaNumero
            );
        }

        // ejecuta la consulta
        $stmt->execute();
        // cierra la consulta
        $stmt->close();

        // si el admin se edito a si mismo, cambian los datos de la url
        if ($anterior["usuario"] === $usuarioSesion) {
            $usuario = $usuarioNuevo;
            $rol = (string)$idTipo;
            // esta variable almacena el usuario y el cargo para no perderlos en los enlaces
            $parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($rol);
        }

        // redirige a edita_usuario.php
        header("Location: edita_usuario.php?" . $parametros . "&mensaje=actualizado");
        exit();
    } catch (InvalidArgumentException $e) {
        // si algun dato no es valido guarda el mensaje de validacion
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

// listado de usuarios (sin mostrar la contrasena), la busqueda la hace React en el navegador
$listaUsuarios = [];
try {
    // ejecuta la consulta sql
    $resultadoUsuarios = $conn->query(
        "SELECT cedula, nombre, apellido, telefono, email, direccion, usuario, id_tipo_usuario
         FROM usuario
         ORDER BY cedula ASC"
    );

    // recorre cada fila y la guarda en la lista
    while ($fila = $resultadoUsuarios->fetch_assoc()) {
        // texto del cargo segun el tipo de usuario
        if ((int)$fila["id_tipo_usuario"] === 1) {
            $fila["cargo_texto"] = "Administrador";
        } elseif ((int)$fila["id_tipo_usuario"] === 2) {
            $fila["cargo_texto"] = "Empleado";
        } else {
            $fila["cargo_texto"] = "Sin cargo";
        }
        $listaUsuarios[] = $fila;
    }
} catch (mysqli_sql_exception $e) {
    // si falla la base de datos guarda el mensaje de error
    $mensaje = "No se pudo cargar el listado de usuarios.";
    $tipoMensaje = "error";
}

// envia los datos de php a react de forma segura
$usuariosJson = json_encode(
    $listaUsuarios,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | La Granja</title>
    <!-- hoja de estilos de la pagina -->
    <link rel="stylesheet" href="css/edita_usuario.css">

    <!-- React sencillo desde CDN -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
</head>
<!-- cuerpo de la pagina -->
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
    <!-- datos del usuario que inicio sesion -->
    <div class="encabezado-usuario">
        <span class="usuario-inicial"><?= e($inicial) ?></span>
        <div>
            <strong><?= e($usuario) ?></strong>
            <p><?= e($nombreCargo) ?></p>
        </div>
    </div>
</header>

<!-- fondo que oscurece la pagina cuando el menu esta abierto -->
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

    <!-- datos del usuario en el menu lateral -->
    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= e($inicial) ?></div>
        <div>
            <strong><?= e($usuario) ?></strong>
            <p><?= e($nombreCargo) ?></p>
        </div>
    </div>

    <!-- enlaces de navegacion -->
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

    <!-- formulario para cerrar sesion -->
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
    <!-- titulo de la pagina -->
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
        <!-- panel de contenido -->
        <section class="panel">
            <h3 class="panel-titulo">Modificar usuario</h3>
            <!-- formulario -->
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

                <!-- botones del formulario -->
                <div class="botones campo-ancho">
                    <button type="submit" name="actualizar" value="1" class="boton boton-principal">Actualizar usuario</button>
                    <a href="edita_usuario.php?<?= e($parametros) ?>" class="boton boton-secundario">Cancelar</a>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- aqui React dibuja el buscador y la tabla -->
    <div id="app-usuarios"></div>
</main>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <!-- enlaces de ayuda -->
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
    <!-- parte inferior del pie de pagina -->
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<!-- codigo javascript de la pagina -->
<script>
// menu lateral
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

// escucha el clic del usuario
abrir.addEventListener("click", mostrarMenu);
// escucha el clic del usuario
cerrar.addEventListener("click", ocultarMenu);
// escucha el clic del usuario
fondo.addEventListener("click", ocultarMenu);

// escucha las teclas que presiona el usuario
document.addEventListener("keydown", function(evento) {
    if (evento.key === "Escape") {
        ocultarMenu();
    }
});
</script>

<!-- datos iniciales que envia php a React -->
<script>
    window.usuariosIniciales = <?= $usuariosJson ?: "[]" ?>;
    window.datosUsuario = {
        usuario: <?= json_encode($usuario, JSON_UNESCAPED_UNICODE) ?>,
        rol: <?= json_encode($rol) ?>
    };
</script>

<!-- busqueda automatica con React -->
<script type="text/babel">

    const { useState } = React;

    function AplicacionUsuarios() {

        // esta variable almacena el texto que se escribe en el buscador
        const [busqueda, setBusqueda] = useState("");

        const lista = window.usuariosIniciales || [];
        const datos = window.datosUsuario;

        // usuario y cargo para no perderlos en los enlaces
        const parametros = "usuario=" + encodeURIComponent(datos.usuario) +
                           "&rol=" + encodeURIComponent(datos.rol);

        // filtra mientras el usuario escribe
        const filtrados = lista.filter(item => {

            const texto = busqueda.toLowerCase().trim();

            return (
                String(item.cedula).toLowerCase().includes(texto) ||
                String(item.nombre).toLowerCase().includes(texto) ||
                String(item.apellido).toLowerCase().includes(texto) ||
                String(item.telefono).toLowerCase().includes(texto) ||
                String(item.email).toLowerCase().includes(texto) ||
                String(item.direccion).toLowerCase().includes(texto) ||
                String(item.usuario).toLowerCase().includes(texto)
            );
        });

        return (
            <div>

                {/* buscador */}
                <section className="panel">
                    <h3 className="panel-titulo">Buscar usuario</h3>
                    <div className="buscador">
                        <input
                            type="text"
                            placeholder="Cédula, nombre, apellido, teléfono, correo, dirección o usuario..."
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                        />
                        {busqueda !== "" && (
                            <button type="button" className="boton boton-secundario" onClick={() => setBusqueda("")}>
                                Mostrar todos
                            </button>
                        )}
                    </div>
                </section>

                {/* tabla de resultados */}
                <section className="panel">
                    <h3 className="panel-titulo">Listado de usuarios</h3>
                    <div className="tabla-contenedor">
                        <table className="tabla">
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

                                {filtrados.length > 0 ? (

                                    filtrados.map(item => (

                                        <tr key={item.cedula}>
                                            <td>{item.cedula}</td>
                                            <td>{item.nombre}</td>
                                            <td>{item.apellido}</td>
                                            <td>{item.telefono}</td>
                                            <td>{item.email}</td>
                                            <td>{item.direccion}</td>
                                            <td>{item.usuario}</td>
                                            <td>{item.cargo_texto}</td>
                                            <td className="tabla-acciones">
                                                <a
                                                    className="boton boton-chico boton-editar"
                                                    href={"edita_usuario.php?" + parametros + "&accion=editar&cedula=" + encodeURIComponent(item.cedula)}
                                                >
                                                    Actualizar
                                                </a>

                                                <form
                                                    action={"edita_usuario.php?" + parametros}
                                                    method="POST"
                                                    className="form-eliminar"
                                                    onSubmit={(e) => {
                                                        if (!confirm("¿Desea eliminar este usuario?")) {
                                                            e.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    <input type="hidden" name="usuario" value={datos.usuario} />
                                                    <input type="hidden" name="rol" value={datos.rol} />
                                                    <input type="hidden" name="cedula" value={item.cedula} />
                                                    <button type="submit" name="eliminar_usuario" value="1" className="boton boton-chico boton-eliminar">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>

                                    ))

                                ) : (

                                    <tr>
                                        <td colSpan="9" className="tabla-vacia">No se encontraron usuarios.</td>
                                    </tr>

                                )}

                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        );
    }

    ReactDOM.createRoot(document.getElementById("app-usuarios")).render(<AplicacionUsuarios />);

</script>
</body>
</html>
<?php
// cierra la conexion con la base de datos
$conn->close();
?>
