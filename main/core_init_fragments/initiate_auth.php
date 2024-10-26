<?php

if(!isset($settings))
    require_once 'initiate_local_settings.php';
if(!isset($defaultSettingsParams))
    require_once 'initiate_default_settings_params.php';

$auth = new \IOFrame\Handlers\AuthHandler($settings,$defaultSettingsParams);
$defaultSettingsParams['AuthHandler'] = $auth;