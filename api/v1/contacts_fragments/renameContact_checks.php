<?php
$paramsArray = ['identifier','newIdentifier'];
foreach($paramsArray as $param){
    if($inputs[$param] !== null){
        if(!preg_match('/'.IDENTIFIER_REGEX.'/',$inputs[$param])){
            if($test)
                echo $param.' must match '.IDENTIFIER_REGEX.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    else{
        if($test)
            echo $param.' must be set!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}