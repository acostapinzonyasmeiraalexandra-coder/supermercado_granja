<?php
// inicia la sesion
session_start();

// conexion a la base de datos
include("conexion.php");

// si entran a esta pagina sin enviar el formulario, vuelven al inicio
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit();
}

// datos del formulario
$usuario = trim((string)($_POST["usuario"] ?? ""));
$password = (string)($_POST["contra"] ?? "");
$cargo = (int)($_POST["cargo"] ?? 0);

// busca el usuario en la base de datos
$stmt = $conn->prepare(
    "SELECT usuario, id_tipo_usuario
     FROM usuario
     WHERE usuario = ? AND contra = ? AND id_tipo_usuario = ?"
);
$stmt->bind_param("ssi", $usuario, $password, $cargo);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

// si los datos son correctos
if ($fila) {
    // genera un identificador de sesion nuevo (mas seguro)
    session_regenerate_id(true);

    // guarda los datos del usuario en la sesion
    $_SESSION["usuario"] = $fila["usuario"];
    $_SESSION["rol"] = (string)$fila["id_tipo_usuario"];

    // manda al menu
    header("Location: menu.php");
    exit();
}

// datos incorrectos: vuelve al inicio y muestra la ventana de error
header("Location: ../index.php?error=1");
exit();
?>