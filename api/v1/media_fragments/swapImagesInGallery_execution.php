<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->swapFrontendResourceCollectionOrder(
    $inputs['num1'],
    $inputs['num2'],
    $inputs['gallery'],
    ($action === 'swapImagesInGallery' ? 'img':'vid'),
    ['test'=>$test]
);