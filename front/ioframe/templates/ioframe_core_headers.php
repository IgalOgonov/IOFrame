
<?php

$dirToRoot = \IOFrame\Util\FrameworkUtilFunctions::htmlDirDist($_SERVER['REQUEST_URI'],$settings->getSetting('pathToRoot'));
$currentPage = substr($_SERVER['PHP_SELF'],strlen($settings->getSetting('pathToRoot')));
$currentPageURI = substr($_SERVER['REQUEST_URI'],strlen($settings->getSetting('pathToRoot')));
$rootURI = $settings->getSetting('pathToRoot');
if(!isset($IOFrameCSSRoot) || !isset($IOFrameJSRoot))
    require_once 'definitions.php';

/* -- Initiate resource handler and get core JS files and the CSS file--*/

if(!isset($FrontEndResources))
    $FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);
$coreJS = $FrontEndResources->getJSCollection( 'IOFrameCoreJS',['rootFolder'=>$IOFrameJSRoot]);
echo '<script src=\''.$dirToRoot.$IOFrameJSRoot.$coreJS['relativeAddress'].'\'></script>';
$ezAlertCSS = $FrontEndResources->getCSS(['ezAlert.css'],['rootFolder'=>$IOFrameCSSRoot])['ezAlert.css'];
echo '<link rel="stylesheet" href=\''.$dirToRoot.$IOFrameCSSRoot.$ezAlertCSS['relativeAddress'].'\'>';

/*Include plugins JS files, if those exist*/
$jsIncludes = $orderedPlugins;
if(is_dir($settings->getSetting('absPathToRoot').'front/ioframe/js/plugins')){
    $dirArray = scandir($settings->getSetting('absPathToRoot').'front/ioframe/js/plugins');
    foreach($jsIncludes as $key => $val){
        $jsIncludes[$key] .= '.js';
        $jsInclude = $settings->getSetting('absPathToRoot').'front/ioframe/js/plugins/'.$jsIncludes[$key];
        if(file_exists($jsInclude))
            echo '<script src=\''.$dirToRoot.'front/ioframe/js/plugins/'.$jsIncludes[$key].'\'></script>';
    }
    foreach($dirArray as $key => $fileName){
        if(preg_match('/^[a-zA-Z0-9_-]+\.js$/',$fileName) && !in_array($fileName,$jsIncludes)){
            echo '<script src=\''.$dirToRoot.'front/ioframe/js/plugins/'.$fileName.'\'></script>';
        }
    }
}

/*Get the languages and parse them*/
$languages = $siteSettings->getSetting('languages');
if(!empty($languages))
    $languages = explode(',',$languages);
else
    $languages = [];
?>

<script>
    document.ioframe = {};
    //This is the path to the root of the IOFrame site
    document.ioframe.pathToRoot = '<?php echo $dirToRoot;?>';
    //Site Name
    document.ioframe.siteName = encodeURI('<?php echo $siteSettings->getSetting('siteName');?>');
    //Capcha Site Key
    <?php if($siteSettings->getSetting('captcha_site_key')) echo 'document.ioframe.captchaSiteKey = "'.$siteSettings->getSetting('captcha_site_key').'"';?>
    //Current page full name
    document.ioframe.currentPage = encodeURI('<?php echo $currentPage;?>');
    //Current page URI
    document.ioframe.currentPageURI = encodeURI('<?php echo $currentPageURI;?>');
    //Current page URI
    document.ioframe.imagePathLocal = encodeURI('<?php echo $resourceSettings->getSetting('imagePathLocal');?>');
    //Current page URI
    document.ioframe.videoPathLocal = encodeURI('<?php echo $resourceSettings->getSetting('videoPathLocal');?>');
    //Current page URI
    document.ioframe.jsPathLocal = encodeURI('<?php echo $resourceSettings->getSetting('jsPathLocal');?>');
    //Current page URI
    document.ioframe.cssPathLocal = encodeURI('<?php echo $resourceSettings->getSetting('cssPathLocal');?>');
    //Current root URI
    document.ioframe.rootURI = encodeURI('<?php echo $rootURI;?>');
    //Path to the current page from root
    document.ioframe.loggedIn = <?php echo $auth->isLoggedIn()? "true" : "false";  ?>;
    //Difference between local time and server time - in seconds!
    document.ioframe.serverTimeDelta = Math.floor( Math.floor(Date.now()/1000 - <?php echo time();?>) / 10) * 10;
    //Languages
    document.ioframe.languages = <?php echo json_encode($languages);?>;
    //Default Language
    document.ioframe.defaultLanguage = "<?php echo $siteSettings->getSetting('defaultLanguage');?>";
    //Default Languages Map
    document.ioframe.languagesMap = <?php echo $siteSettings->getSetting('languagesMap');?>;
    //Preferred Language
    document.ioframe.preferredLanguage = <?php echo isset($_SESSION['details'])? (json_decode($_SESSION['details'],true)['Preferred_Language'])? '"'.json_decode($_SESSION['details'],true)['Preferred_Language'].'"':'null' : 'null'?>;

    document.ioframe.selectedLanguage = document.ioframe.preferredLanguage ?? document.ioframe.defaultLanguage;

    if(localStorage){
        //CSRF Token
        localStorage.setItem('CSRF_token','<?php echo $_SESSION['CSRF_token'];?>');
        //In a very specific case PHP has re-logged using cookies and the session ID changed - this will only work if the relog happaned on a page with this script
        let newID = <?php echo isset($newID) ? "'".$newID."'" : 'false';?>;
        if(newID)
            localStorage.setItem('sesID',newID);
        document.ioframe.selectedLanguage = localStorage.getItem('lang')? localStorage.getItem('lang') : document.ioframe.selectedLanguage;
    }

    document.addEventListener('DOMContentLoaded', function(e) {
        //Define callbacks if not defined
        if(document.ioframe.callbacks === undefined)
            document.ioframe.callbacks = {};
        //Initiate the page
        initPage(document.ioframe.pathToRoot, document.ioframe.callbacks);
    }, true);

</script>