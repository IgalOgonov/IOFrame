<?php

if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

foreach($params as $key=>$value){
    if($key!='@' && !\IOFrame\Util\validator::validateSQLKey($key)){
        if($test)
            echo 'Illegal group name!';
        die('-1');
    }
    if(!is_array($value) || count($value) == 0){
        if($test)
            echo 'Contents of requested object groups to view must be a non empty object array!';
        die('-1');
    }
    else{
        foreach($value as $innerKey => $innerVal){
            if($innerKey != '@' &&
                (gettype($innerKey) == 'string' && preg_match_all('/[0-9]/',$innerKey)<strlen($innerKey) || strlen($innerKey) == 0)){
                if($test)
                    echo 'Object IDs must be numbers!';
                die('-1');
            }
            if(gettype($innerKey) == 'string' && preg_match_all('/[0-9]/',$innerVal)<strlen($innerVal) || strlen($innerVal)<1 || strlen($innerVal)>14){
                if($test)
                    echo 'Object dates need to be between 1 and 14 characters long, and only digits (UNIX TIMESTAMP)!!';
                die('-1');
            }
        }
    }
}

