<?php
require_once __DIR__ . '/api/models/MySQLConnection.php';

$db = new MySQLConnection();
$res = $db->query("SELECT * FROM personajes LIMIT 5");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

$countRes = $db->query("SELECT COUNT(*) as c FROM personajes");
$count = $countRes->fetch_assoc()['c'];
echo "Total characters: $count\n";
