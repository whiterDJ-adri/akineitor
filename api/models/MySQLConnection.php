<?php

class MySQLConnection
{
    private $host = "51.44.255.228";
    private $port = 3307;
    private $username = "akinator";
    private $password = "Hzk0EYeOHK42242@"; // Replace with your MySQL password
    private $database = "akinator"; // Replace with your database name
    private $connection;

    public function __construct()
    {
        try {
            $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database, $this->port);

            if ($this->connection->connect_error) {
                throw new Exception("Error de conexión a MySQL: " . $this->connection->connect_error);
            }
            // echo "Conexión a MySQL exitosa!";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        if (!$this->connection) {
            throw new Exception("No hay conexión a la base de datos.");
        }

        $stmt = $this->connection->prepare($sql);

        if ($stmt === false) {
            throw new Exception("Error al preparar la consulta: " . $this->connection->error);
        }

        if (!empty($params)) {
            $types = str_repeat('s', count($params)); // Assuming all parameters are strings for simplicity. Adjust as needed.
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    }

    public function closeConnection()
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
