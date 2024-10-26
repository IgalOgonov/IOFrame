<?php

if(!isset($settings))
    require_once 'initiate_local_settings.php';

$SQLManager = new \IOFrame\Managers\SQLManager(
    $settings,
    $defaultSettingsParams
);
$defaultSettingsParams['SQLManager'] = $SQLManager;
