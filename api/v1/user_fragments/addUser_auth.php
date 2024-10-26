<?php

if(!isset($userSettings))
    $userSettings = new \IOFrame\Handlers\SettingsHandler(
        $settings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/userSettings/',
        $defaultSettingsParams
    );

//Whether regular login is not allowed
if($userSettings->getSetting('allowRegularReg') != 1){
    if($test)
        echo('Registration through this API is not allowed!'.EOL);
    exit(AUTHENTICATION_FAILURE);
}

//Check if self registration is allowed, user is admin, or user has the appropriate action:
if (
!(
    $userSettings->getSetting('selfReg')!=0 ||
    $auth->isAuthorized() ||
    $auth->hasAction('REGISTER_USER_AUTH') ||
    isset($inputs['token'])
)
){
    if($test)
        echo "User must be authorized to register new users".EOL;
    exit(AUTHENTICATION_FAILURE);
}
