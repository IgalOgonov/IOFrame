<?php

define('coreInit',true);

session_start();
//First, include all user includes
if(!defined('helperFunctions'))
    require __DIR__ . '/../IOFrame/Util/helperFunctions.php';
IOFrame\Util\include_all_php(__DIR__.'/include/', true);
require_once __DIR__.'/../vendor/autoload.php';
use Monolog\Logger;
use IOFrame\Handlers\Monolog\IOFrameHandler;

//This gives the user a way to run his own script through his include, independent of this framework.
if(!isset($skipCoreInit) || $skipCoreInit==false){
    //Require basic things needed to function
    require 'definitions.php';
    //require __DIR__ . '/../IOFrame/Handlers/ext/monolog/vendor/autoload.php';
    if(!defined('SettingsHandler'))
        require __DIR__ . '/../IOFrame/Handlers/SettingsHandler.php';
    if(!defined('SQLHandler'))
        require __DIR__ . '/../IOFrame/Handlers/SQLHandler.php';
    if(!defined('RedisHandler'))
        require __DIR__ . '/../IOFrame/Handlers/RedisHandler.php';
    if(!defined('AuthHandler'))
        require __DIR__ . '/../IOFrame/Handlers/AuthHandler.php';
    if(!defined('SessionHandler'))
        require __DIR__ . '/../IOFrame/Handlers/SessionHandler.php';
    if(!defined('PluginHandler'))
        require __DIR__ . '/../IOFrame/Handlers/PluginHandler.php';
    if(!defined('FrontEndResourceHandler'))
        require __DIR__ . '/../IOFrame/Handlers/FrontEndResourceHandler.php';
    if(!defined('FileHandler'))
        require __DIR__ . '/../IOFrame/Handlers/FileHandler.php';

    //--------------------The global settings parameters. They'll get updated as we go.--------------------
    $defaultSettingsParams = [];

    //--------------------Initialize redis handler--------------------
    $redisSettings = new IOFrame\Handlers\SettingsHandler(IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/redisSettings/',['useCache'=>false]);
    $RedisHandler = new IOFrame\Handlers\RedisHandler($redisSettings);
    if($RedisHandler->isInit){
        $defaultSettingsParams['useCache'] = true;
    }
    $defaultSettingsParams['RedisHandler'] = $RedisHandler;

    //--------------------Initialize local settings handlers--------------------
    $settings = new IOFrame\Handlers\SettingsHandler(IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/localSettings/');

    //--------------------Save the root folder for shorter syntax later--------------------
    $rootFolder = $settings->getSetting('absPathToRoot');

    //--------------------Decide what mode of operation we're in--------------------
    if($settings->getSetting('opMode')!=null)
        $opMode = $settings->getSetting('opMode');
    else
        $opMode = IOFrame\Handlers\SETTINGS_OP_MODE_MIXED;
    $defaultSettingsParams['opMode'] = $opMode;

    //--------------------Initialize sql handler--------------------
    $SQLHandler = new IOFrame\Handlers\SQLHandler(
        $settings,
        $defaultSettingsParams
    );
    $defaultSettingsParams['SQLHandler'] = $SQLHandler;

    //--------------------Initialize site settings handler--------------------
    $siteAndResourceSettings = new IOFrame\Handlers\SettingsHandler(
        [
            IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
            $rootFolder.'/localFiles/resourceSettings/'
        ],
        $defaultSettingsParams
    );
    $siteSettings = clone $siteAndResourceSettings;
    $resourceSettings = clone $siteAndResourceSettings;
    $siteSettings->keepSettings('siteSettings');
    $resourceSettings->keepSettings('resourceSettings');
    $defaultSettingsParams['siteSettings'] = $siteSettings;
    $defaultSettingsParams['resourceSettings'] = $resourceSettings;

    //Redirections - wont happen on localhost
    if($_SERVER['REMOTE_ADDR']!="::1" && $_SERVER['REMOTE_ADDR']!="127.0.0.1"){
        $redirectionAddress = '';
        $requestScheme =  empty($_SERVER['REQUEST_SCHEME'])? 'http://' : $_SERVER['REQUEST_SCHEME'].'://';
        if(($requestScheme === 'http://') && $siteSettings->getSetting('sslOn') == 1)
            $requestScheme = 'https://';

        //-------------------Redirect somewhere else-------------------
        if($siteSettings->getSetting('redirectTo') && ($_SERVER['HTTP_HOST'] !== $siteSettings->getSetting('redirectTo')) ){
            $redirectionAddress = $requestScheme . $siteSettings->getSetting('redirectTo') . $_SERVER['REQUEST_URI'];
        }
        //-------------------Convert to SSL if needed-------------------
        elseif(($siteSettings->getSetting('sslOn') == 1) && (empty($_SERVER['HTTPS']) || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "off")) ){
            $redirectionAddress = $requestScheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }

        if($redirectionAddress){
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $redirectionAddress);
            exit();
        }

        unset($redirectionAddress,$requestScheme);
    }

    //-------------------Iniitialize other soft singletons-------------------
    $loggerHandler = new IOFrameHandler($settings, $SQLHandler, 'local');
    // Create the main logger of the app
    $logger = new Logger('testChannel1');
    $logger->pushHandler($loggerHandler);
    $SessionHandler = new IOFrame\Handlers\SessionHandler($settings,$defaultSettingsParams);

    $sessionExpired = !$SessionHandler->checkSessionNotExpired();
    //-------------------Perform default checks-------------------

    //If we logged out, see if we can try to log in
    if(
        ($sessionExpired || !isset($_SESSION['logged_in'])) &&
        isset($_COOKIE['sesID']) && $_COOKIE['sesID'] &&
        isset($_COOKIE['sesIV']) && $_COOKIE['sesIV'] &&
        isset($_COOKIE['userMail']) && $_COOKIE['userMail'] &&
        isset($_COOKIE['userID']) && $_COOKIE['userID']
    ){
        $userID = $_COOKIE['userID'];
        $sesID = $_COOKIE['sesID'];
        $key = IOFrame\Util\stringScrumble($_COOKIE['sesID'],$_COOKIE['sesIV']);
        $sesKey = bin2hex(base64_decode(openssl_encrypt($userID,'aes-256-ecb' , hex2bin($key) ,OPENSSL_ZERO_PADDING)));

        //Try to log in
        if(!defined('UserHandler'))
            require __DIR__ . '/../IOFrame/Handlers/UserHandler.php';
        $UserHandler = new IOFrame\Handlers\UserHandler(
            $settings,
            $defaultSettingsParams
        );
        $inputs = [
            'log'=>'temp',
            'sesKey'=> $sesKey,
            'm'=> $_COOKIE['userMail'],
            'userID'=>$userID
        ];

        //The result wont matter, since if we fail, the sesID wont change, but otherwise it will
        $res = $UserHandler->logIn($inputs);

        $success = false;

        if(
            gettype($res) === 'string' &&
            strlen($res) === 128
        ){
            $res = openssl_decrypt(base64_encode(hex2bin($res)),'aes-256-ecb', hex2bin( $key ), OPENSSL_ZERO_PADDING );
            $res = \IOFrame\Util\stringDescrumble($res);
            $oldID = $res[0];
            $newID = $res[1];
            //Should always happen with https only cookies, but here just for consistency
            if($oldID === $sesID){
                //Should not matter at this point, but whatever
                $_COOKIE['sesID'] = $newID;
                setcookie("sesID", $newID, time()+(60*60*24*36500),'/','', 1, 1);
                $success = true;
            }
            else
                $res = 'POSSIBLE_FAKE_SERVER';
        }

        //This is how we check if it worked - if $sesID is still the same, we didn't relog
        if(!$success){
            unset($_COOKIE['lastRelogResult']);
            setcookie("lastRelogResult", $res, time()+(60*60*24*36500),'/','', 1, 1);
            unset($_COOKIE['sesID']);
            setcookie("sesID", '', -1,'/');
            unset($_COOKIE['sesIV']);
            setcookie("sesIV", '', -1,'/');
            unset($_COOKIE['userMail']);
            setcookie("userMail", '', -1,'/');
        }
        setcookie("lastRelog", time(), time()+(60*60*24*36500),'/','', 1, 1);
        setcookie("lastRelogResult", ($success ? 'success' : $res), time()+(60*60*24*36500),'/','', 1, 1);
    }

    //Reset CSRF token if need be
    if(!isset($_SESSION['CSRF_token'])){
        $SessionHandler->reset_CSRF_token();
    }

    //-----AuthHandler should be a part of the default setting parameters-----
    $auth = new IOFrame\Handlers\AuthHandler($settings,$defaultSettingsParams);
    $defaultSettingsParams['AuthHandler'] = $auth;

    //-------------------Include Installed Plugins----------------
    //Get the list of active plugins
    $orderedPlugins = [];                                               //To be used later, after initiation
    $plugins = new IOFrame\Handlers\SettingsHandler(IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/plugins/');
    $PluginHandler = new IOFrame\Handlers\PluginHandler($settings,$defaultSettingsParams);
    $pluginList = $plugins->getSettings();
    if(count($pluginList)<1)
        $pluginList =[];
    //Get the order in which plugins should be included, if there is one. Get the local and global ones.
    $localOrder = $PluginHandler->getOrder(['local'=>true]);
    //var_dump($localOrder);
    $order = $PluginHandler->getOrder(['local'=>false]);
    //var_dump($order);
    //If there is a mismatch, specify it so the front end knows!
    $pluginMismatch = false;
    //If there are no plugins, we got nothing to do
    if($order != [] || $localOrder!=[]){
        if(implode(',',$order)!=implode(',',$localOrder)){
            //Since the mismatched plugins might just be in different order, but the same ones installed, check for it
            if(count(array_diff($localOrder,$order)) == 0){
                //If we are here, it means we may just use the correct order, and update the local one while we're at it
                $url = $settings->getSetting('absPathToRoot').'/localFiles/plugin_order/';
                $filename = 'order';
                if(!isset($FileHandler))
                    $FileHandler = new IOFrame\Handlers\FileHandler();
                $FileHandler->writeFileWaitMutex($url, $filename, implode(',',$order), ['backUp' => true]);
            }
            //If the plugins are still mismatched, die or notify the front-end (according to settings)
            elseif($settings->getSetting('dieOnPluginMismatch')){
                header('HTTP/1.0 500 Internal server plugin conflict');
                die('Plugin mismatch - contact the webmaster of this site!');
            }
            else
                $pluginMismatch = true;
        }
        //First, require all includes that have an order
        if(is_array($order))
            foreach($order as $value){
                if(isset($pluginList[$value])){
                    if($PluginHandler->getInfo(['name'=>$value])[0]['status'] == 'active' ){
                        require $rootFolder.'plugins/'.$value.'/include.php';
                        array_push($orderedPlugins,$value);
                    }
                    unset($pluginList[$value]);
                }
            }
        //Then, require those that are orderless
        if(is_array($pluginList))
            foreach($pluginList as $key => $value){
                if($PluginHandler->getInfo(['name'=>$key])[0]['status'] == 'active' ){
                    require $rootFolder.'plugins/'.$key.'/include.php';
                    array_push($orderedPlugins,$key);
                }
            }
    }
}
?>