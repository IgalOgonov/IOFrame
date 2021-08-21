<?php
require __DIR__ . '/../../IOFrame/Handlers/SecurityHandler.php';
$SecurityHandler = new \IOFrame\Handlers\SecurityHandler($settings,$defaultSettingsParams);

$result = $SecurityHandler->setEventsMeta($inputs['inputs'],['update'=>$inputs['update'],'override'=>$inputs['override'],'test'=>$test]);