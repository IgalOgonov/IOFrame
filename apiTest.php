<?php
/**
Meant to manage objects throughout the system.
 */

//Standard framework initialization
if(!require __DIR__ . '/main/coreInit.php')
    echo 'Core utils unavailable!'.'<br>';
?>


<!DOCTYPE html>
<?php require_once $settings->getSetting('absPathToRoot').'front/ioframe/templates/headers.php';


echo '<script src="'.$dirToRoot.'front/ioframe/js/ezPopup.js"></script>';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/popUpTooltip.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/bootstrap_3_3_7/css/bootstrap.min">';
if($auth->isAuthorized(0))
    echo '<script src="'.$dirToRoot.'front/ioframe/js/vue/2.6.10/vue.js"></script>';
else
    echo '<script src="'.$dirToRoot.'front/ioframe/js/vue/2.6.10/vue.min.js"></script>';

echo '<title>API Test</title>';
?>

<body>
<p id="errorLog"></p>

<h1>API Test</h1>
<?php include $settings->getSetting('absPathToRoot').'front/ioframe/templates/modules/apiTest.php'?>

<?php require_once $settings->getSetting('absPathToRoot').'front/ioframe/templates/footers.php';?>

</body>
