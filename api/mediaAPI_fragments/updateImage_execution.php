<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/FrontEndResourceHandler.php';

$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->setResources(
    [
        [$inputs['address'],null,null,$meta]
    ],
    'img',
    ['test'=>true,'verbose'=>true,'update'=>true]
)[$inputs['address']];
