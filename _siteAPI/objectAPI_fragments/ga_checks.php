<?php

if(!isset($params['page'])){
    echo 'You must specify a page!';
    return false;
}
if(!isset($params['date'])){
    $params['date'] = 0;
}

foreach($params as $key=>$value){
    switch($key){
        case 'date':
            if(strlen($value)<1 || strlen($value)>14 || (gettype($value) == 'string' && preg_match_all('/\D/',$value)>0)){
                echo 'The date needs to be between 1 and 14 characters long, and only digits (UNIX TIMESTAMP)!';
                return false;
            }
            break;
        case 'page':
            if(filter_var($value,FILTER_VALIDATE_URL)){
                echo 'Illegal page name!';
                return false;
            }
            if(strlen($value)<1){
                echo 'You need a non empty page address if you want to create it!';
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


