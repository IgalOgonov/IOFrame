<?php


//Handlers
$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);

$result = $FrontEndResources->setFrontendResourceCollection(
    $inputs['gallery'],
    ($action === 'setGallery' ? 'img':'vid'),
    $meta,
    [ 'test'=>$test,'update'=>$inputs['update'],'overwrite'=>$inputs['overwrite'] ]
);