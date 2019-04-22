<?php

if(!defined('INSTALL_CLI'))
    exit('Must be included from _install.php to run!');

if(file_exists('_siteFiles/_installComplete'))
    die(EOL.'It seems the site is already installed! If this is an error, go to the /siteFiles folder and delete _installComplete.'.EOL);

$handle = fopen ("php://stdin","r");

echo EOL."You are installing in CLI mode.".EOL.
     "This will install this instance as a DB reliant node.".EOL.
     "This node will be reliant on the DB of a main node (already installed).".EOL.
     "--IMPORTANT-- Such node MUST BE installed at *server root* --IMPORTANT--".EOL.
     "If you want to continue, type \"yes\", else this installer will exit.".EOL;
$line = trim(fgets($handle));

if($line!=="yes")
    exit('Exiting setup...');

//--------------------Initialize Current DIR--------------------
$baseUrl = IOFrame\replaceInString('\\','/',__DIR__).'/';


//--------------------Initialize settings handler--------------------
if(!is_dir('_siteFiles/localSettings')){
    if(!mkdir('_siteFiles/localSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('_siteFiles/localSettings/settings','w'));
}
if(!is_dir('_siteFiles/sqlSettings')){
    if(!mkdir('_siteFiles/sqlSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('_siteFiles/sqlSettings/settings','w'));
}

if(!is_dir('_siteFiles/redisSettings')){
    if(!mkdir('_siteFiles/redisSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('_siteFiles/redisSettings/settings','w'));
}

//Initialize handlers
$localSettings = new IOFrame\settingsHandler($baseUrl.'/_siteFiles/localSettings/');
$sqlSettings = new IOFrame\settingsHandler($baseUrl.'/_siteFiles/sqlSettings/');
$redisSettings = new IOFrame\settingsHandler($baseUrl.'/_siteFiles/redisSettings/',['useCache'=>false]);

//Create initial settings
echo EOL.'Default Settings:'.EOL;
//Settings to set..
$localArgs = [

];

array_push($localArgs,["absPathToRoot",$baseUrl]);
array_push($localArgs,["pathToRoot",'']);
array_push($localArgs,["opMode",IOFrame\SETTINGS_OP_MODE_DB]);
$res = true;

//Update all settings, and return false if any of the updates failed
foreach($localArgs as $key=>$val){
    if($localSettings->setSetting($val[0],$val[1],true))
        echo 'Setting '.$val[0].' set to '.$val[1].EOL;
    else{
        echo 'Failed to set setting '.$val[0].' to '.$val[1].EOL;
        $res = false;
    }
}

if($res){
        echo '- All default settings set!'.EOL.EOL;
}

//If this node is sitting behind a reverse proxy (or more than one),
echo "If this node is sitting behind a proxy (or a few), type \"yes\"".EOL.
     "to input the proxy IPs".EOL;
$line = trim(fgets($handle));

if($line!=="yes")
    echo 'Skipping proxy setup!'.EOL.EOL;
else{
    echo "Enter proxy list, seperated by comma+space. For example,".EOL.
        " if your load balancer IP is 10.10.11.11, and it itself is behind".EOL.
        " a proxy with IP 210.20.1.10, type \"210.20.1.10,10.10.11.11\" without quotes.";
    $line = trim(fgets($handle));
    if($localSettings->setSetting('expectedProxy',$line,true))
        echo 'Setting expectedProxy set to '.$line.EOL;
    else
        echo 'Failed to set setting expectedProxy to '.$line.EOL;
}

//Now for the Redis settings, if you're using redis
echo "If you are using Redis for cache, and have it installed, type ".EOL.
 "\"yes\" to set its settings, else the setup will skip this part.".EOL;
$line = trim(fgets($handle));

if($line!=="yes")
echo 'Skipping Redis setup!'.EOL.EOL;

else{
    echo "Enter Redis address - E.g 127.0.0.1".EOL;
    $line = trim(fgets($handle));
    if($redisSettings->setSetting('redis_addr',$line,true))
        echo 'Setting redis_addr set to '.$line.EOL;
    else
        echo 'Failed to set setting redis_addr to '.$line.EOL.EOL;

    echo "Enter Redis port or press Enter to skip (Default 6379)".EOL;
    $line = trim(fgets($handle));
    if($line === "")
        $line = 6379;
    else
        $line = (int)($line);

    if($redisSettings->setSetting('redis_port',$line,true))
        echo 'Setting redis_port set to '.$line.EOL;
    else
        echo 'Failed to set setting redis_port to '.$line.EOL.EOL;

    echo "Enter Redis password or press Enter to skip:".EOL;
    $line = trim(fgets($handle));
    //This is optional!
    if($line != ''){
        if($redisSettings->setSetting('redis_password',$line,true))
            echo 'Setting redis_password set to '.$line.EOL;
        else
            echo 'Failed to set setting redis_password to '.$line.EOL.EOL;
    }

    echo "Enter Redis timeout in seconds - eg 60 - or press Enter to skip: ".EOL;
    $line = trim(fgets($handle));
    //This is optional!
    if($line !== ''){
        $line = (int)($line);
        //Has to be at least 1
        if($line < 1)
            $line = 1;
        if($redisSettings->setSetting('redis_timeout',$line,true))
            echo 'Setting redis_timeout set to '.$line.EOL;
        else
            echo 'Failed to set setting redis_timeout to '.$line.EOL.EOL;
    }


    echo " Enter \"yes\" to enable Redis Persistent Connection, or anything else to skip: ".EOL;
    $line = trim(fgets($handle));
    if($line === "yes"){
        if($redisSettings->setSetting('redis_default_persistent',1,true))
            echo 'Setting redis_default_persistent set to 1'.EOL;
        else
            echo 'Failed to set setting redis_default_persistent to 1'.EOL;
    }
}

echo EOL;

//Now for the Redis settings, if you're using redis
echo 'Please input the SQL credentials (user must have ALL privileges):'.EOL;

echo "Enter the MySQL server address - E.g 127.0.0.1".EOL;
$line = trim(fgets($handle));
if($sqlSettings->setSetting('sql_addr',$line,true))
    echo 'Setting sql_addr set to '.$line.EOL;
else
    echo 'Failed to set setting sql_addr to '.$line.EOL;

echo "Enter the MySQL username (remember- ALL privileges)".EOL;
$line = trim(fgets($handle));
if($sqlSettings->setSetting('sql_u',$line,true))
    echo 'Setting sql_u set to '.$line.EOL;
else
    echo 'Failed to set setting sql_u to '.$line.EOL;

echo "Enter the MySQL password for the username".EOL;
$line = trim(fgets($handle));
if($sqlSettings->setSetting('sql_p',$line,true))
    echo 'Setting sql_p set to '.$line.EOL;
else
    echo 'Failed to set setting sql_p to '.$line.EOL;

echo "Enter the Database name".EOL;
$line = trim(fgets($handle));
if($sqlSettings->setSetting('sql_db',$line,true))
    echo 'Setting sql_db set to '.$line.EOL;
else
    echo 'Failed to set setting sql_db to '.$line.EOL;

echo "Enter the table prefix, or press Enter if there is none".EOL;
$line = trim(fgets($handle));
if($sqlSettings->setSetting('sql_p',$line,true))
    echo 'Setting sql_p set to '.$line.EOL;
else
    echo 'Failed to set setting sql_p to '.$line.EOL;

echo "To enable node lock on state-modifying query, type \"yes\"".EOL;
$line = trim(fgets($handle));
if($line === 'yes'){
    if($sqlSettings->setSetting('dbLockOnAction',1,true))
        echo 'Setting dbLockOnAction set to 1'.EOL;
    else
        echo 'Failed to set setting dbLockOnAction to 1'.EOL;
}
else{
    if($sqlSettings->setSetting('dbLockOnAction',0,true))
        echo 'Setting dbLockOnAction set to 0'.EOL;
    else
        echo 'Failed to set setting dbLockOnAction to 0'.EOL;
}

try {
    //Create a PDO connection
    $conn = IOFrame\prepareCon($sqlSettings);
    echo 'Database connection established!'.EOL.EOL;
}
catch(Exception $e){
    exit('Database connection failed, please restart the setup! Error:'.$e);
}

//This means installation was complete!
$myFile = fopen('_siteFiles/_installComplete', 'w');
fclose($myFile);

exit('Installation complete!');

