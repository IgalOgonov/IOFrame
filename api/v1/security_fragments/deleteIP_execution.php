<?php
require __DIR__ . '/../../IOFrame/Handlers/IPHandler.php';
$IPHandler = new \IOFrame\Handlers\IPHandler($settings,$defaultSettingsParams);

$result = $IPHandler->deleteIP(
        $inputs['ip'],
        ['test'=>$test]
    );