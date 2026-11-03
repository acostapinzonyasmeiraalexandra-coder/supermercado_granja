<?php
session_start();

// Si no hay una sesion iniciada, regresar al inicio.
if (!isset($_SESSION["usuario"]) || !isset($_SESSION["cargo"])) {
    header("Location: index.html");
    exit();
}

$cargo = (string)$_SESSION["cargo"];

// Redirigir al modulo seleccionado.
if (isset($_POST["pro"])) {
    header("Location: producto.php");
    exit();
} elseif (isset($_POST["prov"]) && $cargo === "1") {
    header("Location: proveedor.php");
    exit();
} elseif (isset($_POST["mov_entrada"])) {
    header("Location: mov_entrada.php");
    exit();
} elseif (isset($_POST["mov_salida"])) {
    header("Location: mov_salida.php");
    exit();
} elseif (isset($_POST["cli"]) ) {
    header("Location: cliente.php");
    exit();
} elseif (isset($_POST["inv"])) {
    header("Location: inventario.php");
    exit();
} elseif (isset($_POST["editar"]) && $cargo === "1") {
    // Solo el administrador puede modificar usuarios
    header("Location: edita_usuario.php");
    exit();
}elseif (isset($_POST["salir"])) {
    $_SESSION = array();
    session_destroy();
    header("Location: index.html");
    exit();
}elseif (isset($_POST["reportes"])) {
    header("Location: reportes.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú principal</title>
</head>
<body>
    <h2>Menú principal</h2>

    <?php if ($cargo === "1") { ?>
        <p>Bienvenido Administrador, <?php echo htmlspecialchars($_SESSION["usuario"]); ?></p>

        <form action="menu.php" method="POST">
            <button type="submit" name="pro">Producto</button>
            <button type="submit" name="prov">Proveedor</button>
            <button type="submit" name="mov_entrada">Movimiento de entrada</button>
            <button type="submit" name="mov_salida">Movimiento de salida</button>
            <button type="submit" name="cli">Cliente</button>
            <button type="submit" name="inv">Inventario</button><br><br>
            <button type="submit" name="editar">Modificar usuario</button>
            <button type="submit" name="reportes">Reportes</button><br><br>
            <button type="submit" name="salir">Cerrar sesión</button>
           
        </form>
        

    <?php } elseif ($cargo === "2") { ?>
        <p>Bienvenido Empleado, <?php echo htmlspecialchars($_SESSION["usuario"]); ?></p>

        <form action="menu.php" method="POST">
            <button type="submit" name="pro">Producto</button>
            <button type="submit" name="mov_entrada">Movimiento de entrada</button>
            <button type="submit" name="mov_salida">Movimiento de salida</button>
             <button type="submit" name="cli">Cliente</button><br><br>
            <button type="submit" name="salir">Cerrar sesión</button>
        </form>

    <?php } else { ?>
        <p>El tipo de usuario no es válido. Comunícate con el administrador.</p>
        <form action="menu.php" method="POST">
            <button type="submit" name="salir">Volver al inicio</button>
        </form>
    <?php } ?>
</body>
</html>