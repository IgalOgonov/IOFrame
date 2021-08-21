<?php

if( $inputs['time'] !== null && !(filter_var($inputs['time'],FILTER_VALIDATE_INT))){
    if($test)
        echo 'time needs to be a positive int!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}