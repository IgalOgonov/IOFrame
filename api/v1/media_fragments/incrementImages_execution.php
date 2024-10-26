<?php


//Handlers
$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);

$result =   $FrontEndResources->incrementResourcesVersions(
    $inputs['addresses'],
    ($action === 'incrementImages' ? 'img' : 'vid'),
    ['test'=>$test]
);