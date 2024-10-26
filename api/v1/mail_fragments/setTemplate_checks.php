<?php

//ID
if(!$inputs['id']){
    if($test)
        echo 'ID must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
else{
    if(!preg_match('/'.TEMPLATE_ID_REGEX.'/',$inputs['id'])){
        if($test)
            echo 'ID must be valid!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//Title or Content
if($action == 'updateTemplate' && !$inputs['title'] && !$inputs['content']){
    if($test)
        echo 'You need new content when updating a template!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//Title
if(!$inputs['title'] && $action == 'createTemplate'){
    if($test)
        echo 'Title must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//Content
if(!$inputs['content'] && $action == 'createTemplate'){
    if($test)
        echo 'Content must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}



