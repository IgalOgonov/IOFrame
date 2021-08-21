<?php
if(!defined('MenuHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/MenuHandler.php';
$MenuHandler = new IOFrame\Handlers\MenuHandler($settings,$defaultSettingsParams);

$result =
    $MenuHandler->moveMenuBranch(
        $inputs['identifier'],
        $inputs['blockIdentifier'],
        $inputs['sourceAddress'],
        $inputs['targetAddress'],
        $setParams
    );