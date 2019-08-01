<?php

if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

if(!\IOFrame\Util\validator::validateSQLTableName($target)){
    if($test)
        echo 'Target must be a valid settings file name - which is a valid sql table name!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($action == 'getSetting'){
    $expectedParam = 'settingName';
    if($params == null){
        if($test)
            echo 'Params must be set!';
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else
    $expectedParam = null;

if($expectedParam){
    if(!isset($params[$expectedParam]) || !\IOFrame\Util\validator::validateSQLKey($params[$expectedParam])){
        if($test)
            echo $expectedParam.' must be a valid setting name - which is a valid key!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
//Auth check TODO Add relevant actions, not just rank 0
//TODO REMEMBER DIFFERENT ACTIONS - DEPENDING ON REQUEST

if(!$auth->isAuthorized(0)){
    if($test)
        echo 'Authorization rank must be 0!';
    exit(AUTHENTICATION_FAILURE);
}

