<?php

//ID
if($inputs['id'] !== null){
    if(!filter_var($inputs['id'],FILTER_VALIDATE_INT)){
        if($test)
            echo 'id must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else{
    if($test)
        echo 'id must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
