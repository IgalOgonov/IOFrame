<?php
require __DIR__ . '/../../IOFrame/Handlers/SecurityHandler.php';
$SecurityHandler = new \IOFrame\Handlers\SecurityHandler($settings,$defaultSettingsParams);

$result = $SecurityHandler->getEventsMeta($inputs['inputs'],['limit'=>$inputs['limit'],'offset'=>$inputs['offset'],'test'=>$test]);