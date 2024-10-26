<?php


if(!isset($userSettings))
    $userSettings = new \IOFrame\Handlers\SettingsHandler(
        $settings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/userSettings/',
        $defaultSettingsParams
    );

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
    $u= $inputs["u"] ?? null;
    $p=$inputs["p"];
    $m=$inputs["m"];
    //Validate Username
    if($u != null)
        if(!\IOFrame\Util\ValidatorFunctions::validateUsername($u)){
            if($test)
                echo 'Username illegal!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        //Validate Password
        else if(!\IOFrame\Util\ValidatorFunctions::validatePassword($p)){
            if($test)
                echo 'Password illegal!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        //Validate Mail
        else if(!filter_var($m, FILTER_VALIDATE_EMAIL)){
            if($test)
                echo 'Email illegal!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    if(!empty($inputs['language']))
        if(!preg_match('/'.LANGUAGE_REGEX.'/',$inputs['language'])){
            if($test)
                echo 'Language must match '.LANGUAGE_REGEX.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
}

//Token
if($inputs['token']!==null && !preg_match('/'.TOKEN_REGEX.'/',$inputs['token']) ){
    if($test)
        echo 'Illegal token!';
    exit(INPUT_VALIDATION_FAILURE);
}
