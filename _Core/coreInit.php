<?php

session_start();


//First, include all user includes
require_once __DIR__.'/../_util/helperFunctions.php';
IOFrame\include_all_php(__DIR__.'/include/', true);
use Monolog\Logger;
use Monolog\Handler\IOFrameHandler;

//This gives the user a way to run his own script through his include, independent of this framework.
if(!isset($skipCoreInit) || $skipCoreInit==false){
    //Require basic things needed to function
        require_once 'definitions/definitions.php';
        require_once __DIR__.'/../_siteHandlers/ext/monolog/vendor/autoload.php';
        require_once __DIR__.'/../_siteHandlers/settingsHandler.php';
        require_once __DIR__.'/../_siteHandlers/sqlHandler.php';
        require_once __DIR__.'/../_siteHandlers/redisHandler.php';
        require_once __DIR__.'/../_siteHandlers/authHandler.php';
        require_once __DIR__.'/../_siteHandlers/sessionHandler.php';
        require_once __DIR__.'/../_siteHandlers/pluginHandler.php';

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
        $redisSettings = new IOFrame\settingsHandler(IOFrame\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/redisSettings/',['useCache'=>false]);
        $redisHandler = new IOFrame\redisHandler($redisSettings);
        if($redisHandler->isInit){
            $defaultSettingsParams['useCache'] = true;
            $defaultSettingsParams['redisHandler'] = $redisHandler;
        }

    //--------------------Initialize local settings handlers--------------------
        $settings = new IOFrame\settingsHandler(IOFrame\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/localSettings/');

    //--------------------Save the root folder for shorter syntax later--------------------
        $rootFolder = $settings->getSetting('absPathToRoot');

    //--------------------Decide what mode of operation we're in--------------------
        if($settings->getSetting('opMode')!=null)
            $opMode = $settings->getSetting('opMode');
        else
            $opMode = IOFrame\SETTINGS_OP_MODE_MIXED;
        $defaultSettingsParams['opMode'] = $opMode;

    //--------------------Initialize sql handler--------------------
        $sqlHandler = new IOFrame\sqlHandler(
            $settings,
            $defaultSettingsParams
        );
        $defaultSettingsParams['sqlHandler'] = $sqlHandler;

    //--------------------Initialize site settings handler--------------------
        $siteSettings = new IOFrame\settingsHandler(
            IOFrame\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
            $defaultSettingsParams
        );
        $defaultSettingsParams['siteSettings'] = $siteSettings;

    //-------------------Convert to SSL if needed-------------------
        if($_SERVER['REMOTE_ADDR']!="::1" && $_SERVER['REMOTE_ADDR']!="127.0.0.1")
            if($siteSettings->getSetting('sslOn') == 1)
                convertToHTTPS();

    //--------------------Initialize sql handler--------------------
        $sqlHandler = new IOFrame\sqlHandler($settings);

    //-------------------Iniitialize other soft singletons-------------------
        $loggerHandler = new IOFrameHandler($settings, $sqlHandler, 'local');
    // Create the main logger of the app
        $logger = new Logger('testChannel1');
        $logger->pushHandler($loggerHandler);
        $sessionHandler = new IOFrame\sessionHandler($settings,$defaultSettingsParams);
        $auth = new IOFrame\authHandler($settings,$defaultSettingsParams);
    //-----AuthHandler should be a part of the default setting parameters-----
    $defaultSettingsParams['authHandler'] = $auth;
    //-------------------Perform default checks-------------------
        $sessionHandler->checkSessionNotExpired();
    //-------------------Include Installed Plugins----------------
    //Get the list of active plugins
        $orderedPlugins = [];                                               //To be used later, after initiation
        $plugins = new IOFrame\settingsHandler(IOFrame\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/plugins/');
        $pluginHandler = new IOFrame\pluginHandler($settings,['sqlHandler'=>$sqlHandler,'logger'=>$logger]);
        $pluginList = $plugins->getSettings();
        if(count($pluginList)<1)
            $pluginList =[];
    //Get the order in which plugins should be included, if there is one
        $order = $pluginHandler->getOrder();
    //First, require all includes that have an order
        if(is_array($order))
            foreach($order as $value){
                if(isset($pluginList[$value])){
                    if($pluginHandler->getInfo($value)[0]['status'] == 'active' ){
                        require $rootFolder.'_plugins/'.$value.'/include.php';
                        array_push($orderedPlugins,$value);
                    }
                    unset($pluginList[$value]);
                }
            }
    //Then, require those that are orderless
        if(is_array($pluginList))
            foreach($pluginList as $key => $value){
                if($pluginHandler->getInfo($key)[0]['status'] == 'active' ){
                    require $rootFolder.'_plugins/'.$key.'/include.php';
                    array_push($orderedPlugins,$key);
                }
            }
}
?>