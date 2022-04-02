<?php

if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

if(!\IOFrame\Util\validator::validateSQLTableName($target)){
    if($test)
        echo 'Target must be a valid settings file name - which is a valid sql table name!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($params == null){
    if($test)
        echo 'Params must be set!';
    exit(INPUT_VALIDATION_FAILURE);
}


if(!is_array($params)){
    if($test)
        echo 'Params must be an associative array!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//Potentially correct the boolean
if(isset($params['createNew'])){
    if($params['createNew'] == '0' || strtolower($params['createNew']) == 'false')
        $params[$expectedParam][$name] = false;
}
else
    $params['createNew'] = false;

if(!isset($params['settingName'])){
    if($test)
        echo 'A setting must have a name!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!\IOFrame\Util\validator::validateSQLKey(str_replace('-','',$params['settingName']))){
    if($test)
        echo 'A setting must have a valid name!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!isset($params['settingValue'])){
    if($test)
        echo 'A setting must have a value!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
$params['settingValue'] = str_replace('\'','\\\'',$params['settingValue']);