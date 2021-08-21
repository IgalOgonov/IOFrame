<?php

if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

if(!isset($params['groupName'])){
    if($test)
        echo 'You must send a groupname to query!';
    exit(INPUT_VALIDATION_FAILURE);
}
if(!\IOFrame\Util\validator::validateSQLKey($params['groupName'])){
    if($test)
        echo 'Illegal group name!';
    exit(INPUT_VALIDATION_FAILURE);
}
if(isset($params['updated'])){
    if((gettype($params['updated']) == 'string' && preg_match_all('/\D/',$params['updated'])>0) || $params['updated']<0){
        if($test)
            echo 'updated has to be a number not smaller than 0!';
        exit(INPUT_VALIDATION_FAILURE);
    }
}
