<!DOCTYPE html>
<?php

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

$isLoginPage = true;
require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

array_push($CSS, 'cp.css','popUpTooltip.css', 'modules/CPMenu.css', 'modules/loginRegister.css');
array_push($JS, 'ezPopup.js','mixins/sourceUrl.js', 'modules/CPMenu.js', 'modules/loginRegister.js');

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';

echo '<title>Login</title>';

$frontEndResourceTemplateManager->printResources('CSS');

?>


<?php
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'login',
            'title' => 'Login'
        ]
    ]
);
$inviteToken = empty($_SESSION['VALID_INVITE_TOKEN'])?null:$_SESSION['VALID_INVITE_TOKEN'];
$inviteMail = empty($_SESSION['VALID_INVITE_MAIL'])?null:$_SESSION['VALID_INVITE_MAIL'];
$userSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/userSettings/');
$siteConfig['login'] = [
    'hasRememberMe'=> (bool)$userSettings->getSetting('rememberMe'),
];
$siteConfig['register'] = [
    'canRegister'=> $userSettings->getSetting('selfReg') || $inviteToken,
    'canHaveUsername'=>$userSettings->getSetting('usernameChoice') < 2,
    'requiresUsername'=>$userSettings->getSetting('usernameChoice') == 0,
    'inviteToken'=>$inviteToken,
    'inviteMail'=>$inviteMail,
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
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/loginRegister.php';?>

 </div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';

echo '<script src="https://hcaptcha.com/1/api.js&render=explicit" async defer></script>';
