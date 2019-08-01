<?php
/* To install silently, run the command with flag -f and place the following file at /localFiles:
 * File name: installOptionFile.json
 * Contents are of the following form, where optional options are marked with *:
    {
        "expectedProxy"*: < local setting expectedProxy, e.g: "10.12.145.200,100.2.32.6" >,
        "expectedPath"*: < local setting pathToRoot, e.g: "/IOFrame/" or "/Framework/Test Folder/" >,
        "dieOnPluginMismatch": <local setting dieOnPluginMismatch, e.g: true>,
        "redis"*:{
            "redis_addr": < As in redisSettings, eg: "127.0.0.1" >,
            "redis_port"*: < As in redisSettings, eg: 6379 >,
            "redis_password"*: < As in redisSettings, eg: "password" >,
            "redis_timeout"*: < As in redisSettings, eg: 60 >,
            "redis_default_persistent"*: < As in redisSettings, eg: false >
        },
        "sql":{
            "sql_server_addr": < As in sqlSettings, eg: false >"127.0.0.1:3306",
            "sql_username": < As in sqlSettings, eg: false >"username",
            "sql_password": < As in sqlSettings, eg: "password" >,
            "sql_db_name": < As in sqlSettings, eg: "databaseName" >,
            "sql_table_prefix": < As in sqlSettings, eg: "ABC" >,
            "dbLockOnAction"*: < As in sqlSettings, eg: false >
        }
    }
    NOTE: If an option is optional but a sub option is required, it is required as long as the parent option is present.
 * */


echo EOL.'---------Install IOFrame in CLI mode!--------'.EOL;

if(!defined('INSTALL_CLI'))
    exit('Must be included from _install.php to run!');

require_once 'IOFrame/Handlers/FileHandler.php';

//This should only be true if we are installing on a system that is inside a VM, and this environment variable is set in
//the Dockerfile / Vagrantfile / etc, OR if we are running with a flag -f.
$installFromFile = getenv('IOFRAME_CLI_INSTALL_FROM_FILE') || isset(getopt('f')['f']);
if($installFromFile){
    //Exit if the install options file does not exist despite the fact it should
    if(!file_exists($baseUrl.'/localFiles/installOptionFile.json'))
        exit('Install Options file does not exist!');
    //If the file exists, read it
    $FileHandler = new IOFrame\Handlers\FileHandler();
    $installOptions = json_decode($FileHandler->readFileWaitMutex($baseUrl.'/localFiles/','installOptionFile.json',[]),true);

    //Check required options
    if(isset($installOptions['redis']) && !isset($installOptions['redis']['redis_addr']))
        die('Redis address must be set in options file if you are using a cache!');
    if(!isset($installOptions['sql']))
        die('SQL credential must be provided!');
    if(!isset($installOptions['sql']['sql_server_addr']))
        die('SQL server address must be provided!');
    if(!isset($installOptions['sql']['sql_username']))
        die('SQL username must be provided!');
    if(!isset($installOptions['sql']['sql_password']))
        die('SQL password must be provided!');
    if(!isset($installOptions['sql']['sql_db_name']))
        die('SQL database name must be provided!');
    if(!isset($installOptions['sql']['sql_table_prefix']))
        die('SQL table prefix must be provided!');
}

    $handle = fopen ("php://stdin","r");

    echo "This will install this instance as a DB reliant node.".EOL.
        "This node will be reliant on the DB of a main node (already installed).".EOL.
        "If the node is not installed in the server root, it must be specified!".EOL;
if(!$installFromFile){
    echo "If you want to continue, type \"yes\", else this installer will exit.".EOL;
    $line = trim(fgets($handle));

    if($line!=="yes")
        exit('Exiting setup...');
}

//Create initial settings
echo EOL.'Local Settings:'.EOL;
//Settings to set..
$localArgs = [

];

array_push($localArgs,["absPathToRoot",$baseUrl]);
array_push($localArgs,["opMode",IOFrame\Handlers\SETTINGS_OP_MODE_DB]);
//The node should sit at the server root, but if it does not it must be specified!
if(!$installFromFile){
    echo "If this node is not sitting at server root, type its relative location".EOL.
        "STARTING AND ENDING WITH \"\\\", else press Enter".EOL;
    $line = trim(fgets($handle));
}
else{
    if(isset($installOptions['expectedPath']))
        $line = $installOptions['expectedPath'];
    else
        $line = '';
}

if($line!=="")
    array_push($localArgs,["pathToRoot",$line]);
else
    array_push($localArgs,["pathToRoot",'']);

