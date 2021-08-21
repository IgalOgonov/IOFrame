<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->addFrontendResourcesToCollection(
    $inputs['addresses'],
    $inputs['gallery'],
    ($action === 'addToGallery' ? 'img':'vid'),
    ['test'=>$test]
);