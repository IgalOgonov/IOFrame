<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$ResourceHandler = new IOFrame\Handlers\ResourceHandler($settings,$defaultSettingsParams);

$result =  $ResourceHandler->getCollectionsOfResource(
    $inputs['address'],
    ($action === 'getGalleriesOfImage' ? 'img' : 'vid'),
    ['test'=>$test]
);