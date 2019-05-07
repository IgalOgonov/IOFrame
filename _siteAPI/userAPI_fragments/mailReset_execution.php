<?php

if(!defined('userHandler'))
    require __DIR__.'/../../_siteHandlers/userHandler.php';

if(!isset($userHandler))
    $userHandler = new IOFrame\userHandler($settings,['sqlHandler'=>$sqlHandler,'logger'=>$logger,'redisHandler'=>$redisHandler]);

//Attempts to send a mail to the user requiring password reset.
if(isset($inputs['mail'])){
    $result = $userHandler->mailChangeSend($inputs['mail'],$test);
}

//Checks if the info provided by the user was correct, if so authorizes the Session to reset the mail for a few minutes.
//For now, depends on password reset time - due to it making sense (both are sensitive information with similar weight)
else if(isset($inputs['id']) and isset($inputs['code']) ){
    $result = $userHandler->mailChangeConfirm($inputs['id'], $inputs['code'],$test);

    if( $result == 0 ){
        $pageSettings = new IOFrame\settingsHandler(
            $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/pageSettings/',
            $defaultSettingsParams
        );
        if(!$test){
            $_SESSION['MAIL_CHANGE_EXPIRES']=time()+$userHandler->userSettings->getSetting('passwordResetTime')*60;
            $_SESSION['MAIL_CHANGE_ID']=$inputs['id'];
            if(!isset($inputs['async']))
                header('Location: http://'.$_SERVER['SERVER_NAME'].'/'.$pageSettings->getSetting('mailChange').'?mes=You%20%have%20%'.$userHandler->userSettings->getSetting('passwordResetTime').'minutes%20%to%20%reset%20%your%20%mail');
        }
        else{
            echo 'Changing MAIL_CHANGE_EXPIRES to '.(time()+$userHandler->userSettings->getSetting('passwordResetTime')*60).EOL;
            echo 'Changing MAIL_CHANGE_ID to '.$inputs['id'].EOL;
            if(!isset($inputs['async']))
                echo 'Changing header location to http://'.$_SERVER['SERVER_NAME'].'/'.$pageSettings->getSetting('mailChange').'?mes=You%20%have%20%'.$userHandler->userSettings->getSetting('passwordResetTime').'minutes%20%to%20%reset%20%your%20%mail'.EOL;
        }
    }
    else{
        if(!$test){
            if(!isset($inputs['async']))
                header('Location: http://'.$_SERVER['SERVER_NAME'].'?mes='.$result);
        }
        else{
            if(!isset($inputs['async']))
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