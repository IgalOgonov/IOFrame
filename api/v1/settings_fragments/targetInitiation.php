<?php
$metaSettings = new \IOFrame\Handlers\SettingsHandler(
    $settings->getSetting('absPathToRoot').'/localFiles/metaSettings/',
    $defaultSettingsParams
);
$targetSettingsInfo = $metaSettings->getSetting($target);
if(\IOFrame\Util\PureUtilFunctions::is_json($targetSettingsInfo)){
    $targetSettingsInfo = json_decode($targetSettingsInfo,true);
    if($targetSettingsInfo['local']??false){
        $defaultSettingsParams['opMode'] = \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL;
        $defaultSettingsParams['useCache'] = false;
        $defaultSettingsParams['RedisManager'] = null;
    }
    if($targetSettingsInfo['base64']??false)
        $defaultSettingsParams['base64Storage'] = true;
}

$defaultSettingsParams['initiate'] = true;

$targetSettings = new \IOFrame\Handlers\SettingsHandler(
    $rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$target.'/',
    $defaultSettingsParams
);