<?php

if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';
if(!defined('UserHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/UserHandler.php';
if(!defined('IPHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/IPHandler.php';
if(!defined('SecurityHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/SecurityHandler.php';


//We need to check whether the current IP is blacklisted
$IPHandler = new \IOFrame\Handlers\IPHandler(
    $settings,
    array_merge($defaultSettingsParams,['siteSettings'=>$siteSettings])
);

//IP check
if($IPHandler->checkIP(['test'=>$test]))
    exit(SECURITY_FAILURE);

if(!isset($userSettings))
    $userSettings = new IOFrame\Handlers\SettingsHandler(
        $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/userSettings/',
        $defaultSettingsParams
    );


//If regular login is not allowed, return 4 (login type not allowed).
if($userSettings->getSetting('allowRegularLogin') != 1){
    if($test)
        echo 'Logging through this API is not allowed!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}


if( $inputs["userID"]!=null && $userSettings->getSetting('rememberMe') < 1){
    if($test)
        echo 'Cannot log in automatically when rememberMe server setting is less then 1! Don\'t post userID!'.EOL;
    exit(AUTHENTICATION_FAILURE);
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
        exit(INPUT_VALIDATION_FAILURE);
    }
    else{
        $m=$inputs["m"];
        ($inputs["log"]!= 'temp')? $p = $inputs["p"] : $sesKey = $inputs["sesKey"];
        //Validate Username
        if(!filter_var($m, FILTER_VALIDATE_EMAIL)){
            $res=false;
            if($test)
                echo 'Email illegal.';
            exit(INPUT_VALIDATION_FAILURE);
        }
        //Validate Password
        else if( $inputs["log"]!= 'temp' && !IOFrame\Util\validator::validatePassword($p)){
            if($test)
                echo 'Password illegal.';
            exit(INPUT_VALIDATION_FAILURE);
        }
        //If this is a temp login, check if sesKey is valid
        else if( ($inputs["log"]== 'temp')
            &&( preg_match_all('/[a-f]|[0-9]/',$inputs["sesKey"])!=strlen($inputs["sesKey"])
                || strlen($inputs["sesKey"])>64 //64 will always be the length, unless you increase the sesID length
            )
        ){
            if($test)
                echo 'Session key illegal.';
            exit(INPUT_VALIDATION_FAILURE);
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
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}

if(!isset($UserHandler))
    $UserHandler = new IOFrame\Handlers\UserHandler(
        $settings,
        $defaultSettingsParams
    );

//Check if the user is eligible to log in
if($inputs["log"]!='out')
    if ($UserHandler->checkUserLogin($inputs["m"],['allowWhitelistedIP' => $IPHandler->directIP,'test'=>$test]) === 1){
        if($test)
            echo 'Suspicious user activity - cannot login without 2FA or whitelisting the IP!'.EOL;
        exit(SECURITY_FAILURE);
    }
