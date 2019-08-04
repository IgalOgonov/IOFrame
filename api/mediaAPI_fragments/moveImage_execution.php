<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);


$copy = $inputs['copy'];

$result =   $FrontEndResourceHandler->moveImage(
    $inputs['oldAddress'],
    $inputs['newAddress'],
    ['test'=>$test, 'copy'=>$copy]
);