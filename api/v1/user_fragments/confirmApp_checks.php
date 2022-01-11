<?php

use RobThree\Auth\TwoFactorAuth;

$inputs['id'] = json_decode($_SESSION['details'],true)['ID'];
$inputs['require2FA'] = $inputs['require2FA'] === null? true : (bool)$inputs['require2FA'];

if(!filter_var($inputs['code'],FILTER_VALIDATE_INT) || $inputs['code'] < 100000 || $inputs['code'] > 999999){
    if($test)
        echo 'Invalid code!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
$inputs['code'] = (string)$inputs['code'];

if(!$test && empty($_SESSION['TEMP_2FASecret'])){
    if($test)
        echo 'Session secret not set! Try requesting 2FA again.'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//Strangely located here, checking the app code VS what should
$tfa = new RobThree\Auth\TwoFactorAuth($siteSettings->getSetting('siteName'));
$expectedSecret = $test? 'JBSWY3DPEHPK3PXP' : $_SESSION['TEMP_2FASecret'];

if(!$tfa->verifyCode($expectedSecret,$inputs['code'])){
    if($test)
        echo 'Invalid code!'.EOL;
    die('-2');
}