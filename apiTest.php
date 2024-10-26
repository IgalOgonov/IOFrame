<?php
/**
Meant to manage objects throughout the system.
 */

require 'main/core_init.php';

if(!(
    $siteSettings->getSetting('devMode') ||
    ( $siteSettings->getSetting('allowTesting') && $auth->isAuthorized() )
)
){
    \IOFrame\Util\DefaultErrorTemplatingFunctions::handleGenericHTTPError(
        $settings,
        [
            'error'=>401,
            'errorInMsg'=>false,
            'errorHTTPMsg'=>'Testing unauthorized',
            'mainMsg'=>'Testing Unauthorized',
            'subMsg'=>'May not access testing functionality',
            'mainFilePath'=>$settings->getSetting('_templates_unauthorized_generic'),
        ]
    );
}
?>

<!DOCTYPE html>
<?php require_once $settings->getSetting('absPathToRoot').'front/ioframe/templates/headers.php';


echo '<script src="'.$dirToRoot.'front/ioframe/js/ezPopup.js"></script>';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/popUpTooltip.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/ext/bootstrap_3_3_7/css/bootstrap.min.css">';
if($auth->isAuthorized())
    echo '<script src="'.$dirToRoot.'front/ioframe/js/ext/vue/2.6.10/vue.js"></script>';
else
    echo '<script src="'.$dirToRoot.'front/ioframe/js/ext/vue/2.6.10/vue.min.js"></script>';

echo '<title>API Test</title>';
?>

<body>
<p id="errorLog"></p>

<h1>API Test</h1>
<?php
    echo '<span id="apiTest"></span>';
    echo '<script src="'.$dirToRoot.'front/ioframe/js/modules/apiTest.js"></script>';
?>

<?php require_once $settings->getSetting('absPathToRoot').'front/ioframe/templates/footers.php';?>

</body>
