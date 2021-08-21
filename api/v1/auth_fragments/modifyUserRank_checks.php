<?php
//Input checks
if($params == null){
    if($test)
        echo 'Params must be set!';
    exit(INPUT_VALIDATION_FAILURE);
}

$expectedParams = ['identifier','newRank'];

foreach($expectedParams as $expectedParam){

    if( !isset($params[$expectedParam]) ){
        if($test)
            echo 'Parameter '.$expectedParam.' must be set!';
        exit(INPUT_VALIDATION_FAILURE);
    }

    switch($expectedParam){
        case 'identifier':
            if(!filter_var($params[$expectedParam],FILTER_VALIDATE_INT) && !filter_var($params[$expectedParam],FILTER_VALIDATE_EMAIL) ){
                if($test)
                    echo 'identifier must be a number or a valid email!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(preg_match('/\D/',$params[$expectedParam]) == 0)
                $params[$expectedParam] = (int)$params[$expectedParam];
            break;
        case 'newRank':
            if(!filter_var($params[$expectedParam],FILTER_VALIDATE_INT)){
                if($test)
                    echo 'newRank must be a number!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if($params[$expectedParam]<0){
                if($test)
                    echo 'newRank must be positive or 0!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;
    }

}