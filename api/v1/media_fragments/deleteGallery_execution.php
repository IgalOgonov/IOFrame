<?php


//Handlers
$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);

$result = $FrontEndResources->deleteFrontendResourceCollection(
    $inputs['gallery'],
    ($action === 'deleteGallery' ? 'img':'vid'),
    [ 'test'=>$test]
);
