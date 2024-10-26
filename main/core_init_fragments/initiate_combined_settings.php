<?php

if(!isset($rootFolder))
    require_once 'initiate_path_to_root.php';
if(!isset($defaultSettingsParams))
    require 'initiate_default_settings_params.php';

$combinedSettings = new \IOFrame\Handlers\SettingsHandler(
    [
        $rootFolder.'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
        $rootFolder.'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/resourceSettings/',
        $rootFolder.'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/logSettings/'
    ],
    $defaultSettingsParams
);

$siteSettings = clone $combinedSettings;
$resourceSettings = clone $combinedSettings;
$logSettings = clone $combinedSettings;
$siteSettings->keepSettings('siteSettings');
$resourceSettings->keepSettings('resourceSettings');
$logSettings->keepSettings('logSettings');
$defaultSettingsParams['siteSettings'] = $siteSettings;
$defaultSettingsParams['resourceSettings'] = $resourceSettings;
$defaultSettingsParams['logSettings'] = $logSettings;