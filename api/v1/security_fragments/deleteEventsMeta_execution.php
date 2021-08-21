<?php
require __DIR__ . '/../../IOFrame/Handlers/SecurityHandler.php';
$SecurityHandler = new \IOFrame\Handlers\SecurityHandler($settings,$defaultSettingsParams);

$result = $SecurityHandler->deleteEventsMeta($inputs['inputs'],['test'=>$test]);