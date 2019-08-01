<?php
/*For now basic, this is the admin panel for a CMS framework. Currently handles logging in and creating users.*/
if(!defined('coreInit'))
    require __DIR__ . '/../../main/coreInit.php';

?>


    <!DOCTYPE html>
    <?php require $settings->getSetting('absPathToRoot').'front/ioframe/templates/headers.php';

    /* ----- All css might be skipped and replaced with something else if you would like*/
    echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/global.css">';

    echo '<script src="'.$dirToRoot.'front/ioframe/js/ezPopup.js"></script>';
    echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/popUpTooltip.css">';
    echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/bootstrap_3_3_7/css/bootstrap.min">';

    if($auth->isAuthorized(0))
        echo '<script src="'.$dirToRoot.'front/ioframe/js/vue/2.6.10/vue.js"></script>';
    else
        echo '<script src="'.$dirToRoot.'front/ioframe/js/vue/2.6.10/vue.min.js"></script>';

    echo '<title>IOFrame welcome page</title>';

    ?>


    <body>
    <p id="errorLog"></p>

    <h1>Todo - Design this page</h1>


<?php require $settings->getSetting('absPathToRoot').'front/ioframe/templates/footers.php';?>