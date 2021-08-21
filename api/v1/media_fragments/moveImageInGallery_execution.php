<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->moveFrontendResourceCollectionOrder(
    $inputs['from'],
    $inputs['to'],
    $inputs['gallery'],
    ($action === 'moveImageInGallery' ? 'img':'vid'),
    ['test'=>$test]
);