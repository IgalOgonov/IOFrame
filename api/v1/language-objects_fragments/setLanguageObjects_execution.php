<?php
//Parsing
$setInputs = [];

foreach ($inputs['objects'] as $key => $item){
    $temp = [];
    $temp['object'] = json_encode($item);
    $temp['name'] = $key;

    $arr = $APIManager::baseItemParser($temp,$LANGUAGE_OBJECTS_PARSING_MAP_DEFINITIONS['setLanguageObjects']);

    $setInputs[] = $arr;
}

$params = ['test'=>$test];
$params['overwrite'] = $inputs['overwrite'] ?? null;
$params['update'] = $inputs['update'] ?? null;

$result = $LanguageObjectHandler->setItems(
    $setInputs,
    'language-objects',
    $params
);