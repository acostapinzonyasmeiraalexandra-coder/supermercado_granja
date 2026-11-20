<?php
// configuracion central de la sesion (ruta del login desde la raiz)
$rutaLogin = "index.php";
require_once __DIR__ . "/php/sesion.php";

// si ya hay una sesion valida (usuario y rol), se manda directo al menu
if (!empty($_SESSION["usuario"]) && in_array((string)($_SESSION["rol"] ?? ""), ["1", "2"], true)) {
    header("Location: php/menu.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supermercado La Granja</title>
    <link rel="stylesheet" href="css/index.css?v=6">
</head>
<body class="pagina">

    <header class="encabezado">
        <h2 class="encabezado__titulo">Supermercado La Granja</h2>
    </header>

    <main class="contenedor">
        <section class="tarjeta">

            <form class="formulario" action="php/usuario.php" method="POST">

                <div class="campo">
                    <label class="campo__etiqueta" for="cargo">
                        Cargo: <span class="campo__obligatorio">*</span>
                    </label>
                    <select class="campo__select" id="cargo" name="cargo" required>
                        <option value="">seleccione</option>
                        <option value="1">Administrador</option>
                        <option value="2">Empleado</option>
                    </select>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="usuario">
                        Usuario: <span class="campo__obligatorio">*</span>
                    </label>
                    <input class="campo__input" type="text" id="usuario" name="usuario"
                           required maxlength="50" autocomplete="username">
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="contra">
                        Contraseña: <span class="campo__obligatorio">*</span>
                    </label>
                    <input class="campo__input" type="password" id="contra" name="contra"
                           required autocomplete="current-password">
                </div>

                <button class="boton boton--principal" type="submit" name="ingresar">Ingresar</button>
            </form>

            <form class="formulario formulario--registro" action="php/registro.php">
                <button class="boton boton--secundario" type="submit">Registrarse</button>
            </form>

        </section>
    </main>

    <?php if (isset($_GET["error"])): ?>
        <div class="modal" id="modalError">
            <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="modalTitulo">
                <img src="img/error.png" alt="Error" width="80" height="80"
                     style="display: block; margin: 0 auto 15px;">
                <h3 class="modal__titulo" id="modalTitulo">Error</h3>
                <?php if ($_GET["error"] == "2"): ?>
                    <p class="modal__texto">No se pudo conectar con la base de datos. Revisa conexion.php.</p>
                <?php elseif ($_GET["error"] == "3"): ?>
                    <p class="modal__texto">Tu sesión expiró por inactividad. Inicia sesión de nuevo.</p>
                <?php else: ?>
                    <p class="modal__texto">Usuario, contraseña o cargo incorrectos.</p>
                <?php endif; ?>
                <button type="button" class="modal__boton modal__boton--error" id="cerrarModal">Aceptar</button>
            </div>
        </div>
    <?php endif; ?>

    <script src="js/index.js?v=2"></script>
</body>
</html>
