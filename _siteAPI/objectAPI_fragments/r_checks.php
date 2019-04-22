<?php

require_once __DIR__.'/../../_util/validator.php';

//Check to make sure somebody didn't only send the test
if((count($params)==1) && isset($params['?'])){
    if($test)
        echo 'Seriously? Only the test? Send some object IDs, too!';
    die('-1');
}
foreach($params as $key=>$value){
    if($key!='?' && $key!='@' && !\IOFrame\validator::validateSQLKey($key)){
        if($test)
            echo 'Illegal group name!';
        die('-1');
    }
    if($key!='?'){
        if(!IOFrame\isJson($value) || preg_match_all('/{/', $value) == 0){
            if($test)
                echo 'All contents of requested object groups to view must be in JSON format!';
            die('-1');
        }
        else{
            foreach(json_decode($value, true) as $innerKey => $innerVal){
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
    else{
        if(!($value==true || $value==false)){
            if($test)
                echo 'Illegal test value!';
            die('-1');
        }
    }
}

