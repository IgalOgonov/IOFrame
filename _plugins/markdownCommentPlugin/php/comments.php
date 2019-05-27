<?php
/**
Meant to manage objects throughout the system.
 */

//Standard framework initialization
if(!require __DIR__ . '/../_main/coreInit.php')
    echo 'Core utils unavailable!'.'<br>';
$dirToRoot = IOFrame\htmlDirDist($_SERVER['PHP_SELF'],$settings->getSetting('pathToRoot'));
?>


<!DOCTYPE html>
<?php require_once $settings->getSetting('absPathToRoot').'templates/headers.php';


echo '<script src="'.$dirToRoot.'js/ezPopup.js"></script>';
echo '<link rel="stylesheet" href="'.$dirToRoot.'css/popUpTooltip.css">';
echo '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">';
if($auth->isAuthorized(0))
    echo '<script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.js"></script>';
else
    echo '<script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.min.js"></script>';

echo '<title>Comments API</title>';
?>

<body>
<p id="errorLog"></p>

<h1>Comments</h1>
<?php include $settings->getSetting('absPathToRoot').'moduleIncludes/Vue_Module_comments.php'?>

<?php require_once $settings->getSetting('absPathToRoot').'templates/footers.php';?>

</body>
