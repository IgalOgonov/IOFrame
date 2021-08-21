<?php
if(!defined('UserHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/UserHandler.php';
if(!defined('IPHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/IPHandler.php';


//We need to check whether the current IP is blacklisted
$IPHandler = new \IOFrame\Handlers\IPHandler(
    $settings,
    array_merge($defaultSettingsParams,['siteSettings'=>$siteSettings])
);

if(!isset($UserHandler))
    $UserHandler = new IOFrame\Handlers\UserHandler(
        $settings,
        $defaultSettingsParams
    );