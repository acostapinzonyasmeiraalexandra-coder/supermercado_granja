<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "supermercado";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Para que las tildes y la ñ se guarden y se vean bien
$conn->set_charset("utf8mb4");

?>
