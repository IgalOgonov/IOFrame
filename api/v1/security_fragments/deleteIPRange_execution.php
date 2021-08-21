<?php
require __DIR__ . '/../../IOFrame/Handlers/IPHandler.php';
$IPHandler = new \IOFrame\Handlers\IPHandler($settings,$defaultSettingsParams);

$result = $IPHandler->deleteIPRange(
    $inputs['prefix'],
    $inputs['from'],
    $inputs['to'],
    ['test'=>$test]
);