//Let the user decide whether this node should fail on plugin mismatch
if(!$installFromFile){
    echo "Should this node die on local/global plugin mismatch? ".EOL.
        "Type 'yes' for yes, or anything else for no.".EOL;
    $line = trim(fgets($handle));
}
else{
    if(isset($installOptions['dieOnPluginMismatch']) && $installOptions['dieOnPluginMismatch'])
        $line = 'yes';
    else
        $line = 'no';
}

if($line=="yes")
    array_push($localArgs,["dieOnPluginMismatch",true]);
else
    array_push($localArgs,["dieOnPluginMismatch",false]);

$res = true;

//Update all settings, and return false if any of the updates failed
foreach($localArgs as $key=>$val){
    if($localSettings->setSetting($val[0],$val[1],['createNew'=>true]))
        echo 'Setting '.$val[0].' set to '.$val[1].EOL;
    else{
        echo 'Failed to set setting '.$val[0].' to '.$val[1].EOL;
        $res = false;
    }
}

//If this node is sitting behind a reverse proxy (or more than one)
if(!$installFromFile){
    echo "If this node is sitting behind a proxy (or a few), type \"yes\"".EOL.
        "to input the proxy IPs".EOL;
    $line = trim(fgets($handle));
}
else{
    if(isset($installOptions['expectedProxy']))
        $line = "yes";
    else
        $line = 'no';
}

if($line!=="yes")
    echo 'Skipping proxy setup!'.EOL.EOL;
else{
    if(!$installFromFile){
        echo "Enter proxy list, separated by comma+space. For example,".EOL.
            " if your load balancer IP is 10.10.11.11, and it itself is behind".EOL.
            " a proxy with IP 210.20.1.10, type \"210.20.1.10,10.10.11.11\" without quotes.";
        $line = trim(fgets($handle));
    }
    else{
        $line = $installOptions['expectedProxy'];
    }
    if($localSettings->setSetting('expectedProxy',$line,['createNew'=>true]))
        echo 'Setting expectedProxy set to '.$line.EOL;
    else
        echo 'Failed to set setting expectedProxy to '.$line.EOL.EOL;
}

//Now for the Redis settings, if you're using redis
if(!$installFromFile){
    echo "If you are using Redis for cache, and have it installed, type ".EOL.
        "\"yes\" to set its settings, else the setup will skip this part.".EOL;
    $line = trim(fgets($handle));
}
else{
    if(isset($installOptions['redis']))
        $line = "yes";
    else
        $line = 'no';
}

if($line!=="yes")
    echo 'Skipping Redis setup!'.EOL.EOL;
else{
    echo EOL.'Redis Settings:'.EOL;
    if(!$installFromFile){
        echo "Enter Redis address - E.g 127.0.0.1".EOL;
        $line = trim(fgets($handle));
    }
    else{
        $line = $installOptions['redis']['redis_addr'];
    }
    if($redisSettings->setSetting('redis_addr',$line,['createNew'=>true]))
        echo 'Setting redis_addr set to '.$line.EOL;
    else
        echo 'Failed to set setting redis_addr to '.$line.EOL.EOL;

    if(!$installFromFile){
        echo "Enter Redis port or press Enter to skip (Default 6379)".EOL;
        $line = trim(fgets($handle));
    }
    else{
        $line = isset($installOptions['redis']['redis_port'])?
            $installOptions['redis']['redis_port'] : "";
    }
    if($line === "")
        $line = 6379;
    else
        $line = (int)($line);

    if($redisSettings->setSetting('redis_port',$line,['createNew'=>true]))
        echo 'Setting redis_port set to '.$line.EOL;
    else
        echo 'Failed to set setting redis_port to '.$line.EOL.EOL;

    if(!$installFromFile){
        echo "Enter Redis password or press Enter to skip:".EOL;
        $line = trim(fgets($handle));
    }
    else{
        $line = isset($installOptions['redis']['redis_password'])?
            $installOptions['redis']['redis_password'] : "";
    }
    //This is optional!
    if($line != ""){
        if($redisSettings->setSetting('redis_password',$line,['createNew'=>true]))
            echo 'Setting redis_password set to '.$line.EOL;
        else
            echo 'Failed to set setting redis_password to '.$line.EOL.EOL;
    }

    if(!$installFromFile){
        echo "Enter Redis timeout in seconds - eg 60 - or press Enter to skip: ".EOL;
        $line = trim(fgets($handle));
    }
    else{
        $line = isset($installOptions['redis']['redis_timeout'])?
            $installOptions['redis']['redis_timeout'] : "";
    }
    //This is optional!
    if($line !== ''){
        $line = (int)($line);
        //Has to be at least 1
        if($line < 1)
            $line = 1;
        if($redisSettings->setSetting('redis_timeout',$line,['createNew'=>true]))
            echo 'Setting redis_timeout set to '.$line.EOL;
        else
            echo 'Failed to set setting redis_timeout to '.$line.EOL.EOL;
    }

    if(!$installFromFile){
        echo " Enter \"yes\" to enable Redis Persistent Connection, or anything else to skip: ".EOL;
        $line = trim(fgets($handle));
    }
    else{
        $line = (isset($installOptions['redis']['redis_default_persistent']) && $installOptions['redis']['redis_default_persistent'])?
            "yes" : "no";
    }
    if($line === "yes"){
        if($redisSettings->setSetting('redis_default_persistent',1,['createNew'=>true]))
            echo 'Setting redis_default_persistent set to 1'.EOL;
        else
            echo 'Failed to set setting redis_default_persistent to 1'.EOL;
    }
}

