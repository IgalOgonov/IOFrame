<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->setGallery(
    $inputs['gallery'],
    $meta,
    [ 'test'=>$test,'update'=>$inputs['update'],'overwrite'=>$inputs['overwrite'] ]
);