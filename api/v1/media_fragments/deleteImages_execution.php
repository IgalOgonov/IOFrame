<?php


//Handlers
$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);

$result = $inputs['remote'] ?
    $FrontEndResources->deleteResources(
        $inputs['addresses'],
        ($action === 'deleteImages' ? 'img' : 'vid'),
        ['test' => $test]
    ) :
    $FrontEndResources->deleteFrontendResourceFiles(
        $inputs['addresses'],
        ($action === 'deleteImages' ? 'img' : 'vid'),
        ['test' => $test]
    );