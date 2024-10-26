<?php
$MenuHandler = new \IOFrame\Handlers\MenuHandler($settings,$defaultSettingsParams);

$result =
    $MenuHandler->moveMenuBranch(
        $inputs['identifier'],
        $inputs['blockIdentifier'],
        $inputs['sourceAddress'],
        $inputs['targetAddress'],
        $setParams
    );