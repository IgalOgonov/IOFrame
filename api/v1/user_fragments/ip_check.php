<?php

if(!defined('IPHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/IPHandler.php';

//We need to check whether the current IP is blacklisted
if(!isset($IPHandler))
    $IPHandler = new \IOFrame\Handlers\IPHandler(
        $settings,
        array_merge($defaultSettingsParams,['siteSettings'=>$siteSettings])
    );

//IP check
if($IPHandler->checkIP(['test'=>$test]))
    exit(SECURITY_FAILURE);
else
    $ip = $IPHandler->directIP;