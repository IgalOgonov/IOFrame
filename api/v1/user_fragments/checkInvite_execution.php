<?php

if(!defined('UserHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/UserHandler.php';

if(!isset($UserHandler))
    $UserHandler = new IOFrame\Handlers\UserHandler(
        $settings,
        $defaultSettingsParams
    );
$inputs['async'] = (bool)$inputs['async'];

$result = $UserHandler->confirmInviteToken($inputs['token'],['mail'=>$inputs['mail'],'consume'=>0,'test'=>$test]);

if(!isset($userSettings))
    $userSettings = new IOFrame\Handlers\SettingsHandler(
        $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/userSettings/',
        $defaultSettingsParams
    );

if(!isset($pageSettings))
    $pageSettings = new IOFrame\Handlers\SettingsHandler(
        $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/pageSettings/',
        $defaultSettingsParams
    );

if($result === 0){
    if(!$test){
        $_SESSION['VALID_INVITE_TOKEN']=$inputs['token'];
        if($inputs['mail'])
            $_SESSION['VALID_INVITE_MAIL']=$inputs['mail'];
    }
    else{
        echo 'Changing VALID_INVITE_TOKEN to '.$inputs['token'].EOL;
        if($inputs['mail'])
            echo 'Changing VALID_INVITE_MAIL to '.$inputs['mail'].EOL;
    }
}

if(!$inputs['async']  && $pageSettings->getSetting('registrationPage')){
    if(!$test)
        header('Location: http://'.$_SERVER['SERVER_NAME'].'/'.$settings->getSetting('pathToRoot').$pageSettings->getSetting('registrationPage').'?res='.$result);
    else
        echo 'Changing header location to http://'.$_SERVER['SERVER_NAME'].'/'.$settings->getSetting('pathToRoot').$pageSettings->getSetting('registrationPage').'?res='.$result.EOL;
}


?>