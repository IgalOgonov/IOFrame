<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><?php
$dirToRoot = IOFrame\htmlDirDist($_SERVER['REQUEST_URI'],$settings->getSetting('pathToRoot'));
$currentPage = substr($_SERVER['PHP_SELF'],strlen($settings->getSetting('pathToRoot')));
$currentPageURI = substr($_SERVER['REQUEST_URI'],strlen($settings->getSetting('pathToRoot')));
$rootURI = $settings->getSetting('pathToRoot');
/* ----- Generally I would not include aesthetic JS into the main file, but it is needed for utils.js*/
echo '<script src="'.$dirToRoot.'front/js/ioframe/ezAlert.js"></script>';
/* ----- All the /sec files can be removed if you rewrite utils and initPage */
echo '<script src="'.$dirToRoot.'front/js/ioframe/sec/aes.js"></script>';
echo '<script src="'.$dirToRoot.'front/js/ioframe/sec/mode-ecb.js"></script>';
echo '<script src="'.$dirToRoot.'front/js/ioframe/sec/mode-ctr.js"></script>';
echo '<script src="'.$dirToRoot.'front/js/ioframe/sec/pad-ansix923-min.js"></script>';
echo '<script src="'.$dirToRoot.'front/js/ioframe/sec/pad-zeropadding.js"></script>';
echo '<script src="'.$dirToRoot.'front/js/ioframe/utils.js"></script>';
echo '<script src="'.$dirToRoot.'front/js/ioframe/initPage.js"></script>';
/* ----- fp.js is only used as a way to separate different browser/device logins - hense, it identifies unique devices*/
echo '<script src="'.$dirToRoot.'front/js/ioframe/fp.js"></script>';
//echo '<script src="'.$dirToRoot.'js/objectDB.js"></script>';
$jsIncludes = $orderedPlugins;
$dirArray = scandir($settings->getSetting('absPathToRoot').'front/js/ioframe/plugins');
foreach($jsIncludes as $key => $val){
    $jsIncludes[$key] .= '.js';
    $jsInclude = $settings->getSetting('absPathToRoot').'front/js/ioframe/plugins/'.$jsIncludes[$key];
     if(file_exists($jsInclude))
         echo '<script src="'.$dirToRoot.'front/js/ioframe/plugins/'.$jsIncludes[$key].'"></script>';
}
foreach($dirArray as $key => $fileName){
    if(preg_match('/^[a-zA-Z0-9_-]+\.js$/',$fileName) && !in_array($fileName,$jsIncludes)){
        echo '<script src="'.$dirToRoot.'front/js/ioframe/plugins/'.$fileName.'"></script>';
    }
}
?>

<script>
    //This is the path to the root of the IOFrame site
    document.pathToRoot = "<?php echo $dirToRoot;?>";
    //Current page full name
    document.currentPage = encodeURI("<?php echo $currentPage;?>");
    //Current page URI
    document.currentPageURI = encodeURI("<?php echo $currentPageURI;?>");
    //Current root URI
    document.rootURI = encodeURI("<?php echo $rootURI;?>");
    //Path to the current page from root
    document.loggedIn = <?php echo $auth->isLoggedIn()? "true" : "false";  ?>;

    document.addEventListener('DOMContentLoaded', function(e) {
        //console.log('Doc loaded',Date.now());
        //Initiate the page
        initPage(document.pathToRoot, document.currentPage );
    }, true);

</script>