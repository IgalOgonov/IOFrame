<?php

if(!isset($params['maps'])){
    if($test)
        echo 'You must specify maps!';
    exit(INPUT_VALIDATION_FAILURE);
}
if(!isset($params['date'])){
    $params['date'] = 0;
}
else{
    $params['date'] = (int)$params['date'];
}

foreach($params as $key=>$value){
    switch($key){
        case 'date':
            if(strlen($value)<1 || strlen($value)>14 || (gettype($value) == 'string' && preg_match_all('/\D/',$value)>0)){
                if($test)
                    echo 'The date needs to be between 1 and 14 characters long, and only digits (UNIX TIMESTAMP)!';
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;
        case 'maps':
            foreach($value as $pageName=>$time){
                if(preg_match_all('/\w|\/|\./',$pageName)<strlen($pageName) && $pageName != '@'){
                    if($test)
                        echo 'Illegal page name!';
                    exit(INPUT_VALIDATION_FAILURE);
                }
                if(strlen($pageName)<1){
                    if($test)
                        echo 'You need a non empty page address if you want to create it!';
                    exit(INPUT_VALIDATION_FAILURE);
                }
                if(!filter_var($time,FILTER_VALIDATE_INT) && $time!=0){
                    if($test)
                        echo 'Illegal update time!';
                    exit(INPUT_VALIDATION_FAILURE);
                }
            }
            break;
    }
}


