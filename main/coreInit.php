<?php

define('coreInit',true);

session_start();

//First, include all user includes
if(!defined('helperFunctions'))
    require __DIR__ . '/../IOFrame/Util/helperFunctions.php';
IOFrame\Util\include_all_php(__DIR__.'/include/', true);
use Monolog\Logger;
use Monolog\Handler\IOFrameHandler;


//This gives the user a way to run his own script through his include, independent of this framework.
if(!isset($skipCoreInit) || $skipCoreInit==false){
    //Require basic things needed to function
    require 'definitions.php';
    require __DIR__ . '/../IOFrame/Handlers/ext/monolog/vendor/autoload.php';
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

    /*Changes connection type to https if it isn't already*/
    function convertToHTTPS(){
        if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off"){
            $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $redirect);
            exit();
        }
    }

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

    //-------------------Convert to SSL if needed-------------------
    if($_SERVER['REMOTE_ADDR']!="::1" && $_SERVER['REMOTE_ADDR']!="127.0.0.1")
        if($siteSettings->getSetting('sslOn') == 1)
            convertToHTTPS();

    //--------------------Initialize sql handler--------------------
    $SQLHandler = new IOFrame\Handlers\SQLHandler($settings);

    //-------------------Iniitialize other soft singletons-------------------
    $loggerHandler = new IOFrameHandler($settings, $SQLHandler, 'local');
    // Create the main logger of the app
    $logger = new Logger('testChannel1');
    $logger->pushHandler($loggerHandler);
    $SessionHandler = new IOFrame\Handlers\SessionHandler($settings,$defaultSettingsParams);
    $auth = new IOFrame\Handlers\AuthHandler($settings,$defaultSettingsParams);
    //-----AuthHandler should be a part of the default setting parameters-----
    $defaultSettingsParams['AuthHandler'] = $auth;
    //-------------------Perform default checks-------------------
    $SessionHandler->checkSessionNotExpired();
    if(!isset($_SESSION['CSRF_token'])){
        $SessionHandler->reset_CSRF_token();
    }
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