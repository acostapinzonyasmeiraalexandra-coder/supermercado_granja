<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include("conexion.php");

// Obtener los datos del formulario.
$usuario = $_POST["usuario"] ?? "";
$password = $_POST["contra"] ?? "";
$cargo = $_POST["cargo"] ?? "";

// Buscar el usuario en la base de datos.
$stmt = $conn->prepare(
    "SELECT usuario, id_tipo_usuario
     FROM usuario
     WHERE usuario = ? AND contra = ? AND id_tipo_usuario = ?"
);

// Vincular los datos.
$stmt->bind_param("ssi", $usuario, $password, $cargo);

// Ejecutar la consulta.
$stmt->execute();

// Obtener el resultado.
$resultado = $stmt->get_result();

// Verificar los datos del usuario.
if ($resultado->num_rows > 0) {

    $fila = $resultado->fetch_assoc();

    // Obtener el nombre y el cargo del usuario.
    $nombreUsuario = $fila["usuario"];
    $rol = (string) $fila["id_tipo_usuario"];

    // Cerrar la consulta y la conexión.
    $stmt->close();
    $conn->close();

    // Abrir el menú con el nombre y el rol.
    if ($rol === "1" || $rol === "2") {
        header(
            "Location: menu.php?usuario=" . urlencode($nombreUsuario) .
            "&rol=" . urlencode($rol)
        );
        exit();
    }

    header("Location: index.html");
    exit();

} else {

    // Cerrar la consulta y la conexión.
    $stmt->close();
    $conn->close();

    // Mostrar un mensaje si los datos son incorrectos.
    echo "<script>
        alert('Usuario o contraseña incorrectos');
        window.location.href = 'index.html';
    </script>";
    exit();
}

?>
