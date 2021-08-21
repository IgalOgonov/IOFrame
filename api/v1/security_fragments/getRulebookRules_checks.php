<?php

if($inputs['category'] !== null){
    if(!filter_var($inputs['category'],FILTER_VALIDATE_INT) && $inputs['category'] !== 0){
        if($test)
            echo 'category must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['type'] !== null){
    if(!filter_var($inputs['type'],FILTER_VALIDATE_INT) && $inputs['type'] !== 0){
        if($test)
            echo 'type must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}