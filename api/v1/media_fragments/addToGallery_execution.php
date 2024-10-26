<?php

//Handlers
$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);

$result = $FrontEndResources->addFrontendResourcesToCollection(
    $inputs['addresses'],
    $inputs['gallery'],
    ($action === 'addToGallery' ? 'img':'vid'),
    ['test'=>$test]
);