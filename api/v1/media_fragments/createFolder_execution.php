<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->createFolder(
    $inputs['relativeAddress'],
    $inputs['name'],
    $inputs['category'],
    ['test'=>$test]
);
