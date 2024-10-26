<?php

namespace main{

    ////-----------------Correct EOL - CLI vs Server--------------------
    require_once __DIR__.'/../polyfill/eol.php';

    ////-----------------STR Polyfill--------------------
    require_once __DIR__.'/../polyfill/strFunctionsPolyfill.php';

    //-------------------Opens definitions.json and defines them.-------
    $dynamicDefinitions = __DIR__ . '/../localFiles/definitions/definitions.json';
    $dynamicDefinitionsFolder = __DIR__ . '/../localFiles/definitions';

    if(file_exists($dynamicDefinitions)){
        //If new definitions are being installed, wait
        $mutex = new \IOFrame\Managers\LockManager($dynamicDefinitionsFolder);
        if(!$mutex->waitForMutex())
            return false;
        //Read the definitions
        $myFile = fopen($dynamicDefinitions, "r+") or die("Unable to open definitions file!");
        $definitions = json_decode( fread($myFile,(filesize($dynamicDefinitions)+1)) , true );
        fclose($myFile);
        //Define what needs to be defined
        if(is_array($definitions))
            foreach($definitions as $k => $val){
                define($k,$val);
            }
        unset($mutex);
        unset($myFile);
        unset($definitions);
    }
    unset($dynamicDefinitions);
    unset($dynamicDefinitionsFolder);

}







