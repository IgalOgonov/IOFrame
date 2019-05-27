<?php

if(!defined('validator'))
    require __DIR__.'/../../_util/validator.php';
if(!defined('userHandler'))
    require __DIR__.'/../../_siteHandlers/userHandler.php';
if(!defined('IPHandler'))
    require __DIR__.'/../../_siteHandlers/IPHandler.php';
if(!defined('securityHandler'))
    require __DIR__.'/../../_siteHandlers/securityHandler.php';

//No need to do extra work
if(!$auth->isLoggedIn())
    if($inputs["log"] == 'out')
        exit(0);

//We need to check whether the current IP is blacklisted
$IPHandler = new \IOFrame\IPHandler(
    $settings,
    array_merge($defaultSettingsParams,['siteSettings'=>$siteSettings])
);

//IP check
if($IPHandler->checkIP(['test'=>$test]))
    exit('-3');

if(!isset($userSettings))
    $userSettings = new IOFrame\settingsHandler(
        $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/userSettings/',
        $defaultSettingsParams
    );


//If regular login is not allowed, return 4 (login type not allowed).
if($userSettings->getSetting('allowRegularLogin') != 1){
    if($test)
        echo 'Logging through this API is not allowed!'.EOL;
    exit('-2');
}


if( $inputs["userID"]!=null && $userSettings->getSetting('rememberMe') < 1){
    if($test)
        echo 'Cannot log in automatically when rememberMe server setting is less then 1! Don\'t post userID!'.EOL;
    exit('-2');
}

//If this is a log out request no input is required
if(!$inputs["log"]=='out'){
    //Check for missing input first.
    if( $inputs["m"]!=null  ||
        ($inputs["log"]!= 'temp' && $inputs["p"] === null) ||
        ( $inputs["log"]== 'temp' && ( $inputs["sesKey"] === null || $inputs["userID"] === null ) ) ||
        ( $userSettings->getSetting('rememberMe') == 2 && $inputs["userID"] === null )
    ){
        if($test)
            echo 'Missing input parameters.';
        exit('-1');
    }
    else{
        $m=$inputs["m"];
        ($inputs["log"]!= 'temp')? $p = $inputs["p"] : $sesKey = $inputs["sesKey"];
        //Validate Username
        if(!filter_var($m, FILTER_VALIDATE_EMAIL)){
            $res=false;
            if($test)
                echo 'Email illegal.';
            exit('-1');
        }
        //Validate Password
        else if( $inputs["log"]!= 'temp' && !IOFrame\validator::validatePassword($p)){
            if($test)
                echo 'Password illegal.';
            exit('-1');
        }
        //If this is a temp login, check if sesKey is valid
        else if( ($inputs["log"]== 'temp')
            &&( preg_match_all('/[a-f]|[0-9]/',$inputs["sesKey"])!=strlen($inputs["sesKey"])
                || strlen($inputs["sesKey"])>64 //64 will always be the length, unless you increase the sesID length
            )
        ){
            if($test)
                echo 'Session key illegal.';
            exit('-1');
        }
        //If this is a temp login, check if user idenfication key is correct
        else if(
            ($inputs["log"]== 'temp' ||  $userSettings->getSetting('rememberMe') == 2)
            &&(
                preg_match_all('/[0-9]|[a-z]/',$inputs["userID"])!=strlen($inputs["userID"]) ||
                preg_match_all('/[0-9]|[a-z]/',$inputs["userID"])>32
            )
        ){
            if($test)
                echo 'UserID illegal.';
            exit('-1');
        }
    }
}

if(!isset($userHandler))
    $userHandler = new IOFrame\userHandler(
        $settings,
        $defaultSettingsParams
    );

//Check if the user is eligible to log in
if($inputs["log"]!='out')
    if ($userHandler->checkUserLogin($inputs["m"],['allowWhitelistedIP' => $IPHandler->directIP],false) == 1){
        if($test)
            if($test)
                echo 'Suspicious user activity - cannot login without 2FA or whitelisting the IP!'.EOL;
        exit('-4');
    }
