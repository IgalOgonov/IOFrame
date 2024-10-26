<?php


//Handlers
$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);

$result = $FrontEndResources->createFolder(
    $inputs['relativeAddress'],
    $inputs['name'],
    $inputs['category'],
    ['test'=>$test]
);
