<?php
/*For now basic, this is the admin panel for a CMS framework. Currently handles logging in and creating users.*/
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

echo '<title>Admin Panel</title>';

?>


<body>
<p id="errorLog"></p>

<h1>User Creation</h1>
<?php include $settings->getSetting('absPathToRoot').'front/templates/ioframe/modules/userReg.php'?>

<h1>User Login</h1>

<div id="userFields" style="background: aliceblue; border-left: 5px solid rgba(135,135,255,0.3); padding: 3px;">
    <?php //Notice the styles are inline!
     include $settings->getSetting('absPathToRoot').'front/templates/ioframe/modules/userLog.php';
     include $settings->getSetting('absPathToRoot').'front/templates/ioframe/modules/logOut.php';
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


<?php require $settings->getSetting('absPathToRoot').'front/templates/ioframe/footers.php';?>