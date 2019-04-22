<?php


if(!isset($params['id'])){
    echo 'You must send an object id to delete an object!';
    return false;
}
foreach($params as $key=>$value){
    switch($key){
        case 'id':
            if(strlen($value)<1 || !filter_var($value,FILTER_VALIDATE_INT)){
                echo 'You need a valid ID to delete!';
                return false;
            }
            break;
        case 'time':
            if(strlen($value)<1 || strlen($value)>14 || (gettype($value) == 'string' && preg_match_all('/\D/',$value)>0)){
                echo 'The time needs to be between 1 and 14 characters long, and only digits (UNIX TIMESTAMP)!';
                return false;
            }
            break;
        case 'after':
            if($value != '')
                if(!($value==true || $value==false)){
                    echo 'Illegal time constraint value!';
                    return false;
                }
            break;
        case '?':
            if(!($value==true || $value==false)){
                echo 'Illegal test value!';
                return false;
            }
            break;
    }
}


//Get object ID
$id = $params['id'];
//Get all optional parameters
isset($params['time'])?
    $time = (int)$params['time'] : $time = 0;
isset($params['after'])?
    $after = $params['after'] : $after = true;
