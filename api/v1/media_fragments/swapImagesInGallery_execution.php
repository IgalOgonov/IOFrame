<?php


//Handlers
$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);

$result = $FrontEndResources->swapFrontendResourceCollectionOrder(
    $inputs['num1'],
    $inputs['num2'],
    $inputs['gallery'],
    ($action === 'swapImagesInGallery' ? 'img':'vid'),
    ['test'=>$test]
);