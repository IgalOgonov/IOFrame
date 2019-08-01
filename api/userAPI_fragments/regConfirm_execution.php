<?php

if(!defined('UserHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/UserHandler.php';

//Attempts to activate the user, if the REQUEST contains both the ID and the code
if(isset($inputs['id']) and isset($inputs['code']) ){

    if(!isset($UserHandler))
        $UserHandler = new IOFrame\Handlers\UserHandler(
            $settings,
            $defaultSettingsParams
        );

    if(!isset($pageSettings))
        $pageSettings = new IOFrame\Handlers\SettingsHandler(
            $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/pageSettings/',
            $defaultSettingsParams
        );

    $result = $UserHandler->confirmRegistration($inputs['id'], $inputs['code'],['test'=>$test]);

    if($pageSettings->getSetting('regConfirm') and !isset($inputs['async']))
        header('Location: http://'.$_SERVER['SERVER_NAME'].'/'.$pageSettings->getSetting('regConfirm').'?res='.$res);
}
else{
    if($test)
        echo 'Wrong user input!';
    $result =  '-1';
}




