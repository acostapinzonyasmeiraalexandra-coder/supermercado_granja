<?php

// los errores de mysql se manejan como excepciones
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// direccion del servidor de la base de datos
$servername = "localhost";
// usuario de la base de datos
$username = "root";
// contrasena de la base de datos
$password = "";
// nombre de la base de datos
$dbname = "supermercado";

// crear conexion
$conn = new mysqli($servername, $username, $password, $dbname);

// letras con tilde y la ñ
$conn->set_charset("utf8mb4");

?>
