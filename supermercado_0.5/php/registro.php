<?php
// inicia la sesion
session_start();

// si ya inicio sesion, lo manda directo al menu
if (!empty($_SESSION["usuario"])) {
    header("Location: menu.php");
    exit();
}

// carga la conexion con la base de datos
include("conexion.php");

// evita que el texto rompa el html
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

// variables de la pagina
$mensaje = "";
// esta variable almacena el tipo de mensaje (ok o error)
$tipoMensaje = "";

// registrar usuario
if (isset($_POST["guardar"])) {
    // datos que llegan del formulario
    $cedula = trim((string)($_POST["cedula"] ?? ""));
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $apellido = trim((string)($_POST["apellido"] ?? ""));
    $telefono = trim((string)($_POST["telefono"] ?? ""));
    $direccion = trim((string)($_POST["direccion"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $usuario = trim((string)($_POST["usuario"] ?? ""));
    $contra = (string)($_POST["contra"] ?? "");
    $id_tipo_usuario = (int)($_POST["id_tipo_usuario"] ?? 0);

    // intenta ejecutar el codigo y atrapa los errores
    try {
        // revisa que no haya campos vacios
        if ($cedula === "" || $nombre === "" || $apellido === "" || $telefono === "" ||
            $direccion === "" || $email === "" || $usuario === "" || $contra === "") {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Completa todos los campos obligatorios.");
        }

        // la cedula solo puede tener numeros
        if (!ctype_digit($cedula)) {
            throw new InvalidArgumentException("La cédula solo puede tener números.");
        }

        // el nombre y el apellido solo pueden tener letras y espacios
        if (!preg_match('/^[\p{L}\s]+$/u', $nombre) || !preg_match('/^[\p{L}\s]+$/u', $apellido)) {
            throw new InvalidArgumentException("El nombre y el apellido solo pueden tener letras.");
        }

        // el telefono solo puede tener numeros (entre 7 y 10 digitos)
        if (!ctype_digit($telefono) || strlen($telefono) < 7 || strlen($telefono) > 10) {
            throw new InvalidArgumentException("El teléfono debe tener solo números (entre 7 y 10 dígitos).");
        }

        // el correo debe ser valido
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Escribe un correo electrónico válido.");
        }

        // la contrasena debe tener minimo 8 caracteres
        if (strlen($contra) < 8) {
            throw new InvalidArgumentException("La contraseña debe tener mínimo 8 caracteres.");
        }

        // el cargo solo puede ser 1 (administrador) o 2 (empleado)
        if (!in_array($id_tipo_usuario, [1, 2], true)) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Selecciona un cargo válido.");
        }

        // guarda el usuario en la base de datos
        $cedulaNumero = (int)$cedula;
        // convierte la contrasena en un hash seguro antes de guardarla
        $contraHash = password_hash($contra, PASSWORD_DEFAULT);
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare(
            "INSERT INTO usuario
            (cedula, nombre, apellido, telefono, direccion, email, usuario, contra, id_tipo_usuario)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        // envia los datos a la consulta
        $stmt->bind_param(
            "isssssssi",
            $cedulaNumero,
            $nombre,
            $apellido,
            $telefono,
            $direccion,
            $email,
            $usuario,
            $contraHash,
            $id_tipo_usuario
        );
        // ejecuta la consulta
        $stmt->execute();
        // cierra la consulta
        $stmt->close();

        // guarda el mensaje de exito (se muestra en la ventana)
        $mensaje = "Usuario registrado correctamente. Ahora inicia sesión.";
        $tipoMensaje = "ok";

        // limpia los datos para que el formulario quede vacio
        $_POST = [];
    } catch (InvalidArgumentException $e) {
        // si algun dato no es valido guarda el mensaje de validacion
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // 1062 = cedula o usuario repetido
        if ($e->getCode() == 1062) {
            $mensaje = "La cédula o el nombre de usuario ya están registrados.";
        } else {
            error_log($e->getMessage());
            $mensaje = "No se pudo registrar el usuario. Verifica los datos e inténtalo nuevamente.";
        }
        $tipoMensaje = "error";
    }
}

// cargo que queda marcado en el formulario (0 = seleccione)
$cargoSeleccionado = (int)($_POST["id_tipo_usuario"] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de usuarios | La Granja</title>
    <link rel="stylesheet" href="../css/registro.css?v=2">
</head>
<body class="pagina-registro">

<!-- encabezado -->
<header class="encabezado">
    <a href="../index.php" class="encabezado-logo">
        <img src="../img/logo.png" alt="Logo de La Granja">
    </a>
    <div class="encabezado-texto">
        <h1>Supermercado La Granja</h1>
        <p>Sistema de gestión de inventario</p>
    </div>
</header>

<!-- contenido principal -->
<main class="contenido">
    <section class="titulo-pagina">
        <div class="titulo-icono">
            <img src="../img/usuario.png" alt="Icono de usuario">
        </div>
        <div>
            <h2>Crear una cuenta</h2>
            <p>Completa tus datos para registrarte en el sistema.</p>
        </div>
    </section>

    <!-- formulario de registro -->
    <section class="panel">
        <div class="panel-encabezado">
            <div>
                <h3>Información del usuario</h3>
                <p>Ingresa tus datos personales y de acceso.</p>
            </div>
        </div>

        <!-- formulario -->
        <form action="registro.php" method="POST" class="formulario">

            <!-- datos personales -->
            <div class="campo">
                <label for="cedula">Cédula <span class="obligatorio">*</span></label>
                <input type="text" id="cedula" name="cedula" required
                       inputmode="numeric" pattern="[0-9]+" maxlength="10"
                       data-tipo="numeros" data-error="Escribe solo números."
                       placeholder="Ingrese cédula"
                       value="<?= e($_POST["cedula"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="nombre">Nombre <span class="obligatorio">*</span></label>
                <input type="text" id="nombre" name="nombre" required
                       pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" maxlength="50"
                       data-tipo="letras" data-error="Escribe solo letras, sin números."
                       placeholder="Ingrese nombre"
                       value="<?= e($_POST["nombre"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="apellido">Apellido <span class="obligatorio">*</span></label>
                <input type="text" id="apellido" name="apellido" required
                       pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" maxlength="50"
                       data-tipo="letras" data-error="Escribe solo letras, sin números."
                       placeholder="Ingrese apellido"
                       value="<?= e($_POST["apellido"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="telefono">Teléfono <span ></span></label>
                <input type="tel" id="telefono" name="telefono" required
                       inputmode="numeric" pattern="[0-9]{7,10}" minlength="7" maxlength="10"
                       data-tipo="numeros" data-error="Escribe solo números (entre 7 y 10 dígitos)."
                       placeholder="Ingrese teléfono"
                       value="<?= e($_POST["telefono"] ?? "") ?>">
            </div>

            <div class="campo campo-ancho">
                <label for="direccion">Dirección <span ></span></label>
                <input type="text" id="direccion" name="direccion" required maxlength="50"
                       placeholder="Ingrese dirección"
                       value="<?= e($_POST["direccion"] ?? "") ?>">
            </div>

            <div class="campo campo-ancho">
                <label for="email">Correo electrónico <span class="obligatorio">*</span></label>
                <input type="email" id="email" name="email" required maxlength="50"
                       placeholder="Ingrese correo"
                       value="<?= e($_POST["email"] ?? "") ?>">
            </div>

            <!-- datos de acceso -->
            <div class="separador-formulario">
                <h3>Datos de acceso</h3>
                <p>Estos datos te permitirán ingresar al sistema.</p>
            </div>

            <div class="campo">
                <label for="usuario">Nombre de usuario <span class="obligatorio">*</span></label>
                <input type="text" id="usuario" name="usuario" required maxlength="50"
                       autocomplete="username"
                       placeholder="Ingrese usuario"
                       value="<?= e($_POST["usuario"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="contra">Contraseña <span class="obligatorio">*</span></label>
                <input type="password" id="contra" name="contra" required minlength="8" maxlength="50"
                       autocomplete="new-password"
                       placeholder="Ingrese contraseña">
            </div>

            <!-- cargo del usuario -->
            <div class="campo campo-ancho">
                <label for="id_tipo_usuario">Cargo <span class="obligatorio">*</span></label>
                <select id="id_tipo_usuario" name="id_tipo_usuario" required>
                    <option value="" <?= ($cargoSeleccionado === 0) ? "selected" : "" ?>>Seleccione</option>
                    <option value="1" <?= ($cargoSeleccionado === 1) ? "selected" : "" ?>>Administrador</option>
                    <option value="2" <?= ($cargoSeleccionado === 2) ? "selected" : "" ?>>Empleado</option>
                </select>
            </div>

            <!-- boton de registrar -->
            <div class="botones campo-ancho">
                <button type="submit" name="guardar" class="boton boton-principal">Registrar usuario</button>
            </div>

        </form>

        <!-- enlace para iniciar sesion -->
        <p class="texto-login">¿Ya tienes cuenta? <a href="../index.php">Iniciar sesión</a></p>
    </section>
</main>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <!-- enlaces de ayuda -->
        <section class="footer-ayuda">
            <h4>Ayuda y soporte</h4>
            <p class="footer-descripcion">Consulta la información necesaria para utilizar correctamente el sistema de inventario.</p>
            <a href="ayuda.php" class="footer-enlace"><span class="footer-icono">?</span>Centro de ayuda</a>
            <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">✓</span>Cómo utilizar el sistema</a>
            <a href="ayuda.php#soporte" class="footer-enlace"><span class="footer-icono">✉</span>Soporte</a>
        </section>
        <!-- informacion del sistema -->
        <section class="footer-informacion">
            <h4>Información del sistema</h4>
            <p><strong>Supermercado La Granja</strong><br>Sistema de gestión de inventario.</p>
            <p>Este sistema permite gestionar la información relacionada con los productos y los movimientos del inventario.</p>
            <div class="footer-nota">
                <strong>Registro de usuarios</strong>
                <p>Protege tus datos de acceso y no compartas tu contraseña con otras personas.</p>
            </div>
        </section>
    </div>
    <!-- parte inferior del pie de pagina -->
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<!-- ventana de aviso (exito o error) -->
<?php if ($mensaje !== ""): ?>
    <div class="modal" id="modalAviso">
        <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="modalTitulo">
            <div class="modal__icono modal__icono--<?= e($tipoMensaje) ?>">
                <?= ($tipoMensaje === "ok") ? "&#10003;" : "&#10005;" ?>
            </div>
            <h3 class="modal__titulo" id="modalTitulo">
                <?= ($tipoMensaje === "ok") ? "¡Registro exitoso!" : "Error" ?>
            </h3>
            <p class="modal__texto"><?= e($mensaje) ?></p>
            <button type="button" class="modal__boton modal__boton--<?= e($tipoMensaje) ?>" id="cerrarModal">Aceptar</button>
        </div>
    </div>
<?php endif; ?>

<script src="../js/registro.js"></script>

<!-- cierra la ventana de aviso al dar aceptar (por si js/registro.js no carga) -->
<script>
    var botonCerrar = document.getElementById("cerrarModal");
    if (botonCerrar) {
        botonCerrar.addEventListener("click", function () {
            var modal = document.getElementById("modalAviso");
            if (modal) {
                modal.style.display = "none";
            }
        });
    }
</script>

</body>
</html>