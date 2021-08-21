<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<?php
require __DIR__.'/../../ioframe/templates/ioframe_core_headers.php';

$details = isset($_SESSION['details']) && \IOFrame\Util\is_json($_SESSION['details'])? json_decode($_SESSION['details'],true) : [];

$signedIn = !empty($details['logged_in']);
$active = !empty($details['Active']);
$requires2FA = !empty($details['require2FA']);

$siteConfig = [
    'signedIn'=>$signedIn,
    'active'=>$active,
    'requires2FA'=>$requires2FA,
    'isAdmin'=>$auth->isAuthorized(0)
];

//Allows modification of the menu via the setting CPMenu
$CPMenuSetting = $siteSettings->getSetting('CPMenu');
if(\IOFrame\Util\is_json($CPMenuSetting)){
    $siteConfig['cp'] = json_decode($CPMenuSetting,true);
}

$devMode = true;
$auth->isAuthorized(0) ||
( isset($_REQUEST['devMode']) && $_REQUEST['devMode'] && $auth->hasAction('DEV_MODE') )||
(isset($_SESSION['devMode']) && $_SESSION['devMode']);

if($devMode)
    echo '<script src="'.$dirToRoot.'front/ioframe/js/ext/vue/2.6.10/vue.js"></script>';
else
    echo '<script src="'.$dirToRoot.'front/ioframe/js/ext/vue/2.6.10/vue.min.js"></script>';

$JS = ['mixins/commons.js','mixins/componentHookFunctions.js','components/userLogin.js','components/userRegistration.js','components/userLogout.js'];
$CSS = ['standard.css','global.css','fonts.css'];


?>
