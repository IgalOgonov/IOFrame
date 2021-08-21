<?php

//ID
if($action == 'updateTemplate' && !$inputs['id']){
    if($test)
        echo 'ID must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
else{
    if($action === 'createTemplate')
        $inputs['id'] = -1;
    else
        if(!filter_var($inputs['id'],FILTER_VALIDATE_INT)){
            if($test)
                echo 'ID must be an integer!'.EOL;
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



