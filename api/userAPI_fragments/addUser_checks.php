<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

if(!isset($userSettings))
    $userSettings = new IOFrame\Handlers\SettingsHandler(
        $settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/userSettings/',
        $defaultSettingsParams
    );

//If regular login is not allowed, return 4 (login type not allowed).
if($userSettings->getSetting('allowRegularReg') != 1){
    if($test)
        echo('Registration through this API is not allowed!'.EOL);
    exit(AUTHENTICATION_FAILURE);
}

//Make sure a username is specified if the settings require it.
if( ($userSettings->getSetting('usernameChoice') == 0) && $inputs["u"] === null ){
    if($test)
        echo('Username must be specified as per usernameChoice'.EOL);
    exit(INPUT_VALIDATION_FAILURE);
}

//If a username is specified despite the settings, lose it.
if( ($userSettings->getSetting('usernameChoice') == 2) && $inputs["u"] !== null ){
    if($test)
        echo('Username not allowed as per usernameChoice, deleting it.'.EOL);
    $inputs["u"] = null;
}

if($inputs["p"]==null||$inputs["m"]==null){
    if($test)
        echo 'Mail and password must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
else{
    $u= isset($inputs["u"]) ? $inputs["u"] : null;
    $p=$inputs["p"];
    $m=$inputs["m"];
    //Validate Username
    if($u != null)
        if(!\IOFrame\Util\validator::validateUsername($u)){
            $res=false;
            if($test)
                echo 'Username illegal!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        //Validate Password
        else if(!IOFrame\Util\validator::validatePassword($p)){
            $res=false;
            if($test)
                echo 'Password illegal!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        //Validate Mail
        else if(!filter_var($m, FILTER_VALIDATE_EMAIL)){
            $res=false;
            if($test)
                echo 'Email illegal!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
}

//Check if self registration is allowed, user is admin, or user has the appropriate action:
if (
!(
    $userSettings->getSetting('selfReg')!=0 ||
    $auth->isAuthorized(0) ||
    $auth->hasAction('REGISTER_USER_AUTH') ||
    isset($_SESSION['INSTALLING'])
)
){
    if($test)
        echo "User must be authorized to register new users".EOL;
    exit(AUTHENTICATION_FAILURE);
}



?>