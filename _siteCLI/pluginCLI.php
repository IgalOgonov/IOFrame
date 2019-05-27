<?php
/* This interface is meant to be ran on 'db' operation mode nodes (non-local nodes).
 * Basically this interface is meant to be run on nodes individually in order to install plugins locally after they have been
 * installed globally.
 *
 *
 *
 *
 *
 *
 * */
if(php_sapi_name() != "cli"){
    die('This file must be accessed through the CLI!');
}

require __DIR__ . '/../_main/definitions.php';
if(!defined('settingsHandler'))
    require __DIR__.'/../_siteHandlers/settingsHandler.php';
if(!defined('helperFunctions'))
    require __DIR__.'/../_util/helperFunctions.php';
if(!defined('pluginHandler'))
    require __DIR__.'/../_siteHandlers/pluginHandler.php';

//--------------------Initialize Root DIR--------------------
$baseUrl = IOFrame\replaceInString('\\','/',substr(__DIR__,0,-8));

//--------------------Initialize EOL --------------------
if(!defined("EOL"))
    define("EOL",PHP_EOL);

echo EOL.'----IOFrame Plugin CLI----'.EOL.EOL;

//-------------------- Get user options --------------------
$test = false;
$useOptionsFile = false;
$override = false;
$options = [];
$flags = getopt('n:a:f:hto');
//Help message
if(isset($flags['h']))
    die('Available flags are:'.EOL.'
    -h Displays this help message'.EOL.'
    -n Name of the requested plugin [REQUIRED]'.EOL.'
    -a Action type. Can be "install" or "uninstall" [REQUIRED]'.EOL.'
    -f Plugin options file location RELATIVE to server root [OPTIONAL]'.EOL.'
    -o Override command [OPTIONAL]'.EOL.'
    ');

if(!isset($flags['n']))
    die('Plugin name must be provided - option name -n'.EOL);
if(!isset($flags['a']))
    die('Action type must be provided - option name -a'.EOL);
if(isset($flags['f'])){
    $useOptionsFile = $flags['f'];
    if($flags['f'] === false)
        die('If -f is set, the value must be a file location RELATIVE to server root!'.EOL);
}
if(isset($flags['t']))
    $test = true;
if(isset($flags['ov']))
    $override = true;

if($useOptionsFile){
    //Exit if the options file does not exist despite the fact it should
    if(!file_exists($baseUrl.$useOptionsFile))
        exit('Install Options file does not exist!');
    //If the file exists, read it
    $fileHandler = new IOFrame\fileHandler();
    $optionFile = $fileHandler->readFileWaitMutex($baseUrl,$useOptionsFile,[]);
    $options = json_decode($optionFile,true);
}

//------------------------ Create settings --------------------------

$localSettings = new IOFrame\settingsHandler($baseUrl.'/_siteFiles/localSettings/');

$pluginHandler = new IOFrame\pluginHandler($localSettings);

switch($flags['a']){
    case 'install':
        if($test)
            echo 'Installing plugin '.$flags['n'].EOL;
        $res = $pluginHandler->install($flags['n'],$options,['local'=>true,'override'=>true,'test'=>$test]);
        if($res == 0)
            echo 'Plugin installation successful!'.EOL;
        else
            echo 'Plugin install failed, returned: '.$res.EOL;
        break;
    case 'uninstall':
        if($test)
            echo 'Uninstalling plugin '.$flags['n'].EOL;
        $res = $pluginHandler->uninstall($flags['n'],$options,['local'=>true,'override'=>true,'test'=>$test]);
        if($res == 0)
            echo 'Plugin uninstall successful!'.EOL;
        else
            echo 'Plugin uninstall failed, returned: '.$res.EOL;
        break;
    default:
}





?>