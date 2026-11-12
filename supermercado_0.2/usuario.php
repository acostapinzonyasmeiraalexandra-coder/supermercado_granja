<?php

// hace que los errores de mysql se manejen como excepciones
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// carga el archivo que hace la conexion con la base de datos
include("conexion.php");

// obtener los datos del formulario.
$usuario = $_POST["usuario"] ?? "";
$password = $_POST["contra"] ?? "";
$cargo = $_POST["cargo"] ?? "";

// buscar el usuario en la base de datos.
$stmt = $conn->prepare(
    "SELECT usuario, id_tipo_usuario
     FROM usuario
     WHERE usuario = ? AND contra = ? AND id_tipo_usuario = ?"
);

// vincular los datos.
$stmt->bind_param("ssi", $usuario, $password, $cargo);

// ejecutar la consulta.
$stmt->execute();

// obtener el resultado.
$resultado = $stmt->get_result();

// verificar los datos del usuario.
if ($resultado->num_rows > 0) {

    // obtiene la fila del resultado
    $fila = $resultado->fetch_assoc();

    // obtener el nombre y el cargo del usuario.
    $nombreUsuario = $fila["usuario"];
    $rol = (string) $fila["id_tipo_usuario"];

    // cerrar la consulta y la conexion.
    $stmt->close();
    // cierra la conexion con la base de datos
    $conn->close();

    // abrir el menu con el nombre y el rol.
    if ($rol === "1" || $rol === "2") {
        header(
            "Location: menu.php?usuario=" . urlencode($nombreUsuario) .
            "&rol=" . urlencode($rol)
        );
        exit();
    }

    // redirige a index.html
    header("Location: index.html");
    exit();

} else {

    // cerrar la consulta y la conexion.
    $stmt->close();
    // cierra la conexion con la base de datos
    $conn->close();

    // mostrar un mensaje si los datos son incorrectos.
    echo "<script>
        alert('Usuario o contraseña incorrectos');
        window.location.href = 'index.html';
    </script>";
    exit();
}

?>
