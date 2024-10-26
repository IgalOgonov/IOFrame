<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<?php
require 'ioframe_core_headers.php';
require 'check_if_user_banned.php';

$details = isset($_SESSION['details']) && \IOFrame\Util\PureUtilFunctions::is_json($_SESSION['details'])? json_decode($_SESSION['details'],true) : [];
$active = !empty($details['Active']);
$requires2FA = !empty($details['require2FA']);

$siteConfig = [
    'active'=>$active,
    'requires2FA'=>$requires2FA,
    'isAdmin'=>$auth->isAuthorized()
];

//Allows modification of the menu via the setting CPMenu
$CPMenuSetting = $siteSettings->getSetting('CPMenu');
if(\IOFrame\Util\PureUtilFunctions::is_json($CPMenuSetting)){
    $siteConfig['cp'] = json_decode($CPMenuSetting,true);
}

$devMode = true;

if( $siteSettings->getSetting('devMode') ||
    $auth->isAuthorized() ||
    ( !empty($_REQUEST['devMode']) && $auth->hasAction('DEV_MODE') )||
    !empty($_SESSION['devMode'])
)
    $devMode = $siteSettings->getSetting('allowTesting') || $siteSettings->getSetting('devMode');

if($devMode)
    echo '<script src="'.$dirToRoot.'front/ioframe/js/ext/vue/2.7.16/vue.js"></script>';
else
    echo '<script src="'.$dirToRoot.'front/ioframe/js/ext/vue/2.7.16/vue.min.js"></script>';

$JS = [];
$JSPackages = [
        'commonJSMixinsComponents'=>
        [
            'items'=>['mixins/commons.js','mixins/componentHookFunctions.js', 'mixins/eventHubManager.js',
                'mixins/multipleLanguages.js','components/userLogin.js','components/userRegistration.js',
                'components/userLogout.js','components/languageSelector.js'],
            'order'=> 0
        ]
];
$CSS = [];
$CSSPackages = [
    'commonCSS'=>
        [
            'items'=>['standard.css','global.css','fonts.css','components/languageSelector.css'],
            'order'=>0
        ]
];
$languageObjects = [];

