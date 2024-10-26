<?php

if(!isset($settings))
    require_once 'initiate_local_settings.php';
if(!isset($defaultSettingsParams))
    require_once 'initiate_default_settings_params.php';

session_start();
$SessionManager = new \IOFrame\Managers\SessionManager($settings,$defaultSettingsParams);