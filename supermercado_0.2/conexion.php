<?php

        // esta variable almacena la direccion del servidor de la base de datos
        $servername ="localhost" ;

        // esta variable almacena el usuario de la base de datos
        $username ="root";
        // esta variable almacena la contrasena de la base de datos
        $password ="";
        // esta variable almacena el nombre de la base de datos
        $dbname ="supermercado";

        // crear conexion
        $conn =  new mysqli($servername,$username,$password,$dbname);

        // verificar conexion
        
        if($conn->connect_error){

            die("Conexion Fallida". $conn->connect_error);
        }


?>