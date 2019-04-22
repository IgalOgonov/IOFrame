<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><?php
/* ----- All css might be skipped and replaced with something else if you would like*/

echo '<link rel="stylesheet" href="'.$dirToRoot.'/css/global.css">';
/* ----- Generally I would not include aesthetic JS into the main file, but it is needed for utils.js*/
echo '<script src="'.$dirToRoot.'js/ezAlert.js"></script>';
/* ----- All the /sec files can be removed if you rewrite utils and initPage */
echo '<script src="'.$dirToRoot.'js/sec/aes.js"></script>';
echo '<script src="'.$dirToRoot.'js/sec/mode-ecb.js"></script>';
echo '<script src="'.$dirToRoot.'js/sec/mode-ctr.js"></script>';
echo '<script src="'.$dirToRoot.'js/sec/pad-ansix923-min.js"></script>';
echo '<script src="'.$dirToRoot.'js/sec/pad-zeropadding.js"></script>';
echo '<script src="'.$dirToRoot.'js/utils.js"></script>';
echo '<script src="'.$dirToRoot.'js/initPage.js"></script>';
/* ----- fp.js is only used as a way to separate different browser/device logins - hense, it identifies unique devices*/
echo '<script src="'.$dirToRoot.'js/fp.js"></script>';
//echo '<script src="'.$dirToRoot.'js/objectDB.js"></script>';
$jsIncludes = $orderedPlugins;
$dirArray = scandir($settings->getSetting('absPathToRoot').'js/plugins');
foreach($jsIncludes as $key => $val){
    $jsIncludes[$key] .= '.js';
    $jsInclude = $settings->getSetting('absPathToRoot').'js/plugins/'.$jsIncludes[$key];
     if(file_exists($jsInclude))
         echo '<script src="'.$dirToRoot.'js/plugins/'.$jsIncludes[$key].'"></script>';
}
foreach($dirArray as $key => $fileName){
    if(preg_match('/^[a-zA-Z0-9_-]+\.js$/',$fileName) && !in_array($fileName,$jsIncludes)){
        echo '<script src="'.$dirToRoot.'js/plugins/'.$fileName.'"></script>';
    }
}

//foreach($orderedPlugins as $plugin){
//    $jsInclude = $settings->getSetting('absPathToRoot').'_plugins/'.$plugin.'/include.js';
// if(file_exists($jsInclude))
 //    echo '<script src="'.$dirToRoot.'_plugins/'.$plugin.'/include.js></script>';
//}

?>

<script>
    //This is the path to the root of the IOFrame site
    document.pathToRoot = "<?php echo IOFrame\htmlDirDist($_SERVER['PHP_SELF'],$settings->getSetting('pathToRoot'));?>";
    //Path to the current page from root
    document.currentPage = encodeURI("<?php echo substr($_SERVER['PHP_SELF'],strlen($settings->getSetting('pathToRoot')));?>");
    //Path to the current page from root
    document.loggedIn = <?php $auth->isLoggedIn()? $temp = "true" : $temp = "false"; echo $temp;  ?>;

    document.addEventListener('DOMContentLoaded', function(e) {
        //console.log('Doc loaded',Date.now());
        //Initiate the page
        initPage(document.pathToRoot, document.currentPage );

        //Check if we are logged in, and call a function to act depending on the result
        checkLoggedIn(document.pathToRoot, true).then(
            function(res){
                res? organizeLoggedIn(res):organizeLoggedOut();
            }, function(error) {
                console.error("failed to check loggedIn status!", error);
            }
        );
    }, true);
    /*
    window.addEventListener('load', function(e) {
        console.log('Window loaded',Date.now());
    }, true);

    $(document).ready(function() {
        console.log('Doc ready',Date.now());

    });*/

</script>