<?php
require_once $settings->getSetting('absPathToRoot').'IOFrame/Handlers/ext/TwoFactorAuth/vendor/autoload.php';
$tfa = new RobThree\Auth\TwoFactorAuth($siteSettings->getSetting('siteName'));
$secret = $tfa->createSecret(160);
if(!$test)
    $_SESSION['TEMP_2FASecret'] = $secret;
else
    echo 'Setting session secret to '.$secret.EOL;
$result = [
    'secret' => $secret,
    'issuer'=>$siteSettings->getSetting('siteName'),
    'mail'=>json_decode($_SESSION['details'],true)['Email']
];