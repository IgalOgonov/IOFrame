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

//echo '<link rel="stylesheet" href="'.$dirToRoot.'/css/bootstrap_3_3_7/css/bootstrap.min.css">';
echo '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">';
echo '<script src="'.$dirToRoot.'js/jQuery_3_1_1/jquery.js"></script>';
//echo '<script src="'.$dirToRoot.'css/bootstrap_3_3_7/js/bootstrap.js"></script>';

/* ----- Angular needed only for 2 example apps (Admin panel and objects page) */
echo '<script src="'.$dirToRoot.'js/angular_1_4_8/angular.js"></script>';

echo '<title>Objects Control Panel</title>';
?>

<body>
<p id="errorLog"></p>

<h1>Object Manager</h1>
<?php require_once $settings->getSetting('absPathToRoot').'moduleIncludes/AngJS_Module_objects.php'?>

<?php require_once $settings->getSetting('absPathToRoot').'templates/footers.php';?>

</body>
