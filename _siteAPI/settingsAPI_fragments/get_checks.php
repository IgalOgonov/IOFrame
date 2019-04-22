<?php

require_once __DIR__.'/../../_util/validator.php';

if(!\IOFrame\validator::validateSQLTableName($_REQUEST["target"])){
    if($test)
        echo 'Target must be a valid settings file name - which is a valid sql table name!'.EOL;
    exit('-1');
}

if($_REQUEST['action'] == 'getSetting'){
    $expectedParam = 'settingName';
    if($params == null){
        if($test)
            echo 'Params must be set!';
        exit('-1');
    }
}
else
    $expectedParam = null;

if($expectedParam){
    if(!isset($params[$expectedParam]) || !\IOFrame\validator::validateSQLKey($params[$expectedParam])){
        if($test)
            echo $expectedParam.' must be a valid setting name - which is a valid key!'.EOL;
        exit('-1');
    }
}
//Auth check TODO Add relevant actions, not just rank 0
//TODO REMEMBER DIFFERENT ACTIONS - DEPENDING ON REQUEST

if(!$auth->isAuthorized(0)){
    if($test)
        echo 'Authorization rank must be 0!';
    exit('-2');
}

