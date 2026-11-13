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
            $contra,
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
    <?php // hoja de estilos de la pagina (sube el numero v cada vez que cambies el css) ?>
    <link rel="stylesheet" href="../css/registro.css?v=2">
</head>
<?php // cuerpo de la pagina ?>
<body class="pagina-registro">

<?php // encabezado ?>
<header class="encabezado">
    <a href="../index.php" class="encabezado-logo">
        <img src="../img/logo.png" alt="Logo de La Granja">
    </a>
    <div class="encabezado-texto">
        <h1>Supermercado La Granja</h1>
        <p>Sistema de gestión de inventario</p>
    </div>
</header>

<?php // contenido principal ?>
<main class="contenido">
    <?php // titulo de la pagina ?>
    <section class="titulo-pagina">
        <div class="titulo-icono">
            <img src="../img/usuario.png" alt="Icono de usuario">
        </div>
        <div>
            <h2>Crear una cuenta</h2>
            <p>Completa tus datos para registrarte en el sistema.>
        </div>
    </section>

    <?php // formulario de registro ?>
    <section class="panel">
        <div class="panel-encabezado">
            <div>
                <h3>Información del usuario</h3>
                <p>Ingresa tus datos personales y de acceso.</p>
            </div>
        </div>

        <?php // formulario ?>
        <form action="registro.php" method="POST" class="formulario">

            <?php // datos personales ?>
            <div class="campo">
                <label for="cedula">Cédula <span class="obligatorio">*</span></label>
                <input type="text" id="cedula" name="cedula" required
                       inputmode="numeric" pattern="[0-9]+" maxlength="10"
                       data-tipo="numeros" data-error="Escribe solo números."
                       placeholder="Ej: 1098765432"
                       value="<?= e($_POST["cedula"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="nombre">Nombre <span class="obligatorio">*</span></label>
                <input type="text" id="nombre" name="nombre" required
                       pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" maxlength="50"
                       data-tipo="letras" data-error="Escribe solo letras, sin números."
                       placeholder="Ej: Juan"
                       value="<?= e($_POST["nombre"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="apellido">Apellido <span class="obligatorio">*</span></label>
                <input type="text" id="apellido" name="apellido" required
                       pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" maxlength="50"
                       data-tipo="letras" data-error="Escribe solo letras, sin números."
                       placeholder="Ej: Pérez"
                       value="<?= e($_POST["apellido"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="telefono">Teléfono <span class="obligatorio">*</span></label>
                <input type="tel" id="telefono" name="telefono" required
                       inputmode="numeric" pattern="[0-9]{7,10}" minlength="7" maxlength="10"
                       data-tipo="numeros" data-error="Escribe solo números (entre 7 y 10 dígitos)."
                       placeholder="Ej: 3001234567"
                       value="<?= e($_POST["telefono"] ?? "") ?>">
            </div>

            <div class="campo campo-ancho">
                <label for="direccion">Dirección <span class="obligatorio">*</span></label>
                <input type="text" id="direccion" name="direccion" required maxlength="50"
                       placeholder="Ej: Calle 10 # 5-20, Barrio Centro"
                       value="<?= e($_POST["direccion"] ?? "") ?>">
            </div>

            <div class="campo campo-ancho">
                <label for="email">Correo electrónico <span class="obligatorio">*</span></label>
                <input type="email" id="email" name="email" required maxlength="50"
                       placeholder="Ej: juan.perez@correo.com"
                       value="<?= e($_POST["email"] ?? "") ?>">
            </div>

            <?php // datos de acceso ?>
            <div class="separador-formulario">
                <h3>Datos de acceso</h3>
                <p>Estos datos te permitirán ingresar al sistema.</p>
            </div>

            <div class="campo">
                <label for="usuario">Nombre de usuario <span class="obligatorio">*</span></label>
                <input type="text" id="usuario" name="usuario" required maxlength="50"
                       autocomplete="username"
                       placeholder="Ej: juanperez"
                       value="<?= e($_POST["usuario"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="contra">Contraseña <span class="obligatorio">*</span></label>
                <input type="password" id="contra" name="contra" required minlength="8" maxlength="50"
                       autocomplete="new-password"
                       placeholder="Ej: mínimo 8 caracteres">
            </div>

            <?php // cargo del usuario ?>
            <div class="campo campo-ancho">
                <label for="id_tipo_usuario">Cargo <span class="obligatorio">*</span></label>
                <select id="id_tipo_usuario" name="id_tipo_usuario" required>
                    <option value="" <?= ($cargoSeleccionado === 0) ? "selected" : "" ?>>Seleccione</option>
                    <option value="1" <?= ($cargoSeleccionado === 1) ? "selected" : "" ?>>Administrador</option>
                    <option value="2" <?= ($cargoSeleccionado === 2) ? "selected" : "" ?>>Empleado</option>
                </select>
            </div>

            <?php // boton de registrar ?>
            <div class="botones campo-ancho">
                <button type="submit" name="guardar" class="boton boton-principal">Registrar usuario</button>
            </div>

        </form>

        <?php // enlace para iniciar sesion ?>
        <p class="texto-login">¿Ya tienes cuenta? <a href="../index.php">Iniciar sesión</a></p>
    </section>
