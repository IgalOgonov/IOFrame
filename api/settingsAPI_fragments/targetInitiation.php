<?php

if($target == 'localSettings' || $target == 'redisSettings' || $target == 'sqlSettings')
    $defaultSettingsParams['opMode'] = \IOFrame\SETTINGS_OP_MODE_LOCAL;
if($target == 'localSettings' || $target == 'redisSettings'){
    $defaultSettingsParams['useCache'] = false;
    $defaultSettingsParams['redisHandler'] = null;
}
$defaultSettingsParams['initiate'] = true;

$targetSettings = new IOFrame\settingsHandler(
    $rootFolder.'/'.SETTINGS_DIR_FROM_ROOT.'/'.$target.'/',
    $defaultSettingsParams
);