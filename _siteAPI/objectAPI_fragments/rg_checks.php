<?php

if(!defined('validator'))
    require __DIR__.'/../../_util/validator.php';

if(!isset($params['groupName'])){
    if($test)
        echo 'You must send a groupname to query!';
    exit('-1');
}
if(!\IOFrame\validator::validateSQLKey($params['groupName'])){
    if($test)
        echo 'Illegal group name!';
    exit('-1');
}
if(isset($params['updated'])){
    if((gettype($params['updated']) == 'string' && preg_match_all('/\D/',$params['updated'])>0) || $params['updated']<0){
        if($test)
            echo 'updated has to be a number not smaller than 0!';
        exit('-1');
    }
}
