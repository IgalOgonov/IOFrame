<?php
/**
Meant to manage objects throughout the system.
 */
if(!defined('coreInit'))
    require __DIR__ . '/../../main/coreInit.php';
?>


<!DOCTYPE html>
<?php require $settings->getSetting('absPathToRoot').'front/templates/ioframe/headers.php';

/* ----- All css might be skipped and replaced with something else if you would like*/
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/css/ioframe/global.css">';

echo '<script src="'.$dirToRoot.'front/js/ioframe/ezPopup.js"></script>';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/css/ioframe/popUpTooltip.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/css/ioframe/bootstrap_3_3_7/css/bootstrap.min">';

if($auth->isAuthorized(0))
    echo '<script src="'.$dirToRoot.'front/js/ioframe/vue/2.6.10/vue.js"></script>';
else
    echo '<script src="'.$dirToRoot.'front/js/ioframe/vue/2.6.10/vue.min.js"></script>';

echo '<script src="'.$dirToRoot.'front/js/ioframe/objects.js"></script>';
echo '<script>startObjectDB({\'updateObjects\':true,\'updateObjectMap\':true,\'extraMaps\':[\'objects\']})</script>';

echo '<title>Objects API</title>';
?>

<body>
<p id="errorLog"></p>

<h1>Objects</h1>
<?php include $settings->getSetting('absPathToRoot').'front/templates/ioframe/modules/objects.php'?>

<?php require $settings->getSetting('absPathToRoot').'front/templates/ioframe/footers.php';?>

</body>
