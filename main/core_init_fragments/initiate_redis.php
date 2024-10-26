<?php

if(!isset($defaultSettingsParams))
    require 'initiate_default_settings_params.php';

$redisSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/redisSettings/',['useCache'=>false]);
$RedisManager = new \IOFrame\Managers\RedisManager($redisSettings);

$defaultSettingsParams['useCache'] = $RedisManager->isInit;
$defaultSettingsParams['redisSettings'] = $redisSettings;
$defaultSettingsParams['RedisManager'] = $RedisManager;