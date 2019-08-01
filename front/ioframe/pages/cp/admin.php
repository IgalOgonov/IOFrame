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

echo '<title>Admin Panel</title>';

?>


<body>
<p id="errorLog"></p>

<h1>User Creation</h1>
<?php
include $settings->getSetting('absPathToRoot').'front/ioframe/templates/modules/userReg.php';
echo '<script src="'.$dirToRoot.'front/ioframe/js/modules/userReg.js"></script>';
?>

<h1>User Login</h1>

<div id="userFields" style="background: aliceblue; border-left: 5px solid rgba(135,135,255,0.3); padding: 3px;">
    <?php //Notice the styles are inline!
     include $settings->getSetting('absPathToRoot').'front/ioframe/templates/modules/userLog.php';
    echo '<script src="'.$dirToRoot.'front/ioframe/js/modules/userLog.js"></script>';
     include $settings->getSetting('absPathToRoot').'front/ioframe/templates/modules/logOut.php';
    echo '<script src="'.$dirToRoot.'front/ioframe/js/modules/logOut.js"></script>';
    ?>
</div>

</body>

<script>

    //Organizes the page assuming the user is logged in
    function organizeLoggedIn(res){
        let userlog = document.getElementById('userLog');
        if(userlog!=undefined && userlog.innerHTML.length>0){
            (res === true)?
                userlog.innerHTML = 'Hello! Relog in progress...':
                userlog.innerHTML = 'Hello '+res+'!';

        }
    }

    //Organizes the page assuming the user is logged out
    function organizeLoggedOut(){
        let userlog = document.getElementById('userLogOut');
        if(userlog!=undefined && userlog.innerHTML.length>0)
            userlog.parentNode.removeChild(userlog);
    }

    //Check if we are logged in, and call a function to act depending on the result
    checkLoggedIn(document.pathToRoot, true).then(
        function(res){
            res? organizeLoggedIn(res):organizeLoggedOut();
        }, function(error) {
            console.error("failed to check loggedIn status!", error);
        }
    );
</script>


<?php require $settings->getSetting('absPathToRoot').'front/ioframe/templates/footers.php';?>