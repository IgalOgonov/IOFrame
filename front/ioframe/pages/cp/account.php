<!DOCTYPE html>
<?php
require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

$isLoginPage = true;
require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

array_push($CSS, 'cp.css', 'components/searchList.css', 'modules/CPMenu.css');
$CSSPackages['CPAccountJS'] = [
    'items'=>['components/account/resetMail.css', 'components/account/resetPassword.css',
        'components/account/managePhone.css','components/account/add2fa.css','components/account/toggle2faApp.css','components/account/activateAccount.css',
        'components/account/changePassword.css','components/account/changeMail.css','modules/account.css'],
    'order'=>-1
];
array_push($JS, 'mixins/sourceUrl.js','mixins/parseLimit.js',
    'modules/CPMenu.js');
$JSPackages['CPAccountJS'] = [
    'items'=>[ 'ext/QRCodeGenerator/qrcodegen.js','components/searchList.js', 'components/account/resetMail.js', 'components/account/resetPassword.js',
        'components/account/managePhone.js','components/account/add2fa.js','components/account/toggle2faApp.js', 'components/account/activateAccount.js',
        'components/account/changePassword.js','components/account/changeMail.js', 'modules/account.js'],
    'order'=>-1
];


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';

echo '<title>Users</title>';

$frontEndResourceTemplateManager->printResources('CSS');

?>


<?php
$password = [
    'canReset'=>!empty($_SESSION['PWD_RESET_ID']),
    'expires'=>empty($_SESSION['PWD_RESET_EXPIRES'])? 0 : ((int)$_SESSION['PWD_RESET_EXPIRES']-time())
];
$mail = [
    'canReset'=>!empty($_SESSION['MAIL_CHANGE_ID']),
    'expires'=>empty($_SESSION['MAIL_CHANGE_EXPIRES'])? 0 : ((int)$_SESSION['MAIL_CHANGE_EXPIRES']-time())
];
$twoFactor = [
    'hasPhone'=>!empty($auth->getDetail('Phone')),
    'hasApp'=>!empty($auth->getDetail('TwoFactorAppReady'))
];
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'account',
            'title' => 'Account'
        ],
        'account'=>[
            'password'=>$password,
            'mail'=>$mail,
            'twoFactor'=>$twoFactor
        ]
    ]);
$userSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/userSettings/');
$siteConfig['login'] = [
    'hasRememberMe'=> (bool)$userSettings->getSetting('rememberMe'),
];
?>

<script>
    document.siteConfig = <?php echo json_encode($siteConfig)?>;
    if(document.siteConfig.page.title !== undefined)
        document.title = document.siteConfig.page.title;
</script>

<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_end.php'; ?>

<body>

<div class="wrapper">
<?php if($auth->isLoggedIn() && empty($banned)) require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/CPMenu.php';?>
<?php if($banned) require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'user_banned.php'; ?>
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/account.php';?>

 </div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';