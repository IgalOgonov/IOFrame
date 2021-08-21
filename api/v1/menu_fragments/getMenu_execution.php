<?php
if(!defined('MenuHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/MenuHandler.php';
$MenuHandler = new IOFrame\Handlers\MenuHandler($settings,$defaultSettingsParams);

$result = $MenuHandler->getMenu(
    $inputs['identifier'],
    $retrieveParams
);
if(is_array($result))
    parseMenuItems($result);