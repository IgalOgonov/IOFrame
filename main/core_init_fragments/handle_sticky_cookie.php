<?php
if(!isset($settings))
    require_once 'initiate_local_settings.php';
if(!isset($siteSettings))
    require_once 'initiate_combined_settings.php';

if($siteSettings->getSetting('enableStickyCookie') && $settings->getSetting('nodeID') && !isset($_COOKIE['IOFrameStickyCookie'])){
    setcookie(
        "IOFrameStickyCookie",
        $settings->getSetting('nodeID'),
        time()+( $siteSettings->getSetting('stickyCookieDuration') ? $siteSettings->getSetting('stickyCookieDuration') : (60*60*8) ),
        '/',
        '',
        1,
        1
    );
}