<?php
if(!isset($params['id'])){
    if($test)
        echo 'You must send an object id to delete an object!';
    exit(INPUT_VALIDATION_FAILURE);
}
foreach($params as $key=>$value){
    switch($key){
        case 'id':
            if(strlen($value)<1 || !filter_var($value,FILTER_VALIDATE_INT)){
                if($test)
                    echo 'You need a valid ID to delete!';
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;
        case 'time':
            if(strlen($value)<1 || strlen($value)>14 || (gettype($value) == 'string' && preg_match_all('/\D/',$value)>0)){
                if($test)
                    echo 'The time needs to be between 1 and 14 characters long, and only digits (UNIX TIMESTAMP)!';
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;
        case 'after':
            if($value != '')
                if(!($value==true || $value==false)){
                    if($test)
                        echo 'Illegal time constraint value!';
                    exit(INPUT_VALIDATION_FAILURE);
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
