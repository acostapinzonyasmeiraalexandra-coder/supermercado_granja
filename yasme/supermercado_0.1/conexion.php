<?php

        $servername ="localhost" ;

        $username ="root";
        $password ="";
        $dbname ="supermercado";

        //crear conexion
        $conn =  new mysqli($servername,$username,$password,$dbname);

        //verificar conexion
        
        if($conn->connect_error){

            die("Conexion Fallida". $conn->connect_error);
        }


?>