<?php
require __DIR__ . '/../../IOFrame/Handlers/IPHandler.php';
$IPHandler = new \IOFrame\Handlers\IPHandler($settings,$defaultSettingsParams);

$result = (
$action === 'addIPRange' ?
    $IPHandler->addIPRange(
        $inputs['prefix'],
        $inputs['from'],
        $inputs['to'],
        (bool)$inputs['type'],
        $inputs['ttl'],
        ['test'=>$test]
    )
    :
    $IPHandler->updateIPRange(
        $inputs['prefix'],
        $inputs['from'],
        $inputs['to'],
        [
            'from'=>$inputs['newFrom'],
            'to'=>$inputs['newTo'],
            'type'=>$inputs['type'],
            'ttl'=>$inputs['ttl'],
            'test'=>$test
        ]
    )
);