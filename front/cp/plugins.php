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
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/css/ioframe/plugins.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/css/ioframe/popUpTooltip.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/css/ioframe/bootstrap_3_3_7/css/bootstrap.min">';
/* ----- Included for all future example apps after the angular ones - for now, the admin only will run it in production mode*/
if($auth->isAuthorized(0))
    echo '<script src="'.$dirToRoot.'front/js/ioframe/vue/2.6.10/vue.js"></script>';
else
    echo '<script src="'.$dirToRoot.'front/js/ioframe/vue/2.6.10/vue.min.js"></script>';

echo '<title>Plugins</title>';
?>

<body>
<p id="errorLog"></p>

<div class="wrapper">
    <?php require $settings->getSetting('absPathToRoot').'front/templates/ioframe/modules/plugins.php'?>
    <?php require $settings->getSetting('absPathToRoot').'front/templates/ioframe/modules/pluginList.php'?>
</div>


<?php require $settings->getSetting('absPathToRoot').'front/templates/ioframe/footers.php';?>

</body>
