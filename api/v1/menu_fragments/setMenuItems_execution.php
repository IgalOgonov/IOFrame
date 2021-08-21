<?php
if(!defined('MenuHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/MenuHandler.php';
$MenuHandler = new IOFrame\Handlers\MenuHandler($settings,$defaultSettingsParams);

$result =
    $MenuHandler->setMenuItems(
        $inputs['identifier'],
        $inputs['inputs'],
        $setParams
    );