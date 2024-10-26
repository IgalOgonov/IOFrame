<?php
if(!isset($siteSettings))
    require 'initiate_combined_settings.php';

if($_SERVER['REMOTE_ADDR']!="::1" && $_SERVER['REMOTE_ADDR']!="127.0.0.1"){
    $redirectionAddress = '';
    $requestScheme =  empty($_SERVER['REQUEST_SCHEME'])? 'https://' : $_SERVER['REQUEST_SCHEME'].'://';
    if(($requestScheme === 'http://') && $siteSettings->getSetting('sslOn') == 1)
        $requestScheme = 'https://';

    //-------------------Redirect somewhere else-------------------
    if($siteSettings->getSetting('redirectTo') && ($_SERVER['HTTP_HOST'] !== $siteSettings->getSetting('redirectTo')) ){
        $redirectionAddress = $requestScheme . $siteSettings->getSetting('redirectTo') . $_SERVER['REQUEST_URI'];
    }
    //-------------------Convert to SSL if needed-------------------
    elseif(($siteSettings->getSetting('sslOn') == 1) && (empty($_SERVER['HTTPS']) || ($_SERVER['HTTPS'] == "off")) ){
        $redirectionAddress = $requestScheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    if($redirectionAddress){
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirectionAddress);
        exit();
    }

    unset($redirectionAddress,$requestScheme);
}