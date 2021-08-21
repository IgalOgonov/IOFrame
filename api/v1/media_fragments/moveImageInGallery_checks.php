<?php

if($inputs['gallery'] === null){
    if($test)
        echo 'Gallery name must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!preg_match('/'.GALLERY_REGEX.'/',$inputs['gallery'])){
    if($test)
        echo 'Gallery name invalid!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//From, To
if($inputs['from'] === null || $inputs['to'] === null){
    if($test)
        echo 'Both from and to must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!filter_var($inputs['from'],FILTER_VALIDATE_INT) && $inputs['from'] != 0){
    if($test)
        echo 'from must be an integer!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!filter_var($inputs['to'],FILTER_VALIDATE_INT) && $inputs['to'] != 0){
    if($test)
        echo 'to must be an integer!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}