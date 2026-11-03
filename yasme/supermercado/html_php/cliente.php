<?php
session_start();

// Si no hay sesión, regresar al inicio
if (!isset($_SESSION["usuario"])) {
    header("Location: index.html");
    exit();
}


include("conexion.php");

// Que los errores de MySQL lleguen a $stmt->error y no detengan la página
mysqli_report(MYSQLI_REPORT_OFF);

if (isset($conexion) && !isset($conn)) {
    $conn = $conexion;
}

$mensaje = "";
$cliente_editar = null;
$busqueda = "";


/* ELIMINAR */

if (isset($_GET['borrar'])) {

    $cedula = intval($_GET['borrar']);

    $stmt = $conn->prepare("DELETE FROM cliente WHERE cedula_cliente = ?");

    $stmt->bind_param("i", $cedula);

    if ($stmt->execute()) {
        $mensaje = "Cliente eliminado correctamente.";
    } else {
        $mensaje = "Error al eliminar el cliente: " . $stmt->error;
    }

    $stmt->close();
}


/* EDITAR */

if (isset($_GET['editar'])) {

    $cedula = intval($_GET['editar']);

    $stmt = $conn->prepare(
        "SELECT * FROM cliente WHERE cedula_cliente = ?"
    );
    $stmt->bind_param("i", $cedula);
    $stmt->execute();
    $resultado = $stmt->get_result();
    if ($resultado->num_rows > 0) {

        $cliente_editar = $resultado->fetch_assoc();

    }

    $stmt->close();
}


/* GUARDAR Y ACTUALIZAR */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST['accion'] ?? "";

    $cedula = intval($_POST['cedula_cliente'] ?? 0);
    $nombre = $_POST['nombre'] ?? "";
    $telefono = $_POST['telefono'] ?? "";
    $email = $_POST['email'] ?? "";
    $direccion = $_POST['direccion'] ?? "";


    /* GUARDAR */

    if ($accion === "guardar") {

        $stmt = $conn->prepare(
            "INSERT INTO cliente (cedula_cliente, nombre, telefono, email, direccion)VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("issss",$cedula,$nombre,$telefono,$email,$direccion);
        if ($stmt->execute()) {

            $mensaje = "Cliente guardado correctamente.";

        } else {

            $mensaje = "Error al guardar: " . $stmt->error;

        }
        $stmt->close();
    }


    /* ACTUALIZAR */

    if ($accion === "actualizar") {

        $stmt = $conn->prepare("UPDATE cliente SET nombre = ?,telefono = ?,email = ?,direccion = ?
             WHERE cedula_cliente = ?"
        );

        $stmt->bind_param("ssssi",$nombre,$telefono,$email,$direccion,$cedula);

        if ($stmt->execute()) {

            $mensaje = "Cliente actualizado correctamente.";

        } else {

            $mensaje = "Error al actualizar: " . $stmt->error;

        }

        $stmt->close();
    }
}


/* BUSCAR */

if (isset($_GET['buscar'])) {

    $busqueda = trim($_GET['buscar']);

}


/* LISTAR CLIENTES */

$sql = "SELECT cedula_cliente,nombre,telefono,email,direccion
        FROM cliente";

if ($busqueda !== "") {

    $sql .= " WHERE cedula_cliente LIKE ? OR nombre LIKE ? OR telefono LIKE ? OR email LIKE ? OR direccion LIKE ?";
}


$sql .= " ORDER BY cedula_cliente ASC";
$stmt = $conn->prepare($sql);
if ($busqueda !== "") {

    $texto = "%" . $busqueda . "%";

    $stmt->bind_param("sssss",$texto,$texto,$texto,$texto,$texto);
}
$stmt->execute();
$resultado = $stmt->get_result();

?>


<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cliente</title>

</head>


<body>


<h2>Cliente</h2>


<?php if ($mensaje != ""): ?>

    <p> <?php echo htmlspecialchars($mensaje); ?></p>

<?php endif; ?>


<?php if ($cliente_editar): ?>


<!-- FORMULARIO ACTUALIZAR -->

<h3>Modificar Cliente</h3>


<form method="POST">

    <input type="hidden" name="accion" value="actualizar">

    <label>Cédula Cliente:</label><br>
    <input type="number" name="cedula_cliente" value="<?php echo $cliente_editar['cedula_cliente']; ?>" readonly>

    <br><br>

    <label>Nombre:</label><br>
    <input type="text" name="nombre" value="<?php echo htmlspecialchars($cliente_editar['nombre'] ?? ''); ?>" required>

    <br><br>

    <label>Teléfono:</label><br>
    <input type="text" name="telefono" value="<?php echo htmlspecialchars($cliente_editar['telefono'] ?? ''); ?>" required>

    <br><br>

    <label>Email:</label><br>
    <input type="email" name="email" value="<?php echo htmlspecialchars($cliente_editar['email'] ?? ''); ?>" required>

    <br><br>

    <label>Dirección:</label><br>
    <input type="text" name="direccion" value="<?php echo htmlspecialchars($cliente_editar['direccion'] ?? ''); ?>" required>

    <br><br>

    <button type="submit">
        Actualizar
    </button>


    <a href="cliente.php">
        Cancelar
    </a>

</form>


<?php else: ?>


<!-- FORMULARIO GUARDAR -->

<form method="POST">

    <input type="hidden" name="accion" value="guardar">

    <label>Cédula Cliente:</label><br>
    <input type="number" name="cedula_cliente" required placeholder="Cédula">

    <br><br>

    <label>Nombre:</label><br>
    <input type="text" name="nombre" required placeholder="Nombre">

    <br><br>

    <label>Teléfono:</label><br>
    <input type="text" name="telefono" required placeholder="Teléfono">

    <br><br>

    <label>Email:</label><br>
    <input type="email" name="email" required placeholder="Correo">

    <br><br>

    <label>Dirección:</label><br>
    <input type="text" name="direccion" required placeholder="Dirección">

    <br><br>

    <button type="submit">
        Registrar
    </button>


     <br>

</form><br>

<form action="menu.php">

         <button type="submit">
             Menu Principal
    </button>

</form>


<?php endif; ?>


<br>


<!-- BUSCAR -->

<h3>Buscar Cliente</h3>


<form method="GET">

    <input type="text" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar">


    <button type="submit">
        Buscar
    </button>


    <a href="cliente.php">
        Mostrar todos
    </a>

</form>


<br>


<!-- TABLA -->

<table border="1" cellpadding="5" cellspacing="0">


    <tr>

        <th>Cédula Cliente</th>
        <th>Nombre</th>
        <th>Teléfono</th>
        <th>Email</th>
        <th>Dirección</th>
        <th>Acciones</th>

    </tr>


    <?php while ($fila = $resultado->fetch_assoc()): ?>


        <tr>

            <td><?php echo $fila['cedula_cliente']; ?></td>
            <td><?php echo htmlspecialchars($fila['nombre'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($fila['telefono'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($fila['email'] ?? '');?></td>
            <td><?php echo htmlspecialchars($fila['direccion'] ?? ''); ?></td>
            
            <td><a href="?editar=<?php echo $fila['cedula_cliente']; ?>"> Actualizar </a> |
                <a href="?borrar=<?php echo $fila['cedula_cliente']; ?>"
                   onclick="return confirm('¿Desea eliminar este cliente?');">
                    Eliminar
                </a>


            </td>


        </tr>


    <?php endwhile; ?>


</table>
<br>

</body>

</html>


<?php

$stmt->close();
$conn->close();

?>

