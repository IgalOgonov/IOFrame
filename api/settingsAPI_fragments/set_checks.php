<?php

if(!defined('validator'))
    require __DIR__ . '/../../util/validator.php';

if(!\IOFrame\validator::validateSQLTableName($target)){
    if($test)
        echo 'Target must be a valid settings file name - which is a valid sql table name!'.EOL;
    exit('-1');
}

if($params == null){
    if($test)
        echo 'Params must be set!';
    exit('-1');
}


if(!is_array($params)){
    if($test)
        echo 'Params must be an associative array!'.EOL;
    exit('-1');
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
    exit('-1');
}

if(!\IOFrame\validator::validateSQLKey($params['settingName'])){
    if($test)
        echo 'A setting must have a valid name!'.EOL;
    exit('-1');
}

if(!isset($params['settingValue'])){
    if($test)
        echo 'A setting must have a value!'.EOL;
    exit('-1');
}

//Auth check TODO Add relevant actions, not just rank 0
//TODO REMEMBER DIFFERENT ACTIONS - DEPENDING ON REQUEST

if(!$auth->isAuthorized(0)){
    if($test)
        echo 'Authorization rank must be 0!';
    exit('-2');
}

