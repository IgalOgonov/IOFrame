<?php


//Handlers
$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);


$copy = $inputs['copy'];

$result = $inputs['remote'] ?
    $FrontEndResources->renameResource(
        $inputs['oldAddress'],
        $inputs['newAddress'],
        ($action === 'moveImage' ? 'img' : 'vid'),
        ['test'=>$test, 'copy'=>$copy]
    ):
    $FrontEndResources->moveFrontendResourceFile(
        $inputs['oldAddress'],
        $inputs['newAddress'],
        ($action === 'moveImage' ? 'img' : 'vid'),
        ['test'=>$test, 'copy'=>$copy]
    )
    ;