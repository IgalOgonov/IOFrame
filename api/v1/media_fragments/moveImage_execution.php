<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);


$copy = $inputs['copy'];

$result = $inputs['remote'] ?
    $FrontEndResourceHandler->renameResource(
        $inputs['oldAddress'],
        $inputs['newAddress'],
        ($action === 'moveImage' ? 'img' : 'vid'),
        ['test'=>$test, 'copy'=>$copy]
    ):
    $FrontEndResourceHandler->moveFrontendResourceFile(
        $inputs['oldAddress'],
        $inputs['newAddress'],
        ($action === 'moveImage' ? 'img' : 'vid'),
        ['test'=>$test, 'copy'=>$copy]
    )
    ;