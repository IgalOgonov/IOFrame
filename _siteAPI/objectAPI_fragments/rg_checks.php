<?php

require_once __DIR__.'/../../_util/validator.php';

if(!isset($params['groupName'])){
    echo 'You must send a groupname to query!';
    return false;
}
if(!\IOFrame\validator::validateSQLKey($params['groupName'])){
    echo 'Illegal group name!';
    return false;
}
if(isset($params['updated'])){
    if((gettype($params['updated']) == 'string' && preg_match_all('/\D/',$params['updated'])>0) || $params['updated']<0){
        echo 'updated has to be a number not smaller than 0!';
        return false;
    }
}
