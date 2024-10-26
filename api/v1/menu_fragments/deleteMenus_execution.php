<?php
$MenuHandler = new \IOFrame\Handlers\MenuHandler($settings,$defaultSettingsParams);

$result = $MenuHandler->deleteItems(
    $inputs['menus'],
    'menus',
    $deleteParams
);