echo EOL.'SQL Settings:'.EOL;

//Now for the Redis settings, if you're using redis
if(!$installFromFile){
    echo 'Please input the SQL credentials (user must have ALL privileges):'.EOL;
}

if(!$installFromFile){
    echo "Enter the MySQL server address - E.g 127.0.0.1".EOL;
    $line = trim(fgets($handle));
}
else{
    $line = $installOptions['sql']['sql_server_addr'];
}
if($sqlSettings->setSetting('sql_server_addr',$line,['createNew'=>true]))
    echo 'Setting sql_server_addr set to '.$line.EOL;
else
    echo 'Failed to set setting sql_server_addr to '.$line.EOL;

if(!$installFromFile){
    echo "Enter the MySQL username (remember- ALL privileges)".EOL;
    $line = trim(fgets($handle));
}
else{
    $line = $installOptions['sql']['sql_username'];
}
if($sqlSettings->setSetting('sql_username',$line,['createNew'=>true]))
    echo 'Setting sql_username set to '.$line.EOL;
else
    echo 'Failed to set setting sql_username to '.$line.EOL;

if(!$installFromFile){
    echo "Enter the MySQL password for the username".EOL;
    $line = trim(fgets($handle));
}
else{
    $line = $installOptions['sql']['sql_password'];
}
if($sqlSettings->setSetting('sql_password',$line,['createNew'=>true]))
    echo 'Setting sql_password set to '.$line.EOL;
else
    echo 'Failed to set setting sql_password to '.$line.EOL;

if(!$installFromFile){
    echo "Enter the Database name".EOL;
    $line = trim(fgets($handle));
}
else{
    $line = $installOptions['sql']['sql_db_name'];
}
if($sqlSettings->setSetting('sql_db_name',$line,['createNew'=>true]))
    echo 'Setting sql_db_name set to '.$line.EOL;
else
    echo 'Failed to set setting sql_db_name to '.$line.EOL;

if(!$installFromFile){
    echo "Enter the table prefix, or press Enter if there is none".EOL;
    $line = trim(fgets($handle));
}
else{
    $line = $installOptions['sql']['sql_table_prefix'];
}
if(strlen($line)>6)
    $line = substr($line,0,6);
if($sqlSettings->setSetting('sql_table_prefix',$line,['createNew'=>true]))
    echo 'Setting sql_table_prefix set to '.$line.EOL;
else
    echo 'Failed to set setting sql_table_prefix to '.$line.EOL;

if(!$installFromFile){
    echo "To enable node lock on state-modifying query, type \"yes\"".EOL;
    $line = trim(fgets($handle));
}
else{
    $line = (isset($installOptions['sql']['dbLockOnAction']) && $installOptions['sql']['dbLockOnAction'])?
        'yes':'no';
}
if($line === 'yes'){
    if($sqlSettings->setSetting('dbLockOnAction',1,['createNew'=>true]))
        echo 'Setting dbLockOnAction set to 1'.EOL;
    else
        echo 'Failed to set setting dbLockOnAction to 1'.EOL;
}
else{
    if($sqlSettings->setSetting('dbLockOnAction',0,['createNew'=>true]))
        echo 'Setting dbLockOnAction set to 0'.EOL;
    else
        echo 'Failed to set setting dbLockOnAction to 0'.EOL;
}

try {
    //Create a PDO connection
    $conn = IOFrame\Util\prepareCon($sqlSettings);
    echo 'Database connection established!'.EOL.EOL;
}
catch(Exception $e){
    exit('Database connection failed, please restart the setup! Error:'.$e);
}

//This means installation was complete!
$myFile = fopen('localFiles/_installComplete', 'w');
fclose($myFile);

exit('--------Installation complete!--------'.EOL);

