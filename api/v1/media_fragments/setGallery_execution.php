<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->setFrontendResourceCollection(
    $inputs['gallery'],
    ($action === 'setGallery' ? 'img':'vid'),
    $meta,
    [ 'test'=>$test,'update'=>$inputs['update'],'overwrite'=>$inputs['overwrite'] ]
);