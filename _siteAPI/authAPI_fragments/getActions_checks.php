<?php

$expectedParams = ['limit','offset'];
foreach($expectedParams as $expectedParam){

    if(isset($params[$expectedParam]))
        switch($expectedParam){
            case 'limit':
                $params[$expectedParam] = (int)$params[$expectedParam];
                if(!filter_var($params[$expectedParam],FILTER_VALIDATE_INT)){
                    if($test)
                        echo 'limit expression must be a number!'.EOL;
                    exit('-1');
                }
                break;
            case 'offset':
                $params[$expectedParam] = (int)$params[$expectedParam];
                if(!filter_var($params[$expectedParam],FILTER_VALIDATE_INT) && $params[$expectedParam]!=0){
                    if($test)
                        echo 'offset expression must be a number!'.EOL;
                    exit('-1');
                }
                break;
        }
}

//Auth check TODO Add relevant actions, not just rank 0
if(!$auth->isAuthorized(0)){
    if($test)
        echo 'Authorization rank must be 0!';
    exit('-2');
}


