<?php
if(!defined('FrontEndResourceHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/FrontEndResourceHandler.php';

$FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$defaultSettingsParams);

$result = $FrontEndResourceHandler->setResources(
    [
        ['address'=>$inputs['address'],'text'=>$meta]
    ],
    ($action === 'updateImage' ? 'img' : 'vid'),
    ['test'=>$test,'update'=>true]
)[$inputs['address']];
