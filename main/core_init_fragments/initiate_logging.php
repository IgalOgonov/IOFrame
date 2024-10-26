<?php
require_once __DIR__ . '/../../vendor/autoload.php';

if(!isset($defaultSettingsParams))
    require_once 'initiate_default_settings_params.php';
if(!isset($settings))
    require_once 'initiate_local_settings.php';
if(!isset($logSettings))
    require_once 'initiate_combined_settings.php';

$defaultSettingsParams['logHandler'] = new \IOFrame\Managers\Integrations\Monolog\IOFrameHandler($settings, ['opMode'=>'local','level'=>$logSettings->getSetting('logStatus')??\Monolog\Logger::NOTICE]);