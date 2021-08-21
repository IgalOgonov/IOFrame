<?php

if($target == 'localSettings' || $target == 'redisSettings' || $target == 'sqlSettings')
    $defaultSettingsParams['opMode'] = \IOFrame\Handlers\SETTINGS_OP_MODE_LOCAL;
if($target == 'localSettings' || $target == 'redisSettings'){
    $defaultSettingsParams['useCache'] = false;
    $defaultSettingsParams['RedisHandler'] = null;
}
$defaultSettingsParams['initiate'] = true;

$targetSettings = new IOFrame\Handlers\SettingsHandler(
    $rootFolder.SETTINGS_DIR_FROM_ROOT.'/'.$target.'/',
    $defaultSettingsParams
);