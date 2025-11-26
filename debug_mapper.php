<?php

require_once __DIR__ . '/api/modules/algorithm/AttributeMapper.php';

$gokuData = [
    'id' => 1,
    'name' => 'Goku',
    'race' => 'Saiyan',
    'gender' => 'Male',
    'affiliation' => 'Z Fighter',
    'description' => 'El protagonista de la serie...',
    'ki' => '60.000.000',
    'maxKi' => '90 Septillion',
    'image' => '...'
];

$questions = [
    '¿Es de raza Saiyan?',
    '¿Pertenece a la raza Saiyan?',
    '¿Forma parte de los Guerreros Z?',
    '¿Es mujer?',
    '¿Es un villano?',
    '¿Aparece en Dragon Ball Super?',
    '¿Tiene una relación directa con los Dioses de la Destrucción?' // Question 221
];

foreach ($questions as $q) {
    $ans = \Modules\Algorithm\AttributeMapper::getAnswer($gokuData, $q);
    echo "Q: '$q' -> A: '$ans'\n";
}
