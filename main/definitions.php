<?php

namespace IOFrame{
    /* While this file should contain static definitions that belong to the system,
     * every definition that belongs to a plugin, that isn't a setting, should go into definitions.json
     * */
    const MAX_USER_RANK = 9999;                                 //Maximum (lowest) rank a user can have.
    const LOG_MODE_0 = 'none';    //No logs at all - please DONT do this in most cases.
    const LOG_MODE_1 = 'low';     //Only logs exceptional/rare events and critical errors.
    const LOG_MODE_2 = 'medium';  //Will also log more common events of note.
    const LOG_MODE_3 = 'high';    //Will log almost any event that has any value. USUALLY NOT RECOMMENDED in real environment.
    const LOG_MODE_4 = 'debug';   //Logs any event that has meaning. NOT RECOMMENDED in real environment.
    const SEC_MODE_0 = 'none';    //TODO IMPLEMENT
    const SEC_MODE_1 = 'low';     //TODO IMPLEMENT
    const SEC_MODE_2 = 'medium';  //TODO IMPLEMENT
    const SEC_MODE_3 = 'high';    //TODO IMPLEMENT
    const DB_FIELD_SEPARATOR = '#&%#'; //For DB purposes
    ////--------------------Correct EOL - CLI vs Server--------------------
    if(!defined('EOL')){
        if (\php_sapi_name() == "cli") {
            define("EOL",PHP_EOL);
        } else {
            define("EOL",'<br>');;
        }
    }
    define('EOL_FILE',\mb_convert_encoding('&#x000A;', 'UTF-8', 'HTML-ENTITIES'));

//-------------------Opens definitions.json and defines them.
    $dynamicDefinitions = __DIR__.'\..\localFiles\definitions\definitions.json';
    $dynamicDefinitionsFolder = __DIR__.'\..\localFiles\definitions';
    if(file_exists($dynamicDefinitions)){
        //Just in case somebody is installing a new plugin and adding new definitions, we need to be able to wait
        if(!defined('LockHandler'))
            require __DIR__ . '/../IOFrame/Handlers/LockHandler.php';
        //If new definitions are being installed, wait
        $mutex = new Handlers\LockHandler($dynamicDefinitionsFolder, 'mutex');
        if(!$mutex->waitForMutex())
            return false;
        //Read the definitions
        $myfile = fopen($dynamicDefinitions, "r+") or die("Unable to open definitions file!");
        $definitions = json_decode( fread($myfile,(filesize($dynamicDefinitions)+1)) , true );
        fclose($myfile);
        //Define what needs to be defined
        if(is_array($definitions))
            foreach($definitions as $k => $val){
                define($k,$val);
            }
    }

}







