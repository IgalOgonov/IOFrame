<?php

if(!isset($params['page'])){
    if($test)
        echo 'You must specify a page!';
    exit('-1');
}
if(!isset($params['date'])){
    $params['date'] = 0;
}

foreach($params as $key=>$value){
    switch($key){
        case 'date':
            if(strlen($value)<1 || strlen($value)>14 || (gettype($value) == 'string' && preg_match_all('/\D/',$value)>0)){
                if($test)
                    echo 'The date needs to be between 1 and 14 characters long, and only digits (UNIX TIMESTAMP)!';
                exit('-1');
            }
            break;
        case 'page':
            if(filter_var($value,FILTER_VALIDATE_URL)){
                if($test)
                    echo 'Illegal page name!';
                exit('-1');
            }
            if(strlen($value)<1){
                if($test)
                    echo 'You need a non empty page address if you want to create it!';
                exit('-1');
            }
            break;
    }
}


