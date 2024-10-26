<?php


//Handlers
$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);

$result = $FrontEndResources->removeFrontendResourcesFromCollection(
    $inputs['addresses'],
    $inputs['gallery'],
    ($action === 'removeFromGallery' ? 'img':'vid'),
    ['test'=>$test]
);