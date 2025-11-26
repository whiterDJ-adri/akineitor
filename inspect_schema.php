<?php
require_once __DIR__ . '/api/models/MySQLConnection.php';

$db = new MySQLConnection();
$tables = ['personajes', 'preguntas', 'personaje_pregunta'];

foreach ($tables as $table) {
    echo "Table: $table\n";
    $res = $db->query("DESCRIBE $table");
    while ($row = $res->fetch_assoc()) {
        echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "\n";
}
