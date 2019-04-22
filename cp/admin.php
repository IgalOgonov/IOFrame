<?php
/*For now basic, this is the admin panel for this CMS framework*/
if(!require __DIR__ . '/../_Core/coreInit.php')
    echo 'Core utils unavailable!'.'<br>';

    $dirToRoot = IOFrame\htmlDirDist($_SERVER['PHP_SELF'],$settings->getSetting('pathToRoot'));

//if(!$auth->isAuthorized(0) and !$auth->hasAction('ADMIN_ACCESS_AUTH'))
//    header('Location: ' . 'login.php');
?>


<!DOCTYPE html>
<?php require_once $settings->getSetting('absPathToRoot').'templates/headers.php';


echo '<script src="'.$dirToRoot.'js/ezPopup.js"></script>';
echo '<link rel="stylesheet" href="'.$dirToRoot.'css/popUpTooltip.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'/css/bootstrap_3_3_7/css/bootstrap.min.css">';
//echo '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">';
//echo '<script src="'.$dirToRoot.'js/jQuery_3_1_1/jquery.js"></script>';
//echo '<script src="'.$dirToRoot.'css/bootstrap_3_3_7/js/bootstrap.js"></script>';
//echo '<script src="'.$dirToRoot.'js/angular_1_4_8/angular.js"></script>';

if($auth->isAuthorized(0))
    echo '<script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.js"></script>';
else
    echo '<script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.min.js"></script>';

echo '<title>Admin Panel</title>';
?>

<body>
<p id="errorLog"></p>

<h1>User Creation</h1>
<?php include $settings->getSetting('absPathToRoot').'moduleIncludes/Vue_Module_userReg.php'?>

<h1>User Login</h1>

<div id="userFields" style="background: aliceblue; border-left: 5px solid rgba(135,135,255,0.3); padding: 3px;">
    <?php //Notice the styles are inline!
     include $settings->getSetting('absPathToRoot').'moduleIncludes/Vue_Module_userLog.php';
     include $settings->getSetting('absPathToRoot').'moduleIncludes/Vue_Module_logOut.php';
    ?>
</div>


<?php require_once $settings->getSetting('absPathToRoot').'templates/footers.php';?>

</body>
