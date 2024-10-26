<?php
$MenuHandler = new \IOFrame\Handlers\MenuHandler($settings,$defaultSettingsParams);

$result =
    $MenuHandler->setMenuItems(
        $inputs['identifier'],
        $inputs['inputs'],
        $setParams
    );