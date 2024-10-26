<?php


if(!filter_var($inputs['id'],FILTER_VALIDATE_INT)){
    if($test)
        echo 'id must be a number!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!filter_var($inputs['minutes'],FILTER_VALIDATE_INT)){
    if($test)
        echo 'minutes must be a number!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

