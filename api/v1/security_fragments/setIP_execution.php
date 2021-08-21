<?php
require __DIR__ . '/../../IOFrame/Handlers/IPHandler.php';
$IPHandler = new \IOFrame\Handlers\IPHandler($settings,$defaultSettingsParams);

$result = (
    $action === 'addIP' ?
        $IPHandler->addIP($inputs['ip'],(bool)$inputs['type'],['reliable'=>(bool)$inputs['reliable'],'ttl'=>(int)$inputs['ttl'],'test'=>$test])
        :
        $IPHandler->updateIP($inputs['ip'],(bool)$inputs['type'],['reliable'=>(bool)$inputs['reliable'],'ttl'=>$inputs['ttl'],'test'=>$test])
);