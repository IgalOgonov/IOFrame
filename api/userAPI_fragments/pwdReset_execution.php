<?php

if(!defined('userHandler'))
    require __DIR__ . '/../../handlers/userHandler.php';

if(!isset($userHandler))
    $userHandler = new IOFrame\userHandler(
        $settings,
        $defaultSettingsParams
    );

//Attempts to send a mail to the user requiring password reset.
if($inputs['mail'] !== null){
    $result = $userHandler->pwdResetSend($inputs['mail'],['test'=>$test]);
}

//Checks if the info provided by the user was correct, if so authorizes the Session to reset the password for a few minutes.
else if($inputs['id'] !== null and $inputs['code'] !== null ){

    $result = $userHandler->pwdResetConfirm($inputs['id'], $inputs['code'],['test'=>$test]);

    if($result){
        if(!isset($userSettings))
            $userSettings = new IOFrame\settingsHandler(
                $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/userSettings/',
                $defaultSettingsParams
            );

        if(!isset($pageSettings))
            $pageSettings = new IOFrame\settingsHandler(
                $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/pageSettings/',
                $defaultSettingsParams
            );

        if(!$test){
            $_SESSION['PWD_RESET_EXPIRES']=time()+$userSettings->getSetting('passwordResetTime')*60;
            $_SESSION['PWD_RESET_ID']=$inputs['id'];
            if($inputs['async']===null)
                header('Location: http://'.$_SERVER['SERVER_NAME'].'/'.$pageSettings->getSetting('pwdReset').'?mes=You%20%have%20%'.$userSettings->getSetting('passwordResetTime').'minutes%20%to%20%reset%20%your%20%password');
        }
        else{
            echo 'Changing PWD_RESET_EXPIRES to '.(time()+$userSettings->getSetting('passwordResetTime')*60).EOL;
            echo 'Changing PWD_RESET_ID to '.$inputs['id'].EOL;
            if($inputs['async']===null)
                echo 'Changing header location to http://'.$_SERVER['SERVER_NAME'].'/'.$pageSettings->getSetting('pwdReset').'?mes=You%20%have%20%'.$userSettings->getSetting('passwordResetTime').'minutes%20%to%20%reset%20%your%20%password'.EOL;
        }
    }
    else{
        if(!$test){
            if($inputs['async']===null)
                header('Location: http://'.$_SERVER['SERVER_NAME'].'?mes='.$result);
        }
        else{
            if($inputs['async']===null)
                echo 'Changing header location to  http://'.$_SERVER['SERVER_NAME'].'?mes='.$result.EOL;
        }
    }
}

else{
    if($test)
        echo 'Wrong user input.';
    $result = -1;
}


?>