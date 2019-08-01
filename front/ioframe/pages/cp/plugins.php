<?php
/**
Meant to manage objects throughout the system.
 */
if(!defined('coreInit'))
    require __DIR__ . '/../../main/coreInit.php';
?>


<!DOCTYPE html>
<?php require $settings->getSetting('absPathToRoot').'front/ioframe/templates/headers.php';

/* ----- All css might be skipped and replaced with something else if you would like*/
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/global.css">';

echo '<script src="'.$dirToRoot.'front/ioframe/js/ezPopup.js"></script>';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/plugins.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/popUpTooltip.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/bootstrap_3_3_7/css/bootstrap.min">';
/* ----- Included for all future example apps after the angular ones - for now, the admin only will run it in production mode*/
if($auth->isAuthorized(0))
    echo '<script src="'.$dirToRoot.'front/ioframe/js/vue/2.6.10/vue.js"></script>';
else
    echo '<script src="'.$dirToRoot.'front/ioframe/js/vue/2.6.10/vue.min.js"></script>';

echo '<title>Plugins</title>';
?>

<body>
<p id="errorLog"></p>

<div class="wrapper">
<?php
    require $settings->getSetting('absPathToRoot').'front/ioframe/templates/modules/plugins.php';
    echo '<script src="'.$dirToRoot.'front/ioframe/js/modules/plugins.js"></script>';
    require $settings->getSetting('absPathToRoot').'front/ioframe/templates/modules/pluginList.php';
    echo '<script src="'.$dirToRoot.'front/ioframe/js/modules/pluginList.js"></script>';
?>
</div>


<?php require $settings->getSetting('absPathToRoot').'front/ioframe/templates/footers.php';?>

</body>
