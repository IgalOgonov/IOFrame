<?php

if($_REQUEST["target"] == 'localSettings' || $_REQUEST["target"] == 'redisSettings' || $_REQUEST["target"] == 'sqlSettings')
    $defaultSettingsParams['opMode'] = \IOFrame\SETTINGS_OP_MODE_LOCAL;
if($_REQUEST["target"] == 'localSettings' || $_REQUEST["target"] == 'redisSettings'){
    $defaultSettingsParams['useCache'] = false;
    $defaultSettingsParams['redisHandler'] = null;
}
$defaultSettingsParams['initiate'] = true;

$targetSettings = new IOFrame\settingsHandler(
    $rootFolder.'/'.SETTINGS_DIR_FROM_ROOT.'/'.$_REQUEST["target"].'/',
    $defaultSettingsParams
);