<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result =   $FrontEndResourceHandler->incrementResourcesVersions(
    $inputs['addresses'],
    ($action === 'incrementImages' ? 'img' : 'vid'),
    ['test'=>$test]
);