</main>

<?php // pie de pagina ?>
<footer class="pie-pagina">
    <div class="footer-contenido">
        <?php // enlaces de ayuda ?>
        <section class="footer-ayuda">
            <h4>Ayuda y soporte</h4>
            <p class="footer-descripcion">Consulta la información necesaria para utilizar correctamente el sistema de inventario.</p>
            <a href="ayuda.php" class="footer-enlace"><span class="footer-icono">?</span>Centro de ayuda</a>
            <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">✓</span>Cómo utilizar el sistema</a>
            <a href="ayuda.php#soporte" class="footer-enlace"><span class="footer-icono">✉</span>Soporte</a>
        </section>
        <?php // informacion del sistema ?>
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
    <?php // parte inferior del pie de pagina ?>
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<?php // ventana de aviso (exito o error) ?>
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

<script>
    // ========== solo letras o solo numeros ==========
    // quita en vivo lo que no corresponde mientras la persona escribe
    document.querySelectorAll("[data-tipo]").forEach(function (campo) {
        campo.addEventListener("input", function () {
            if (campo.dataset.tipo === "letras") {
                // deja solo letras (con tildes y ñ) y espacios
                campo.value = campo.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s]/g, "");
            } else if (campo.dataset.tipo === "numeros") {
                // deja solo numeros
                campo.value = campo.value.replace(/\D/g, "");
            }
        });
    });

    // ========== mensajes de validacion en español ==========
    document.querySelectorAll(".formulario input, .formulario select").forEach(function (campo) {
        // cuando el campo no es valido, muestra el mensaje en español
        campo.addEventListener("invalid", function () {
            // limpia el mensaje anterior para recalcular el estado
            campo.setCustomValidity("");

            if (campo.validity.valueMissing) {
                // campo vacio
                campo.setCustomValidity(
                    campo.tagName === "SELECT"
                        ? "Por favor, selecciona una opción."
                        : "Por favor, llena este campo."
                );
            } else if (campo.validity.typeMismatch) {
                // correo mal escrito
                campo.setCustomValidity("Escribe un correo válido. Ejemplo: juan.perez@correo.com");
            } else if (campo.validity.patternMismatch) {
                // formato incorrecto (letras o numeros)
                campo.setCustomValidity(campo.dataset.error || "El formato no es válido.");
            } else if (campo.validity.tooShort) {
                // muy corto
                campo.setCustomValidity("Debe tener al menos " + campo.minLength + " caracteres.");
            }
        });

        // borra el mensaje cuando la persona corrige el campo
        campo.addEventListener("input", function () {
            campo.setCustomValidity("");
        });
        campo.addEventListener("change", function () {
            campo.setCustomValidity("");
        });
    });

    // ========== ventana de aviso ==========
    const modal = document.getElementById("modalAviso");

    if (modal) {
        // true si el registro salio bien
        const exito = <?= ($tipoMensaje === "ok") ? "true" : "false" ?>;
        const botonCerrar = document.getElementById("cerrarModal");

        // cierra la ventana (si fue exito, manda al inicio de sesion)
        function cerrarModal() {
            if (exito) {
                window.location.href = "../index.php";
            } else {
                modal.remove();
            }
        }

        // cerrar con el boton
        botonCerrar.addEventListener("click", cerrarModal);

        // cerrar al hacer clic en el fondo oscuro
        modal.addEventListener("click", function (e) {
            if (e.target === modal) {
                cerrarModal();
            }
        });

        // cerrar con la tecla Escape
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                cerrarModal();
            }
        });

        // pone el foco en el boton para poder cerrar con Enter
        botonCerrar.focus();
    }
</script>

</body>
</html>