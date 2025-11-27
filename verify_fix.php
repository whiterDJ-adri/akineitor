<?php
require_once __DIR__ . '/api/modules/algorithm/AttributeMapper.php';

use Modules\Algorithm\AttributeMapper;

// Mock character data
$trunks = ['name' => 'trunks', 'description' => 'hijo de vegeta', 'race' => 'saiyan'];
$bulma = ['name' => 'bulma', 'description' => 'cientifica', 'race' => 'human'];
$gotenks = ['name' => 'gotenks', 'description' => 'fusión de goten y trunks', 'race' => 'saiyan'];

$question = '¿Puede formar parte de una fusión?';

echo "Testing Fusion Logic:\n";
echo "Trunks: " . AttributeMapper::getAnswer($trunks, $question) . " (Expected: si)\n";
echo "Bulma: " . AttributeMapper::getAnswer($bulma, $question) . " (Expected: no)\n";
echo "Gotenks: " . AttributeMapper::getAnswer($gotenks, $question) . " (Expected: si)\n";
