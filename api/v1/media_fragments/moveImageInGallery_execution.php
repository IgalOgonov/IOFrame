<?php


//Handlers
$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);

$result = $FrontEndResources->moveFrontendResourceCollectionOrder(
    $inputs['from'],
    $inputs['to'],
    $inputs['gallery'],
    ($action === 'moveImageInGallery' ? 'img':'vid'),
    ['test'=>$test]
);