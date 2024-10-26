<?php


$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);

$result = $FrontEndResources->setResources(
    [
        ['address'=>$inputs['address'],'text'=>$meta]
    ],
    ($action === 'updateImage' ? 'img' : 'vid'),
    ['test'=>$test,'update'=>true]
)[$inputs['address']];
