<?php

if(!isset($userSettings))
    $userSettings = new IOFrame\Handlers\SettingsHandler(
        $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/userSettings/',
        $defaultSettingsParams
    );


//If regular login is not allowed, return 4 (login type not allowed).
if($userSettings->getSetting('allowRegularLogin') != 1){
    if($test)
        echo 'Logging through this API is not allowed!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}


if( $inputs["userID"]!=null && $userSettings->getSetting('rememberMe') < 1){
    if($test)
        echo 'Cannot log in automatically when rememberMe server setting is less then 1! Don\'t post userID!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
