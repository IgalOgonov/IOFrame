<?php
$MenuHandler = new \IOFrame\Handlers\MenuHandler($settings,$defaultSettingsParams);

$result = $MenuHandler->setItems(
    $inputs['inputs'],
    'menus',
    $setParams
);