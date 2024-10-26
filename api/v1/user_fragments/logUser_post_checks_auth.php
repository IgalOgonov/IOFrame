<?php


//We need to check whether the current IP is blacklisted
$IPHandler = new \IOFrame\Handlers\IPHandler(
    $settings,
    array_merge($defaultSettingsParams,['siteSettings'=>$siteSettings])
);

if(!isset($UsersHandler))
    $UsersHandler = new \IOFrame\Handlers\UsersHandler(
        $settings,
        $defaultSettingsParams
    );