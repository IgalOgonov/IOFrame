<?php

if(!defined('UserHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/UserHandler.php';

if(!isset($UserHandler))
    $UserHandler = new IOFrame\Handlers\UserHandler(
        $settings,
        $defaultSettingsParams
    );

//Attempts to send a mail to the user requiring password reset.
if(isset($inputs['mail'])){
    $result = $UserHandler->pwdResetSend($inputs['mail'],['async' => false,'language'=>$inputs['language'],'test'=>$test]);
}
//Checks if the info provided by the user was correct, if so authorizes the Session to reset the password for a few minutes.
else if($inputs['id'] !== null and $inputs['code'] !== null ){

    $result = $UserHandler->pwdResetConfirm($inputs['id'], $inputs['code'],['test'=>$test]);
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
            $_SESSION['PWD_RESET_EXPIRES']=time()+$userSettings->getSetting('passwordResetTime')*60;
            $_SESSION['PWD_RESET_ID']=$inputs['id'];
        }
        else{
            echo 'Changing PWD_RESET_EXPIRES to '.(time()+$userSettings->getSetting('passwordResetTime')*60).EOL;
            echo 'Changing PWD_RESET_ID to '.$inputs['id'].EOL;
        }
    }

    if(!isset($inputs['async'])  && $pageSettings->getSetting('pwdReset')){
        if(!$test)
            header('Location: http://'.$_SERVER['SERVER_NAME'].'/'.$settings->getSetting('pathToRoot').$pageSettings->getSetting('pwdReset').'?res='.$result);

        else
            echo 'Changing header location to http://'.$_SERVER['SERVER_NAME'].'/'.$settings->getSetting('pathToRoot').$pageSettings->getSetting('pwdReset').'?res='.$result.EOL;
    }
}
else{
    if($test)
        echo 'Wrong user input.';
    $result = -1;
}


?>