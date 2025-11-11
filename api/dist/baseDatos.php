<?php
require_once '../models/MySQLConnection.php'; // Asegúrate de que el archivo de tu clase esté incluido


function secureQuery($sql, $params = [])
{
    try {
        $db = new MySQLConnection();
        $conn = $db->getConnection();

        if (!$conn) {
            throw new Exception("No se pudo establecer la conexión con la base de datos.");
        }

        // Validar que la consulta sea solo SELECT, INSERT, UPDATE o DELETE
        $permitidos = ['SELECT', 'INSERT', 'UPDATE'];
        $primerPalabra = strtoupper(strtok(trim($sql), ' '));

        if (!in_array($primerPalabra, $permitidos)) {
            throw new Exception("Tipo de consulta no permitida.");
        }

        // Ejecutar la consulta usando la función segura de la clase
        $resultado = $db->query($sql, $params);

        // Si es una consulta SELECT, devolver los datos en array asociativo
        if ($resultado instanceof mysqli_result) {
            $datos = [];
            while ($fila = $resultado->fetch_assoc()) {
                $datos[] = $fila;
            }
            $db->closeConnection();
            return $datos;
        }

        $db->closeConnection();
        return true; // Para INSERT/UPDATE/DELETE
    } catch (Exception $e) {
        error_log("Error al ejecutar consulta: " . $e->getMessage());
        return false;
    }
}


$result = secureQuery("SELECT * FROM personajes");
if (is_array($result)) {
    echo json_encode($result);
} else {
    var_dump($result);
}
