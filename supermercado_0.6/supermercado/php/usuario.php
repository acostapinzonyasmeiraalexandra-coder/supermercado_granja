<?php

include_once(__DIR__ . "/sesion.php");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// carga la conexion de la carpeta php
include_once(__DIR__ . "/conexion.php");

// verifica que exista la conexion
if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log("No se encontro la conexion mysqli en conexion.php");
    header("Location: ../index.php?error=2");
    exit();
}

// verifica que venga del formulario
if (!isset($_POST["ingresar"])) {
    header("Location: ../index.php");
    exit();
}

// recibe los datos
$cargo = (int)($_POST["cargo"] ?? 0);
$usuario = trim((string)($_POST["usuario"] ?? ""));
$contra = (string)($_POST["contra"] ?? "");

try {

    if ($usuario === "" || $contra === "" || !in_array($cargo, [1, 2], true)) {
        throw new InvalidArgumentException("Datos incompletos");
    }

    // busca el usuario y el cargo seleccionado
    $stmt = $conn->prepare(
        "SELECT nombre, usuario, contra, id_tipo_usuario
        FROM usuario
        WHERE usuario = ? AND id_tipo_usuario = ?
        LIMIT 1"
    );

    $stmt->bind_param("si", $usuario, $cargo);
    $stmt->execute();
    $stmt->bind_result($nombre, $usuarioGuardado, $contraGuardada, $cargoGuardado);

    $fila = null;

    if ($stmt->fetch()) {
        $fila = [
            "nombre" => $nombre,
            "usuario" => $usuarioGuardado,
            "contra" => $contraGuardada,
            "id_tipo_usuario" => $cargoGuardado
        ];
    }

    $stmt->close();

    $acceso = false;

    if ($fila) {

        // comprueba primero las contrasenas protegidas con password_hash
        $info = password_get_info($fila["contra"]);

        if (!empty($info["algo"])) {
            $acceso = password_verify($contra, $fila["contra"]);

            // si el hash usa un algoritmo o costo anterior, se actualiza al actual
            if ($acceso && password_needs_rehash($fila["contra"], PASSWORD_DEFAULT)) {
                try {
                    $nuevaContra = password_hash($contra, PASSWORD_DEFAULT);
                    $actualizar = $conn->prepare(
                        "UPDATE usuario SET contra = ? WHERE usuario = ? AND id_tipo_usuario = ?"
                    );
                    $actualizar->bind_param("ssi", $nuevaContra, $usuario, $cargo);
                    $actualizar->execute();
                    $actualizar->close();
                } catch (mysqli_sql_exception $error) {
                    error_log("No se pudo actualizar el hash: " . $error->getMessage());
                }
            }
        } else {

            // contrasenas antiguas: texto normal o hash simple (md5, sha1, sha256)
            $guardada = (string)$fila["contra"];
            $largo = strlen($guardada);

            if (hash_equals($guardada, $contra)) {
                $acceso = true;
            } elseif ($largo === 32 && ctype_xdigit($guardada)) {
                $acceso = hash_equals(strtolower($guardada), md5($contra));
            } elseif ($largo === 40 && ctype_xdigit($guardada)) {
                $acceso = hash_equals(strtolower($guardada), sha1($contra));
            } elseif ($largo === 64 && ctype_xdigit($guardada)) {
                $acceso = hash_equals(strtolower($guardada), hash("sha256", $contra));
            }

            // protege la contrasena antigua despues de iniciar sesion
            // si la columna es muy corta no se actualiza, pero el ingreso sigue funcionando
            if ($acceso) {
                try {
                    $nuevaContra = password_hash($contra, PASSWORD_DEFAULT);

                    $actualizar = $conn->prepare(
                        "UPDATE usuario
                        SET contra = ?
                        WHERE usuario = ? AND id_tipo_usuario = ?"
                    );

                    $actualizar->bind_param("ssi", $nuevaContra, $usuario, $cargo);
                    $actualizar->execute();
                    $actualizar->close();
                } catch (mysqli_sql_exception $error) {
                    error_log("No se pudo proteger la contrasena: " . $error->getMessage());
                }
            }
        }
    }

    if (!$acceso) {
        header("Location: ../index.php?error=1");
        exit();
    }

    // guarda los datos de la sesion
    session_regenerate_id(true);
    $_SESSION["usuario"] = $fila["usuario"];
    $_SESSION["nombre"] = $fila["nombre"];
    // "rol" es la misma llave que revisan todos los modulos (1 administrador | 2 empleado)
    $_SESSION["rol"] = (string)$fila["id_tipo_usuario"];
    $_SESSION["ultima_actividad"] = time();

    header("Location: menu.php");
    exit();

} catch (mysqli_sql_exception $error) {
    error_log("Error al iniciar sesion: " . $error->getMessage());
    header("Location: ../index.php?error=2");
    exit();
}
?>
