<?php
/**
Meant to manage objects throughout the system.
 */

//Standard framework initialization
if(!require __DIR__ . '/../_Core/coreInit.php')
    echo 'Core utils unavailable!'.'<br>';

$dirToRoot = IOFrame\htmlDirDist($_SERVER['PHP_SELF'],$settings->getSetting('pathToRoot'));
?>


<!DOCTYPE html>
<?php require_once $settings->getSetting('absPathToRoot').'templates/headers.php';

echo '<script src="'.$dirToRoot.'js/ezPopup.js"></script>';
echo '<link rel="stylesheet" href="'.$dirToRoot.'/css/plugins.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'/css/popUpTooltip.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'/css/bootstrap_3_3_7/css/bootstrap.min.css">';
/* ----- Included for all future example apps after the angular ones - for now, the admin only will run it in production mode*/
if($auth->isAuthorized(0))
    echo '<script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.js"></script>';
else
    echo '<script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.min.js"></script>';

echo '<title>Plugins</title>';
?>

<body>
<p id="errorLog"></p>

<div class="wrapper">
    <?php require_once $settings->getSetting('absPathToRoot').'moduleIncludes/Vue_Module_plugins.php'?>
    <?php require_once $settings->getSetting('absPathToRoot').'moduleIncludes/Vue_Module_pluginList.php'?>
</div>


<?php require_once $settings->getSetting('absPathToRoot').'templates/footers.php';?>

</body>
