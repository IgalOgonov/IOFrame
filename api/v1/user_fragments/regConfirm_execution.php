<?php


//Attempts to activate the user, if the REQUEST contains both the ID and the code
if(isset($inputs['id']) && isset($inputs['code']) ){

    if(!isset($UsersHandler))
        $UsersHandler = new \IOFrame\Handlers\UsersHandler(
            $settings,
            $defaultSettingsParams
        );

    if(!isset($pageSettings))
        $pageSettings = new \IOFrame\Handlers\SettingsHandler(
            $settings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/pageSettings/',
            $defaultSettingsParams
        );

    $result = $UsersHandler->confirmRegistration($inputs['id'], $inputs['code'],['test'=>$test]);

    if($pageSettings->getSetting('regConfirm') && !isset($inputs['async'])){

        if(!$test)
            header('Location: https://' .$_SERVER['SERVER_NAME'].'/'.$settings->getSetting('pathToRoot').$pageSettings->getSetting('regConfirm').'?res='.$result);
        else
            echo 'Changing header location to https://' .$_SERVER['SERVER_NAME'].'/'.$settings->getSetting('pathToRoot').$pageSettings->getSetting('regConfirm').'?res='.$result.EOL;
    }
}
elseif(isset($inputs['mail']) ){
    if(!isset($UsersHandler))
        $UsersHandler = new \IOFrame\Handlers\UsersHandler(
            $settings,
            $defaultSettingsParams
        );

    if(!isset($userSettings))
        $userSettings = new \IOFrame\Handlers\SettingsHandler(
            $settings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/userSettings/',
            $defaultSettingsParams
        );

    if(!$userSettings->getSetting('regConfirmMail')) {
        if($test)
            echo 'Email activation not required on this system!';
        $result =  '1';
    }
    else{
        $result = $UsersHandler->accountActivation($inputs['mail'], null, ['async' => false,'language'=>$inputs['language'],'test'=>$test]);
    }
}
else{
    if($test)
        echo 'Wrong user input!';
    $result =  '-1';
}




