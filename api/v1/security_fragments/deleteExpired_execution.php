<?php
require __DIR__ . '/../../IOFrame/Handlers/IPHandler.php';
$IPHandler = new \IOFrame\Handlers\IPHandler($settings,$defaultSettingsParams);

$result = $IPHandler->deleteExpired(
    [
        'range'=>(bool)$inputs['range'],
        'test'=>$test,
    ]
);