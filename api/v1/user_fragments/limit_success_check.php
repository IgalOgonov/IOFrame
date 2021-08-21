<?php

if(!defined('SecurityHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/SecurityHandler.php';
if(!isset($SecurityHandler))
    $SecurityHandler = new IOFrame\Handlers\SecurityHandler(
        $settings,
        $defaultSettingsParams
    );
//Limit check
if(isset($shouldCommitActions)){
    //Commit user action UNLESS this was an identifier failure
    if(isset($userId) && isset(USERS_API_LIMITS[$action]['userAction']) && $result !== 5){
        $SecurityHandler->commitEventUser(USERS_API_LIMITS[$action]['userAction'],$userId,['test'=>$test,'susOnLimit'=>USERS_API_LIMITS[$action]['susOnLimit'],'banOnLimit'=>USERS_API_LIMITS[$action]['banOnLimit']]);
    }
    //Commit IP action
    if(isset(USERS_API_LIMITS[$action]['ipAction'])){
        $SecurityHandler->commitEventIP(USERS_API_LIMITS[$action]['ipAction'],['test'=>$test,'markOnLimit'=>USERS_API_LIMITS[$action]['markOnLimit']]);
    }
}
