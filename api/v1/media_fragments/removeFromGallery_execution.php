<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->removeFrontendResourcesFromCollection(
    $inputs['addresses'],
    $inputs['gallery'],
    ($action === 'removeFromGallery' ? 'img':'vid'),
    ['test'=>$test]
);