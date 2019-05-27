<?php

if(!defined('userHandler'))
    require __DIR__.'/../../_siteHandlers/userHandler.php';

//Attempts to activate the user, if the REQUEST contains both the ID and the code
if(isset($inputs['id']) and isset($inputs['code']) ){

    if(!isset($userHandler))
        $userHandler = new IOFrame\userHandler(
            $settings,
            $defaultSettingsParams
        );

    if(!isset($pageSettings))
        $pageSettings = new IOFrame\settingsHandler(
            $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/pageSettings/',
            $defaultSettingsParams
        );

    $result = $userHandler->confirmRegistration($inputs['id'], $inputs['code'],['test'=>$test]);

    if($pageSettings->getSetting('mailConfirmedPage')!='' and !isset($inputs['async']))
        header('Location: http://'.$_SERVER['SERVER_NAME'].'/'.$pageSettings->getSetting('mailConfirmedPage').'?res='.$res);
}
else{
    if($test)
        echo 'Wrong user input!';
    $result =  '-1';
}




