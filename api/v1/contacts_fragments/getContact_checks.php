<?php

if($inputs['id'] !== null){
    if(!preg_match('/'.IDENTIFIER_REGEX.'/',$inputs['id'])){
        if($test)
            echo 'ID must match '.IDENTIFIER_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else{
    if($test)
        echo 'id must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}