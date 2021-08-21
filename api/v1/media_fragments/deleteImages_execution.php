<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

//Handlers
$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $inputs['remote'] ?
    $FrontEndResourceHandler->deleteResources(
        $inputs['addresses'],
        ($action === 'deleteImages' ? 'img' : 'vid'),
        ['test' => $test]
    ) :
    $FrontEndResourceHandler->deleteFrontendResourceFiles(
        $inputs['addresses'],
        ($action === 'deleteImages' ? 'img' : 'vid'),
        ['test' => $test]
    );